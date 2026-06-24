<?php
/**
 * Agent Routing — Round-Robin Dispatcher
 *
 * Handles smart assignment of pending chat sessions to available online agents.
 * Uses a round-robin strategy that balances load by active-chat count.
 *
 * Agent presence states: online | away | offline
 *   • 'online'  — agent tab is open and recently pinged (within SPINE_CHATBOT_AGENT_TIMEOUT)
 *   • 'away'    — ping has timed out; bypassed in routing loop
 *   • 'offline' — manually set or never registered
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_Router {

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Attempt to assign the session to an available online agent.
     * Returns the assigned agent WP_User or null when no agents are online.
     */
    public function dispatch( string $session_id ): ?WP_User {
        // Sweep stale agents first to keep presence state accurate
        Spine_Chatbot_DB::sweep_stale_agents();

        $agents = Spine_Chatbot_DB::get_online_agents(); // sorted by active_chat_count ASC

        if ( empty( $agents ) ) {
            // No agents online → push session to offline lead flow
            Spine_Chatbot_DB::update_session( $session_id, [ 'status' => 'pending_agent' ] );
            return null;
        }

        // Pick agent with fewest active chats (already sorted)
        $agent = $agents[0];

        // Lock session to this agent
        Spine_Chatbot_DB::update_session( $session_id, [
            'agent_id' => $agent->ID,
            'status'   => 'pending_agent', // becomes 'active' once agent accepts
        ] );

        Spine_Chatbot_DB::increment_agent_chats( $agent->ID );

        // Notify the agent (transient flag picked up by their next heartbeat tick)
        $this->flag_incoming_chat( $agent->ID, $session_id );

        return $agent;
    }

    /**
     * Agent accepts an incoming chat — locks session to agent and marks 'active'.
     */
    public function accept( int $agent_id, string $session_id ): bool {
        $session = Spine_Chatbot_DB::get_session( $session_id );
        if ( ! $session ) {
            return false;
        }

        // Prevent cross-wire: only the assigned agent can accept
        if ( (int) $session->agent_id !== $agent_id ) {
            return false;
        }

        return Spine_Chatbot_DB::update_session( $session_id, [
            'agent_id' => $agent_id,
            'status'   => 'active',
        ] );
    }

    /**
     * Close/end a chat session and decrement the agent's active count.
     */
    public function close( string $session_id ): bool {
        $session = Spine_Chatbot_DB::get_session( $session_id );
        if ( ! $session ) {
            return false;
        }

        if ( $session->agent_id ) {
            Spine_Chatbot_DB::decrement_agent_chats( (int) $session->agent_id );
        }

        return Spine_Chatbot_DB::update_session( $session_id, [ 'status' => 'closed' ] );
    }

    /**
     * When an agent goes away/offline, re-route their pending sessions to the next
     * available agent. Active (in-progress) sessions remain with them until ended.
     */
    public function rebalance_away_agent( int $away_agent_id ): void {
        global $wpdb;

        $pending = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT session_id FROM " . Spine_Chatbot_DB::table() .
                " WHERE agent_id = %d AND status = 'pending_agent'",
                $away_agent_id
            )
        );

        foreach ( $pending as $row ) {
            // Release the slot
            Spine_Chatbot_DB::decrement_agent_chats( $away_agent_id );
            // Re-dispatch to next available agent
            Spine_Chatbot_DB::update_session( $row->session_id, [
                'agent_id' => null,
                'status'   => 'pending_agent',
            ] );
            $this->dispatch( $row->session_id );
        }
    }

    /**
     * Record a heartbeat ping for an agent — keeps them 'online'.
     */
    public function ping_agent( int $agent_id ): void {
        Spine_Chatbot_DB::ping_agent( $agent_id );
    }

    /**
     * Get live status of a single agent.
     */
    public function get_agent_status( int $agent_id ): string {
        $last_ping = (int) get_user_meta( $agent_id, Spine_Chatbot_DB::META_LAST_PING, true );
        if ( $last_ping < time() - SPINE_CHATBOT_AGENT_TIMEOUT ) {
            return 'away';
        }
        return get_user_meta( $agent_id, Spine_Chatbot_DB::META_STATUS, true ) ?: 'offline';
    }

    /**
     * Check whether any agent is currently online.
     */
    public function any_agent_online(): bool {
        return ! empty( Spine_Chatbot_DB::get_online_agents() );
    }

    /**
     * Returns minimal agent info for the frontend when a chat is active.
     */
    public function get_agent_display_info( int $agent_id ): array {
        $user = get_userdata( $agent_id );
        if ( ! $user ) return [];

        $avatar_url = get_avatar_url( $agent_id, [ 'size' => 40 ] );

        return [
            'id'         => $agent_id,
            'name'       => esc_html( $user->display_name ),
            'avatar_url' => esc_url( $avatar_url ),
        ];
    }

    // ── Internal helpers ───────────────────────────────────────────────────────

    /**
     * Set a short-lived transient the agent's heartbeat will pick up.
     */
    private function flag_incoming_chat( int $agent_id, string $session_id ): void {
        $key      = 'spine_agent_incoming_' . $agent_id;
        $existing = get_transient( $key ) ?: [];
        $existing[] = $session_id;
        set_transient( $key, array_unique( $existing ), 120 );
    }

    /**
     * Consume and clear the incoming-chat flag for a given agent (called by heartbeat).
     */
    public function consume_incoming_flags( int $agent_id ): array {
        $key  = 'spine_agent_incoming_' . $agent_id;
        $data = get_transient( $key ) ?: [];
        delete_transient( $key );
        return $data;
    }
}
