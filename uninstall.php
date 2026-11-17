<?php
/**
 * Removes the plugin's options and post meta when a user deletes the plugin.
 *
 * @package PostAloud
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/backend/Support/Settings.php';

delete_option( \PostAloud\Support\Settings::OPTION );
delete_option( \PostAloud\Support\Settings::SETUP_OPTION );
delete_post_meta_by_key( '_postaloud_disabled' );
delete_option( 'postaloud_google_key' );
delete_post_meta_by_key( '_postaloud_plays' );
delete_option( 'postaloud_plays_total' );
delete_option( 'postaloud_insert_miss' );

// Cached live audio and rate-limit counters.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_postaloud\_%' OR option_name LIKE '\_transient\_timeout\_postaloud\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
