<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bitesize_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handler for saving credentials from popup
        add_action( 'wp_ajax_bitesize_save_credentials', array( $this, 'ajax_save_credentials' ) );
        add_action( 'wp_ajax_bitesize_disconnect', array( $this, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_bitesize_change_password', array( $this, 'ajax_change_password' ) );
        add_action( 'wp_ajax_bitesize_get_usage', array( $this, 'ajax_get_usage' ) );
        add_action( 'wp_ajax_bitesize_get_upgrade_url', array( $this, 'ajax_get_upgrade_url' ) );
    }

    public function add_menu() {
        add_options_page(
            'Bitesize Chatbot',
            'Bitesize Chatbot',
            'manage_options',
            'bitesize-chatbot',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_bitesize-chatbot' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'bitesize-admin',
            BITESIZE_CHATBOT_URL . 'assets/css/admin.css',
            array(),
            BITESIZE_CHATBOT_VERSION
        );
        wp_enqueue_script(
            'bitesize-admin-settings',
            BITESIZE_CHATBOT_URL . 'assets/js/admin-settings.js',
            array( 'jquery' ),
            BITESIZE_CHATBOT_VERSION,
            true
        );
        wp_localize_script( 'bitesize-admin-settings', 'bitesizeSettings', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bitesize_auth' ),
            'authUrl'  => BITESIZE_AUTH_URL,
            'tenantId' => get_option( 'bitesize_tenant_id', '' ),
        ) );

        // Documents JS (AJAX handlers registered in class-bitesize-documents.php)
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
            'allowedTypes' => array( 'pdf', 'docx', 'txt' ),
        ) );
    }

    public function register_settings() {
        register_setting( 'bitesize_chatbot', 'bitesize_widget_title', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Chat with us',
        ) );
        register_setting( 'bitesize_chatbot', 'bitesize_primary_color', array(
            'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
            'default'           => '#4f46e5',
        ) );
        register_setting( 'bitesize_chatbot', 'bitesize_enabled', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );

        add_settings_section(
            'bitesize_widget_section',
            'Widget Appearance',
            null,
            'bitesize-chatbot'
        );
        add_settings_field( 'bitesize_widget_title', 'Widget Title', array( $this, 'render_text_field' ), 'bitesize-chatbot', 'bitesize_widget_section', array(
            'label_for' => 'bitesize_widget_title',
            'default'   => 'Chat with us',
        ) );
        add_settings_field( 'bitesize_primary_color', 'Primary Color', array( $this, 'render_color_field' ), 'bitesize-chatbot', 'bitesize_widget_section', array(
            'label_for' => 'bitesize_primary_color',
            'default'   => '#4f46e5',
        ) );
        add_settings_field( 'bitesize_enabled', 'Enable Chatbot', array( $this, 'render_checkbox_field' ), 'bitesize-chatbot', 'bitesize_widget_section', array(
            'label_for'   => 'bitesize_enabled',
            'description' => 'Show the chat widget on the frontend.',
        ) );
    }

    // --- Sanitization ---

    public function sanitize_hex_color( $value ) {
        if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
            return $value;
        }
        return '#4f46e5';
    }

    public function sanitize_checkbox( $value ) {
        return $value ? '1' : '0';
    }

    // --- Field renderers ---

    public function render_text_field( $args ) {
        $id      = $args['label_for'];
        $default = isset( $args['default'] ) ? $args['default'] : '';
        $value   = get_option( $id, $default );
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $id ),
            esc_attr( $id ),
            esc_attr( $value )
        );
    }

    public function render_color_field( $args ) {
        $id      = $args['label_for'];
        $default = isset( $args['default'] ) ? $args['default'] : '#4f46e5';
        $value   = get_option( $id, $default );
        printf(
            '<input type="color" id="%s" name="%s" value="%s" />',
            esc_attr( $id ),
            esc_attr( $id ),
            esc_attr( $value )
        );
    }

    public function render_checkbox_field( $args ) {
        $id    = $args['label_for'];
        $value = get_option( $id, '1' );
        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr( $id ),
            esc_attr( $id ),
            checked( $value, '1', false )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<label for="%s"> %s</label>', esc_attr( $id ), esc_html( $args['description'] ) );
        }
    }

    // --- AJAX handlers ---

    public function ajax_save_credentials() {
        check_ajax_referer( 'bitesize_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $api_key   = sanitize_text_field( $_POST['api_key'] );
        $tenant_id = sanitize_title( $_POST['tenant_id'] );
        $email     = sanitize_email( $_POST['email'] );

        if ( empty( $api_key ) || empty( $tenant_id ) || empty( $email ) ) {
            wp_send_json_error( 'Missing credentials.' );
        }

        update_option( 'bitesize_api_key', $api_key );
        update_option( 'bitesize_tenant_id', $tenant_id );
        update_option( 'bitesize_account_email', $email );

        wp_send_json_success();
    }

    public function ajax_disconnect() {
        check_ajax_referer( 'bitesize_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        delete_option( 'bitesize_api_key' );
        delete_option( 'bitesize_account_email' );

        wp_send_json_success();
    }

    public function ajax_change_password() {
        check_ajax_referer( 'bitesize_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $password = sanitize_text_field( $_POST['password'] );
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( 'Password must be at least 8 characters.' );
        }

        $api_key = get_option( 'bitesize_api_key', '' );
        $email   = get_option( 'bitesize_account_email', '' );

        if ( empty( $api_key ) || empty( $email ) ) {
            wp_send_json_error( 'Account not connected.' );
        }

        $url = trailingslashit( BITESIZE_ADMIN_API_URL ) . 'admin/users/' . rawurlencode( $email );

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'headers' => array(
                'X-API-Key'    => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'password' => $password ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            wp_send_json_success();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $detail = isset( $body['detail'] ) ? $body['detail'] : 'Failed to change password.';
        wp_send_json_error( $detail );
    }

    public function ajax_get_usage() {
        check_ajax_referer( 'bitesize_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );

        if ( empty( $api_key ) || empty( $tenant_id ) ) {
            wp_send_json_error( 'Account not connected.' );
        }

        $url = trailingslashit( BITESIZE_ADMIN_API_URL ) . 'admin/usage/' . rawurlencode( $tenant_id );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'X-API-Key' => $api_key,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && $body ) {
            wp_send_json_success( $body );
        }

        $detail = isset( $body['detail'] ) ? $body['detail'] : 'Failed to fetch usage.';
        wp_send_json_error( $detail );
    }

    public function ajax_get_upgrade_url() {
        check_ajax_referer( 'bitesize_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );

        if ( empty( $api_key ) || empty( $tenant_id ) ) {
            wp_send_json_error( 'Account not connected.' );
        }

        $url = trailingslashit( BITESIZE_ADMIN_API_URL ) . 'admin/upgrade-token';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'X-API-Key'    => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'tenant_id' => $tenant_id ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && ! empty( $body['token'] ) ) {
            $pricing_url = 'https://weng.ca/pricing/?token=' . urlencode( $body['token'] );
            wp_send_json_success( array( 'url' => $pricing_url ) );
        }

        $detail = isset( $body['detail'] ) ? $body['detail'] : 'Failed to generate upgrade link.';
        wp_send_json_error( $detail );
    }

    // --- Page render ---

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_key   = get_option( 'bitesize_api_key', '' );
        $email     = get_option( 'bitesize_account_email', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );
        $connected = ! empty( $api_key ) && ! empty( $email );
        ?>
        <div class="wrap">
            <h1>Bitesize Chatbot</h1>

            <!-- Account Section -->
            <div class="bitesize-card" id="bitesize-account-section">
                <h2>Account</h2>
                <div id="bitesize-auth-notices"></div>

                <div id="bitesize-connected" class="bitesize-connected" <?php echo $connected ? '' : 'style="display:none;"'; ?>>
                    <p>Connected as <strong id="bitesize-connected-email"><?php echo esc_html( $email ); ?></strong></p>
                    <p class="description">Tenant ID: <code><?php echo esc_html( $tenant_id ); ?></code></p>

                    <div style="margin-top: 16px;">
                        <h3 style="margin-bottom: 8px;">Change Password</h3>
                        <p style="display: flex; align-items: center; gap: 8px;">
                            <input type="password" id="bitesize-new-password" placeholder="New password (min 8 chars)" class="regular-text" />
                            <button type="button" id="bitesize-change-password-btn" class="button button-primary">Change Password</button>
                        </p>
                    </div>

                    <div id="bitesize-usage" style="margin-top: 16px; padding: 12px; background: #f0f0f1; border-radius: 4px;">
                        <strong>Usage:</strong> <span id="bitesize-usage-text">Loading...</span>
                        <button type="button" id="bitesize-upgrade-btn" class="button button-primary" style="margin-left: 12px;">Upgrade Plan</button>
                    </div>

                    <p style="margin-top: 16px;">
                        <button type="button" id="bitesize-disconnect-btn" class="button">Disconnect</button>
                    </p>
                </div>

                <div id="bitesize-not-connected" <?php echo $connected ? 'style="display:none;"' : ''; ?>>
                    <p>Sign up or log in to connect your site to Bitesize Chatbot.</p>
                    <p style="margin-top: 12px;">
                        <button type="button" id="bitesize-connect-btn" class="button button-primary button-hero">Sign Up / Log In</button>
                    </p>
                </div>
            </div>

            <!-- Widget Appearance -->
            <div class="bitesize-card">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'bitesize_chatbot' );
                    do_settings_sections( 'bitesize-chatbot' );
                    submit_button();
                    ?>
                </form>
            </div>

            <?php if ( $connected ) : ?>
            <!-- Documents -->
            <div class="bitesize-card">
                <h2>Documents</h2>
                <div id="bitesize-notices"></div>

                <h3>Upload Documents</h3>
                <div class="bitesize-upload-area">
                    <input type="file" id="bitesize-file-input" accept=".pdf,.docx,.txt" multiple />
                    <button type="button" id="bitesize-upload-btn" class="button button-primary">Upload</button>
                    <span id="bitesize-upload-status"></span>
                </div>
                <p class="description">Supported file types: PDF, DOCX, TXT</p>

                <h3>Uploaded Files</h3>
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

                <h3>Process Documents</h3>
                <p>After uploading, click below to ingest documents into the vector database for chat.</p>
                <button type="button" id="bitesize-ingest-btn" class="button button-primary">Process Documents</button>
                <span id="bitesize-ingest-status"></span>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
