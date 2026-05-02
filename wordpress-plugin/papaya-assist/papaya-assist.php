<?php
/**
 * Plugin Name: Papaya Assist
 * Plugin URI:  https://weng.ca/
 * Description: AI-powered chatbot widget with document-based knowledge (RAG).
 * Version:     1.5.2
 * Author:      Papaya Assist
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: papaya-assist
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PAPAYA_ASSIST_VERSION', '1.5.2' );
define( 'PAPAYA_ASSIST_FILE', __FILE__ );
define( 'PAPAYA_ASSIST_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAPAYA_ASSIST_URL', plugin_dir_url( __FILE__ ) );

// Hardcoded backend URLs — all users connect to the same backend
define( 'PAPAYA_ASSIST_ADMIN_API_URL', 'https://34i2s32sx774dqpo6wssfi2jzy0ycwmw.lambda-url.us-east-1.on.aws' );
define( 'PAPAYA_ASSIST_CHAT_API_URL', 'https://iirqt9b6h3.execute-api.us-east-1.amazonaws.com/Prod/chat' );
define( 'PAPAYA_ASSIST_STREAM_URL', 'https://dd63bb6z7j4r7pyiwvi5sqmhcq0gsgyz.lambda-url.us-east-1.on.aws/' );
define( 'PAPAYA_ASSIST_AUTH_URL', 'https://weng.ca/auth/chatbot/' );

require_once PAPAYA_ASSIST_DIR . 'includes/class-papaya-settings.php';
require_once PAPAYA_ASSIST_DIR . 'includes/class-papaya-documents.php';
require_once PAPAYA_ASSIST_DIR . 'includes/class-papaya-widget.php';

/**
 * Initialize the plugin.
 */
function papaya_assist_init() {
    // Ensure tenant_id exists (fallback if activation hook was skipped during update).
    // DB option names kept as bitesize_* for backward compat with self-hosted users.
    if ( ! get_option( 'bitesize_tenant_id' ) ) {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        update_option( 'bitesize_tenant_id', sanitize_title( $host ) );
    }

    new Papaya_Assist_Settings();
    new Papaya_Assist_Documents();
    new Papaya_Assist_Widget();
}
add_action( 'plugins_loaded', 'papaya_assist_init' );

/**
 * Auto-generate tenant ID from site domain on activation.
 */
function papaya_assist_activate() {
    if ( ! get_option( 'bitesize_tenant_id' ) ) {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $tenant_id = sanitize_title( $host );
        update_option( 'bitesize_tenant_id', $tenant_id );
    }
    if ( false === get_option( 'bitesize_enabled' ) ) {
        update_option( 'bitesize_enabled', '1' );
    }
}
register_activation_hook( __FILE__, 'papaya_assist_activate' );
