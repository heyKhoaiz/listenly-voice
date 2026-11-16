<?php
/**
 * Per-post "audio on/off" switch in the block editor.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Editor;

use ListenlyVoice\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `_listenly_disabled` for the REST API and loads build/editor/editor.js, which adds
 * a switch to the post's sidebar.
 */
final class PostToggle {

	const META   = '_listenly_disabled';
	const HANDLE = 'listenly-editor';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		// After themes and plugins have registered their post types.
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether audio is turned off for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_disabled( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::META, true );
	}

	/**
	 * Registers the meta on every post type. Protected meta (leading underscore) needs an
	 * auth callback to be editable through the REST API.
	 */
	public function register_meta(): void {
		register_post_meta(
			'',
			self::META,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);

		// The REST API only returns meta for post types that support custom fields.
		foreach ( (array) Settings::get( 'post_types' ) as $type ) {
			if ( ! post_type_supports( $type, 'custom-fields' ) ) {
				add_post_type_support( $type, 'custom-fields' );
			}
		}
	}

	/**
	 * Loads the switch on the edit screen of post types the player shows on.
	 */
	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			self::HANDLE,
			LISTENLY_URL . 'build/editor/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			LISTENLY_VERSION,
			true
		);
		wp_set_script_translations( self::HANDLE, 'listenly-voice', LISTENLY_PATH . 'languages' );
	}
}
