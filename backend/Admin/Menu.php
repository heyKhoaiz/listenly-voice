<?php
/**
 * Admin menu.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Admin;

use ListenlyVoice\Hooks;

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
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Lets the app fill the screen next to the admin menu on its own page only.
	 *
	 * @param string $classes Space-separated body classes.
	 */
	public function body_class( string $classes ): string {
		return self::is_plugin_page() ? $classes . ' listenly-fullscreen' : $classes;
	}

	/**
	 * Adds the menu page.
	 */
	public function add(): void {
		add_menu_page(
			self::brand(),
			self::brand(),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-controls-volumeon',
			58
		);
	}

	/**
	 * The plugin name in the menu and the app header. Filterable for white-label sites.
	 */
	public static function brand(): string {
		$brand = trim( (string) apply_filters( Hooks::BRAND, __( 'Listenly Voice', 'listenly-voice' ) ) );
		return '' !== $brand ? $brand : __( 'Listenly Voice', 'listenly-voice' );
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
