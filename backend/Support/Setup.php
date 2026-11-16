<?php
/**
 * First-run setup state and the redirect to the wizard after activation.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Support;

use ListenlyVoice\Admin\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks whether the setup wizard has been finished or skipped.
 */
final class Setup {

	const REDIRECT_TRANSIENT = 'listenly_setup_redirect';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * True once the wizard was finished or skipped.
	 */
	public static function is_done(): bool {
		return (bool) get_option( Settings::SETUP_OPTION, false );
	}

	/**
	 * Marks setup as done (finished or skipped).
	 */
	public static function complete(): void {
		update_option( Settings::SETUP_OPTION, 1 );
	}

	/**
	 * Shows the wizard again next time the plugin page opens.
	 */
	public static function reset(): void {
		delete_option( Settings::SETUP_OPTION );
	}

	/**
	 * On activation: open the wizard once, unless setup was already done.
	 */
	public static function queue_redirect(): void {
		if ( ! self::is_done() ) {
			set_transient( self::REDIRECT_TRANSIENT, 1, 30 );
		}
	}

	/**
	 * Redirect only for a single activation by someone who can finish setup.
	 *
	 * @param bool $queued     The activation set the transient.
	 * @param bool $network    In the network admin.
	 * @param bool $ajax       An AJAX request.
	 * @param bool $bulk       Several plugins were activated at once.
	 * @param bool $can_manage The user can manage options.
	 */
	public static function should_redirect( bool $queued, bool $network, bool $ajax, bool $bulk, bool $can_manage ): bool {
		return $queued && ! $network && ! $ajax && ! $bulk && $can_manage;
	}

	/**
	 * Sends the admin to the wizard once after activation.
	 */
	public function maybe_redirect(): void {
		$queued = (bool) get_transient( self::REDIRECT_TRANSIENT );
		if ( ! $queued ) {
			return;
		}
		delete_transient( self::REDIRECT_TRANSIENT );
		$bulk = isset( $_GET['activate-multi'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check set by WordPress.
		if ( self::should_redirect( $queued, is_network_admin(), wp_doing_ajax(), $bulk, current_user_can( 'manage_options' ) ) ) {
			wp_safe_redirect( Menu::url() );
			exit;
		}
	}

	/**
	 * Where "View a post" goes: the newest published post of the chosen types, else the home page.
	 *
	 * @param string[] $post_types Post types that show the player.
	 */
	public static function view_url( array $post_types ): string {
		if ( $post_types ) {
			$ids = get_posts(
				array(
					'post_type'    => $post_types,
					'post_status'  => 'publish',
					'numberposts'  => 1,
					'fields'       => 'ids',
					'has_password' => false,
				)
			);
			if ( $ids ) {
				return (string) get_permalink( (int) $ids[0] );
			}
		}
		return (string) home_url( '/' );
	}
}
