<?php
/**
 * Spine HR Chatbot — Anthropic AI Agent  (v2.0)
 *
 * Replaces the static keyword-scoring engine with a Claude-powered agentic loop
 * using native Function Calling and RAG via the wp_spine_kb_entries table.
 *
 * Flow per user message:
 *   1. Build message history from session transcript (last 20 stored messages).
 *   2. POST to Anthropic Messages API with system prompt + two tool schemas.
 *   3. If stop_reason = 'tool_use'  → execute tool(s), append tool_result, loop.
 *   4. If stop_reason = 'end_turn'  → return final text.
 *   5. Detect [HANDOVER] prefix     → trigger pending_agent routing.
 *   6. Detect demo_booked result    → mark session lead_captured.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_AI {

    private const MODEL      = 'claude-3-5-sonnet-20241022';
    private const MAX_TOKENS = 1024;
    private const MAX_LOOPS  = 6;
    private const API_URL    = 'https://api.anthropic.com/v1/messages';
    private const KB_LIMIT   = 5;

    private string               $api_key;
    private Spine_Chatbot_Leads  $leads;
    private Spine_Chatbot_Router $router;

    public function __construct( Spine_Chatbot_Leads $leads, Spine_Chatbot_Router $router ) {
        $this->api_key = get_option( 'spine_chatbot_anthropic_key', '' );
        $this->leads   = $leads;
        $this->router  = $router;
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Main entry point called by the AJAX handler.
     *
     * @param string $user_message  Sanitised user message.
     * @param string $session_id    Active session ID.
     * @param string $branch        Branch slug (hr_suite|assets|international).
     *
     * @return array{
     *   type: string,       // answer | handover | demo_booked | error
     *   response: string,
     *   demo_booked?: bool,
     * }
     */
    public function query( string $user_message, string $session_id, string $branch = '' ): array {
        if ( empty( $this->api_key ) ) {
            return [
                'type'     => 'error',
                'response' => 'AI assistant is not configured. Please add your Anthropic API key in Spine Chat → Settings.',
            ];
        }

        $messages = $this->build_messages( $session_id, $user_message, $branch );
        return $this->run_agentic_loop( $messages, $session_id, $branch );
    }

    // ── Agentic loop ───────────────────────────────────────────────────────────

    private function run_agentic_loop( array $messages, string $session_id, string $branch ): array {
        $loops = 0;

        while ( $loops < self::MAX_LOOPS ) {
            $loops++;

            $api_response = $this->call_anthropic( $messages );

            if ( is_wp_error( $api_response ) ) {
                return [
                    'type'     => 'error',
                    'response' => 'I\'m having trouble connecting right now. Please try again in a moment.',
                ];
            }

            $stop_reason = $api_response['stop_reason'] ?? 'end_turn';
            $content     = $api_response['content']     ?? [];

            // ── Tool use: execute + continue loop ─────────────────────────
            if ( $stop_reason === 'tool_use' ) {
                $messages[] = [ 'role' => 'assistant', 'content' => $content ];

                $tool_results = [];
                foreach ( $content as $block ) {
                    if ( ( $block['type'] ?? '' ) !== 'tool_use' ) {
                        continue;
                    }

                    $tool_name   = $block['name']  ?? '';
                    $tool_input  = $block['input']  ?? [];
                    $tool_use_id = $block['id']     ?? '';

                    $result = $this->dispatch_tool( $tool_name, $tool_input, $session_id );

                    // book_product_demo success → get Claude's closing message then return
                    if ( $tool_name === 'book_product_demo' && ( $result['success'] ?? false ) ) {
                        $tool_results[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $tool_use_id,
                            'content'     => $result['message'],
                        ];
                        $messages[] = [ 'role' => 'user', 'content' => $tool_results ];

                        $final       = $this->call_anthropic( $messages );
                        $final_text  = $this->extract_text( ( $final['content'] ?? [] ) );

                        return [
                            'type'        => 'demo_booked',
                            'response'    => $final_text ?: 'Your demo request has been saved! Our team will be in touch shortly.',
                            'demo_booked' => true,
                        ];
                    }

                    $content_str  = is_string( $result )
                        ? $result
                        : ( $result['message'] ?? wp_json_encode( $result ) );

                    $tool_results[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $tool_use_id,
                        'content'     => $content_str,
                    ];
                }

                $messages[] = [ 'role' => 'user', 'content' => $tool_results ];
                continue;
            }

            // ── End turn: extract and classify text ───────────────────────
            $text = $this->extract_text( $content );

            if ( str_starts_with( $text, '[HANDOVER]' ) ) {
                $clean = trim( substr( $text, strlen( '[HANDOVER]' ) ) );
                return [
                    'type'     => 'handover',
                    'response' => $clean ?: 'Let me connect you with one of our specialists right away.',
                ];
            }

            return [
                'type'     => 'answer',
                'response' => $text,
            ];
        }

        return [
            'type'     => 'answer',
            'response' => 'I\'m having trouble processing your request. Would you like me to connect you with a specialist instead?',
        ];
    }

    // ── Message history builder ────────────────────────────────────────────────

    /**
     * Build a Claude-compatible messages array from the stored session transcript.
     * Includes the current user message at the end.
     */
    private function build_messages( string $session_id, string $current_message, string $branch ): array {
        $session    = Spine_Chatbot_DB::get_session( $session_id );
        $transcript = json_decode( $session->transcript ?? '[]', true ) ?: [];

        // Keep last 20 stored messages (10 exchanges) to bound token usage
        $recent   = array_slice( $transcript, -20 );
        $messages = [];

        foreach ( $recent as $msg ) {
            $role    = $msg['role'] ?? '';
            $content = $msg['content'] ?? '';

            if ( empty( $content ) || in_array( $role, [ 'system', 'agent' ], true ) ) {
                continue;
            }

            $claude_role = ( $role === 'user' ) ? 'user' : 'assistant';
            $messages[]  = [ 'role' => $claude_role, 'content' => $content ];
        }

        // Append current user message
        $messages[] = [ 'role' => 'user', 'content' => $current_message ];

        return $this->normalize_messages( $messages );
    }

    /**
     * Claude requires strictly alternating user/assistant roles.
     * Merge consecutive same-role messages.
     */
    private function normalize_messages( array $messages ): array {
        if ( empty( $messages ) ) {
            return [];
        }

        $normalized = [ $messages[0] ];

        for ( $i = 1, $n = count( $messages ); $i < $n; $i++ ) {
            $prev = &$normalized[ count( $normalized ) - 1 ];
            $curr = $messages[ $i ];

            if ( $prev['role'] === $curr['role'] && is_string( $prev['content'] ) ) {
                $prev['content'] .= "\n" . $curr['content'];
            } else {
                $normalized[] = $curr;
            }
        }

        // Must begin with 'user'
        if ( $normalized[0]['role'] !== 'user' ) {
            array_unshift( $normalized, [ 'role' => 'user', 'content' => 'Hello' ] );
        }

        return $normalized;
    }

    // ── Anthropic API call ─────────────────────────────────────────────────────

    /**
     * @return array|WP_Error  Decoded response body or WP_Error on failure.
     */
    private function call_anthropic( array $messages ): array|WP_Error {
        $payload = [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $this->get_system_prompt(),
            'tools'      => $this->get_tool_schemas(),
            'messages'   => $messages,
        ];

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new WP_Error(
                'anthropic_api_error',
                $body['error']['message'] ?? 'Unknown Anthropic API error'
            );
        }

        return $body;
    }

    // ── Tool dispatcher ────────────────────────────────────────────────────────

    private function dispatch_tool( string $name, array $input, string $session_id ): mixed {
        return match ( $name ) {
            'search_knowledge_base' => $this->tool_search_kb( $input['search_query'] ?? '' ),
            'book_product_demo'     => $this->tool_book_demo( $input, $session_id ),
            default                 => 'Unknown tool: ' . sanitize_key( $name ),
        };
    }

    // ── Tool: search_knowledge_base ────────────────────────────────────────────

    private function tool_search_kb( string $query ): string {
        if ( empty( trim( $query ) ) ) {
            return 'No search query provided.';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'spine_kb_entries';

        // FULLTEXT search (requires InnoDB FULLTEXT index seeded at install)
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT content, module, entry_type,
                    MATCH(content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance
             FROM {$table}
             WHERE MATCH(content) AGAINST(%s IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC
             LIMIT %d",
            $query,
            $query,
            self::KB_LIMIT
        ) );

        // LIKE fallback when FULLTEXT returns nothing (e.g. table has < 4 rows)
        if ( empty( $results ) ) {
            $like    = '%' . $wpdb->esc_like( $query ) . '%';
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT content, module, entry_type FROM {$table}
                 WHERE content LIKE %s
                 ORDER BY id DESC
                 LIMIT %d",
                $like,
                self::KB_LIMIT
            ) );
        }

        if ( empty( $results ) ) {
            return "No KB entries found for query: " . sanitize_text_field( $query );
        }

        $out = "Found " . count( $results ) . " relevant knowledge base entries:\n\n";
        foreach ( $results as $i => $row ) {
            $out .= "[" . ( $i + 1 ) . "] Module: {$row->module} | Type: {$row->entry_type}\n";
            $out .= $row->content . "\n\n";
        }

        return trim( $out );
    }

    // ── Tool: book_product_demo ────────────────────────────────────────────────

    private function tool_book_demo( array $input, string $session_id ): array {
        $email  = sanitize_email( $input['work_email'] ?? '' );
        $domain = strtolower( (string) substr( $email, (int) strpos( $email, '@' ) + 1 ) );

        $personal_domains = [
            'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.in', 'yahoo.co.uk',
            'yahoo.com.au', 'ymail.com', 'hotmail.com', 'hotmail.co.uk', 'hotmail.in',
            'outlook.com', 'outlook.in', 'live.com', 'msn.com', 'icloud.com', 'me.com',
            'mac.com', 'aol.com', 'mail.com', 'rediffmail.com', 'rocketmail.com',
            'protonmail.com', 'proton.me', 'zoho.com', 'tutanota.com',
        ];

        if ( in_array( $domain, $personal_domains, true ) ) {
            return [
                'success' => false,
                'message' => 'Personal email detected. Ask the user for their corporate/work email address — personal domains like Gmail are not accepted for demo requests.',
            ];
        }

        $city         = sanitize_text_field( $input['city']            ?? '' );
        $company_size = sanitize_text_field( $input['company_size']    ?? '' );
        $interest     = sanitize_text_field( $input['interest_type']   ?? '' );
        $help_text    = sanitize_textarea_field( $input['how_can_we_help'] ?? '' );

        $raw = [
            'name'    => sanitize_text_field( $input['first_name']     ?? '' ),
            'email'   => $email,
            'phone'   => sanitize_text_field( $input['contact_number'] ?? '' ),
            'company' => sanitize_text_field( $input['company_name']   ?? '' ),
            'message' => "City: {$city} | Company Size: {$company_size} | Interest: {$interest} | Requirement: {$help_text}",
            'type'    => 'demo_request',
        ];

        $result = $this->leads->capture( $session_id, $raw );

        if ( $result['success'] ) {
            return [
                'success' => true,
                'message' => "Demo request saved for {$raw['name']} ({$raw['email']}). Session ID: {$session_id}.",
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to save demo: ' . implode( ', ', $result['errors'] ?? [ 'Unknown error' ] ),
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function extract_text( array $content_blocks ): string {
        foreach ( $content_blocks as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' ) {
                return $block['text'] ?? '';
            }
        }
        return '';
    }

    // ── System prompt ──────────────────────────────────────────────────────────

    private function get_system_prompt(): string {
        $bot_name = get_option( 'spine_chatbot_bot_name', 'Spine AI' );

        return <<<PROMPT
You are "{$bot_name}", a professional and helpful AI consultant for Spine Technologies Pvt. Ltd. — a leading HR & Asset Management software company with 4,500+ clients across India, the Middle East, and South-East Asia. Products: Spine HR Suite, Spine Assets (Fixed Assets), and International HR Suite.

## Core Behavior
- ALWAYS call `search_knowledge_base` before answering any product question. Base answers ONLY on what the tool returns — never fabricate features, pricing, or product details.
- Be conversational, warm, and professional. Keep responses concise (2–4 sentences) unless detail is genuinely needed.
- If the KB returns no relevant results, say so honestly and offer to connect the visitor with a specialist or collect their details for a demo.
- Format responses as plain text — no markdown headers, no excessive bullet lists.

## Demo / Lead Capture Protocol
When a user expresses interest in a demo, pricing, trial, or purchasing:
- Collect exactly 8 fields conversationally — ONE field per message, woven naturally into conversation. Never dump all fields at once or present a form.
  1. First Name
  2. Work Email (corporate domain only — see validation rules below)
  3. Contact Number (with country code)
  4. City
  5. Number of Employees (HR Suite/International) or Number of Assets (Fixed Assets)
  6. Company Name
  7. Area of Interest — must be one of: "HR Suite", "Fixed Assets", or "Partnership"
  8. How can we help you? (one sentence describing their requirement)

- Work Email Validation: If the user provides a personal email (Gmail, Yahoo, Hotmail, Outlook, iCloud, Rediffmail, Protonmail, etc.), respond: "We'd need your corporate work email for demo bookings — personal addresses like Gmail aren't accepted. Could you share your work email?" Then wait before continuing.

- Once ALL 8 fields are confirmed and the email is a valid corporate address, call `book_product_demo` IMMEDIATELY — do not ask for confirmation first.

- If `book_product_demo` returns a personal-email error, ask again for the corporate email.

## Human Agent Handover
Begin your ENTIRE response with exactly [HANDOVER] (including brackets, no space before) if:
- The user says words like: "human", "agent", "person", "representative", "live chat", "speak to someone", "connect me"
- The user expresses clear frustration: "not helpful", "useless", "this is pathetic", "I want to talk to someone"
- The user has asked the same question 3+ times without satisfaction

Example: "[HANDOVER] I completely understand — let me connect you with one of our specialists right away. Please hold on for just a moment."

## Scope
- Only discuss Spine Technologies products and services.
- For off-topic questions (sports, general coding, etc.), politely redirect: "I'm focused on Spine HR products — is there anything about our HR Suite or Asset management I can help you with?"
- Never mention competitors by name.
PROMPT;
    }

    // ── Tool schemas ───────────────────────────────────────────────────────────

    private function get_tool_schemas(): array {
        return [
            [
                'name'         => 'search_knowledge_base',
                'description'  => 'Searches the Spine HR knowledge base for software features, FAQs, module overviews, and product definitions. Call this before answering any product-related question.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search_query' => [
                            'type'        => 'string',
                            'description' => 'Keywords or phrase to search within the knowledge base.',
                        ],
                    ],
                    'required' => [ 'search_query' ],
                ],
            ],
            [
                'name'         => 'book_product_demo',
                'description'  => 'Call this ONLY when the user has conversationally provided all 8 required fields. Saves the demo request and notifies the Spine sales team.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'first_name'      => [ 'type' => 'string', 'description' => "Visitor's first name." ],
                        'work_email'      => [ 'type' => 'string', 'description' => 'Corporate email address — not Gmail/Yahoo/personal.' ],
                        'contact_number'  => [ 'type' => 'string', 'description' => 'Phone number with country code.' ],
                        'city'            => [ 'type' => 'string', 'description' => "Visitor's city." ],
                        'company_size'    => [ 'type' => 'string', 'description' => 'Number of employees or number of assets.' ],
                        'company_name'    => [ 'type' => 'string', 'description' => 'Name of the company.' ],
                        'interest_type'   => [
                            'type'        => 'string',
                            'enum'        => [ 'HR Suite', 'Fixed Assets', 'Partnership' ],
                            'description' => 'Which Spine product the visitor is interested in.',
                        ],
                        'how_can_we_help' => [ 'type' => 'string', 'description' => 'Brief description of their specific requirement.' ],
                    ],
                    'required' => [
                        'first_name', 'work_email', 'contact_number', 'city',
                        'company_size', 'company_name', 'interest_type', 'how_can_we_help',
                    ],
                ],
            ],
        ];
    }
}
