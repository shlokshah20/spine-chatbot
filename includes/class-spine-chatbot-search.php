<?php
/**
 * Knowledge-Base Search Engine  (v1.1 — branch-scoped search)
 *
 * Performs keyword + semantic string matching against the static knowledgebase-v1.php
 * array. Scoring is designed so Phase 2 can swap this class's data loader for a
 * MySQL FULLTEXT query while keeping all response-generation logic identical.
 *
 * Confidence levels
 *   score >= SPINE_CHATBOT_HIGH_THRESHOLD  → direct answer
 *   score >= SPINE_CHATBOT_ALT_THRESHOLD   → show top-3 alternatives
 *   score <  SPINE_CHATBOT_ALT_THRESHOLD   → no match → agent / offline flow
 *
 * v1.1 addition:
 *   query( $input, $scope ) accepts an optional scope key that maps to
 *   $kb['product_categories'][$scope]. When a scope is set, only modules in
 *   that category are evaluated — keeping product branches fully isolated.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Search {

    /** @var array Full KB payload */
    private array $kb;

    /** @var array Stop-words excluded from token scoring */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'shall', 'can', 'i', 'you', 'we', 'they',
        'he', 'she', 'it', 'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or',
        'but', 'with', 'about', 'by', 'from', 'that', 'this', 'what', 'how',
        'when', 'where', 'which', 'who', 'me', 'my', 'your', 'our', 'their',
        'please', 'help', 'tell', 'need', 'want', 'know', 'more', 'info',
        'information', 'also', 'so', 'very', 'too', 'not', 'no', 'yes', 'if',
    ];

    /** Weights for each matching location */
    private const W_FAQ_QUESTION  = 18.0;
    private const W_FAQ_ANSWER    = 7.0;
    private const W_FAQ_KEYWORD   = 14.0;
    private const W_MOD_KEYWORD   = 12.0;
    private const W_FEATURE_NAME  = 9.0;
    private const W_FEATURE_DESC  = 4.0;
    private const W_OVERVIEW      = 5.0;
    private const W_SEMANTIC      = 8.0;  // synonym-group hit
    private const W_PHRASE_BONUS  = 15.0; // multi-word exact phrase match

    public function __construct() {
        $this->kb = require SPINE_CHATBOT_DIR . 'includes/knowledgebase-v1.php';
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Main entry point.
     *
     * @param string $user_input  Raw user message.
     * @param string $scope       Optional product-category key: 'hr_suite' | 'assets' | 'international'.
     *                            When empty, all modules are searched (used by the branch-less path
     *                            and backward-compat callers).
     *
     * @return array{
     *   type: string,
     *   score: float,
     *   response: string,
     *   alternatives: array,
     *   module_id: string
     * }
     */
    public function query( string $user_input, string $scope = '' ): array {
        $tokens = $this->tokenise( $user_input );
        if ( empty( $tokens ) ) {
            return $this->no_match_result( $scope );
        }

        // Resolve which module IDs are allowed for this scope.
        // Returns null to mean "search all modules" (e.g. scope = '').
        $allowed_ids = $this->resolve_scope( $scope );

        $scores  = [];
        $matches = []; // best FAQ or feature per module

        foreach ( $this->kb['modules'] as $module_id => $module ) {
            // Skip modules not in the active branch
            if ( $allowed_ids !== null && ! in_array( $module_id, $allowed_ids, true ) ) {
                continue;
            }

            [ $score, $match ] = $this->score_module( $tokens, $user_input, $module );
            $scores[ $module_id ]  = $score;
            $matches[ $module_id ] = $match;
        }

        // Global FAQs are only included when no scope is set (branch-less queries)
        if ( $allowed_ids === null ) {
            [ $global_score, $global_match ] = $this->score_global_faqs( $tokens, $user_input );
            $scores['__global__']  = $global_score;
            $matches['__global__'] = $global_match;
        }

        if ( empty( $scores ) ) {
            return $this->no_match_result( $scope );
        }

        arsort( $scores );
        $top_id    = array_key_first( $scores );
        $top_score = $scores[ $top_id ];

        if ( $top_score >= SPINE_CHATBOT_HIGH_THRESHOLD ) {
            return [
                'type'         => 'answer',
                'score'        => $top_score,
                'response'     => $this->build_response( $top_id, $matches[ $top_id ] ),
                'alternatives' => [],
                'module_id'    => $top_id,
            ];
        }

        // Build alternative suggestions from top-3 modules above alt threshold
        $alternatives = [];
        foreach ( $scores as $mid => $sc ) {
            if ( count( $alternatives ) >= 3 ) break;
            if ( $sc >= SPINE_CHATBOT_ALT_THRESHOLD ) {
                $label = $mid === '__global__'
                    ? 'General Spine HR Information'
                    : ( $this->kb['modules'][ $mid ]['title'] ?? $mid );
                $alternatives[] = [
                    'module_id' => $mid,
                    'label'     => $label,
                    'score'     => $sc,
                    'preview'   => $this->brief_preview( $mid ),
                ];
            }
        }

        if ( ! empty( $alternatives ) ) {
            return [
                'type'         => 'alternatives',
                'score'        => $top_score,
                'response'     => "I found a few topics that may help. Please select one:",
                'alternatives' => $alternatives,
                'module_id'    => '',
            ];
        }

        return $this->no_match_result( $scope );
    }

    /**
     * Retrieve a direct module answer by ID (used when user picks an alternative).
     */
    public function get_module_overview( string $module_id ): array {
        if ( $module_id === '__global__' ) {
            return [
                'type'         => 'answer',
                'score'        => 100.0,
                'response'     => $this->format_global_overview(),
                'module_id'    => '__global__',
                'alternatives' => [],
            ];
        }

        $module = $this->kb['modules'][ $module_id ] ?? null;
        if ( ! $module ) {
            return $this->no_match_result();
        }

        return [
            'type'         => 'answer',
            'score'        => 100.0,
            'response'     => $this->format_module_overview( $module ),
            'module_id'    => $module_id,
            'alternatives' => [],
        ];
    }

    // ── Scope resolution (v1.1) ────────────────────────────────────────────────

    /**
     * Resolve the active scope to an array of allowed module IDs.
     *
     * @param string $scope  Branch key (e.g. 'hr_suite', 'assets', 'international').
     * @return string[]|null  Array of allowed module IDs, or null for "all modules".
     */
    private function resolve_scope( string $scope ): ?array {
        if ( $scope === '' ) {
            return null; // No scope restriction — search everything.
        }

        $cats = $this->kb['product_categories'] ?? [];

        if ( isset( $cats[ $scope ] ) && is_array( $cats[ $scope ] ) ) {
            return $cats[ $scope ];
        }

        // Unknown / unrecognised scope key — fall back to full search.
        return null;
    }

    // ── Scoring ────────────────────────────────────────────────────────────────

    /**
     * Score a module against the tokenised query.
     *
     * @return array{float, array} [score, best_match]
     */
    private function score_module( array $tokens, string $raw_input, array $module ): array {
        $score      = 0.0;
        $best_match = null;

        // Module keywords
        foreach ( $module['keywords'] as $kw ) {
            $hits   = $this->token_hits( $tokens, $this->tokenise( $kw ) );
            $score += $hits * self::W_MOD_KEYWORD;
        }

        // Phrase match bonus against title / tagline
        if ( $this->phrase_match( strtolower( $raw_input ), strtolower( $module['title'] ) ) ) {
            $score += self::W_PHRASE_BONUS;
        }

        // Overview text
        $score += $this->text_token_score( $tokens, $module['overview'] ?? '' ) * self::W_OVERVIEW;

        // Features
        foreach ( $module['features'] as $feature ) {
            $fn_score = $this->text_token_score( $tokens, $feature['name'] ) * self::W_FEATURE_NAME;
            $fd_score = $this->text_token_score( $tokens, $feature['desc'] ) * self::W_FEATURE_DESC;
            $score   += $fn_score + $fd_score;
        }

        // FAQs — track best individual FAQ match
        $best_faq_score = 0.0;
        foreach ( $module['faqs'] as $faq ) {
            $faq_score = 0.0;

            // FAQ-specific keywords
            foreach ( ( $faq['keywords'] ?? [] ) as $kw ) {
                $faq_score += $this->token_hits( $tokens, $this->tokenise( $kw ) ) * self::W_FAQ_KEYWORD;
            }

            $faq_score += $this->text_token_score( $tokens, $faq['question'] ) * self::W_FAQ_QUESTION;
            $faq_score += $this->text_token_score( $tokens, $faq['answer']   ) * self::W_FAQ_ANSWER;

            // Exact phrase bonus
            if ( $this->phrase_match( strtolower( $raw_input ), strtolower( $faq['question'] ) ) ) {
                $faq_score += self::W_PHRASE_BONUS;
            }

            if ( $faq_score > $best_faq_score ) {
                $best_faq_score = $faq_score;
                $best_match     = [ 'type' => 'faq', 'data' => $faq ];
            }
        }
        $score += $best_faq_score;

        // Semantic synonym scoring
        $score += $this->semantic_score( $tokens, $module );

        return [ $score, $best_match ];
    }

    /**
     * Score global FAQs (only reached when scope = '').
     *
     * @return array{float, array|null}
     */
    private function score_global_faqs( array $tokens, string $raw_input ): array {
        $score = 0.0;
        $best  = null;

        foreach ( $this->kb['global']['general_faqs'] as $faq ) {
            $fs = 0.0;
            foreach ( ( $faq['keywords'] ?? [] ) as $kw ) {
                $fs += $this->token_hits( $tokens, $this->tokenise( $kw ) ) * self::W_FAQ_KEYWORD;
            }
            $fs += $this->text_token_score( $tokens, $faq['question'] ) * self::W_FAQ_QUESTION;
            $fs += $this->text_token_score( $tokens, $faq['answer']   ) * self::W_FAQ_ANSWER;

            if ( $this->phrase_match( strtolower( $raw_input ), strtolower( $faq['question'] ) ) ) {
                $fs += self::W_PHRASE_BONUS;
            }

            if ( $fs > $score ) {
                $score = $fs;
                $best  = [ 'type' => 'faq', 'data' => $faq ];
            }
        }

        return [ $score, $best ];
    }

    /**
     * Score semantic synonym groups against a module.
     */
    private function semantic_score( array $tokens, array $module ): float {
        $groups   = $this->kb['global']['semantic_groups'] ?? [];
        $mod_kwds = array_map( 'strtolower', $module['keywords'] );
        $total    = 0.0;

        foreach ( $groups as $group_name => $synonyms ) {
            // Check if any synonym hits a query token
            $query_hit = false;
            foreach ( $synonyms as $syn ) {
                if ( $this->token_hits( $tokens, $this->tokenise( $syn ) ) > 0 ) {
                    $query_hit = true;
                    break;
                }
            }
            if ( ! $query_hit ) continue;

            // Check if this module has a keyword in the same group
            foreach ( $synonyms as $syn ) {
                if ( in_array( strtolower( $syn ), $mod_kwds, true ) ) {
                    $total += self::W_SEMANTIC;
                    break;
                }
            }
        }

        return $total;
    }

    // ── Token utilities ────────────────────────────────────────────────────────

    /**
     * Normalise text into a deduplicated token array, removing stop-words.
     */
    private function tokenise( string $text ): array {
        $text   = strtolower( $text );
        $text   = preg_replace( '/[^a-z0-9\s\-]/', ' ', $text );
        $words  = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
        $tokens = [];
        foreach ( $words as $w ) {
            if ( strlen( $w ) > 2 && ! in_array( $w, self::STOP_WORDS, true ) ) {
                $tokens[] = $w;
            }
        }
        return array_unique( $tokens );
    }

    /**
     * Count how many of $needle_tokens appear in $haystack_tokens.
     */
    private function token_hits( array $haystack, array $needle ): int {
        $count = 0;
        foreach ( $needle as $n ) {
            foreach ( $haystack as $h ) {
                if ( $h === $n || str_starts_with( $h, $n ) || str_starts_with( $n, $h ) ) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }

    /**
     * Score a raw text string against the query tokens.
     * Returns a 0–1 ratio of matching tokens to query tokens.
     */
    private function text_token_score( array $query_tokens, string $text ): float {
        if ( empty( $query_tokens ) ) return 0.0;
        $text_tokens = $this->tokenise( $text );
        $hits        = $this->token_hits( $text_tokens, $query_tokens );
        return $hits / count( $query_tokens );
    }

    /**
     * Check if any significant phrase from $needle appears in $haystack.
     */
    private function phrase_match( string $haystack, string $needle ): bool {
        $needle_clean = preg_replace( '/[^a-z0-9\s]/', ' ', $needle );
        $parts        = preg_split( '/\s+/', trim( $needle_clean ), -1, PREG_SPLIT_NO_EMPTY );

        // Only bonus for multi-word phrases
        if ( count( $parts ) < 2 ) return false;
        return str_contains( $haystack, implode( ' ', $parts ) );
    }

    // ── Response building ──────────────────────────────────────────────────────

    private function build_response( string $module_id, ?array $match ): string {
        if ( $module_id === '__global__' ) {
            if ( $match && $match['type'] === 'faq' ) {
                return $this->format_faq( $match['data'] );
            }
            return $this->format_global_overview();
        }

        $module = $this->kb['modules'][ $module_id ] ?? null;
        if ( ! $module ) {
            return "I have information about Spine HR Suite. Please ask me a specific question.";
        }

        if ( $match && $match['type'] === 'faq' ) {
            return $this->format_faq( $match['data'] );
        }

        return $this->format_module_overview( $module );
    }

    private function format_faq( array $faq ): string {
        return "**" . esc_html( $faq['question'] ) . "**\n\n" . esc_html( $faq['answer'] );
    }

    private function format_module_overview( array $module ): string {
        $text  = "**" . esc_html( $module['title'] ) . "**\n\n";
        $text .= esc_html( $module['overview'] ) . "\n\n";

        if ( ! empty( $module['features'] ) ) {
            $text .= "**Key Features:**\n";
            $top   = array_slice( $module['features'], 0, 5 );
            foreach ( $top as $f ) {
                $text .= "• " . esc_html( $f['name'] ) . " — " . esc_html( $f['desc'] ) . "\n";
            }
        }

        return trim( $text );
    }

    private function format_global_overview(): string {
        $g     = $this->kb['global'];
        $text  = "**" . esc_html( $g['company_name'] ) . " — " . esc_html( $g['product_suite'] ) . "**\n\n";
        $text .= esc_html( $g['description'] ) . "\n\n";
        $text .= "**Deployment options:** " . implode( ', ', array_map( 'esc_html', $g['deployment'] ) ) . "\n";
        $text .= "**Modules available:** Recruitment, Onboarding, Core HRIS, ESS, Leave, Attendance, Expense, Timesheet, Performance, Help Desk, Offboarding, Visitors, Assets, International HR Suite.\n";
        return trim( $text );
    }

    private function brief_preview( string $module_id ): string {
        if ( $module_id === '__global__' ) {
            return esc_html( $this->kb['global']['description'] ?? '' );
        }
        $m = $this->kb['modules'][ $module_id ] ?? [];
        return esc_html( wp_trim_words( $m['overview'] ?? '', 20, '…' ) );
    }

    /**
     * @param string $scope  Pass scope so the no-match message is context-aware.
     */
    private function no_match_result( string $scope = '' ): array {
        $scope_labels = [
            'hr_suite'      => 'Spine HR Suite',
            'assets'        => 'Spine Assets',
            'international' => 'International HR Suite',
        ];

        $label = $scope !== '' && isset( $scope_labels[ $scope ] )
            ? $scope_labels[ $scope ]
            : 'our product suite';

        return [
            'type'         => 'no_match',
            'score'        => 0.0,
            'response'     => sprintf(
                "I couldn't find a precise answer about %s for that query. Would you like to speak with a specialist, or shall I capture your details for a follow-up?",
                $label
            ),
            'alternatives' => [],
            'module_id'    => '',
        ];
    }
}
