<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bitesize_Documents {

    private $allowed_types = array( 'pdf', 'docx', 'txt' );

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_bitesize_get_upload_url', array( $this, 'ajax_get_upload_url' ) );
        add_action( 'wp_ajax_bitesize_list_documents', array( $this, 'ajax_list_documents' ) );
        add_action( 'wp_ajax_bitesize_delete_document', array( $this, 'ajax_delete_document' ) );
        add_action( 'wp_ajax_bitesize_ingest', array( $this, 'ajax_ingest' ) );
    }

    public function add_menu() {
        add_management_page(
            'Chatbot Documents',
            'Chatbot Documents',
            'manage_options',
            'bitesize-documents',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'tools_page_bitesize-documents' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'bitesize-admin',
            BITESIZE_CHATBOT_URL . 'assets/css/admin.css',
            array(),
            BITESIZE_CHATBOT_VERSION
        );
        wp_enqueue_script(
            'bitesize-admin-documents',
            BITESIZE_CHATBOT_URL . 'assets/js/admin-documents.js',
            array( 'jquery' ),
            BITESIZE_CHATBOT_VERSION,
            true
        );
        wp_localize_script( 'bitesize-admin-documents', 'bitesizeAdmin', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bitesize_documents' ),
            'allowedTypes' => $this->allowed_types,
        ) );
    }

    // --- API helper ---

    private function api_request( $method, $path, $body = null ) {
        $api_key = get_option( 'bitesize_api_key', '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_config', 'Please connect your account in Settings &rarr; Bitesize Chatbot first.' );
        }

        $url = trailingslashit( BITESIZE_ADMIN_API_URL ) . ltrim( $path, '/' );
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
        check_ajax_referer( 'bitesize_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $filename     = sanitize_file_name( $_POST['filename'] );
        $content_type = sanitize_text_field( $_POST['content_type'] );
        $tenant_id    = get_option( 'bitesize_tenant_id', '' );

        // Validate file extension
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $this->allowed_types, true ) ) {
            wp_send_json_error( 'File type not allowed. Supported: ' . implode( ', ', $this->allowed_types ) );
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
        check_ajax_referer( 'bitesize_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $result    = $this->api_request( 'GET', 'admin/documents/' . rawurlencode( $tenant_id ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function ajax_delete_document() {
        check_ajax_referer( 'bitesize_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $key       = sanitize_text_field( $_POST['key'] );
        $result    = $this->api_request( 'DELETE', 'admin/documents/' . rawurlencode( $tenant_id ) . '/' . rawurlencode( $key ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function ajax_ingest() {
        check_ajax_referer( 'bitesize_documents', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
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
            <h1>Chatbot Documents</h1>

            <?php if ( $missing ) : ?>
                <div class="notice notice-warning">
                    <p>Please connect your account in
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=bitesize-chatbot' ) ); ?>">Settings &rarr; Bitesize Chatbot</a>
                        before uploading documents.
                    </p>
                </div>
            <?php else : ?>
                <div id="bitesize-notices"></div>

                <h2>Upload Documents</h2>
                <div class="bitesize-upload-area">
                    <input type="file" id="bitesize-file-input" accept=".pdf,.docx,.txt" multiple />
                    <button type="button" id="bitesize-upload-btn" class="button button-primary">Upload</button>
                    <span id="bitesize-upload-status"></span>
                </div>
                <p class="description">Supported file types: PDF, DOCX, TXT</p>

                <h2>Documents</h2>
                <table class="wp-list-table widefat fixed striped" id="bitesize-documents-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Last Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4">Loading...</td></tr>
                    </tbody>
                </table>

                <h2>Process Documents</h2>
                <p>After uploading, click below to ingest documents into the vector database for chat.</p>
                <button type="button" id="bitesize-ingest-btn" class="button button-primary">Process Documents</button>
                <span id="bitesize-ingest-status"></span>
            <?php endif; ?>
        </div>
        <?php
    }
}
