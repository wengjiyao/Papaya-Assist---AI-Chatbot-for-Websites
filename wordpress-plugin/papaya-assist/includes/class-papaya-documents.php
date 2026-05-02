<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Papaya_Assist_Documents {

    private $allowed_types = array( 'pdf', 'docx', 'txt' );

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_papaya_assist_get_upload_url', array( $this, 'ajax_get_upload_url' ) );
        add_action( 'wp_ajax_papaya_assist_list_documents', array( $this, 'ajax_list_documents' ) );
        add_action( 'wp_ajax_papaya_assist_delete_document', array( $this, 'ajax_delete_document' ) );
        add_action( 'wp_ajax_papaya_assist_ingest', array( $this, 'ajax_ingest' ) );
    }

    public function add_menu() {
        add_management_page(
            __( 'Chatbot Documents', 'papaya-assist' ),
            __( 'Chatbot Documents', 'papaya-assist' ),
            'manage_options',
            'papaya-assist-documents',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'tools_page_papaya-assist-documents' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'papaya-assist-admin',
            PAPAYA_ASSIST_URL . 'assets/css/admin.css',
            array(),
            PAPAYA_ASSIST_VERSION
        );
        wp_enqueue_script(
            'papaya-assist-documents',
            PAPAYA_ASSIST_URL . 'assets/js/admin-documents.js',
            array( 'jquery' ),
            PAPAYA_ASSIST_VERSION,
            true
        );
        wp_localize_script( 'papaya-assist-documents', 'papayaAssistAdmin', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'papaya_assist_documents' ),
            'allowedTypes' => $this->allowed_types,
        ) );
    }

    // --- API helper ---

    private function api_request( $method, $path, $body = null ) {
        // DB option name kept as bitesize_* for backward compat.
        $api_key = get_option( 'bitesize_api_key', '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_config', __( 'Please connect your account in Settings &rarr; Papaya Assist first.', 'papaya-assist' ) );
        }

        $url = trailingslashit( PAPAYA_ASSIST_ADMIN_API_URL ) . ltrim( $path, '/' );
        $args = array(
            'method'  => $method,
            'headers' => array(
                'X-API-Key'    => $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60,
        );

        if ( $body !== null ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = isset( $data['detail'] ) ? $data['detail'] : 'API error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $message );
        }

        return $data;
    }

    // --- AJAX handlers ---

    public function ajax_get_upload_url() {
        check_ajax_referer( 'papaya_assist_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $filename     = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
        $content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( wp_unslash( $_POST['content_type'] ) ) : '';
        $tenant_id    = get_option( 'bitesize_tenant_id', '' );

        // Validate file extension
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $this->allowed_types, true ) ) {
            wp_send_json_error( __( 'File type not allowed. Supported: ', 'papaya-assist' ) . implode( ', ', $this->allowed_types ) );
        }

        $result = $this->api_request( 'POST', 'admin/upload-url', array(
            'tenant_id'    => $tenant_id,
            'filename'     => $filename,
            'content_type' => $content_type,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function ajax_list_documents() {
        check_ajax_referer( 'papaya_assist_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $result    = $this->api_request( 'GET', 'admin/documents/' . rawurlencode( $tenant_id ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function ajax_delete_document() {
        check_ajax_referer( 'papaya_assist_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $key       = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
        $result    = $this->api_request( 'DELETE', 'admin/documents/' . rawurlencode( $tenant_id ) . '/' . rawurlencode( $key ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function ajax_ingest() {
        check_ajax_referer( 'papaya_assist_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $result    = $this->api_request( 'POST', 'admin/ingest/' . rawurlencode( $tenant_id ), array( 'clear' => true ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    // --- Page render ---

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $missing   = empty( $api_key ) || empty( $tenant_id );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Chatbot Documents', 'papaya-assist' ); ?></h1>

            <?php if ( $missing ) : ?>
                <div class="notice notice-warning">
                    <p><?php
                        printf(
                            /* translators: %s: URL to Papaya Assist settings page */
                            esc_html__( 'Please connect your account in %s before uploading documents.', 'papaya-assist' ),
                            '<a href="' . esc_url( admin_url( 'options-general.php?page=papaya-assist' ) ) . '">' . esc_html__( 'Settings &rarr; Papaya Assist', 'papaya-assist' ) . '</a>'
                        );
                    ?></p>
                </div>
            <?php else : ?>
                <div id="papaya-assist-notices"></div>

                <h2><?php esc_html_e( 'Upload Documents', 'papaya-assist' ); ?></h2>
                <div class="papaya-assist-upload-area">
                    <input type="file" id="papaya-assist-file-input" accept=".pdf,.docx,.txt" multiple />
                    <button type="button" id="papaya-assist-upload-btn" class="button button-primary"><?php esc_html_e( 'Upload', 'papaya-assist' ); ?></button>
                    <span id="papaya-assist-upload-status"></span>
                </div>
                <p class="description"><?php esc_html_e( 'Supported file types: PDF, DOCX, TXT', 'papaya-assist' ); ?></p>

                <h2><?php esc_html_e( 'Documents', 'papaya-assist' ); ?></h2>
                <table class="wp-list-table widefat fixed striped" id="papaya-assist-documents-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Filename', 'papaya-assist' ); ?></th>
                            <th><?php esc_html_e( 'Size', 'papaya-assist' ); ?></th>
                            <th><?php esc_html_e( 'Last Modified', 'papaya-assist' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'papaya-assist' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4"><?php esc_html_e( 'Loading...', 'papaya-assist' ); ?></td></tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Process Documents', 'papaya-assist' ); ?></h2>
                <p><?php esc_html_e( 'After uploading, click below to ingest documents into the vector database for chat.', 'papaya-assist' ); ?></p>
                <button type="button" id="papaya-assist-ingest-btn" class="button button-primary"><?php esc_html_e( 'Process Documents', 'papaya-assist' ); ?></button>
                <span id="papaya-assist-ingest-status"></span>
            <?php endif; ?>
        </div>
        <?php
    }
}
