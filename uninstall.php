<?php
/**
 * Uninstall routine. Runs only when the plugin is deleted from the admin.
 * Removes the registrations table, plugin options, events, and their meta.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect the admin's choice: preserve all data unless they opted in to deletion.
$plc_settings = get_option( 'plc_settings' );
if ( ! is_array( $plc_settings ) || empty( $plc_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Drop the registrations table.
$table = $wpdb->prefix . 'plc_registrations';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Delete all events (and their post meta via WP).
$events = get_posts(
	array(
		'post_type'      => 'plc_event',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
	)
);
foreach ( $events as $event_id ) {
	wp_delete_post( $event_id, true );
}

// Remove plugin options.
delete_option( 'plc_settings' );
delete_option( 'plc_db_version' );
