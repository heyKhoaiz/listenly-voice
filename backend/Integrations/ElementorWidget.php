<?php
/**
 * The Elementor widget.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the player for the current post, like [listenly_player]. Loaded by Elementor::widgets()
 * only, after Elementor itself.
 */
final class ElementorWidget extends \Elementor\Widget_Base {

	/** {@inheritDoc} */
	public function get_name() {
		return 'listenly-player';
	}

	/** {@inheritDoc} */
	public function get_title() {
		return __( 'Listenly player', 'listenly-voice' );
	}

	/** {@inheritDoc} */
	public function get_icon() {
		return 'eicon-headphones';
	}

	/** {@inheritDoc} */
	public function get_categories() {
		return array( 'general' );
	}

	/** {@inheritDoc} */
	public function get_keywords() {
		return array( 'audio', 'listen', 'text to speech', 'tts', 'player' );
	}

	/**
	 * The player, or a note in the editor when the post has audio switched off.
	 */
	protected function render() {
		$html = (string) do_shortcode( '[listenly_player]' );
		if ( '' === $html && class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<p>' . esc_html__( 'Audio is switched off for this post.', 'listenly-voice' ) . '</p>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Player::markup().
	}
}
