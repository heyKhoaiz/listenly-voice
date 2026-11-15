<?php
/**
 * Admin app assets.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Admin;

use ListenlyVoice\Hooks;
use ListenlyVoice\Plugin;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tts\GoogleProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the built app from `build/` using the Vite manifest. The app uses the React that
 * WordPress loads.
 */
final class Assets {

	const HANDLE = 'listenly-admin';
	const ENTRY  = 'frontend/admin/main.tsx';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( $this, 'module_tag' ), 10, 2 );
	}

	/**
	 * Enqueues the app on the plugin page only.
	 */
	public function enqueue(): void {
		if ( ! Menu::is_plugin_page() ) {
			return;
		}

		$deps  = (array) apply_filters( Hooks::ADMIN_SCRIPT_DEPENDENCIES, array( 'react', 'react-dom', 'wp-element', 'wp-hooks', 'wp-i18n' ) );
		$entry = $this->manifest_entry();
		if ( ! $entry ) {
			add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );
			return;
		}
		wp_enqueue_script( self::HANDLE, LISTENLY_URL . 'build/' . $entry['file'], $deps, null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name has a content hash.
		foreach ( (array) ( $entry['css'] ?? array() ) as $i => $css ) {
			wp_enqueue_style( self::HANDLE . '-' . $i, LISTENLY_URL . 'build/' . $css, array(), LISTENLY_VERSION );
		}

		wp_add_inline_script( self::HANDLE, 'window.listenlyConfig = ' . wp_json_encode( $this->config() ) . ';', 'before' );
		wp_set_script_translations( self::HANDLE, 'listenly-voice', LISTENLY_PATH . 'languages' );
	}

	/**
	 * Loads the app as an ES module.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Handle.
	 */
	public function module_tag( string $tag, string $handle ): string {
		if ( self::HANDLE === $handle && false === strpos( $tag, 'type="module"' ) ) {
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Shown when `build/` is missing.
	 */
	public function missing_build_notice(): void {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Listenly Voice cannot find its built files. Run the build, then reload this page.', 'listenly-voice' ) . '</p></div>';
	}

	/**
	 * Data for `window.listenlyConfig`. Never includes API keys.
	 */
	private function config(): array {
		$providers = array();
		foreach ( Plugin::instance()->providers->all() as $provider ) {
			$providers[] = array(
				'id'         => $provider->id(),
				'label'      => $provider->label(),
				'ready'      => $provider->is_ready(),
				'clientSide' => $provider->is_client_side(),
				'voices'     => $provider->voices(),
			);
		}
		return apply_filters(
			Hooks::ADMIN_CONFIG,
			array(
				'restUrl'      => esc_url_raw( rest_url( 'listenly/v1/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'pageUrl'      => esc_url_raw( Menu::url() ),
				'version'      => LISTENLY_VERSION,
				'settings'     => Settings::all(),
				'providers'    => $providers,
				'googleKeySet' => '' !== GoogleProvider::key(),
				'setupDone'    => (bool) get_option( Settings::SETUP_OPTION ),
				'pro'          => false,
			)
		);
	}

	/**
	 * The entry chunk from `build/.vite/manifest.json`.
	 */
	private function manifest_entry(): ?array {
		$file     = LISTENLY_PATH . 'build/.vite/manifest.json';
		$manifest = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $manifest ) && isset( $manifest[ self::ENTRY ] ) ? $manifest[ self::ENTRY ] : null;
	}
}
