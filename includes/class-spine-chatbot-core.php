<?php
/**
 * Plugin Core — Orchestrator & Asset Loader  (v1.1 — branch routing)
 *
 * Singleton that boots all sub-systems, registers frontend widget hooks,
 * and enqueues scripts/styles on the correct pages.
 *
 * v1.1 changes:
 *   • spineChatVars includes 'branches' config array + 'supportEmail'.
 *   • Widget HTML includes the branch-select panel (hidden by default,
 *     shown by JS state machine on first open, before input is unlocked).
 *   • Input area starts locked; JS unlocks after a product branch is chosen.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Core {

    private static ?self $instance = null;

    private Spine_Chatbot_Search    $search;
    private Spine_Chatbot_Router    $router;
    private Spine_Chatbot_Leads     $leads;
    private Spine_Chatbot_Ajax      $ajax;
    private Spine_Chatbot_Heartbeat $heartbeat;

    private function __construct() {
        $this->search    = new Spine_Chatbot_Search();
        $this->router    = new Spine_Chatbot_Router();
        $this->leads     = new Spine_Chatbot_Leads();
        $this->ajax      = new Spine_Chatbot_Ajax( $this->search, $this->router, $this->leads );
        $this->heartbeat = new Spine_Chatbot_Heartbeat( $this->router );

        $this->register_hooks();
    }

    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Hook registration ──────────────────────────────────────────────────────

    private function register_hooks(): void {
        $this->ajax->register();
        $this->heartbeat->register();

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'wp_footer',          [ $this, 'render_widget' ] );
        add_action( 'admin_init',         [ $this, 'maybe_upgrade_db' ] );
    }

    // ── Frontend assets ────────────────────────────────────────────────────────

    public function enqueue_frontend_assets(): void {
        if ( ! get_option( 'spine_chatbot_enabled', '1' ) ) {
            return;
        }

        wp_enqueue_style(
            'spine-chatbot',
            SPINE_CHATBOT_URL . 'public/css/chatbot-style.css',
            [],
            SPINE_CHATBOT_VERSION
        );

        wp_enqueue_script( 'heartbeat' );

        wp_enqueue_script(
            'spine-chatbot',
            SPINE_CHATBOT_URL . 'public/js/chatbot-script.js',
            [ 'jquery', 'heartbeat' ],
            SPINE_CHATBOT_VERSION,
            true
        );

        $icon_id  = (int) get_option( 'spine_chatbot_icon_id', 0 );
        $icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';

        wp_localize_script( 'spine-chatbot', 'spineChatVars', [
            // Use a root-relative path so the request always goes to whichever
            // host the browser is on — survives siteurl/CDN mismatches.
            'ajaxUrl'        => wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ),
            'nonce'          => wp_create_nonce( 'spine_chat_nonce' ),
            'position'       => sanitize_key( get_option( 'spine_chatbot_position', 'bottom-right' ) ),
            'botName'        => esc_html( get_option( 'spine_chatbot_bot_name', 'Spine Assistant' ) ),
            'welcomeMessage' => esc_html( get_option( 'spine_chatbot_welcome_message',
                "Hello! 👋 I'm the Spine HR Assistant. Please select a topic to get started:" ) ),
            'iconUrl'        => esc_url( $icon_url ),
            'demoUrl'        => esc_url( get_option( 'spine_chatbot_demo_url', 'https://spinetechnologies.com/request-demo/' ) ),
            'accentColor'    => sanitize_hex_color( get_option( 'spine_chatbot_accent_color',  '#1d4ed8' ) ),
            'primaryColor'   => sanitize_hex_color( get_option( 'spine_chatbot_primary_color', '#0891b2' ) ),
            'supportEmail'   => sanitize_email( get_option( 'spine_chatbot_support_email', 'support@spinetechnologies.com' ) ),
            'heartbeatFast'  => 3,
            'heartbeatSlow'  => 15,

            // ── Branch configuration (consumed by JS state machine) ────────
            // Each branch object drives: button rendering, AJAX payload, scope,
            // and (for 'support') the freeze behavior.
            'branches' => [
                [
                    'id'       => 'hr_suite',
                    'label'    => 'HR Suite',
                    'icon'     => 'people',    // maps to SVG icon key in JS
                    'desc'     => 'Recruitment, Leave, Attendance, Payroll & more',
                    'scope'    => 'hr_suite',
                    'support'  => false,
                ],
                [
                    'id'       => 'assets',
                    'label'    => 'Assets',
                    'icon'     => 'box',
                    'desc'     => 'IT Asset tracking, allocation & lifecycle',
                    'scope'    => 'assets',
                    'support'  => false,
                ],
                [
                    'id'       => 'support',
                    'label'    => 'Support',
                    'icon'     => 'headset',
                    'desc'     => 'Raise a support query with our team',
                    'scope'    => '',
                    'support'  => true,
                ],
                [
                    'id'       => 'international',
                    'label'    => 'International HR Suite',
                    'icon'     => 'globe',
                    'desc'     => 'GCC/UAE/SEA payroll compliance & expat management',
                    'scope'    => 'international',
                    'support'  => false,
                ],
            ],

            // Per-product quick replies shown after a product branch is selected.
            'branchQuickReplies' => [
                'hr_suite' => [
                    'Leave Management',
                    'Recruitment & ATS',
                    'Time & Attendance',
                    'Performance Management',
                    'Book a Demo',
                ],
                'assets' => [
                    'Asset Allocation',
                    'Depreciation Methods',
                    'Asset Audit Process',
                    'Offboarding Asset Return',
                    'Book a Demo',
                ],
                'international' => [
                    'UAE WPS Filing',
                    'Gratuity / End of Service',
                    'Saudi GOSI & Nitaqat',
                    'Malaysia EPF & SOCSO',
                    'Multi-Currency Payroll',
                ],
            ],
        ] );
    }

    // ── Widget HTML ────────────────────────────────────────────────────────────

    public function render_widget(): void {
        if ( ! get_option( 'spine_chatbot_enabled', '1' ) ) {
            return;
        }

        $position  = sanitize_key( get_option( 'spine_chatbot_position', 'bottom-right' ) );
        $bot_name  = esc_html( get_option( 'spine_chatbot_bot_name', 'Spine Assistant' ) );
        $icon_id   = (int) get_option( 'spine_chatbot_icon_id', 0 );
        $icon_url  = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
        $pos_class = 'spine-chat--' . $position;

        $accent  = sanitize_hex_color( get_option( 'spine_chatbot_accent_color',  '#1d4ed8' ) );
        $primary = sanitize_hex_color( get_option( 'spine_chatbot_primary_color', '#0891b2' ) );
        echo "<style>:root{--spine-accent:{$accent};--spine-primary:{$primary};}</style>\n";
        ?>
        <div id="spine-chat-root" class="spine-chat <?php echo esc_attr( $pos_class ); ?>"
             role="region" aria-label="<?php echo esc_attr( $bot_name ); ?> Chat">

            <!-- ── Floating Launcher Button ──────────────────────────────── -->
            <button id="spine-chat-launcher"
                    class="spine-chat__launcher"
                    aria-label="Open <?php echo esc_attr( $bot_name ); ?>"
                    aria-expanded="false"
                    aria-controls="spine-chat-widget">
                <?php if ( $icon_url ) : ?>
                    <img src="<?php echo esc_url( $icon_url ); ?>"
                         alt="<?php echo esc_attr( $bot_name ); ?>"
                         class="spine-chat__launcher-img" />
                <?php else : ?>
                    <svg class="spine-chat__launcher-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="currentColor"/>
                        <circle cx="8"  cy="10" r="1.2" fill="white"/>
                        <circle cx="12" cy="10" r="1.2" fill="white"/>
                        <circle cx="16" cy="10" r="1.2" fill="white"/>
                    </svg>
                <?php endif; ?>
                <svg class="spine-chat__launcher-close" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <span class="spine-chat__unread-badge" id="spine-unread-badge" hidden>1</span>
            </button>

            <!-- ── Chat Window ──────────────────────────────────────────── -->
            <div id="spine-chat-widget"
                 class="spine-chat__window"
                 role="dialog"
                 aria-modal="true"
                 aria-label="<?php echo esc_attr( $bot_name ); ?>"
                 hidden>

                <!-- Header -->
                <div class="spine-chat__header" id="spine-chat-header">
                    <div class="spine-chat__header-avatar" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="20" fill="rgba(255,255,255,.15)"/>
                            <path d="M20 10a5 5 0 100 10 5 5 0 000-10zM10 30c0-5.523 4.477-9 10-9s10 3.477 10 9"
                                  stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="spine-chat__header-info">
                        <span class="spine-chat__header-name" id="spine-header-name"><?php echo esc_html( $bot_name ); ?></span>
                        <span class="spine-chat__header-status" id="spine-status-text">
                            <span class="spine-chat__status-dot" id="spine-status-dot"></span>
                            <span id="spine-status-label">Online</span>
                        </span>
                    </div>
                    <div class="spine-chat__header-actions">
                        <!-- Restart / back to branches button (shown after branch is selected) -->
                        <button class="spine-chat__header-btn" id="spine-restart-btn"
                                aria-label="Restart conversation" title="Start over" hidden>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path d="M4 4v6h6M20 20v-6h-6"/>
                                <path d="M20 10a8 8 0 00-16 0M4 14a8 8 0 0016 0" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <button class="spine-chat__header-btn" id="spine-chat-minimise"
                                aria-label="Minimise chat" title="Minimise">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/></svg>
                        </button>
                        <button class="spine-chat__header-btn" id="spine-chat-close"
                                aria-label="Close chat" title="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6L18 18"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Messages area -->
                <div class="spine-chat__messages" id="spine-chat-messages"
                     role="log" aria-live="polite" aria-label="Chat messages"></div>

                <!-- Typing indicator (hidden by default) -->
                <div class="spine-chat__typing" id="spine-typing-indicator" hidden aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>

                <!-- ── Branch-Select Panel ─────────────────────────────────
                     Shown immediately on first open, before user input is
                     unlocked. JS state machine renders the 4 buttons here.
                     HTML skeleton is empty; JS fills #spine-branch-buttons.  -->
                <div class="spine-chat__branch-panel" id="spine-branch-panel" hidden>
                    <p class="spine-chat__branch-heading">What can I help you with?</p>
                    <div class="spine-chat__branch-buttons" id="spine-branch-buttons">
                        <!-- Rendered by chatbot-script.js from spineChatVars.branches -->
                    </div>
                </div>

                <!-- Quick Replies (shown after product branch selected) -->
                <div class="spine-chat__quick-replies" id="spine-quick-replies" hidden></div>

                <!-- Alternatives panel (medium-confidence search results) -->
                <div class="spine-chat__alternatives" id="spine-alternatives" hidden></div>

                <!-- Lead Capture Form -->
                <div class="spine-chat__lead-form" id="spine-lead-form" hidden>
                    <h3 class="spine-chat__lead-title">Leave Your Details</h3>
                    <p class="spine-chat__lead-sub">Our team will get back to you within 1 business day.</p>
                    <form id="spine-lead-form-inner" novalidate>
                        <div class="spine-chat__field">
                            <label for="spine-lead-name">Full Name *</label>
                            <input type="text"  id="spine-lead-name"    name="name"    placeholder="Jane Smith" autocomplete="name" required>
                            <span class="spine-chat__field-error" id="err-name" hidden></span>
                        </div>
                        <div class="spine-chat__field">
                            <label for="spine-lead-email">Business Email *</label>
                            <input type="email" id="spine-lead-email"   name="email"   placeholder="jane@company.com" autocomplete="email" required>
                            <span class="spine-chat__field-error" id="err-email" hidden></span>
                        </div>
                        <div class="spine-chat__field">
                            <label for="spine-lead-phone">Phone Number *</label>
                            <input type="tel"   id="spine-lead-phone"   name="phone"   placeholder="+91 98765 43210" autocomplete="tel" required>
                            <span class="spine-chat__field-error" id="err-phone" hidden></span>
                        </div>
                        <div class="spine-chat__field">
                            <label for="spine-lead-company">Company Name *</label>
                            <input type="text"  id="spine-lead-company" name="company" placeholder="Acme Technologies" autocomplete="organization" required>
                            <span class="spine-chat__field-error" id="err-company" hidden></span>
                        </div>
                        <div class="spine-chat__field">
                            <label for="spine-lead-message">Your Query (optional)</label>
                            <textarea id="spine-lead-message" name="message" placeholder="Tell us what you're looking for…" rows="2"></textarea>
                        </div>
                        <input type="hidden" name="type" value="demo_request">
                        <div class="spine-chat__lead-actions">
                            <button type="submit" class="spine-chat__btn spine-chat__btn--primary" id="spine-lead-submit">
                                Submit Request
                            </button>
                            <a href="#" class="spine-chat__btn spine-chat__btn--secondary" id="spine-demo-link" target="_blank" rel="noopener">
                                Book a Demo
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 8h10m-4-4 4 4-4 4"/></svg>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Input Area (starts locked until a product branch is chosen) -->
                <div class="spine-chat__input-area spine-chat__input-area--locked" id="spine-input-area">
                    <!-- Attach button (only visible during live agent chat) -->
                    <label class="spine-chat__attach-btn" id="spine-attach-btn" title="Attach file (JPG, PNG, PDF · max 5 MB)" hidden aria-label="Attach file">
                        <input type="file" id="spine-file-input" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="18" height="18"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    </label>
                    <textarea id="spine-chat-input"
                              class="spine-chat__input"
                              placeholder="Select a topic above to begin…"
                              aria-label="Type your message"
                              rows="1"
                              maxlength="500"
                              disabled></textarea>
                    <button id="spine-chat-send"
                            class="spine-chat__send-btn"
                            aria-label="Send message"
                            disabled>
                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13"/>
                        </svg>
                    </button>
                </div>

                <!-- Footer brand -->
                <div class="spine-chat__footer">
                    Powered by <a href="https://spinetechnologies.com" target="_blank" rel="noopener">Spine Technologies</a>
                </div>

            </div><!-- #spine-chat-widget -->
        </div><!-- #spine-chat-root -->
        <?php
    }

    // ── DB upgrade ─────────────────────────────────────────────────────────────

    public function maybe_upgrade_db(): void {
        $installed = get_option( 'spine_chatbot_db_version', '0' );
        if ( version_compare( $installed, SPINE_CHATBOT_DB_VERSION, '<' ) ) {
            Spine_Chatbot_DB::install();
        }
    }

    // Prevent cloning / unserialization
    private function __clone() {}
    public function __wakeup(): never {
        throw new \LogicException( 'Cannot unserialize singleton.' );
    }
}
