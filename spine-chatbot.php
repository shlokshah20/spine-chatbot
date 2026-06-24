<?php
/**
 * Plugin Name: Spine HR Chatbot
 * Plugin URI:  https://spinetechnologies.com
 * Description: Production-ready AI chatbot for Spine HR Suite & Spine Assets – knowledge-base search, live agent round-robin routing, lead capture, and real-time WordPress Heartbeat messaging.
 * Version:     1.2.5
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

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'SPINE_CHATBOT_VERSION',        '1.2.5' );
define( 'SPINE_CHATBOT_DB_VERSION',     '3' );
define( 'SPINE_CHATBOT_FILE',           __FILE__ );
define( 'SPINE_CHATBOT_DIR',            plugin_dir_path( __FILE__ ) );
define( 'SPINE_CHATBOT_URL',            plugin_dir_url( __FILE__ ) );
define( 'SPINE_CHATBOT_AGENT_TIMEOUT',  60 );    // seconds before agent auto-marked Away
define( 'SPINE_CHATBOT_HIGH_THRESHOLD', 20.0 );  // score → direct answer
define( 'SPINE_CHATBOT_ALT_THRESHOLD',  8.0  );  // score → show alternatives (top-3)

// ── Lifecycle ──────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, static function (): void {
    require_once SPINE_CHATBOT_DIR . 'includes/class-spine-chatbot-db.php';
    Spine_Chatbot_DB::install();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function (): void {
    flush_rewrite_rules();
} );

register_uninstall_hook( __FILE__, [ 'Spine_Chatbot_DB', 'uninstall' ] );

// ── Boot ───────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', static function (): void {
    $files = [
        'includes/class-spine-chatbot-db',
        'includes/class-spine-chatbot-search',
        'includes/class-spine-chatbot-router',
        'includes/class-spine-chatbot-leads',
        'includes/class-spine-chatbot-ajax',
        'includes/class-spine-chatbot-heartbeat',
        'includes/class-spine-chatbot-core',
        'admin/class-spine-chatbot-settings',
        'admin/class-spine-chatbot-admin',
    ];
    foreach ( $files as $file ) {
        require_once SPINE_CHATBOT_DIR . $file . '.php';
    }
    Spine_Chatbot_Core::get_instance();
}, 0 );
