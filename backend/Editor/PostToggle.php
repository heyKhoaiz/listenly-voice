<?php
/**
 * Per-post "audio on/off" switch in the block editor.
 *
 * @package PostAloud
 */

namespace PostAloud\Editor;

use PostAloud\Hooks;
use PostAloud\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `_postaloud_disabled` for the REST API and loads build/editor/editor.js, which adds
 * a switch to the post's sidebar. Sites on the classic editor get the same switch as a meta box.
 */
final class PostToggle {

	const META   = '_postaloud_disabled';
	const HANDLE = 'postaloud-editor';
	const NONCE  = 'postaloud_toggle_nonce';
	const FIELD  = 'postaloud_audio_on';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		// After themes and plugins have registered their post types.
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
	}

	/**
	 * Whether audio is turned off for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_disabled( int $post_id ): bool {
		return (bool) apply_filters( Hooks::POST_DISABLED, (bool) get_post_meta( $post_id, self::META, true ), $post_id );
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
			POSTALOUD_URL . 'build/editor/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			POSTALOUD_VERSION,
			true
		);
		wp_set_script_translations( self::HANDLE, 'postaloud', POSTALOUD_PATH . 'languages' );
	}

	/**
	 * The classic editor box, on post types the player shows on. The block editor hides it,
	 * because it has its own panel.
	 *
	 * @param string $post_type Post type of the screen.
	 */
	public function add_meta_box( $post_type ): void {
		if ( ! in_array( (string) $post_type, (array) Settings::get( 'post_types' ), true ) ) {
			return;
		}
		add_meta_box(
			'postaloud',
			__( 'PostAloud', 'postaloud' ),
			array( $this, 'render_meta_box' ),
			(string) $post_type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * The checkbox, ticked while audio is on.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		printf(
			'<p><label><input type="checkbox" name="%1$s" value="1"%2$s> %3$s</label></p>',
			esc_attr( self::FIELD ),
			self::is_disabled( (int) $post->ID ) ? '' : ' checked="checked"',
			esc_html__( 'Audio player on this post', 'postaloud' )
		);
	}

	/**
	 * Saves the box. Saves that didn't come from it (block editor, REST, quick edit) carry no
	 * nonce and change nothing.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta_box( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST[ self::FIELD ] ) ) {
			delete_post_meta( $post_id, self::META );
		} else {
			update_post_meta( $post_id, self::META, true );
		}
	}
}
