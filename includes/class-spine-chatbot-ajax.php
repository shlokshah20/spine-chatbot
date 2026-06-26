<?php
/**
 * AJAX Handler — All frontend & admin endpoints  (v2.0 — AI Agent)
 *
 * Every action verifies a nonce, sanitises all inputs, and escapes all outputs.
 * Public (nopriv) actions handle anonymous site visitors;
 * admin actions require capability checks.
 *
 * v2.0 changes:
 *   • handle_message() now routes through Spine_Chatbot_AI instead of static KB search.
 *   • Handles AI response types: answer | handover | demo_booked | error.
 *   • handle_module_select() removed (no longer applicable to AI flow).
 *   • KB CRUD AJAX endpoints added: spine_kb_add, spine_kb_update, spine_kb_delete,
 *     spine_kb_import_file, spine_kb_seed.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Ajax {

    private Spine_Chatbot_AI     $ai;
    private Spine_Chatbot_Router $router;
    private Spine_Chatbot_Leads  $leads;

    /**
     * Maps the frontend branch identifier to the KB product_categories key.
     * The 'support' branch is handled separately (no KB search).
     */
    private const BRANCH_SCOPE_MAP = [
        'hr_suite'      => 'hr_suite',
        'assets'        => 'assets',
        'international' => 'international',
    ];

    /**
     * Human-readable branch labels used in transcript system messages.
     */
    private const BRANCH_LABELS = [
        'hr_suite'      => 'HR Suite',
        'assets'        => 'Spine Assets',
        'support'       => 'Support',
        'international' => 'International HR Suite',
    ];

    public function __construct(
        Spine_Chatbot_AI     $ai,
        Spine_Chatbot_Router $router,
        Spine_Chatbot_Leads  $leads
    ) {
        $this->ai     = $ai;
        $this->router = $router;
        $this->leads  = $leads;
    }

    // ── Hook registration ──────────────────────────────────────────────────────

    public function register(): void {
        // When WP_DEBUG = true, WordPress calls $wpdb->show_errors() globally, which
        // causes $wpdb to echo raw SQL errors straight to output.  For AJAX endpoints
        // that emit JSON that would corrupt the response body and make jQuery unable
        // to parse the result — resulting in "Something went wrong" on the client.
        // Suppressing it here keeps JSON responses clean while still logging errors
        // in $wpdb->last_error for internal inspection.
        if ( wp_doing_ajax() ) {
            global $wpdb;
            $wpdb->hide_errors();
        }

        // ── Frontend (public) actions ──────────────────────────────────────
        $public = [
            'spine_chat_init'           => 'handle_init',
            'spine_chat_branch_select'  => 'handle_branch_select',
            'spine_chat_message'        => 'handle_message',
            'spine_chat_request_agent'  => 'handle_request_agent',
            'spine_chat_submit_lead'    => 'handle_submit_lead',
            'spine_chat_poll'           => 'handle_poll',
            'spine_chat_upload'         => 'handle_visitor_upload',
        ];
        foreach ( $public as $action => $method ) {
            add_action( 'wp_ajax_'        . $action, [ $this, $method ] );
            add_action( 'wp_ajax_nopriv_' . $action, [ $this, $method ] );
        }

        // ── Agent / admin actions (logged-in only) ─────────────────────────
        $agent_actions = [
            'spine_agent_ping'          => 'handle_agent_ping',
            'spine_agent_accept'        => 'handle_agent_accept',
            'spine_agent_message'       => 'handle_agent_message',
            'spine_agent_close'         => 'handle_agent_close',
            'spine_agent_get_sessions'  => 'handle_agent_get_sessions',
            'spine_agent_get_messages'  => 'handle_agent_get_messages',
            'spine_agent_session_poll'  => 'handle_agent_session_poll',
            'spine_agent_upload'        => 'handle_agent_upload',
        ];
        foreach ( $agent_actions as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }

        // ── Super-admin actions ────────────────────────────────────────────
        add_action( 'wp_ajax_spine_admin_all_sessions',  [ $this, 'handle_admin_all_sessions' ] );
        add_action( 'wp_ajax_spine_admin_agent_list',    [ $this, 'handle_admin_agent_list' ] );

        // ── Knowledge Base CRUD (admin-only) ───────────────────────────────
        $kb_actions = [
            'spine_kb_add'         => 'handle_kb_add',
            'spine_kb_update'      => 'handle_kb_update',
            'spine_kb_delete'      => 'handle_kb_delete',
            'spine_kb_import_file' => 'handle_kb_import_file',
            'spine_kb_seed'        => 'handle_kb_seed',
        ];
        foreach ( $kb_actions as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FRONTEND HANDLERS
    // ══════════════════════════════════════════════════════════════════════════

    /** Initialise or resume a chat session. */
    public function handle_init(): void {
        $this->verify_nonce( 'spine_chat_nonce' );

        $raw_sid = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $session = $raw_sid ? Spine_Chatbot_DB::get_session( $raw_sid ) : null;

        if ( $session ) {
            $this->json_success( [
                'session_id' => $session->session_id,
                'status'     => $session->status,
                'branch'     => sanitize_key( $session->branch ?? '' ),
                'transcript' => json_decode( $session->transcript, true ) ?: [],
            ] );
        }

        // Create fresh session
        $session_id = $this->generate_session_id();
        $created    = Spine_Chatbot_DB::create_session( $session_id );

        // If the DB write failed the table may be missing (e.g. activation hook
        // didn't run cleanly).  Attempt self-repair via install() then retry once.
        if ( ! $created ) {
            global $wpdb;
            $first_error = $wpdb->last_error;

            // Re-run dbDelta to create/repair the table
            Spine_Chatbot_DB::install();
            $session_id = $this->generate_session_id(); // fresh id after repair
            $created    = Spine_Chatbot_DB::create_session( $session_id );

            if ( ! $created ) {
                // Still failing — return the real DB error so we can diagnose it
                wp_send_json_error( [
                    'message'     => 'Database error: could not create session.',
                    'db_error'    => $wpdb->last_error ?: $first_error,
                    'db_version'  => get_option( 'spine_chatbot_db_version', 'not_set' ),
                    'table'       => $wpdb->prefix . 'spine_chatbot_interactions',
                    'table_exists'=> (bool) $wpdb->get_var(
                        $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'spine_chatbot_interactions' )
                    ),
                ] );
                exit;
            }
        }

        $welcome = get_option( 'spine_chatbot_welcome_message',
            "Hello! 👋 I'm the Spine HR Assistant. Please select a topic to get started:" );

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'bot',
            'content' => $welcome,
        ] );

        $this->json_success( [
            'session_id'      => $session_id,
            'status'          => 'branch_select',
            'branch'          => '',
            'welcome_message' => esc_html( $welcome ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Branch selection handler  (v1.1 — new endpoint)
     *
     * Called when the user clicks one of the 4 initial branch buttons:
     *   HR Suite / Assets / Support / International HR Suite.
     *
     * POST parameters:
     *   session_id  string  required
     *   branch      string  one of: hr_suite | assets | support | international
     *
     * For the 'support' branch:
     *   - Appends a polite system message with the support mailto link.
     *   - Sets session status to 'support_redirect'.
     *   - Returns { outcome: 'support', support_email, message }.
     *
     * For product branches (hr_suite / assets / international):
     *   - Appends a system acknowledgement message scoped to that product.
     *   - Updates session's 'branch' column via Spine_Chatbot_DB::update_session().
     *   - Returns { outcome: 'chat_open', branch, prompt_message }.
     */
    public function handle_branch_select(): void {
        $this->verify_nonce( 'spine_chat_nonce' );

        $session_id = $this->require_session();
        $branch     = sanitize_key( wp_unslash( $_POST['branch'] ?? '' ) );

        // Validate branch slug
        $valid_branches = [ 'hr_suite', 'assets', 'support', 'international' ];
        if ( ! in_array( $branch, $valid_branches, true ) ) {
            $this->json_error( 'Invalid branch selection.' );
        }

        $label = self::BRANCH_LABELS[ $branch ] ?? $branch;

        // ── Support branch: freeze chat, no KB search ─────────────────────
        if ( $branch === 'support' ) {
            $support_email = sanitize_email(
                get_option( 'spine_chatbot_support_email', 'support@spinetechnologies.com' )
            );

            $msg = sprintf(
                'Our support team is ready to help! To ensure your query reaches the right specialist, please email us directly at <a href="mailto:%1$s" class="spine-msg__link">%1$s</a>. We aim to respond within 1 business day.',
                esc_attr( $support_email )
            );

            // Record branch selection as system message
            Spine_Chatbot_DB::append_message( $session_id, [
                'role'    => 'system',
                'content' => '[Selected branch: Support]',
            ] );

            // Append the bot support message
            Spine_Chatbot_DB::append_message( $session_id, [
                'role'    => 'bot',
                'content' => $msg,
            ] );

            // Mark session as support redirect — no further interaction needed
            Spine_Chatbot_DB::update_session( $session_id, [
                'status' => 'support_redirect',
                'branch' => 'support',
            ] );

            $this->json_success( [
                'outcome'       => 'support',
                'support_email' => esc_html( $support_email ),
                'message'       => $msg,
            ] );
        }

        // ── Product branch: open chat scoped to this product ──────────────
        $prompt_map = [
            'hr_suite' => sprintf(
                "Great choice! I can answer detailed questions about %s — covering Recruitment, Onboarding, Core HRIS, Leave, Attendance, Performance Management, and more. What would you like to know?",
                esc_html( $label )
            ),
            'assets' => sprintf(
                "You've selected %s. I can help with asset allocation, tracking, depreciation, audit workflows, and more. What would you like to know?",
                esc_html( $label )
            ),
            'international' => sprintf(
                "You've selected %s. I can answer questions on GCC/UAE compliance (WPS, GOSI, gratuity), Saudi Nitaqat/Saudization, South-East Asia (EPF, CPF, SSS), expatriate management, multi-currency payroll, and more. What would you like to know?",
                esc_html( $label )
            ),
        ];

        $prompt_message = $prompt_map[ $branch ] ?? "You've selected {$label}. How can I help?";

        // Record branch selection as system message
        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'system',
            'content' => '[Selected branch: ' . $label . ']',
        ] );

        // Append bot acknowledgement
        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'bot',
            'content' => $prompt_message,
        ] );

        // Persist branch on the session record
        Spine_Chatbot_DB::update_session( $session_id, [
            'status' => 'bot',
            'branch' => $branch,
        ] );

        $this->json_success( [
            'outcome'        => 'chat_open',
            'branch'         => $branch,
            'prompt_message' => $prompt_message,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Process a user message through the AI agent (v2.0).
     *
     * Replaces the static KB search with Anthropic Claude API + RAG.
     * Response types from AI:
     *   answer      → normal bot reply
     *   handover    → escalate to live agent (same as handle_request_agent)
     *   demo_booked → lead captured via tool call; return success message
     *   error       → API failure; return graceful error
     */
    public function handle_message(): void {
        $this->verify_nonce( 'spine_chat_nonce' );

        $session_id = $this->require_session();
        $message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
        $branch     = sanitize_key( wp_unslash( $_POST['branch'] ?? '' ) );

        if ( empty( $message ) ) {
            $this->json_error( 'Message cannot be empty.' );
        }

        $session = Spine_Chatbot_DB::get_session( $session_id );

        if ( $session && $session->status === 'support_redirect' ) {
            $this->json_error( 'This session has been redirected to email support. No further messages can be sent.' );
        }

        // Record the user message in the transcript
        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'user',
            'content' => $message,
        ] );

        // ── If a live agent is present, skip AI entirely ───────────────────
        if ( in_array( $session->status ?? '', [ 'active', 'pending_agent' ], true ) ) {
            $agent_id   = (int) ( $session->agent_id ?? 0 );
            $agent_name = '';
            if ( $agent_id ) {
                $info       = $this->router->get_agent_display_info( $agent_id );
                $agent_name = $info['name'] ?? '';
            }
            $this->json_success( [
                'type'       => 'live_agent',
                'status'     => $session->status,
                'agent_name' => $agent_name,
            ] );
        }

        // ── Query the AI agent ─────────────────────────────────────────────
        $result = $this->ai->query( $message, $session_id, $branch );

        // ── Handle response types ──────────────────────────────────────────

        if ( $result['type'] === 'handover' ) {
            // AI decided to escalate — run the same routing logic as handle_request_agent
            $agent = $this->router->dispatch( $session_id );

            if ( $agent ) {
                $info = $this->router->get_agent_display_info( $agent->ID );

                Spine_Chatbot_DB::append_message( $session_id, [
                    'role'    => 'bot',
                    'content' => $result['response'],
                ] );

                $this->json_success( [
                    'type'       => 'requesting_agent',
                    'response'   => $result['response'],
                    'outcome'    => 'pending_agent',
                    'agent_name' => esc_html( $agent->display_name ),
                    'agent'      => $info,
                ] );
            }

            // No agents online
            $offline_msg = "Our support team is currently offline. Please leave your details and we'll get back to you promptly — or book a demo to speak with our team.";
            Spine_Chatbot_DB::append_message( $session_id, [
                'role'    => 'bot',
                'content' => $offline_msg,
            ] );

            $this->json_success( [
                'type'     => 'requesting_agent',
                'response' => $offline_msg,
                'outcome'  => 'offline',
                'demo_url' => esc_url( get_option( 'spine_chatbot_demo_url', 'https://spinetechnologies.com/request-demo/' ) ),
            ] );
        }

        if ( $result['type'] === 'demo_booked' ) {
            // Lead was captured via the book_product_demo tool
            Spine_Chatbot_DB::append_message( $session_id, [
                'role'    => 'bot',
                'content' => $result['response'],
            ] );

            $this->json_success( [
                'type'        => 'demo_booked',
                'response'    => $result['response'],
                'demo_booked' => true,
            ] );
        }

        // Default: answer (or error — still show the response)
        $bot_reply = $result['response'];

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'bot',
            'content' => $bot_reply,
        ] );

        $this->json_success( [
            'type'     => $result['type'] === 'error' ? 'answer' : 'answer',
            'response' => $bot_reply,
            'branch'   => $branch,
        ] );
    }

    /** User requests a live agent. */
    public function handle_request_agent(): void {
        $this->verify_nonce( 'spine_chat_nonce' );

        $session_id = $this->require_session();

        $session = Spine_Chatbot_DB::get_session( $session_id );

        // Guard: don't re-queue if already pending or active
        if ( in_array( $session->status ?? '', [ 'active', 'pending_agent' ], true ) ) {
            $this->json_success( [
                'outcome' => $session->status,
                'message' => 'You are already in the queue. An agent will be with you shortly.',
            ] );
        }

        // Guard: cannot request agent from a support_redirect session
        if ( ( $session->status ?? '' ) === 'support_redirect' ) {
            $this->json_error( 'This session has been directed to email support.' );
        }

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'user',
            'content' => '[Requested live agent support]',
        ] );

        $agent = $this->router->dispatch( $session_id );

        if ( $agent ) {
            $info = $this->router->get_agent_display_info( $agent->ID );

            Spine_Chatbot_DB::append_message( $session_id, [
                'role'    => 'bot',
                'content' => 'You have been connected to ' . esc_html( $agent->display_name ) . '. Please wait while they accept your chat.',
            ] );

            $this->json_success( [
                'outcome'    => 'pending_agent',
                'agent_name' => esc_html( $agent->display_name ),
                'agent'      => $info,
                'message'    => 'An agent has been notified and will join you shortly.',
            ] );
        }

        // No agents online → show offline form
        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'bot',
            'content' => 'Our support team is currently offline. Please leave your details and we\'ll get back to you promptly — or book a demo to speak with our team.',
        ] );

        $demo_url = esc_url( get_option( 'spine_chatbot_demo_url', 'https://spinetechnologies.com/request-demo/' ) );

        $this->json_success( [
            'outcome'  => 'offline',
            'message'  => 'No agents are currently online.',
            'demo_url' => $demo_url,
        ] );
    }

    /** User submits the lead / offline ticket form. */
    public function handle_submit_lead(): void {
        $this->verify_nonce( 'spine_chat_nonce' );

        $session_id = $this->require_session();

        $raw = [
            'name'    => wp_unslash( $_POST['name']    ?? '' ),
            'email'   => wp_unslash( $_POST['email']   ?? '' ),
            'phone'   => wp_unslash( $_POST['phone']   ?? '' ),
            'company' => wp_unslash( $_POST['company'] ?? '' ),
            'message' => wp_unslash( $_POST['message'] ?? '' ),
            'type'    => wp_unslash( $_POST['type']    ?? 'demo_request' ),
        ];

        $result = $this->leads->capture( $session_id, $raw );

        if ( ! $result['success'] ) {
            $this->json_error( 'Validation failed.', $result['errors'] );
        }

        $this->json_success( [
            'message' => 'Thank you! Your details have been received. Our team will reach out within 1 business day.',
        ] );
    }

    /**
     * Frontend long-poll / non-heartbeat fallback.
     * Returns new messages for the session since a given timestamp.
     */
    public function handle_poll(): void {
        $this->verify_nonce( 'spine_chat_nonce' );

        $session_id = $this->require_session();
        $since      = sanitize_text_field( wp_unslash( $_POST['since'] ?? '' ) );

        $session  = Spine_Chatbot_DB::get_session( $session_id );
        $new_msgs = Spine_Chatbot_DB::get_new_messages( $session_id, $since );

        $agent_info = [];
        if ( ! empty( $session->agent_id ) ) {
            $agent_info = $this->router->get_agent_display_info( (int) $session->agent_id );
        }

        $this->json_success( [
            'status'      => $session->status  ?? 'unknown',
            'branch'      => sanitize_key( $session->branch ?? '' ),
            'messages'    => $new_msgs,
            'agent_info'  => $agent_info,
            'server_time' => current_time( 'mysql' ),
        ] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AGENT HANDLERS
    // ══════════════════════════════════════════════════════════════════════════

    /** Agent heartbeat presence ping. */
    public function handle_agent_ping(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $agent_id = get_current_user_id();
        $this->router->ping_agent( $agent_id );

        $incoming = $this->router->consume_incoming_flags( $agent_id );
        $sessions = Spine_Chatbot_DB::get_agent_sessions( $agent_id );

        $this->json_success( [
            'agent_id'        => $agent_id,
            'status'          => 'online',
            'incoming_chats'  => $incoming,
            'active_sessions' => count( $sessions ),
        ] );
    }

    /** Agent accepts an incoming pending chat. */
    public function handle_agent_accept(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $agent_id   = get_current_user_id();
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );

        $ok = $this->router->accept( $agent_id, $session_id );
        if ( ! $ok ) {
            $this->json_error( 'Could not accept chat. Session may have been reassigned.' );
        }

        $agent_info = $this->router->get_agent_display_info( $agent_id );

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'       => 'agent',
            'agent_id'   => $agent_id,
            'agent_name' => $agent_info['name'],
            'content'    => 'Hello! I\'m ' . $agent_info['name'] . ' from the Spine support team. How can I help you today?',
        ] );

        $session = Spine_Chatbot_DB::get_session( $session_id );

        $this->json_success( [
            'session_id' => $session_id,
            'transcript' => json_decode( $session->transcript, true ) ?: [],
            'user_info'  => [
                'name'    => esc_html( $session->user_name    ?? 'Visitor' ),
                'email'   => esc_html( $session->user_email   ?? '' ),
                'company' => esc_html( $session->user_company ?? '' ),
            ],
        ] );
    }

    /** Agent sends a message to a user. */
    public function handle_agent_message(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $agent_id   = get_current_user_id();
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $content    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

        if ( empty( $content ) ) {
            $this->json_error( 'Message cannot be empty.' );
        }

        $session = Spine_Chatbot_DB::get_session( $session_id );
        if ( ! $session || (int) $session->agent_id !== $agent_id ) {
            $this->json_error( 'Unauthorised: this session is not assigned to you.' );
        }

        $agent_info = $this->router->get_agent_display_info( $agent_id );

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'       => 'agent',
            'agent_id'   => $agent_id,
            'agent_name' => $agent_info['name'],
            'content'    => $content,
        ] );

        $this->json_success( [ 'sent' => true ] );
    }

    /** Agent closes a chat session. */
    public function handle_agent_close(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $agent_id   = get_current_user_id();
        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );

        $session = Spine_Chatbot_DB::get_session( $session_id );
        if ( ! $session || (int) $session->agent_id !== $agent_id ) {
            $this->json_error( 'Unauthorised.' );
        }

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'    => 'agent',
            'content' => 'This chat has been closed by the agent. Thank you for contacting Spine HR support!',
        ] );

        $this->router->close( $session_id );

        $this->json_success( [ 'closed' => true ] );
    }

    /** Return all active sessions for the current agent. */
    public function handle_agent_get_sessions(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $agent_id = get_current_user_id();
        $sessions = Spine_Chatbot_DB::get_agent_sessions( $agent_id );

        $out = array_map( static function ( $s ) {
            $t    = json_decode( $s->transcript, true ) ?: [];
            $last = end( $t );
            return [
                'session_id'   => $s->session_id,
                'status'       => $s->status,
                'branch'       => sanitize_key( $s->branch ?? '' ),
                'user_name'    => esc_html( $s->user_name    ?? 'Visitor' ),
                'user_email'   => esc_html( $s->user_email   ?? '' ),
                'user_company' => esc_html( $s->user_company ?? '' ),
                'created_at'   => $s->created_at,
                'updated_at'   => $s->updated_at,
                'last_message' => [
                    'role'    => esc_html( $last['role']    ?? '' ),
                    'content' => esc_html( wp_trim_words( $last['content'] ?? '', 12, '…' ) ),
                ],
            ];
        }, $sessions );

        $this->json_success( [ 'sessions' => $out ] );
    }

    /** Return new messages for a session since a given timestamp. */
    public function handle_agent_get_messages(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $since      = sanitize_text_field( wp_unslash( $_POST['since'] ?? '' ) );

        $session = Spine_Chatbot_DB::get_session( $session_id );
        if ( ! $session || (int) $session->agent_id !== get_current_user_id() ) {
            $this->json_error( 'Unauthorised.' );
        }

        $new_msgs = Spine_Chatbot_DB::get_new_messages( $session_id, $since );
        $this->json_success( [ 'messages' => $new_msgs, 'status' => $session->status ] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SUPER-ADMIN HANDLERS
    // ══════════════════════════════════════════════════════════════════════════

    public function handle_admin_all_sessions(): void {
        $this->verify_nonce( 'spine_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }

        $sessions = Spine_Chatbot_DB::get_all_active_sessions();

        $out = array_map( static function ( $s ) {
            $t    = json_decode( $s->transcript, true ) ?: [];
            $last = end( $t );

            $agent_name = '';
            if ( $s->agent_id ) {
                $u          = get_userdata( (int) $s->agent_id );
                $agent_name = $u ? esc_html( $u->display_name ) : 'Unknown';
            }

            return [
                'session_id'   => $s->session_id,
                'status'       => $s->status,
                'branch'       => sanitize_key( $s->branch ?? '' ),
                'agent_name'   => $agent_name,
                'user_name'    => esc_html( $s->user_name    ?? 'Visitor' ),
                'user_email'   => esc_html( $s->user_email   ?? '' ),
                'user_company' => esc_html( $s->user_company ?? '' ),
                'created_at'   => $s->created_at,
                'updated_at'   => $s->updated_at,
                'msg_count'    => count( $t ),
                'last_message' => [
                    'role'    => esc_html( $last['role']    ?? '' ),
                    'content' => esc_html( wp_trim_words( $last['content'] ?? '', 12, '…' ) ),
                ],
            ];
        }, $sessions );

        $this->json_success( [
            'sessions' => $out,
            'counts'   => Spine_Chatbot_DB::count_by_status(),
        ] );
    }

    public function handle_admin_agent_list(): void {
        $this->verify_nonce( 'spine_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden', 403 );
        }

        $agents = Spine_Chatbot_DB::get_all_agents();
        $out    = [];

        foreach ( $agents as $agent ) {
            $last_ping = (int) get_user_meta( $agent->ID, Spine_Chatbot_DB::META_LAST_PING, true );
            $out[]     = [
                'id'           => $agent->ID,
                'name'         => esc_html( $agent->display_name ),
                'email'        => esc_html( $agent->user_email ),
                'status'       => $this->router->get_agent_status( $agent->ID ),
                'active_chats' => (int) get_user_meta( $agent->ID, Spine_Chatbot_DB::META_CHAT_COUNT, true ),
                'last_ping'    => $last_ping ? human_time_diff( $last_ping ) . ' ago' : 'Never',
                'avatar_url'   => esc_url( get_avatar_url( $agent->ID, [ 'size' => 32 ] ) ),
            ];
        }

        $this->json_success( [ 'agents' => $out ] );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // KNOWLEDGE BASE CRUD (admin-only)
    // ══════════════════════════════════════════════════════════════════════════

    public function handle_kb_add(): void {
        $this->verify_nonce( 'spine_admin_nonce' );
        $this->require_manage_options();

        $content    = sanitize_textarea_field( wp_unslash( $_POST['content']    ?? '' ) );
        $module     = sanitize_text_field(     wp_unslash( $_POST['module']     ?? 'General' ) );
        $entry_type = sanitize_text_field(     wp_unslash( $_POST['entry_type'] ?? 'General' ) );

        if ( strlen( trim( $content ) ) < 10 ) {
            $this->json_error( 'Content must be at least 10 characters.' );
        }

        $id = Spine_Chatbot_DB::insert_kb_entry( $content, $module, $entry_type );
        if ( ! $id ) {
            $this->json_error( 'Failed to save entry.' );
        }

        $this->json_success( [ 'id' => $id, 'message' => 'Entry added.' ] );
    }

    public function handle_kb_update(): void {
        $this->verify_nonce( 'spine_admin_nonce' );
        $this->require_manage_options();

        $id         = (int) ( $_POST['id'] ?? 0 );
        $content    = sanitize_textarea_field( wp_unslash( $_POST['content']    ?? '' ) );
        $module     = sanitize_text_field(     wp_unslash( $_POST['module']     ?? 'General' ) );
        $entry_type = sanitize_text_field(     wp_unslash( $_POST['entry_type'] ?? 'General' ) );

        if ( $id < 1 ) {
            $this->json_error( 'Invalid entry ID.' );
        }
        if ( strlen( trim( $content ) ) < 10 ) {
            $this->json_error( 'Content must be at least 10 characters.' );
        }

        $ok = Spine_Chatbot_DB::update_kb_entry( $id, $content, $module, $entry_type );
        $ok ? $this->json_success( [ 'message' => 'Entry updated.' ] )
            : $this->json_error( 'Failed to update entry.' );
    }

    public function handle_kb_delete(): void {
        $this->verify_nonce( 'spine_admin_nonce' );
        $this->require_manage_options();

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( $id < 1 ) {
            $this->json_error( 'Invalid entry ID.' );
        }

        $ok = Spine_Chatbot_DB::delete_kb_entry( $id );
        $ok ? $this->json_success( [ 'message' => 'Entry deleted.' ] )
            : $this->json_error( 'Failed to delete entry.' );
    }

    public function handle_kb_import_file(): void {
        $this->verify_nonce( 'spine_admin_nonce' );
        $this->require_manage_options();

        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            $this->json_error( 'No file received or upload error.' );
        }

        $file       = $_FILES['file'];
        $module     = sanitize_text_field( wp_unslash( $_POST['module']     ?? 'General' ) );
        $entry_type = sanitize_text_field( wp_unslash( $_POST['entry_type'] ?? 'General' ) );
        $mime       = mime_content_type( $file['tmp_name'] );

        $allowed_mime = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'text/plain',
        ];
        if ( ! in_array( $mime, $allowed_mime, true ) ) {
            $this->json_error( 'Only PDF, DOCX, and TXT files are accepted for KB import.' );
        }

        if ( $file['size'] > 10 * 1024 * 1024 ) {
            $this->json_error( 'File must be under 10 MB.' );
        }

        $importer = new Spine_Chatbot_KB_Importer();
        $result   = $importer->import_file( $file['tmp_name'], $mime, $module, $entry_type );

        if ( ! empty( $result['error'] ) ) {
            $this->json_error( $result['error'] );
        }

        $this->json_success( [
            'imported' => $result['imported'],
            'skipped'  => $result['skipped'],
            'message'  => sprintf(
                'Import complete. %d chunk(s) added to Knowledge Base.',
                $result['imported']
            ),
        ] );
    }

    public function handle_kb_seed(): void {
        $this->verify_nonce( 'spine_admin_nonce' );
        $this->require_manage_options();

        $result = Spine_Chatbot_DB::seed_kb_from_static();

        $this->json_success( [
            'imported' => $result['imported'],
            'message'  => $result['imported'] > 0
                ? sprintf( 'Seeded %d entries from the legacy knowledge base.', $result['imported'] )
                : 'Knowledge base already has entries — seed skipped.',
        ] );
    }

    // ── Shared helpers ─────────────────────────────────────────────────────────

    private function verify_nonce( string $action ): void {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
            exit;
        }
    }

    private function require_session(): string {
        $sid = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        if ( empty( $sid ) ) {
            $this->json_error( 'Missing session ID.' );
        }
        $session = Spine_Chatbot_DB::get_session( $sid );
        if ( ! $session ) {
            $this->json_error( 'Session not found.' );
        }
        return $sid;
    }

    private function require_manage_options(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Administrator access required.' ], 403 );
            exit;
        }
    }

    private function require_agent(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Authentication required.' ], 401 );
            exit;
        }
        $is_agent = get_user_meta( get_current_user_id(), Spine_Chatbot_DB::META_IS_AGENT, true );
        $is_admin = current_user_can( 'manage_options' );
        if ( ! $is_agent && ! $is_admin ) {
            wp_send_json_error( [ 'message' => 'Agent access required.' ], 403 );
            exit;
        }
    }

    private function json_success( array $data ): never {
        wp_send_json_success( $data );
    }

    private function json_error( string $message, array $extra = [] ): never {
        wp_send_json_error( array_merge( [ 'message' => $message ], $extra ) );
    }

    private function generate_session_id(): string {
        return bin2hex( random_bytes( 16 ) );
    }

    // ── Agent session poll (setInterval fallback for real-time messages) ────

    public function handle_agent_session_poll(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        $since      = sanitize_text_field( wp_unslash( $_POST['since']      ?? '' ) );

        if ( ! $session_id ) {
            $this->json_error( 'session_id required' );
        }

        $new_msgs = Spine_Chatbot_DB::get_new_messages( $session_id, $since );

        $this->json_success( [
            'messages'    => $new_msgs,
            'server_time' => current_time( 'mysql' ),
        ] );
    }

    // ── File upload — visitor side ─────────────────────────────────────────

    public function handle_visitor_upload(): void {
        $this->verify_nonce( 'spine_chat_nonce' );
        $session_id = $this->require_session();
        $this->do_upload( $session_id, 'user' );
    }

    // ── File upload — agent side ───────────────────────────────────────────

    public function handle_agent_upload(): void {
        $this->verify_nonce( 'spine_agent_nonce' );
        $this->require_agent();

        $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
        if ( ! $session_id ) {
            $this->json_error( 'session_id required' );
        }

        $agent_id   = get_current_user_id();
        $ai         = $this->router->get_agent_display_info( $agent_id );

        $this->do_upload( $session_id, 'agent', $ai['name'] ?? '' );
    }

    private function do_upload( string $session_id, string $role, string $agent_name = '' ): never {
        if ( empty( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            $this->json_error( 'No file received or upload error.' );
        }

        $file = $_FILES['file'];

        $allowed = [ 'image/jpeg', 'image/png', 'application/pdf' ];
        $type    = mime_content_type( $file['tmp_name'] );
        if ( ! in_array( $type, $allowed, true ) ) {
            $this->json_error( 'Only JPG, PNG, and PDF files are allowed.' );
        }

        if ( $file['size'] > 5 * 1024 * 1024 ) {
            $this->json_error( 'File must be under 5 MB.' );
        }

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $result = wp_handle_upload( $file, [ 'test_form' => false ] );

        if ( isset( $result['error'] ) ) {
            $this->json_error( $result['error'] );
        }

        $file_url  = $result['url'];
        $file_name = basename( $result['file'] );

        Spine_Chatbot_DB::append_message( $session_id, [
            'role'       => $role,
            'content'    => '[Attached: ' . $file_name . ']',
            'agent_name' => $agent_name,
            'file_url'   => $file_url,
            'file_name'  => $file_name,
            'file_type'  => $type,
        ] );

        $this->json_success( [
            'file_url'  => $file_url,
            'file_name' => $file_name,
            'file_type' => $type,
        ] );
    }
}
