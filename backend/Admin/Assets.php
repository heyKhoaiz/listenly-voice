<?php
/**
 * Admin app assets.
 *
 * @package PostAloud
 */

namespace PostAloud\Admin;

use PostAloud\Hooks;
use PostAloud\Player\Player;
use PostAloud\Player\Say;
use PostAloud\Plugin;
use PostAloud\Stats\Plays;
use PostAloud\Support\Settings;
use PostAloud\Support\Setup;
use PostAloud\Tts\GoogleProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the built app from `build/` using the Vite manifest. The app uses the React that
 * WordPress loads.
 */
final class Assets {

	const HANDLE = 'postaloud-admin';
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
		wp_enqueue_script( self::HANDLE, POSTALOUD_URL . 'build/' . $entry['file'], $deps, null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name has a content hash.
		foreach ( (array) ( $entry['css'] ?? array() ) as $i => $css ) {
			wp_enqueue_style( self::HANDLE . '-' . $i, POSTALOUD_URL . 'build/' . $css, array(), POSTALOUD_VERSION );
		}

		wp_add_inline_script( self::HANDLE, 'window.postaloudConfig = ' . wp_json_encode( $this->config() ) . ';', 'before' );
		wp_set_script_translations( self::HANDLE, 'postaloud', POSTALOUD_PATH . 'languages' );
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
		echo '<div class="notice notice-error"><p>' . esc_html__( 'PostAloud cannot find its built files. Run the build, then reload this page.', 'postaloud' ) . '</p></div>';
	}

	/**
	 * Data for `window.postaloudConfig`. Never includes API keys.
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
				'restUrl'      => esc_url_raw( rest_url( 'postaloud/v1/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'pageUrl'      => esc_url_raw( Menu::url() ),
				'version'      => POSTALOUD_VERSION,
				'settings'     => Settings::all(),
				'providers'    => $providers,
				'googleKeySet' => '' !== GoogleProvider::key(),
				'setupDone'    => Setup::is_done(),
				'postTypes'    => $this->post_types(),
				'recentPosts'  => RecentPosts::rows( (array) Settings::get( 'post_types' ) ),
				'userName'     => wp_get_current_user()->display_name,
				'plays'        => Plays::total(),
				'brand'        => Menu::brand(),
				'helpLinks'    => true,
				'sayLimit'     => Say::limit(),
				'insertMiss'   => Player::miss(),
				'pro'          => false,
			)
		);
	}

	/**
	 * Public post types the player can show on, with their names.
	 *
	 * @return array<int, array{id: string, label: string}>
	 */
	private function post_types(): array {
		$list = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$list[] = array(
				'id'    => $type->name,
				'label' => $type->labels->name,
			);
		}
		return $list;
	}

	/**
	 * The entry chunk from `build/manifest.json`.
	 */
	private function manifest_entry(): ?array {
		$file     = POSTALOUD_PATH . 'build/manifest.json';
		$manifest = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $manifest ) && isset( $manifest[ self::ENTRY ] ) ? $manifest[ self::ENTRY ] : null;
	}
}
