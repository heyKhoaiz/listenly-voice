<?php
/**
 * The visitor's browser voice (Web Speech API).
 *
 * @package PostAloud
 */

namespace PostAloud\Tts;

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
		return __( 'Browser voice', 'postaloud' );
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
		return new \WP_Error( 'postaloud_client_side', __( 'The browser voice plays in the visitor\'s browser.', 'postaloud' ) );
	}

	/** {@inheritDoc} */
	public function chunk_limit(): int {
		return 0;
	}
}
