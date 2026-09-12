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

    private const FALLBACK_MODELS = [
        'claude-haiku-4-5-20251001',
        'claude-sonnet-4-6',
        'claude-3-5-haiku-latest',
    ];
    private const MODELS_API_URL = 'https://api.anthropic.com/v1/models';
    private const MODEL_TRANSIENT = 'spine_chatbot_active_model';
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

    // ── Dynamic model resolution ───────────────────────────────────────────────

    private function get_active_model(): string {
        $cached = get_transient( self::MODEL_TRANSIENT );
        if ( $cached ) {
            return $cached;
        }

        $headers = $this->build_headers();
        $response = wp_remote_get( self::MODELS_API_URL, [
            'timeout' => 10,
            'headers' => $headers,
        ] );

        if ( ! is_wp_error( $response ) ) {
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );
            $models = $body['data'] ?? [];
            $best   = $this->select_best_model( $models );
            if ( $best ) {
                set_transient( self::MODEL_TRANSIENT, $best, 7 * DAY_IN_SECONDS );
                return $best;
            }
        }

        return self::FALLBACK_MODELS[0];
    }

    private function select_best_model( array $models ): ?string {
        $active = array_filter( $models, static function ( $m ) {
            $status = $m['status'] ?? 'active';
            return $status === 'active';
        } );

        usort( $active, static function ( $a, $b ) {
            return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
        } );

        foreach ( $active as $m ) {
            if ( str_contains( strtolower( $m['id'] ?? '' ), 'haiku' ) ) {
                return $m['id'];
            }
        }
        foreach ( $active as $m ) {
            if ( str_contains( strtolower( $m['id'] ?? '' ), 'sonnet' ) ) {
                return $m['id'];
            }
        }
        return null;
    }

    private function get_next_fallback_model( string $current ): ?string {
        $list = self::FALLBACK_MODELS;
        $idx  = array_search( $current, $list, true );
        if ( $idx === false ) {
            return $list[0];
        }
        return $list[ $idx + 1 ] ?? null;
    }

    private function build_headers(): array {
        $headers = [
            'x-api-key'         => $this->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ];
        $workspace_id = get_option( 'spine_chatbot_anthropic_workspace_id', '' );
        if ( $workspace_id ) {
            $headers['anthropic-workspace-id'] = $workspace_id;
        }
        return $headers;
    }

    // ── Anthropic API call ─────────────────────────────────────────────────────

    /**
     * @return array|WP_Error  Decoded response body or WP_Error on failure.
     */
    private function call_anthropic( array $messages, ?string $model = null ): array|WP_Error {
        $model   = $model ?? $this->get_active_model();
        $payload = [
            'model'      => $model,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $this->get_system_prompt(),
            'tools'      => $this->get_tool_schemas(),
            'messages'   => $messages,
        ];

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => $this->build_headers(),
            'body'    => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        // Auto-failover: model deprecated / unknown
        if ( $status === 400 && isset( $body['error']['type'] ) && $body['error']['type'] === 'invalid_request_error' ) {
            $msg = strtolower( $body['error']['message'] ?? '' );
            if ( str_contains( $msg, 'model' ) ) {
                $next = $this->get_next_fallback_model( $model );
                if ( $next ) {
                    delete_transient( self::MODEL_TRANSIENT );
                    set_transient( self::MODEL_TRANSIENT, $next, 7 * DAY_IN_SECONDS );
                    update_option( 'spine_chatbot_active_model_log', $next );
                    error_log( "Spine Chatbot: Model '{$model}' unavailable. Auto-switching to '{$next}'." );
                    return $this->call_anthropic( $messages, $next );
                }
            }
        }

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
            'search_knowledge_base' => $this->tool_search_kb( $input['search_query'] ?? '', $session_id ),
            'book_product_demo'     => $this->tool_book_demo( $input, $session_id ),
            default                 => 'Unknown tool: ' . sanitize_key( $name ),
        };
    }

    // ── Tool: search_knowledge_base ────────────────────────────────────────────

    private function tool_search_kb( string $query, string $session_id = '' ): string {
        if ( empty( trim( $query ) ) ) {
            return 'No search query provided.';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'spine_kb_entries';

        // FULLTEXT search
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

        // LIKE fallback when FULLTEXT returns nothing
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
            // Log this unanswered query for cron processing
            Spine_Chatbot_DB::log_unanswered_query( $query, $session_id, $query );

            // Include agent availability so the AI can decide how to respond
            $agents_online = ! empty( Spine_Chatbot_DB::get_online_agents() );
            $agent_status  = $agents_online ? 'online' : 'offline';

            return "NO_MATCH_FOUND_OUT_OF_KB\nAGENT_STATUS: {$agent_status}";
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
        $bot_name      = get_option( 'spine_chatbot_bot_name', 'Spine AI' );
        $support_email = get_option( 'spine_chatbot_support_email', 'support@spinetechnologies.com' );

        return <<<PROMPT
You are "{$bot_name}", an AI assistant for Spine Technologies Pvt. Ltd. — India's leading HR & Asset Management software company with 4,500+ clients across India, the Middle East, and South-East Asia. Products: Spine HR Suite, Spine Assets (Fixed Assets), and International HR Suite.

## STEP 1: Classify Every Message Into Stream 1 or Stream 2

Read the user's message and decide which stream applies before doing anything else.

---

### STREAM 1 — Existing Customer / Technical Support

Apply Stream 1 when the user mentions ANY of the following:
- A bug, error, crash, or malfunction in Spine software
- Data problems (wrong calculations, missing records, incorrect payroll, wrong leave balance)
- Login issues, password resets, or access problems
- Implementation, go-live, or configuration difficulties
- Language that implies they are ALREADY a Spine client ("our system", "in Spine", "the software", "my company uses Spine", "client ID", "after the update")

**Stream 1 Rules — STRICTLY FOLLOW:**
- DO NOT call `search_knowledge_base`. It contains product information for prospects, not technical bug fixes.
- Respond IMMEDIATELY using this exact message structure:

"For technical support, please email our support team at **{$support_email}** with the following details:

• **Company Name** — Your registered company name
• **Client ID** — Your Spine license or client ID number
• **Issue Description** — What is happening and what you expected to happen
• **Screenshot or Screen Recording** — Please attach one if possible, as it drastically speeds up resolution

Our team responds within 1 business day. If the issue is urgent and blocking operations, write **URGENT** in your email subject line."

After sending the support message, you may offer: "Would you like me to connect you with one of our live support agents right now?"

---

### STREAM 2 — Prospect / Feature Enquiry

Apply Stream 2 when the user asks about:
- Features, modules, or capabilities of any Spine product
- Pricing, licensing, or subscription options
- A demo, trial, or product walkthrough
- Implementation timeline, integrations, or compatibility
- Comparisons or "what does Spine do" type questions
- General interest in HR software or payroll systems

**Stream 2 Rules:**
- ALWAYS call `search_knowledge_base` before answering. Base your answer ONLY on what the tool returns — never fabricate features, pricing, or capabilities.
- Keep answers concise (2–4 sentences) and conversational.
- After answering, naturally nudge toward a demo: "Would you like to see this in action? I can arrange a free personalised demo with our product team."
- Format responses as plain text. Avoid heavy bullet lists or markdown headers.

**When `search_knowledge_base` returns `NO_MATCH_FOUND_OUT_OF_KB`:**
- Check the AGENT_STATUS line in the tool result.
- If AGENT_STATUS is `online`: Begin your response with exactly [HANDOVER] — e.g. "[HANDOVER] Let me connect you with a specialist who can answer that directly."
- If AGENT_STATUS is `offline`: Reply politely that you're verifying the exact details and ask for their work email and phone number so the product team can follow up. Say something like: "I want to make sure I give you accurate information on that — our team is currently offline but I can have a specialist reach out with the full details. Could you share your work email and phone number?" Once they provide both, use the `book_product_demo` tool with `how_can_we_help` describing what they asked, and `interest_type` = "HR Suite".

---

## Demo / Lead Capture Protocol (Stream 2 only)

When a user confirms interest in a demo, pricing, trial, or purchasing, collect exactly 8 fields — ONE per message, woven naturally into conversation. Never dump all fields at once.

1. First Name
2. Work Email (corporate domain only — see validation below)
3. Contact Number (with country code)
4. City
5. Number of Employees (HR Suite/International) or Number of Assets (Fixed Assets)
6. Company Name
7. Area of Interest — must be exactly one of: "HR Suite", "Fixed Assets", or "Partnership"
8. How can we help? (one sentence describing their requirement)

**Work Email Validation:** If the user provides a personal email (Gmail, Yahoo, Hotmail, Outlook, iCloud, Rediffmail, Protonmail, etc.), reply: "We need your corporate work email for demo bookings — personal addresses like Gmail aren't accepted. Could you share your work email?" Then wait before continuing.

Once ALL 8 fields are confirmed with a valid corporate email, call `book_product_demo` IMMEDIATELY — do not ask for confirmation.

If `book_product_demo` returns a personal-email error, ask again for the corporate email.

---

## Human Agent Handover

Begin your ENTIRE response with exactly [HANDOVER] (including brackets, no space before) if:
- The user explicitly requests a human, agent, person, representative, or live chat
- The user expresses clear frustration: "not helpful", "useless", "pathetic", "I want to speak to someone"
- The user has repeated the same question 3+ times without satisfaction

Example: "[HANDOVER] I completely understand — let me connect you with one of our specialists right away."

---

## General Guidelines
- Only discuss Spine Technologies products and services.
- For off-topic questions, politely redirect: "I'm here to help with Spine HR software — is there something about our products I can assist with?"
- Never mention competitors by name.

## Response Tone — STRICTLY FOLLOW
- NEVER begin a response with filler openers: "Great question!", "That's a great question!", "Excellent!", "Perfect!", "Absolutely!", "Sure!", "Of course!", "Yes, absolutely!", "Certainly!", "That's interesting!", "Wonderful!", or any similar hollow affirmation.
- Open every response with a direct factual statement, a specific answer, or the first substantive sentence of your reply.
- Keep language precise and executive — avoid padding, hedging, or filler phrases mid-response as well.
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
                        'contact_number'  => [ 'type' => 'string', 'description' => 'Indian mobile number — 10 digits starting with 6, 7, 8, or 9 (e.g. 9876543210). Strip any +91/91/0 prefix before passing.' ],
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

    // ── Cron: automated KB gap processing ─────────────────────────────────────

    /**
     * Called daily by WP-Cron. Finds frequently unanswered queries, asks
     * Anthropic to generate candidate Q&A entries, stores them for admin review.
     */
    public static function process_gap_queries_cron(): void {
        $api_key = get_option( 'spine_chatbot_anthropic_key', '' );
        if ( empty( $api_key ) ) {
            return;
        }

        $gaps = Spine_Chatbot_DB::get_frequent_unanswered( 2, 7 );
        if ( empty( $gaps ) ) {
            return;
        }

        $instance = new self(
            new Spine_Chatbot_Leads(),
            new Spine_Chatbot_Router()
        );

        foreach ( $gaps as $gap ) {
            $query_text = $gap->query_text;

            $prompt = "You are a knowledge base writer for Spine Technologies Pvt. Ltd., an HR software company.\n\n"
                    . "A visitor asked: \"{$query_text}\"\n\n"
                    . "Our knowledge base did not have an answer. Write a concise, accurate Q&A entry about this topic "
                    . "as it relates to Spine HR Suite, Spine Assets, or HR software in general.\n\n"
                    . "Reply ONLY with valid JSON in this exact format (no markdown, no preamble):\n"
                    . "{\"title\": \"short question title\", \"content\": \"Q: full question\\nA: full answer\", \"confidence\": 0.85}";

            $response = wp_remote_post( self::API_URL, [
                'timeout' => 25,
                'headers' => $instance->build_headers(),
                'body'    => wp_json_encode( [
                    'model'      => $instance->get_active_model(),
                    'max_tokens' => 512,
                    'messages'   => [
                        [ 'role' => 'user', 'content' => $prompt ],
                    ],
                ] ),
            ] );

            if ( is_wp_error( $response ) ) {
                continue;
            }

            $body    = json_decode( wp_remote_retrieve_body( $response ), true );
            $raw_txt = '';
            foreach ( ( $body['content'] ?? [] ) as $block ) {
                if ( ( $block['type'] ?? '' ) === 'text' ) {
                    $raw_txt = $block['text'] ?? '';
                    break;
                }
            }

            if ( empty( $raw_txt ) ) {
                continue;
            }

            // Strip markdown code fences if Claude wraps the JSON
            $clean = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw_txt ) );
            $clean = preg_replace( '/\s*```$/', '', $clean );

            $parsed = json_decode( $clean, true );
            if ( ! is_array( $parsed ) || empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
                continue;
            }

            Spine_Chatbot_DB::insert_kb_upgrade(
                $query_text,
                sanitize_text_field( $parsed['title'] ),
                sanitize_textarea_field( $parsed['content'] ),
                (float) ( $parsed['confidence'] ?? 0.7 )
            );
        }
    }
}
