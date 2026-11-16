<?php
/**
 * Elementor support.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Listenly player" widget when Elementor is active. The widget class extends
 * Elementor's base class, so it's only loaded once Elementor asks for widgets.
 */
final class Elementor {

	/**
	 * Registers hooks. Harmless without Elementor: the action never fires.
	 */
	public function register(): void {
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
	}

	/**
	 * Registers the widget.
	 *
	 * @param object $manager Elementor's widgets manager.
	 */
	public function widgets( $manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		require_once __DIR__ . '/ElementorWidget.php';
		$manager->register( new ElementorWidget() );
	}
}
