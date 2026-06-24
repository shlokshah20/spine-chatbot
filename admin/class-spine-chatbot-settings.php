<?php
/**
 * Settings Registration & Sanitisation
 *
 * All options stored under the 'spine_chatbot_' prefix in wp_options.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Settings {

    public function register(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings(): void {

        // ── General ───────────────────────────────────────────────────────────
        register_setting( 'spine_chatbot_general', 'spine_chatbot_enabled', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1',
        ] );
        register_setting( 'spine_chatbot_general', 'spine_chatbot_bot_name', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Spine Assistant',
        ] );
        register_setting( 'spine_chatbot_general', 'spine_chatbot_welcome_message', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => "Hello! 👋 I'm the Spine HR Assistant. How can I help you today?",
        ] );
        register_setting( 'spine_chatbot_general', 'spine_chatbot_admin_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => get_option( 'admin_email' ),
        ] );
        register_setting( 'spine_chatbot_general', 'spine_chatbot_demo_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://spinetechnologies.com/request-demo/',
        ] );
        // Support email — shown to users who click the "Support" branch button
        register_setting( 'spine_chatbot_general', 'spine_chatbot_support_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => 'support@spinetechnologies.com',
        ] );

        // ── Appearance ────────────────────────────────────────────────────────
        register_setting( 'spine_chatbot_appearance', 'spine_chatbot_position', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_position' ],
            'default'           => 'bottom-right',
        ] );
        register_setting( 'spine_chatbot_appearance', 'spine_chatbot_icon_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ] );
        register_setting( 'spine_chatbot_appearance', 'spine_chatbot_accent_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#1d4ed8',
        ] );
        register_setting( 'spine_chatbot_appearance', 'spine_chatbot_primary_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#0891b2',
        ] );

        // ── Agents ────────────────────────────────────────────────────────────
        register_setting( 'spine_chatbot_agents', 'spine_chatbot_agent_ids', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_agent_ids' ],
            'default'           => [],
        ] );
    }

    // ── Sanitisers ─────────────────────────────────────────────────────────────

    public function sanitize_position( string $val ): string {
        return in_array( $val, [ 'bottom-right', 'bottom-left' ], true ) ? $val : 'bottom-right';
    }

    public function sanitize_agent_ids( $val ): array {
        if ( ! is_array( $val ) ) return [];
        return array_filter( array_map( 'absint', $val ) );
    }

    // ── Save agent list (called from admin form POST) ──────────────────────────

    /**
     * Persist the agent user IDs and sync user-meta accordingly.
     */
    public static function save_agents( array $user_ids ): void {
        $all_agents = Spine_Chatbot_DB::get_all_agents();
        $new_ids    = array_map( 'absint', $user_ids );

        // Remove agent flag from deselected users
        foreach ( $all_agents as $agent ) {
            if ( ! in_array( $agent->ID, $new_ids, true ) ) {
                delete_user_meta( $agent->ID, Spine_Chatbot_DB::META_IS_AGENT );
                delete_user_meta( $agent->ID, Spine_Chatbot_DB::META_STATUS );
                delete_user_meta( $agent->ID, Spine_Chatbot_DB::META_LAST_PING );
                delete_user_meta( $agent->ID, Spine_Chatbot_DB::META_CHAT_COUNT );
            }
        }

        // Register new agents
        foreach ( $new_ids as $uid ) {
            if ( get_userdata( $uid ) ) {
                Spine_Chatbot_DB::register_agent( $uid );
            }
        }

        update_option( 'spine_chatbot_agent_ids', $new_ids );
    }
}
