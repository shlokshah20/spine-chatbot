<?php
/**
 * Plugin Name: Spine HR Chatbot
 * Plugin URI:  https://spinetechnologies.com
 * Description: Production-ready AI chatbot for Spine HR Suite & Spine Assets – knowledge-base search, live agent round-robin routing, lead capture, and real-time WordPress Heartbeat messaging.
 * Version:     2.0.0
 * Author:      Spine Technologies Pvt. Ltd.
 * Author URI:  https://spinetechnologies.com
 * Text Domain: spine-chatbot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License:     Proprietary
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

// Guard: abort silently if another copy of this plugin is already loaded.
// This prevents fatal errors when two versions exist in wp-content/plugins.
if ( defined( 'SPINE_CHATBOT_VERSION' ) ) {
    return;
}

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'SPINE_CHATBOT_VERSION',        '2.2.0' );
define( 'SPINE_CHATBOT_DB_VERSION',     '5' );
define( 'SPINE_CHATBOT_FILE',           __FILE__ );
define( 'SPINE_CHATBOT_DIR',            plugin_dir_path( __FILE__ ) );
define( 'SPINE_CHATBOT_URL',            plugin_dir_url( __FILE__ ) );
define( 'SPINE_CHATBOT_AGENT_TIMEOUT',  60 );
define( 'SPINE_CHATBOT_HIGH_THRESHOLD', 20.0 );
define( 'SPINE_CHATBOT_ALT_THRESHOLD',  8.0  );

// ── Lifecycle ──────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, static function (): void {
    require_once SPINE_CHATBOT_DIR . 'includes/class-spine-chatbot-db.php';
    Spine_Chatbot_DB::install();
    flush_rewrite_rules();
} );

register_activation_hook( __FILE__, static function (): void {
    if ( ! wp_next_scheduled( 'spine_chatbot_process_gaps_daily' ) ) {
        wp_schedule_event( time(), 'daily', 'spine_chatbot_process_gaps_daily' );
    }
} );

register_deactivation_hook( __FILE__, static function (): void {
    wp_clear_scheduled_hook( 'spine_chatbot_process_gaps_daily' );
    flush_rewrite_rules();
} );

register_uninstall_hook( __FILE__, [ 'Spine_Chatbot_DB', 'uninstall' ] );

// ── Boot ───────────────────────────────────────────────────────────────────────
// ── Cron hook ──────────────────────────────────────────────────────────────────
add_action( 'spine_chatbot_process_gaps_daily', static function (): void {
    if ( class_exists( 'Spine_Chatbot_AI' ) ) {
        Spine_Chatbot_AI::process_gap_queries_cron();
    }
} );

add_action( 'plugins_loaded', static function (): void {
    // Use a local variable so this closure always resolves files relative to
    // its own plugin directory, regardless of which copy loaded first.
    $dir = plugin_dir_path( __FILE__ );

    $files = [
        'includes/class-spine-chatbot-db',
        'includes/class-spine-chatbot-router',
        'includes/class-spine-chatbot-leads',
        'includes/class-spine-chatbot-kb-importer',
        'includes/class-spine-chatbot-ai',
        'includes/class-spine-chatbot-ajax',
        'includes/class-spine-chatbot-heartbeat',
        'includes/class-spine-chatbot-core',
        'admin/class-spine-chatbot-settings',
        'admin/class-spine-chatbot-admin',
    ];
    foreach ( $files as $file ) {
        require_once $dir . $file . '.php';
    }
    Spine_Chatbot_Core::get_instance();
}, 0 );
