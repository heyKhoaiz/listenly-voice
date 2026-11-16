<?php
/**
 * Activation.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice;

use ListenlyVoice\Support\Settings;
use ListenlyVoice\Support\Setup;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on activation.
 */
final class Activator {

	/**
	 * Stores the default settings once, so the player works with no setup, and opens the setup wizard
	 * on the next admin page load.
	 */
	public static function activate(): void {
		add_option( Settings::OPTION, Settings::defaults() );
		Setup::queue_redirect();
	}
}
