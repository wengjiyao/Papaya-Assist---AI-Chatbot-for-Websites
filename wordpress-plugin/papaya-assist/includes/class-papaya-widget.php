<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Papaya_Assist_Widget {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
    }

    public function render_widget() {
        if ( is_admin() ) {
            return;
        }

        if ( ! get_option( 'bitesize_enabled', '1' ) ) {
            return;
        }

        // DB option names kept as bitesize_* for backward compat.
        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );

        if ( empty( $api_key ) || empty( $tenant_id ) ) {
            return;
        }

        // Security: validate color server-side before passing to frontend.
        $color = get_option( 'bitesize_primary_color', '#4f46e5' );
        if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
            $color = '#4f46e5';
        }

        $widget_js_path = PAPAYA_ASSIST_DIR . 'assets/js/chatbot-widget.js';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read, not a remote URL.
        $widget_js = file_get_contents( $widget_js_path );
        $config    = wp_json_encode( array(
            'apiUrl'       => PAPAYA_ASSIST_CHAT_API_URL,
            'streamUrl'    => PAPAYA_ASSIST_STREAM_URL,
            'tenantId'     => $tenant_id,
            'title'        => get_option( 'bitesize_widget_title', 'Chat with us' ),
            'primaryColor' => $color,
        ) );
        // $config is safe: built from wp_json_encode with controlled values.
        // $widget_js is safe: read from a local plugin file, not user input.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        printf( '<script>window.__papayaAssist=%s;%s</script>', $config, $widget_js );
    }
}
