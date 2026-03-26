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
$sudomock_options = array(
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
    'sudomock_credits_warning_dismissed',
    'sudomock_onboarding_dismissed',
);

foreach ( $sudomock_options as $sudomock_option ) {
    delete_option( $sudomock_option );
}

// Product meta
delete_post_meta_by_key( '_sudomock_mockup_uuid' );
delete_post_meta_by_key( '_sudomock_customization_enabled' );
delete_post_meta_by_key( '_sudomock_mockup_name' );

// Customizer button options (sudomock_btn_*)
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'sudomock_btn_%' ) );

// Clear any transients (sudomock_* and support rate limit transients)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
    '_transient_sudomock_%',
    '_transient_timeout_sudomock_%',
    '_transient_sudomock_support_count_%',
    '_transient_timeout_sudomock_support_count_%'
) );
