<?php
/**
 * Activation.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice;

use ListenlyVoice\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on activation.
 */
final class Activator {

	/**
	 * Stores the default settings once, so the player works with no setup.
	 */
	public static function activate(): void {
		add_option( Settings::OPTION, Settings::defaults() );
	}
}
