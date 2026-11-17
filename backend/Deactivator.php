<?php
/**
 * Deactivation.
 *
 * @package PostAloud
 */

namespace PostAloud;

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
