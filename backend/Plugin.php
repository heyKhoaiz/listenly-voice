<?php
/**
 * Main plugin class.
 *
 * @package PostAloud
 */

namespace PostAloud;

use PostAloud\Admin\Assets;
use PostAloud\Admin\Menu;
use PostAloud\Editor\Block;
use PostAloud\Editor\PostToggle;
use PostAloud\Integrations\Elementor;
use PostAloud\Player\Player;
use PostAloud\Rest\SettingsController;
use PostAloud\Support\Setup;
use PostAloud\Tts\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the services together.
 */
final class Plugin {

	/**
	 * The booted instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Voice providers.
	 *
	 * @var Registry
	 */
	public $providers;

	/**
	 * Boots the plugin once, on `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = new self();
		self::$instance->register();

		do_action( Hooks::LOADED, self::$instance );
	}

	/**
	 * The booted instance.
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * Registers every service.
	 */
	private function register(): void {
		$this->providers = new Registry();

		( new Menu() )->register();
		( new Assets() )->register();
		( new Setup() )->register();
		( new PostToggle() )->register();
		( new Player( $this->providers ) )->register();
		( new Block() )->register();
		( new Elementor() )->register();

		add_action(
			'rest_api_init',
			static function () {
				( new SettingsController() )->register_routes();
			}
		);
	}
}
