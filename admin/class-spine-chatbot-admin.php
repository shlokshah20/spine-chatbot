<?php
/**
 * Admin Menu, Subpages & Asset Loading
 *
 * Creates the "Spine Chat Control" top-level menu with three subpages:
 *   1. Dashboard — agent chat interface + super-admin global view
 *   2. Settings  — plugin configuration
 *   3. Agents    — register/manage agents
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Admin {

    private Spine_Chatbot_Settings $settings;
    private Spine_Chatbot_Router   $router;

    public function __construct() {
        $this->settings = new Spine_Chatbot_Settings();
        $this->router   = new Spine_Chatbot_Router();
    }

    public function register(): void {
        $this->settings->register();

        add_action( 'admin_menu',            [ $this, 'add_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_post_spine_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_spine_save_agents',   [ $this, 'handle_save_agents' ] );
        add_action( 'admin_post_spine_export_leads',  [ $this, 'handle_export_leads' ] );
    }

    // ── Menu registration ──────────────────────────────────────────────────────

    public function add_menus(): void {
        // Top-level menu
        add_menu_page(
            __( 'Spine Chat Control', 'spine-chatbot' ),
            __( 'Spine Chat', 'spine-chatbot' ),
            'read',
            'spine-chat-control',
            [ $this, 'render_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="#a7f3d0"/><circle cx="8" cy="10" r="1.2" fill="white"/><circle cx="12" cy="10" r="1.2" fill="white"/><circle cx="16" cy="10" r="1.2" fill="white"/></svg>' ),
            25
        );

        // Dashboard submenu (same as parent)
        add_submenu_page(
            'spine-chat-control',
            __( 'Chat Dashboard',      'spine-chatbot' ),
            __( 'Dashboard',           'spine-chatbot' ),
            'read',
            'spine-chat-control',
            [ $this, 'render_dashboard' ]
        );

        // Settings
        add_submenu_page(
            'spine-chat-control',
            __( 'Chatbot Settings',    'spine-chatbot' ),
            __( 'Settings',            'spine-chatbot' ),
            'manage_options',
            'spine-chat-settings',
            [ $this, 'render_settings' ]
        );

        // Agent management (super-admin only)
        add_submenu_page(
            'spine-chat-control',
            __( 'Manage Agents',       'spine-chatbot' ),
            __( 'Agents',              'spine-chatbot' ),
            'manage_options',
            'spine-chat-agents',
            [ $this, 'render_agents' ]
        );

        // Leads & enquiries (super-admin only)
        add_submenu_page(
            'spine-chat-control',
            __( 'Leads & Enquiries',   'spine-chatbot' ),
            __( 'Leads',               'spine-chatbot' ),
            'manage_options',
            'spine-chat-leads',
            [ $this, 'render_leads' ]
        );

        // Knowledge base review (super-admin only)
        add_submenu_page(
            'spine-chat-control',
            __( 'Knowledge Base',      'spine-chatbot' ),
            __( 'Knowledge Base',      'spine-chatbot' ),
            'manage_options',
            'spine-chat-kb',
            [ $this, 'render_kb' ]
        );
    }

    // ── Asset enqueue ──────────────────────────────────────────────────────────

    public function enqueue_admin_assets( string $hook ): void {
        $spine_pages = [
            'toplevel_page_spine-chat-control',
            'spine-chat_page_spine-chat-settings',
            'spine-chat_page_spine-chat-agents',
            'spine-chat_page_spine-chat-leads',
            'spine-chat_page_spine-chat-kb',
        ];

        if ( ! in_array( $hook, $spine_pages, true ) ) {
            return;
        }

        wp_enqueue_media(); // WordPress media uploader for icon setting

        wp_enqueue_style(
            'spine-chatbot-admin',
            SPINE_CHATBOT_URL . 'admin/css/admin-style.css',
            [ 'wp-components' ],
            SPINE_CHATBOT_VERSION
        );

        wp_enqueue_script( 'heartbeat' );

        wp_enqueue_script(
            'spine-chatbot-admin',
            SPINE_CHATBOT_URL . 'admin/js/admin-script.js',
            [ 'jquery', 'heartbeat', 'media-upload' ],
            SPINE_CHATBOT_VERSION,
            true
        );

        $is_super_admin = current_user_can( 'manage_options' );
        $agent_id       = get_current_user_id();

        wp_localize_script( 'spine-chatbot-admin', 'spineAdminVars', [
            // Root-relative path — survives siteurl/CDN domain mismatches.
            'ajaxUrl'      => wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ),
            'nonce'        => wp_create_nonce( 'spine_agent_nonce' ),
            'adminNonce'   => wp_create_nonce( 'spine_admin_nonce' ),
            'agentId'      => $agent_id,
            'isSuperAdmin' => $is_super_admin,
            'isAgent'      => (bool) get_user_meta( $agent_id, Spine_Chatbot_DB::META_IS_AGENT, true ),
            'heartbeatFast'=> 3,
            'heartbeatSlow'=> 15,
            'settingsNonce'=> wp_create_nonce( 'spine_settings_nonce' ),
        ] );
    }

    // ── Page renderers ─────────────────────────────────────────────────────────

    public function render_dashboard(): void {
        $is_super_admin = current_user_can( 'manage_options' );
        $current_user   = wp_get_current_user();
        $is_agent       = (bool) get_user_meta( $current_user->ID, Spine_Chatbot_DB::META_IS_AGENT, true );
        $stats          = Spine_Chatbot_DB::count_by_status();

        if ( ! $is_agent && ! $is_super_admin ) {
            wp_die( esc_html__( 'You do not have permission to access the chat dashboard.', 'spine-chatbot' ) );
        }

        require SPINE_CHATBOT_DIR . 'admin/views/view-dashboard.php';
    }

    public function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'spine-chatbot' ) );
        }

        require SPINE_CHATBOT_DIR . 'admin/views/view-settings.php';
    }

    public function render_agents(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'spine-chatbot' ) );
        }

        $all_users  = get_users( [ 'role__in' => [ 'administrator', 'editor', 'author' ] ] );
        $agents     = Spine_Chatbot_DB::get_all_agents();
        $agent_ids  = array_column( $agents, 'ID' );

        require SPINE_CHATBOT_DIR . 'admin/views/view-super-admin.php';
    }

    public function render_leads(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'spine-chatbot' ) );
        }

        $leads = Spine_Chatbot_DB::get_leads();

        require SPINE_CHATBOT_DIR . 'admin/views/view-leads.php';
    }

    public function render_kb(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'spine-chatbot' ) );
        }

        $per_page    = 20;
        $page        = max( 1, (int) ( $_GET['kb_page'] ?? 1 ) );
        $filter_mod  = sanitize_text_field( $_GET['module'] ?? '' );
        $total       = Spine_Chatbot_DB::count_kb_entries( $filter_mod );
        $total_pages = (int) ceil( $total / $per_page );
        $kb_entries  = Spine_Chatbot_DB::get_kb_entries( $page, $per_page, $filter_mod );
        $modules     = Spine_Chatbot_DB::get_kb_modules();

        require SPINE_CHATBOT_DIR . 'admin/views/view-kb.php';
    }

    // ── Form POST handlers ─────────────────────────────────────────────────────

    public function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'spine_save_settings', 'spine_settings_nonce' );

        $fields = [
            'spine_chatbot_enabled'         => 'sanitize_text_field',
            'spine_chatbot_bot_name'        => 'sanitize_text_field',
            'spine_chatbot_welcome_message' => 'sanitize_textarea_field',
            'spine_chatbot_admin_email'     => 'sanitize_email',
            'spine_chatbot_demo_url'        => 'esc_url_raw',
            'spine_chatbot_support_email'   => 'sanitize_email',
            'spine_chatbot_position'        => [ $this->settings, 'sanitize_position' ],
            'spine_chatbot_accent_color'    => 'sanitize_hex_color',
            'spine_chatbot_primary_color'   => 'sanitize_hex_color',
        ];

        foreach ( $fields as $key => $sanitizer ) {
            $raw = wp_unslash( $_POST[ $key ] ?? '' );
            update_option( $key, call_user_func( $sanitizer, $raw ) );
        }

        // Icon media attachment ID
        $icon_id = absint( $_POST['spine_chatbot_icon_id'] ?? 0 );
        update_option( 'spine_chatbot_icon_id', $icon_id );

        // Only overwrite the API key if the user actually entered a new one
        $new_key = sanitize_text_field( wp_unslash( $_POST['spine_chatbot_anthropic_key'] ?? '' ) );
        if ( ! empty( $new_key ) ) {
            update_option( 'spine_chatbot_anthropic_key', $new_key );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'spine-chat-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_save_agents(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'spine_save_agents', 'spine_agents_nonce' );

        $raw_ids = $_POST['agent_ids'] ?? [];
        Spine_Chatbot_Settings::save_agents( is_array( $raw_ids ) ? $raw_ids : [] );

        wp_safe_redirect( add_query_arg( [ 'page' => 'spine-chat-agents', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_export_leads(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }

        check_admin_referer( 'spine_export_leads', 'spine_leads_nonce' );

        $leads    = Spine_Chatbot_DB::get_leads();
        $filename = 'spine-chatbot-leads-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );

        // UTF-8 BOM so Excel opens it correctly
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [ 'Date', 'Name', 'Email', 'Phone', 'Company', 'Branch', 'Type', 'Message', 'Session ID' ] );

        foreach ( $leads as $row ) {
            $data = json_decode( $row->lead_data ?? '{}', true ) ?: [];
            fputcsv( $out, [
                $row->created_at,
                $row->user_name    ?: ( $data['name']    ?? '' ),
                $row->user_email   ?: ( $data['email']   ?? '' ),
                $row->user_phone   ?: ( $data['phone']   ?? '' ),
                $row->user_company ?: ( $data['company'] ?? '' ),
                $row->branch       ?: '',
                ucwords( str_replace( '_', ' ', $data['type'] ?? '' ) ),
                $data['message']   ?? '',
                $row->session_id,
            ] );
        }

        fclose( $out );
        exit;
    }
}

// Boot the admin class
add_action( 'plugins_loaded', static function (): void {
    if ( is_admin() ) {
        $admin = new Spine_Chatbot_Admin();
        $admin->register();
    }
}, 5 );
