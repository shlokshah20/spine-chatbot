<?php
/**
 * Knowledge Base File Importer  (v2.0)
 *
 * Parses uploaded .txt, .pdf, and .docx files into plain text, chunks the
 * result into KB-table-sized rows, and bulk-inserts into wp_spine_kb_entries.
 *
 * PDF parsing: regex-based stream extraction — works reliably for text-based
 *   PDFs (the vast majority of exported docs). Scanned/image PDFs will yield
 *   little text; advise users to paste content manually for those.
 *
 * DOCX parsing: unzips the DOCX container (a ZIP file), reads word/document.xml,
 *   strips XML tags — no external libraries required.
 *
 * TXT parsing: reads the file directly with encoding detection.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_KB_Importer {

    private const CHUNK_WORDS  = 300; // target words per DB row
    private const CHUNK_OVERLAP = 30; // words of overlap between chunks

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Parse an uploaded file and bulk-insert chunks into the KB table.
     *
     * @param string $tmp_path     Absolute path to the uploaded temp file.
     * @param string $mime_type    MIME type detected by mime_content_type().
     * @param string $module       Module name to tag the entries with.
     * @param string $entry_type   Entry type: FAQ | Feature | Overview | General.
     *
     * @return array{ imported: int, skipped: int, error?: string }
     */
    public function import_file(
        string $tmp_path,
        string $mime_type,
        string $module,
        string $entry_type
    ): array {
        $text = $this->extract_text( $tmp_path, $mime_type );

        if ( is_wp_error( $text ) ) {
            return [ 'imported' => 0, 'skipped' => 0, 'error' => $text->get_error_message() ];
        }

        $text = $this->clean_text( $text );

        if ( strlen( $text ) < 20 ) {
            return [ 'imported' => 0, 'skipped' => 0, 'error' => 'No readable text found in the uploaded file.' ];
        }

        $chunks   = $this->chunk_text( $text );
        $imported = 0;
        $skipped  = 0;

        foreach ( $chunks as $chunk ) {
            if ( strlen( trim( $chunk ) ) < 30 ) {
                $skipped++;
                continue;
            }
            $ok = Spine_Chatbot_DB::insert_kb_entry(
                trim( $chunk ),
                sanitize_text_field( $module ),
                sanitize_text_field( $entry_type )
            );
            $ok ? $imported++ : $skipped++;
        }

        return [ 'imported' => $imported, 'skipped' => $skipped ];
    }

    /**
     * Import plain text directly (manual paste from admin UI).
     *
     * @return array{ imported: int, skipped: int }
     */
    public function import_text( string $raw_text, string $module, string $entry_type ): array {
        $text   = $this->clean_text( $raw_text );
        $chunks = $this->chunk_text( $text );

        $imported = $skipped = 0;
        foreach ( $chunks as $chunk ) {
            if ( strlen( trim( $chunk ) ) < 30 ) { $skipped++; continue; }
            $ok = Spine_Chatbot_DB::insert_kb_entry( trim( $chunk ), $module, $entry_type );
            $ok ? $imported++ : $skipped++;
        }

        return [ 'imported' => $imported, 'skipped' => $skipped ];
    }

    // ── Text extraction ────────────────────────────────────────────────────────

    /**
     * Route to the correct parser based on MIME type.
     *
     * @return string|WP_Error
     */
    private function extract_text( string $path, string $mime ): string|WP_Error {
        return match ( true ) {
            str_contains( $mime, 'pdf' )                        => $this->parse_pdf( $path ),
            str_contains( $mime, 'officedocument.wordprocessing' ),
            str_contains( $mime, 'msword' )                     => $this->parse_docx( $path ),
            str_contains( $mime, 'text/' ),
            str_contains( $mime, 'plain' )                      => $this->parse_txt( $path ),
            default                                             => new WP_Error(
                'unsupported_type',
                "Unsupported file type: {$mime}. Please upload .pdf, .docx, or .txt."
            ),
        };
    }

    // ─── TXT ──────────────────────────────────────────────────────────────────

    private function parse_txt( string $path ): string|WP_Error {
        $content = @file_get_contents( $path );
        if ( $content === false ) {
            return new WP_Error( 'read_error', 'Could not read the uploaded file.' );
        }

        // Detect and convert encoding to UTF-8
        $enc = mb_detect_encoding( $content, [ 'UTF-8', 'ISO-8859-1', 'Windows-1252' ], true );
        if ( $enc && $enc !== 'UTF-8' ) {
            $content = mb_convert_encoding( $content, 'UTF-8', $enc );
        }

        return $content;
    }

    // ─── PDF ──────────────────────────────────────────────────────────────────

    /**
     * Best-effort text extraction from PDF streams.
     * Works for text-based PDFs (word-processor exports, most modern PDFs).
     * Scanned image-only PDFs will return little/no text.
     */
    private function parse_pdf( string $path ): string|WP_Error {
        $raw = @file_get_contents( $path );
        if ( $raw === false ) {
            return new WP_Error( 'read_error', 'Could not read the PDF file.' );
        }

        $text = '';

        // ── Strategy 1: Extract uncompressed text streams ─────────────────
        // PDF text operators: Tj (show string), TJ (show array of strings), '
        if ( preg_match_all( '/BT\s+(.*?)\s+ET/s', $raw, $bt_blocks ) ) {
            foreach ( $bt_blocks[1] as $block ) {
                // Extract strings from Tj and TJ operators
                if ( preg_match_all( '/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*(?:Tj|\'|")/', $block, $tj ) ) {
                    $text .= implode( ' ', $tj[1] ) . "\n";
                }
                if ( preg_match_all( '/\[([^\]]*)\]\s*TJ/', $block, $tj_arr ) ) {
                    foreach ( $tj_arr[1] as $arr_str ) {
                        if ( preg_match_all( '/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $arr_str, $parts ) ) {
                            $text .= implode( '', $parts[1] ) . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        // ── Strategy 2: Decompress FlateDecode streams ────────────────────
        if ( strlen( trim( $text ) ) < 100 && preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams ) ) {
            foreach ( $streams[1] as $stream ) {
                $decompressed = @gzuncompress( $stream );
                if ( $decompressed === false ) {
                    $decompressed = @gzinflate( $stream );
                }
                if ( $decompressed && preg_match_all( '/\(([^)]{1,200})\)\s*Tj/', $decompressed, $tj ) ) {
                    $text .= implode( ' ', $tj[1] ) . "\n";
                }
            }
        }

        // Clean PDF escape sequences
        $text = preg_replace( '/\\\\([nrtbf\\\\\(\)])/', ' ', $text );
        $text = preg_replace( '/\\\\[0-9]{3}/', '', $text ); // octal escapes

        if ( strlen( trim( $text ) ) < 50 ) {
            return new WP_Error(
                'pdf_no_text',
                'Could not extract readable text from this PDF. It may be a scanned image-only PDF. Please copy-paste the text content manually.'
            );
        }

        return $text;
    }

    // ─── DOCX ─────────────────────────────────────────────────────────────────

    /**
     * Extract text from a .docx file using ZipArchive + SimpleXML.
     * No external libraries needed — DOCX is a ZIP with XML inside.
     */
    private function parse_docx( string $path ): string|WP_Error {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'no_zip', 'PHP ZipArchive extension is required to parse .docx files.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $path ) !== true ) {
            return new WP_Error( 'zip_open', 'Could not open .docx file (may be corrupt or not a valid .docx).' );
        }

        $xml_str = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( $xml_str === false ) {
            return new WP_Error( 'docx_no_xml', 'word/document.xml not found inside the .docx archive.' );
        }

        // Strip XML namespace declarations to simplify parsing
        $xml_str = preg_replace( '/xmlns[^=]*="[^"]*"/i', '', $xml_str );

        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_str );
        libxml_clear_errors();

        if ( ! $xml ) {
            // Fallback: strip all XML tags from raw string
            $text = wp_strip_all_tags( $xml_str );
            return strlen( trim( $text ) ) > 20 ? $text : new WP_Error( 'docx_parse', 'Failed to parse .docx XML.' );
        }

        // Walk all <w:p> paragraph nodes and join <w:t> text runs
        $xml->registerXPathNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
        $paragraphs = $xml->xpath( '//w:p' );

        if ( empty( $paragraphs ) ) {
            // Broader fallback
            return wp_strip_all_tags( $xml_str );
        }

        $lines = [];
        foreach ( $paragraphs as $para ) {
            $runs = $para->xpath( './/w:t' );
            $line = '';
            foreach ( $runs as $run ) {
                $line .= (string) $run;
            }
            if ( trim( $line ) !== '' ) {
                $lines[] = $line;
            }
        }

        return implode( "\n", $lines );
    }

    // ── Text utilities ─────────────────────────────────────────────────────────

    /**
     * Normalise whitespace and remove non-printable characters.
     */
    private function clean_text( string $text ): string {
        // Remove non-printable characters (but keep newlines, tabs)
        $text = preg_replace( '/[^\P{C}\n\t]/u', '', $text );
        // Collapse multiple blank lines into one
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        // Collapse multiple spaces
        $text = preg_replace( '/[ \t]{2,}/', ' ', $text );
        return trim( $text );
    }

    /**
     * Split text into overlapping word-count-bounded chunks.
     *
     * @return string[]
     */
    private function chunk_text( string $text ): array {
        $words    = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $total    = count( $words );
        $chunks   = [];
        $step     = self::CHUNK_WORDS - self::CHUNK_OVERLAP;
        $step     = max( 1, $step );

        for ( $i = 0; $i < $total; $i += $step ) {
            $slice    = array_slice( $words, $i, self::CHUNK_WORDS );
            $chunks[] = implode( ' ', $slice );
            if ( $i + self::CHUNK_WORDS >= $total ) {
                break;
            }
        }

        return $chunks ?: [ $text ]; // always return at least one chunk
    }
}
