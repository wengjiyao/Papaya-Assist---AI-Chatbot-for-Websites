<?php
/**
 * Plugin Name: Bitesize Chatbot
 * Plugin URI:  https://github.com/bitesizeai/chatbot
 * Description: AI-powered chatbot widget with document-based knowledge (RAG).
 * Version:     1.4.0
 * Author:      Bitesize AI
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bitesize-chatbot
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BITESIZE_CHATBOT_VERSION', '1.4.0' );
define( 'BITESIZE_CHATBOT_FILE', __FILE__ );
define( 'BITESIZE_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITESIZE_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

// Hardcoded backend URLs — all users connect to the same backend
define( 'BITESIZE_ADMIN_API_URL', 'https://34i2s32sx774dqpo6wssfi2jzy0ycwmw.lambda-url.us-east-1.on.aws' );
define( 'BITESIZE_CHAT_API_URL', 'https://iirqt9b6h3.execute-api.us-east-1.amazonaws.com/Prod/chat' );
define( 'BITESIZE_STREAM_URL', 'https://dd63bb6z7j4r7pyiwvi5sqmhcq0gsgyz.lambda-url.us-east-1.on.aws/' );
define( 'BITESIZE_AUTH_URL', 'https://weng.ca/auth/chatbot/' );

require_once BITESIZE_CHATBOT_DIR . 'includes/class-bitesize-settings.php';
require_once BITESIZE_CHATBOT_DIR . 'includes/class-bitesize-documents.php';
require_once BITESIZE_CHATBOT_DIR . 'includes/class-bitesize-widget.php';

/**
 * Initialize the plugin.
 */
function bitesize_chatbot_init() {
    // Ensure tenant_id exists (fallback if activation hook was skipped during update).
    if ( ! get_option( 'bitesize_tenant_id' ) ) {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        update_option( 'bitesize_tenant_id', sanitize_title( $host ) );
    }

    new Bitesize_Settings();
    new Bitesize_Documents();
    new Bitesize_Widget();
}
add_action( 'plugins_loaded', 'bitesize_chatbot_init' );

/**
 * Auto-generate tenant ID from site domain on activation.
 */
function bitesize_chatbot_activate() {
    if ( ! get_option( 'bitesize_tenant_id' ) ) {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $tenant_id = sanitize_title( $host );
        update_option( 'bitesize_tenant_id', $tenant_id );
    }
    if ( false === get_option( 'bitesize_enabled' ) ) {
        update_option( 'bitesize_enabled', '1' );
    }
}
register_activation_hook( __FILE__, 'bitesize_chatbot_activate' );
