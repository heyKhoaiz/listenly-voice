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
