<?php
/**
 * Uninstall SudoMock Product Customizer.
 *
 * Removes all plugin data from the database when user deletes the plugin.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Plugin options
$options = array(
    'sudomock_version',
    'sudomock_api_key',
    'sudomock_account_email',
    'sudomock_plan_name',
    'sudomock_plan_tier',
    'sudomock_credits_used',
    'sudomock_credits_limit',
    'sudomock_credits_remaining',
    'sudomock_connected_at',
    'sudomock_button_label',
    'sudomock_display_mode',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Product meta
delete_post_meta_by_key( '_sudomock_mockup_uuid' );
delete_post_meta_by_key( '_sudomock_customization_enabled' );

// Clear any transients
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sudomock_%' OR option_name LIKE '_transient_timeout_sudomock_%'" );
