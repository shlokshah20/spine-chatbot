<?php
/**
 * WordPress Heartbeat API Integration
 *
 * Frontend (visitor) side:
 *   • Receives session_id via heartbeat-send, delivers new agent messages back.
 *   • Adaptive interval: 3 s when tab focused, 15 s when blurred (JS side).
 *
 * Admin (agent) side:
 *   • Records agent presence ping on every heartbeat tick.
 *   • Pushes new user messages and incoming chat flags to the agent dashboard.
 *   • Triggers round-robin rebalance when an agent goes Away.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Heartbeat {

    private Spine_Chatbot_Router $router;

    public function __construct( Spine_Chatbot_Router $router ) {
        $this->router = $router;
    }

    public function register(): void {
        // Server-side: extend heartbeat received data
        add_filter( 'heartbeat_received',          [ $this, 'on_heartbeat_received' ], 10, 2 );

        // Optionally adjust heartbeat settings
        add_filter( 'heartbeat_settings',           [ $this, 'heartbeat_settings' ] );
    }

    // ── Server-side heartbeat handler ──────────────────────────────────────────

    /**
     * Process incoming heartbeat data and append response data.
     *
     * @param array $response Data sent back to the client.
     * @param array $data     Data received from the client.
     */
    public function on_heartbeat_received( array $response, array $data ): array {
        // ── Frontend visitor chat polling ──────────────────────────────────
        if ( ! empty( $data['spine_chat'] ) ) {
            $response['spine_chat'] = $this->process_visitor_heartbeat(
                $data['spine_chat']
            );
        }

        // ── Agent presence + messages polling ─────────────────────────────
        if ( ! empty( $data['spine_agent'] ) && is_user_logged_in() ) {
            $response['spine_agent'] = $this->process_agent_heartbeat(
                $data['spine_agent']
            );
        }

        return $response;
    }

    // ── Visitor heartbeat ──────────────────────────────────────────────────────

    private function process_visitor_heartbeat( $payload ): array {
        if ( ! is_array( $payload ) ) {
            return [ 'error' => 'Invalid payload' ];
        }

        $session_id = sanitize_text_field( $payload['session_id'] ?? '' );
        $since      = sanitize_text_field( $payload['since']      ?? '' );
        $nonce      = sanitize_text_field( $payload['nonce']      ?? '' );

        if ( ! wp_verify_nonce( $nonce, 'spine_chat_nonce' ) ) {
            return [ 'error' => 'Nonce invalid' ];
        }

        $session = Spine_Chatbot_DB::get_session( $session_id );
        if ( ! $session ) {
            return [ 'error' => 'Session not found' ];
        }

        $new_messages = Spine_Chatbot_DB::get_new_messages( $session_id, $since );

        $agent_info = null;
        if ( $session->agent_id && in_array( $session->status, [ 'active', 'pending_agent' ], true ) ) {
            $agent_info = $this->router->get_agent_display_info( (int) $session->agent_id );
        }

        return [
            'session_id'  => $session_id,
            'status'      => $session->status,
            'messages'    => $new_messages,
            'agent_info'  => $agent_info,
            'server_time' => current_time( 'mysql' ),
        ];
    }

    // ── Agent heartbeat ────────────────────────────────────────────────────────

    private function process_agent_heartbeat( $payload ): array {
        if ( ! is_array( $payload ) ) {
            return [ 'error' => 'Invalid payload' ];
        }

        $nonce    = sanitize_text_field( $payload['nonce']    ?? '' );
        $focused  = (bool) ( $payload['focused']  ?? true );
        $since    = sanitize_text_field( $payload['since']    ?? '' );

        if ( ! wp_verify_nonce( $nonce, 'spine_agent_nonce' ) ) {
            return [ 'error' => 'Nonce invalid' ];
        }

        $agent_id = get_current_user_id();

        // Check this user is actually a registered agent or admin
        $is_agent = get_user_meta( $agent_id, Spine_Chatbot_DB::META_IS_AGENT, true );
        $is_admin = current_user_can( 'manage_options' );
        if ( ! $is_agent && ! $is_admin ) {
            return [ 'error' => 'Not an agent' ];
        }

        // Record ping only when tab is focused to prevent ghost-online status
        if ( $focused ) {
            $this->router->ping_agent( $agent_id );
        }

        // Sweep and potentially rebalance if agent just went away
        Spine_Chatbot_DB::sweep_stale_agents();

        // Consume incoming chat flags
        $incoming_session_ids = $this->router->consume_incoming_flags( $agent_id );
        $incoming_chats       = [];
        foreach ( $incoming_session_ids as $sid ) {
            $s = Spine_Chatbot_DB::get_session( $sid );
            if ( $s ) {
                $incoming_chats[] = [
                    'session_id'  => $s->session_id,
                    'user_name'   => esc_html( $s->user_name ?? 'Visitor' ),
                    'user_email'  => esc_html( $s->user_email ?? '' ),
                    'user_company'=> esc_html( $s->user_company ?? '' ),
                    'created_at'  => $s->created_at,
                ];
            }
        }

        // Gather new messages for all active sessions assigned to this agent
        $sessions      = Spine_Chatbot_DB::get_agent_sessions( $agent_id );
        $session_updates = [];

        foreach ( $sessions as $s ) {
            $new_msgs = Spine_Chatbot_DB::get_new_messages( $s->session_id, $since );
            if ( ! empty( $new_msgs ) ) {
                $session_updates[] = [
                    'session_id' => $s->session_id,
                    'status'     => $s->status,
                    'messages'   => $new_msgs,
                ];
            }
        }

        return [
            'agent_id'        => $agent_id,
            'status'          => $this->router->get_agent_status( $agent_id ),
            'incoming_chats'  => $incoming_chats,
            'session_updates' => $session_updates,
            'active_count'    => count( $sessions ),
            'server_time'     => current_time( 'mysql' ),
        ];
    }

    // ── Heartbeat settings ─────────────────────────────────────────────────────

    /**
     * Allow the JS to push the interval down to 3 s on admin pages.
     * The actual throttle (3 s focused / 15 s blurred) is applied in JS.
     */
    public function heartbeat_settings( array $settings ): array {
        // Only loosen the floor on our own admin page
        if ( isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) === 'spine-chat-control' ) {
            $settings['minimalInterval'] = 3;
        }
        return $settings;
    }
}
