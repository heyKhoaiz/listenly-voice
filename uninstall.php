<?php
/**
 * Removes the plugin's options and post meta when a user deletes the plugin.
 *
 * @package ListenlyVoice
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/backend/Support/Settings.php';

delete_option( \ListenlyVoice\Support\Settings::OPTION );
delete_option( \ListenlyVoice\Support\Settings::SETUP_OPTION );
delete_post_meta_by_key( '_listenly_disabled' );
delete_option( 'listenly_google_key' );

// Cached live audio and rate-limit counters.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_listenly\_%' OR option_name LIKE '\_transient\_timeout\_listenly\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
