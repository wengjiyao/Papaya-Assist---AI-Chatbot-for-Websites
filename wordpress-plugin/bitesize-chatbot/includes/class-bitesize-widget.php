<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bitesize_Widget {

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

        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );

        if ( empty( $api_key ) || empty( $tenant_id ) ) {
            return;
        }

        $widget_js = file_get_contents( BITESIZE_CHATBOT_DIR . 'assets/js/chatbot-widget.js' );
        $config    = wp_json_encode( array(
            'apiUrl'       => BITESIZE_CHAT_API_URL,
            'streamUrl'    => BITESIZE_STREAM_URL,
            'tenantId'     => $tenant_id,
            'title'        => get_option( 'bitesize_widget_title', 'Chat with us' ),
            'primaryColor' => get_option( 'bitesize_primary_color', '#4f46e5' ),
        ) );
        printf( '<script>window.__bitesize=%s;%s</script>', $config, $widget_js );
    }
}
