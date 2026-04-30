<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all bitesize_* options from the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$options = array(
    'bitesize_api_key',
    'bitesize_tenant_id',
    'bitesize_account_email',
    'bitesize_widget_title',
    'bitesize_primary_color',
    'bitesize_enabled',
);

foreach ( $options as $option ) {
    delete_option( $option );
}
