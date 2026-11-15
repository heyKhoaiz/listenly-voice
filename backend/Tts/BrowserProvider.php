<?php
/**
 * The visitor's browser voice (Web Speech API).
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * No server work: the player speaks the text with speechSynthesis.
 */
final class BrowserProvider implements ProviderInterface {

	/** {@inheritDoc} */
	public function id(): string {
		return 'browser';
	}

	/** {@inheritDoc} */
	public function label(): string {
		return __( 'Browser voice', 'listenly-voice' );
	}

	/** {@inheritDoc} */
	public function is_client_side(): bool {
		return true;
	}

	/** {@inheritDoc} */
	public function is_ready(): bool {
		return true;
	}

	/** {@inheritDoc} */
	public function voices(): array {
		return array();
	}

	/**
	 * MP3 bytes for the text, or a WP_Error.
	 *
	 * @param string $text  Plain text.
	 * @param string $voice Voice id.
	 * @param float  $speed 0.5 to 2.
	 * @return string|\WP_Error
	 */
	public function synthesize( string $text, string $voice, float $speed ) {
		return new \WP_Error( 'listenly_client_side', __( 'The browser voice plays in the visitor\'s browser.', 'listenly-voice' ) );
	}

	/** {@inheritDoc} */
	public function chunk_limit(): int {
		return 0;
	}
}
