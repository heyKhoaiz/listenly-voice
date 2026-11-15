<?php
/**
 * Admin menu.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One top-level page that hosts the React app.
 */
final class Menu {

	const SLUG = 'listenly-voice';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add' ) );
	}

	/**
	 * Adds the menu page.
	 */
	public function add(): void {
		add_menu_page(
			__( 'Listenly Voice', 'listenly-voice' ),
			__( 'Listenly Voice', 'listenly-voice' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-controls-volumeon',
			58
		);
	}

	/**
	 * The mount point for the app.
	 */
	public function render(): void {
		echo '<div id="listenly-app" class="listenly-app"></div>';
	}

	/**
	 * True on the plugin's admin page.
	 */
	public static function is_plugin_page(): bool {
		return isset( $_GET['page'] ) && self::SLUG === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The page URL.
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}
}
