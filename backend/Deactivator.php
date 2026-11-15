<?php
/**
 * Deactivation.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on deactivation. Settings stay; uninstall.php removes them.
 */
final class Deactivator {

	/**
	 * Nothing scheduled in the free plugin yet.
	 */
	public static function deactivate(): void {}
}
