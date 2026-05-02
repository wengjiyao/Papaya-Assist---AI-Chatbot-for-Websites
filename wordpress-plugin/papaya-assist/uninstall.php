<?php
/**
 * Fired when the Papaya Assist plugin is uninstalled.
 * Removes all plugin options from the database.
 * Option names kept as bitesize_* for backward compat with self-hosted users.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$papaya_assist_options = array(
    'bitesize_api_key',
    'bitesize_tenant_id',
    'bitesize_account_email',
    'bitesize_widget_title',
    'bitesize_primary_color',
    'bitesize_enabled',
);

foreach ( $papaya_assist_options as $papaya_assist_option ) {
    delete_option( $papaya_assist_option );
}
