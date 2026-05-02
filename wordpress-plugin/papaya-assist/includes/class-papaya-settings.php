<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Papaya_Assist_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_papaya_assist_save_credentials', array( $this, 'ajax_save_credentials' ) );
        add_action( 'wp_ajax_papaya_assist_disconnect', array( $this, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_papaya_assist_change_password', array( $this, 'ajax_change_password' ) );
        add_action( 'wp_ajax_papaya_assist_get_usage', array( $this, 'ajax_get_usage' ) );
        add_action( 'wp_ajax_papaya_assist_get_upgrade_url', array( $this, 'ajax_get_upgrade_url' ) );
        add_action( 'wp_ajax_papaya_assist_check_verification', array( $this, 'ajax_check_verification' ) );
    }

    public function add_menu() {
        add_options_page(
            __( 'Papaya Assist', 'papaya-assist' ),
            __( 'Papaya Assist', 'papaya-assist' ),
            'manage_options',
            'papaya-assist',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_papaya-assist' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'papaya-assist-admin',
            PAPAYA_ASSIST_URL . 'assets/css/admin.css',
            array(),
            PAPAYA_ASSIST_VERSION
        );
        wp_enqueue_script(
            'papaya-assist-settings',
            PAPAYA_ASSIST_URL . 'assets/js/admin-settings.js',
            array( 'jquery' ),
            PAPAYA_ASSIST_VERSION,
            true
        );
        wp_localize_script( 'papaya-assist-settings', 'papayaAssistSettings', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'papaya_assist_auth' ),
            'authUrl'  => PAPAYA_ASSIST_AUTH_URL,
            'tenantId' => get_option( 'bitesize_tenant_id', '' ),
        ) );

        // Documents JS (AJAX handlers registered in class-papaya-documents.php)
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
            'allowedTypes' => array( 'pdf', 'docx', 'txt' ),
        ) );
    }

    public function register_settings() {
        // DB option names kept as bitesize_* for backward compat with self-hosted users.
        register_setting( 'papaya_assist', 'bitesize_widget_title', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Chat with us',
        ) );
        register_setting( 'papaya_assist', 'bitesize_primary_color', array(
            'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
            'default'           => '#4f46e5',
        ) );
        register_setting( 'papaya_assist', 'bitesize_enabled', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );

        add_settings_section(
            'papaya_assist_widget_section',
            __( 'Widget Appearance', 'papaya-assist' ),
            null,
            'papaya-assist'
        );
        add_settings_field( 'bitesize_widget_title', __( 'Widget Title', 'papaya-assist' ), array( $this, 'render_text_field' ), 'papaya-assist', 'papaya_assist_widget_section', array(
            'label_for' => 'bitesize_widget_title',
            'default'   => 'Chat with us',
        ) );
        add_settings_field( 'bitesize_primary_color', __( 'Primary Color', 'papaya-assist' ), array( $this, 'render_color_field' ), 'papaya-assist', 'papaya_assist_widget_section', array(
            'label_for' => 'bitesize_primary_color',
            'default'   => '#4f46e5',
        ) );
        add_settings_field( 'bitesize_enabled', __( 'Enable Chatbot', 'papaya-assist' ), array( $this, 'render_checkbox_field' ), 'papaya-assist', 'papaya_assist_widget_section', array(
            'label_for'   => 'bitesize_enabled',
            'description' => __( 'Show the chat widget on the frontend.', 'papaya-assist' ),
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
        check_ajax_referer( 'papaya_assist_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $api_key   = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        $tenant_id = isset( $_POST['tenant_id'] ) ? sanitize_title( wp_unslash( $_POST['tenant_id'] ) ) : '';
        $email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( empty( $api_key ) || empty( $tenant_id ) || empty( $email ) ) {
            wp_send_json_error( __( 'Missing credentials.', 'papaya-assist' ) );
        }

        // DB option names kept as bitesize_* for backward compat.
        update_option( 'bitesize_api_key', $api_key );
        update_option( 'bitesize_tenant_id', $tenant_id );
        update_option( 'bitesize_account_email', $email );

        $email_verified = isset( $_POST['email_verified'] ) && $_POST['email_verified'] === 'true';
        update_option( 'bitesize_email_verified', $email_verified ? '1' : '0' );

        wp_send_json_success( array( 'email_verified' => $email_verified ) );
    }

    public function ajax_disconnect() {
        check_ajax_referer( 'papaya_assist_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        delete_option( 'bitesize_api_key' );
        delete_option( 'bitesize_account_email' );

        wp_send_json_success();
    }

    public function ajax_change_password() {
        check_ajax_referer( 'papaya_assist_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( __( 'Password must be at least 8 characters.', 'papaya-assist' ) );
        }

        $api_key = get_option( 'bitesize_api_key', '' );
        $email   = get_option( 'bitesize_account_email', '' );

        if ( empty( $api_key ) || empty( $email ) ) {
            wp_send_json_error( __( 'Account not connected.', 'papaya-assist' ) );
        }

        $url = trailingslashit( PAPAYA_ASSIST_ADMIN_API_URL ) . 'admin/users/' . rawurlencode( $email );

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
            wp_send_json_error( __( 'Request failed: ', 'papaya-assist' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            wp_send_json_success();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $detail = isset( $body['detail'] ) ? $body['detail'] : __( 'Failed to change password.', 'papaya-assist' );
        wp_send_json_error( $detail );
    }

    public function ajax_get_usage() {
        check_ajax_referer( 'papaya_assist_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );

        if ( empty( $api_key ) || empty( $tenant_id ) ) {
            wp_send_json_error( __( 'Account not connected.', 'papaya-assist' ) );
        }

        $url = trailingslashit( PAPAYA_ASSIST_ADMIN_API_URL ) . 'admin/usage/' . rawurlencode( $tenant_id );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'X-API-Key' => $api_key,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Request failed: ', 'papaya-assist' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && $body ) {
            wp_send_json_success( $body );
        }

        $detail = isset( $body['detail'] ) ? $body['detail'] : __( 'Failed to fetch usage.', 'papaya-assist' );
        wp_send_json_error( $detail );
    }

    public function ajax_get_upgrade_url() {
        check_ajax_referer( 'papaya_assist_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $api_key   = get_option( 'bitesize_api_key', '' );
        $tenant_id = get_option( 'bitesize_tenant_id', '' );

        if ( empty( $api_key ) || empty( $tenant_id ) ) {
            wp_send_json_error( __( 'Account not connected.', 'papaya-assist' ) );
        }

        $url = trailingslashit( PAPAYA_ASSIST_ADMIN_API_URL ) . 'admin/upgrade-token';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'X-API-Key'    => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'tenant_id' => $tenant_id ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Request failed: ', 'papaya-assist' ) . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && ! empty( $body['token'] ) ) {
            $pricing_url = 'https://weng.ca/pricing/?token=' . urlencode( $body['token'] );
            wp_send_json_success( array( 'url' => $pricing_url ) );
        }

        $detail = isset( $body['detail'] ) ? $body['detail'] : __( 'Failed to generate upgrade link.', 'papaya-assist' );
        wp_send_json_error( $detail );
    }

    public function ajax_check_verification() {
        check_ajax_referer( 'papaya_assist_auth', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'papaya-assist' ), 403 );
        }

        $email = get_option( 'bitesize_account_email', '' );
        if ( empty( $email ) ) {
            wp_send_json_error( __( 'Account not connected.', 'papaya-assist' ) );
        }

        $url = trailingslashit( PAPAYA_ASSIST_ADMIN_API_URL ) . 'admin/verify-status?email=' . rawurlencode( $email );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Request failed.', 'papaya-assist' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $verified = ! empty( $body['email_verified'] );

        update_option( 'bitesize_email_verified', $verified ? '1' : '0' );

        wp_send_json_success( array( 'email_verified' => $verified ) );
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
            <h1><?php esc_html_e( 'Papaya Assist', 'papaya-assist' ); ?></h1>

            <!-- Account Section -->
            <div class="papaya-assist-card" id="papaya-assist-account-section">
                <h2><?php esc_html_e( 'Account', 'papaya-assist' ); ?></h2>
                <div id="papaya-assist-auth-notices"></div>

                <div id="papaya-assist-connected" class="papaya-assist-connected" <?php echo $connected ? '' : 'style="display:none;"'; ?>>
                    <div id="papaya-assist-verify-notice" class="notice notice-warning inline" style="display:none; margin: 0 0 12px;">
                        <p><strong><?php esc_html_e( 'Email not verified.', 'papaya-assist' ); ?></strong>
                        <?php esc_html_e( 'Please check your inbox for a verification email and click the link to activate your chatbot. The chatbot will not respond until your email is verified.', 'papaya-assist' ); ?></p>
                    </div>
                    <p><?php esc_html_e( 'Connected as', 'papaya-assist' ); ?> <strong id="papaya-assist-connected-email"><?php echo esc_html( $email ); ?></strong></p>
                    <p class="description"><?php esc_html_e( 'Tenant ID:', 'papaya-assist' ); ?> <code><?php echo esc_html( $tenant_id ); ?></code></p>

                    <div style="margin-top: 16px;">
                        <h3 style="margin-bottom: 8px;"><?php esc_html_e( 'Change Password', 'papaya-assist' ); ?></h3>
                        <p style="display: flex; align-items: center; gap: 8px;">
                            <input type="password" id="papaya-assist-new-password" placeholder="<?php esc_attr_e( 'New password (min 8 chars)', 'papaya-assist' ); ?>" class="regular-text" />
                            <button type="button" id="papaya-assist-change-password-btn" class="button button-primary"><?php esc_html_e( 'Change Password', 'papaya-assist' ); ?></button>
                        </p>
                    </div>

                    <div id="papaya-assist-usage" style="margin-top: 16px; padding: 12px; background: #f0f0f1; border-radius: 4px;">
                        <strong><?php esc_html_e( 'Usage:', 'papaya-assist' ); ?></strong> <span id="papaya-assist-usage-text"><?php esc_html_e( 'Loading...', 'papaya-assist' ); ?></span>
                        <button type="button" id="papaya-assist-upgrade-btn" class="button button-primary" style="margin-left: 12px;"><?php esc_html_e( 'Upgrade Plan', 'papaya-assist' ); ?></button>
                    </div>

                    <p style="margin-top: 16px;">
                        <button type="button" id="papaya-assist-disconnect-btn" class="button"><?php esc_html_e( 'Disconnect', 'papaya-assist' ); ?></button>
                    </p>
                </div>

                <div id="papaya-assist-not-connected" <?php echo $connected ? 'style="display:none;"' : ''; ?>>
                    <p><?php esc_html_e( 'Sign up or log in to connect your site to Papaya Assist.', 'papaya-assist' ); ?></p>
                    <p style="margin-top: 12px;">
                        <button type="button" id="papaya-assist-connect-btn" class="button button-primary button-hero"><?php esc_html_e( 'Sign Up / Log In', 'papaya-assist' ); ?></button>
                    </p>
                </div>
            </div>

            <!-- Widget Appearance -->
            <div class="papaya-assist-card">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'papaya_assist' );
                    do_settings_sections( 'papaya-assist' );
                    submit_button();
                    ?>
                </form>
            </div>

            <?php if ( $connected ) : ?>
            <!-- Documents -->
            <div class="papaya-assist-card">
                <h2><?php esc_html_e( 'Documents', 'papaya-assist' ); ?></h2>
                <div id="papaya-assist-notices"></div>

                <h3><?php esc_html_e( 'Upload Documents', 'papaya-assist' ); ?></h3>
                <div class="papaya-assist-upload-area">
                    <input type="file" id="papaya-assist-file-input" accept=".pdf,.docx,.txt" multiple />
                    <button type="button" id="papaya-assist-upload-btn" class="button button-primary"><?php esc_html_e( 'Upload', 'papaya-assist' ); ?></button>
                    <span id="papaya-assist-upload-status"></span>
                </div>
                <p class="description"><?php esc_html_e( 'Supported file types: PDF, DOCX, TXT', 'papaya-assist' ); ?></p>

                <h3><?php esc_html_e( 'Uploaded Files', 'papaya-assist' ); ?></h3>
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

                <h3><?php esc_html_e( 'Process Documents', 'papaya-assist' ); ?></h3>
                <p><?php esc_html_e( 'After uploading, click below to ingest documents into the vector database for chat.', 'papaya-assist' ); ?></p>
                <button type="button" id="papaya-assist-ingest-btn" class="button button-primary"><?php esc_html_e( 'Process Documents', 'papaya-assist' ); ?></button>
                <span id="papaya-assist-ingest-status"></span>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
