<?php
/**
 * The "PostAloud player" block.
 *
 * @package PostAloud
 */

namespace PostAloud\Editor;

defined( 'ABSPATH' ) || exit;

/**
 * A dynamic block that shows the player where it's placed, like [postaloud_player]. Its editor
 * script is a few lines of inline JavaScript on the registered wp.blocks API, so it needs no build.
 */
final class Block {

	const NAME   = 'postaloud/player';
	const HANDLE = 'postaloud-block';

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Registers the block type and its editor script.
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script( self::HANDLE, false, array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ), POSTALOUD_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_add_inline_script( self::HANDLE, self::editor_script() );
		wp_set_script_translations( self::HANDLE, 'postaloud', POSTALOUD_PATH . 'languages' );

		register_block_type(
			self::NAME,
			array(
				'api_version'     => 3,
				'title'           => __( 'PostAloud player', 'postaloud' ),
				'category'        => 'media',
				'icon'            => 'controls-volumeon',
				'editor_script'   => self::HANDLE,
				'supports'        => array(
					'html'     => false,
					'multiple' => false,
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * The player for the current post.
	 */
	public function render(): string {
		return (string) do_shortcode( '[postaloud_player]' );
	}

	/**
	 * Registers the block in the editor with a static preview.
	 */
	public static function editor_script(): string {
		return <<<'JS'
( function ( wp ) {
	if ( ! wp || ! wp.blocks || wp.blocks.getBlockType( 'postaloud/player' ) ) return;
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	wp.blocks.registerBlockType( 'postaloud/player', {
		title: __( 'PostAloud player', 'postaloud' ),
		description: __( 'Shows the audio player for this post here.', 'postaloud' ),
		category: 'media',
		icon: 'controls-volumeon',
		supports: { html: false, multiple: false },
		edit: function () {
			var props = wp.blockEditor.useBlockProps( { className: 'postaloud-block-preview' } );
			return el( 'div', props,
				el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 14px', border: '1px solid #d6d3d1', borderRadius: '14px', background: '#f7f7f5', color: '#1c1917' } },
					el( 'span', { 'aria-hidden': 'true', style: { width: '44px', height: '44px', borderRadius: '50%', background: '#1c1917', color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' } }, '▶' ),
					el( 'strong', null, __( 'Listen to this article', 'postaloud' ) )
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
JS;
	}
}
