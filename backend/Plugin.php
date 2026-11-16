<?php
/**
 * Main plugin class.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice;

use ListenlyVoice\Admin\Assets;
use ListenlyVoice\Admin\Menu;
use ListenlyVoice\Editor\PostToggle;
use ListenlyVoice\Player\Player;
use ListenlyVoice\Rest\SettingsController;
use ListenlyVoice\Support\Setup;
use ListenlyVoice\Tts\Registry;

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

		add_action(
			'rest_api_init',
			static function () {
				( new SettingsController() )->register_routes();
			}
		);
	}
}
