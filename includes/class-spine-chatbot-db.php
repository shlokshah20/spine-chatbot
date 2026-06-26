<?php
/**
 * Database Layer
 *
 * Handles schema creation, CRUD operations for wp_spine_chatbot_interactions,
 * and agent-status helpers backed by WordPress user-meta.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

final class Spine_Chatbot_DB {

    // ── Table names (without prefix) ──────────────────────────────────────────
    private const TABLE    = 'spine_chatbot_interactions';
    private const KB_TABLE = 'spine_kb_entries';

    // ── Agent user-meta keys ───────────────────────────────────────────────────
    public const META_IS_AGENT       = 'spine_is_agent';
    public const META_STATUS         = 'spine_agent_status';   // online|away|offline
    public const META_LAST_PING      = 'spine_agent_last_ping'; // Unix timestamp
    public const META_CHAT_COUNT     = 'spine_agent_chat_count';

    // ── Schema ─────────────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        // Column layout (v2):
        //   status  – bot | branch_select | pending_agent | active | closed | lead_captured | support_redirect
        //   branch  – hr_suite | assets | support | international  (set by branch-select step)
        $sql = "CREATE TABLE {$table} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id   VARCHAR(64)  NOT NULL,
            agent_id     BIGINT(20) UNSIGNED DEFAULT NULL,
            status       VARCHAR(20)  NOT NULL DEFAULT 'bot',
            branch       VARCHAR(30)  NOT NULL DEFAULT '',
            user_name    VARCHAR(100) DEFAULT NULL,
            user_email   VARCHAR(150) DEFAULT NULL,
            user_phone   VARCHAR(30)  DEFAULT NULL,
            user_company VARCHAR(150) DEFAULT NULL,
            lead_data    LONGTEXT     DEFAULT NULL,
            transcript   LONGTEXT     NOT NULL,
            ip_address   VARCHAR(45)  DEFAULT NULL,
            created_at   DATETIME     NOT NULL,
            updated_at   DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY   uq_session  (session_id),
            KEY          idx_agent   (agent_id),
            KEY          idx_status  (status),
            KEY          idx_branch  (branch),
            KEY          idx_created (created_at)
        ) ENGINE=InnoDB {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // ── KB entries table (v4) ──────────────────────────────────────────
        $kb_table = $wpdb->prefix . self::KB_TABLE;
        $sql_kb   = "CREATE TABLE {$kb_table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            content     LONGTEXT     NOT NULL,
            module      VARCHAR(50)  NOT NULL DEFAULT 'General',
            entry_type  VARCHAR(20)  NOT NULL DEFAULT 'General',
            embedding   LONGTEXT     DEFAULT NULL,
            created_at  DATETIME     NOT NULL,
            updated_at  DATETIME     NOT NULL,
            PRIMARY KEY (id),
            KEY         idx_module (module),
            KEY         idx_type   (entry_type),
            FULLTEXT KEY ft_content (content)
        ) ENGINE=InnoDB {$charset};";
        dbDelta( $sql_kb );

        update_option( 'spine_chatbot_db_version', SPINE_CHATBOT_DB_VERSION );
    }

    public static function uninstall(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::KB_TABLE );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE );

        $option_names = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE 'spine_chatbot_%'"
        );
        foreach ( $option_names as $opt ) {
            delete_option( $opt );
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function kb_table(): string {
        global $wpdb;
        return $wpdb->prefix . self::KB_TABLE;
    }

    // ── Knowledge Base CRUD ────────────────────────────────────────────────────

    public static function get_kb_entries( int $page = 1, int $per_page = 20, string $module = '' ): array {
        global $wpdb;
        $offset = ( max( 1, $page ) - 1 ) * $per_page;
        $table  = self::kb_table();

        if ( $module ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE module = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $module, $per_page, $offset
                )
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            )
        );
    }

    public static function get_kb_entry( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::kb_table() . ' WHERE id = %d', $id )
        ) ?: null;
    }

    public static function insert_kb_entry( string $content, string $module = 'General', string $entry_type = 'General' ): int|false {
        global $wpdb;
        $now = current_time( 'mysql' );
        $ok  = $wpdb->insert(
            self::kb_table(),
            [
                'content'    => $content,
                'module'     => $module,
                'entry_type' => $entry_type,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function update_kb_entry( int $id, string $content, string $module, string $entry_type ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            self::kb_table(),
            [
                'content'    => $content,
                'module'     => $module,
                'entry_type' => $entry_type,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    public static function delete_kb_entry( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::kb_table(), [ 'id' => $id ], [ '%d' ] );
    }

    public static function count_kb_entries( string $module = '' ): int {
        global $wpdb;
        $table = self::kb_table();
        if ( $module ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE module = %s", $module )
            );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function get_kb_modules(): array {
        global $wpdb;
        $table = self::kb_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_col( "SELECT DISTINCT module FROM {$table} ORDER BY module ASC" );
    }

    /**
     * One-time migration: seed the static knowledgebase array into the DB table.
     * Skips if table already has rows.
     *
     * @return array{ imported: int }
     */
    public static function seed_kb_from_static(): array {
        if ( self::count_kb_entries() > 0 ) {
            return [ 'imported' => 0 ];
        }

        $kb_file = plugin_dir_path( __DIR__ ) . 'data/knowledgebase-v1.php';
        if ( ! file_exists( $kb_file ) ) {
            return [ 'imported' => 0 ];
        }

        $kb       = require $kb_file;
        $imported = 0;

        foreach ( $kb as $item ) {
            $module     = sanitize_text_field( $item['module']     ?? 'General' );
            $entry_type = sanitize_text_field( $item['entry_type'] ?? 'FAQ' );

            // Combine question + answer into a single searchable chunk
            $content = '';
            if ( ! empty( $item['question'] ) ) {
                $content .= 'Q: ' . trim( $item['question'] ) . "\n";
            }
            if ( ! empty( $item['answer'] ) ) {
                $content .= 'A: ' . trim( $item['answer'] );
            }
            if ( ! empty( $item['content'] ) ) {
                $content = trim( $item['content'] );
            }

            if ( strlen( $content ) < 10 ) {
                continue;
            }

            $ok = self::insert_kb_entry( $content, $module, $entry_type );
            if ( $ok ) {
                $imported++;
            }
        }

        return [ 'imported' => $imported ];
    }

    // ── Session CRUD ───────────────────────────────────────────────────────────

    public static function create_session( string $session_id ): bool {
        global $wpdb;
        $now = current_time( 'mysql' );

        return (bool) $wpdb->insert(
            self::table(),
            [
                'session_id' => $session_id,
                'status'     => 'bot',
                'transcript' => '[]',
                'ip_address' => self::client_ip(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    public static function get_session( string $session_id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE session_id = %s LIMIT 1',
                $session_id
            )
        ) ?: null;
    }

    public static function update_session( string $session_id, array $data ): bool {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );

        return (bool) $wpdb->update(
            self::table(),
            $data,
            [ 'session_id' => $session_id ]
        );
    }

    /**
     * Atomically append one message object to the transcript column.
     */
    public static function append_message( string $session_id, array $message ): bool {
        $session = self::get_session( $session_id );
        if ( ! $session ) {
            return false;
        }

        $transcript   = json_decode( $session->transcript, true ) ?: [];
        $transcript[] = array_merge( $message, [ 'timestamp' => current_time( 'mysql' ) ] );

        return self::update_session( $session_id, [
            'transcript' => wp_json_encode( $transcript ),
        ] );
    }

    /**
     * Persist captured lead data against a session.
     */
    public static function save_lead( string $session_id, array $lead ): bool {
        return self::update_session( $session_id, [
            'user_name'    => sanitize_text_field( $lead['name']    ?? '' ),
            'user_email'   => sanitize_email( $lead['email']        ?? '' ),
            'user_phone'   => sanitize_text_field( $lead['phone']   ?? '' ),
            'user_company' => sanitize_text_field( $lead['company'] ?? '' ),
            'lead_data'    => wp_json_encode( $lead ),
            'status'       => 'lead_captured',
        ] );
    }

    /**
     * Get all sessions assigned to a specific agent (active/pending).
     */
    public static function get_agent_sessions( int $agent_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() .
                " WHERE agent_id = %d AND status IN ('active','pending_agent')
                 ORDER BY updated_at DESC",
                $agent_id
            )
        );
    }

    /**
     * All sessions with status = lead_captured, newest first.
     */
    public static function get_leads(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() .
            " WHERE status = 'lead_captured' ORDER BY created_at DESC"
        );
    }

    /**
     * All non-closed sessions — for super-admin monitoring.
     */
    public static function get_all_active_sessions(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() .
            " WHERE status NOT IN ('closed','bot')
             ORDER BY updated_at DESC"
        );
    }

    /**
     * Sessions in 'pending_agent' state, oldest-first, for routing.
     */
    public static function get_pending_sessions(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() .
            " WHERE status = 'pending_agent' ORDER BY created_at ASC"
        );
    }

    /**
     * Messages posted since the given datetime for a session.
     * Used by Heartbeat to deliver new messages to the active party.
     */
    public static function get_new_messages( string $session_id, string $since ): array {
        $session = self::get_session( $session_id );
        if ( ! $session ) {
            return [];
        }

        $transcript = json_decode( $session->transcript, true ) ?: [];
        return array_values( array_filter( $transcript, static function ( $msg ) use ( $since ) {
            return ( $msg['timestamp'] ?? '' ) > $since;
        } ) );
    }

    // ── Dashboard stats ────────────────────────────────────────────────────────

    public static function count_by_status(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT status, COUNT(*) AS cnt FROM ' . self::table() . ' GROUP BY status',
            ARRAY_A
        );
        return array_column( $rows, 'cnt', 'status' );
    }

    // ── Agent presence (user-meta backed) ─────────────────────────────────────

    /**
     * Register a WordPress user as a Spine chat agent.
     */
    public static function register_agent( int $user_id ): void {
        update_user_meta( $user_id, self::META_IS_AGENT,   '1' );
        update_user_meta( $user_id, self::META_STATUS,     'offline' );
        update_user_meta( $user_id, self::META_CHAT_COUNT, 0 );
    }

    /**
     * Record an agent heartbeat and flip status to 'online'.
     */
    public static function ping_agent( int $user_id ): void {
        update_user_meta( $user_id, self::META_LAST_PING, time() );
        update_user_meta( $user_id, self::META_STATUS,    'online' );
    }

    /**
     * Sweep stale agents (last ping > SPINE_CHATBOT_AGENT_TIMEOUT seconds ago).
     */
    public static function sweep_stale_agents(): void {
        $agents = self::get_all_agents();
        $cutoff = time() - SPINE_CHATBOT_AGENT_TIMEOUT;

        foreach ( $agents as $agent ) {
            $last_ping = (int) get_user_meta( $agent->ID, self::META_LAST_PING, true );
            if ( $last_ping < $cutoff ) {
                update_user_meta( $agent->ID, self::META_STATUS, 'away' );
            }
        }
    }

    /**
     * Returns all users registered as agents.
     */
    public static function get_all_agents(): array {
        return get_users( [
            'meta_key'   => self::META_IS_AGENT,
            'meta_value' => '1',
            'fields'     => 'all',
        ] );
    }

    /**
     * Returns agents currently marked 'online', ordered by active chat count ASC.
     */
    public static function get_online_agents(): array {
        $agents = get_users( [
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'   => self::META_IS_AGENT,
                    'value' => '1',
                ],
                [
                    'key'   => self::META_STATUS,
                    'value' => 'online',
                ],
            ],
            'fields' => 'all',
        ] );

        // Sort by active chat count ascending (fair round-robin)
        usort( $agents, static function ( $a, $b ) {
            $ca = (int) get_user_meta( $a->ID, Spine_Chatbot_DB::META_CHAT_COUNT, true );
            $cb = (int) get_user_meta( $b->ID, Spine_Chatbot_DB::META_CHAT_COUNT, true );
            return $ca <=> $cb;
        } );

        return $agents;
    }

    public static function increment_agent_chats( int $agent_id ): void {
        $count = (int) get_user_meta( $agent_id, self::META_CHAT_COUNT, true );
        update_user_meta( $agent_id, self::META_CHAT_COUNT, $count + 1 );
    }

    public static function decrement_agent_chats( int $agent_id ): void {
        $count = max( 0, (int) get_user_meta( $agent_id, self::META_CHAT_COUNT, true ) - 1 );
        update_user_meta( $agent_id, self::META_CHAT_COUNT, $count );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
