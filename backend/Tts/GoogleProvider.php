<?php
/**
 * Google Cloud Text-to-Speech, with the site owner's own API key.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * Calls texttospeech.googleapis.com. The key is stored in its own option (not autoloaded)
 * and never sent to the browser.
 */
final class GoogleProvider implements TimedProviderInterface {

	const KEY_OPTION = 'listenly_google_key';
	const ENDPOINT   = 'https://texttospeech.googleapis.com/v1/text:synthesize';
	// Sentence timings (SSML mark timepoints) are only offered on v1beta1.
	const BETA_ENDPOINT = 'https://texttospeech.googleapis.com/v1beta1/text:synthesize';
	// Google refuses input over 5,000 bytes, SSML tags included.
	const MAX_INPUT_BYTES = 5000;

	/** {@inheritDoc} */
	public function id(): string {
		return 'google';
	}

	/** {@inheritDoc} */
	public function label(): string {
		return __( 'Google Cloud', 'listenly-voice' );
	}

	/** {@inheritDoc} */
	public function is_client_side(): bool {
		return false;
	}

	/** {@inheritDoc} */
	public function is_ready(): bool {
		return '' !== self::key();
	}

	/** {@inheritDoc} */
	public function voices(): array {
		return array(
			array(
				'id'       => 'en-US-Neural2-F',
				'label'    => 'en-US · Neural2-F',
				'language' => 'en-US',
			),
			array(
				'id'       => 'en-US-Neural2-D',
				'label'    => 'en-US · Neural2-D',
				'language' => 'en-US',
			),
		);
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
		$body = $this->request( self::ENDPOINT, array( 'text' => $text ), $voice, $speed );
		return is_wp_error( $body ) ? $body : $body['audio'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * Puts a numbered SSML mark before each sentence and asks for their timepoints. `<mark>` tags
	 * aren't billed, so this costs the same as plain text.
	 *
	 * @param string[] $sentences Sentences.
	 * @param string   $voice     Voice id.
	 * @param float    $speed     Speaking rate.
	 */
	public function synthesize_timed( array $sentences, string $voice, float $speed ) {
		$ssml = self::ssml( $sentences );
		if ( strlen( $ssml ) > self::MAX_INPUT_BYTES ) {
			return new \WP_Error( 'listenly_ssml_too_long', __( 'This part is too long to time sentence by sentence.', 'listenly-voice' ) );
		}
		$body = $this->request( self::BETA_ENDPOINT, array( 'ssml' => $ssml ), $voice, $speed, array( 'enableTimePointing' => array( 'SSML_MARK' ) ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$marks = array();
		foreach ( (array) ( $body['raw']['timepoints'] ?? array() ) as $point ) {
			$name = (string) ( $point['markName'] ?? '' );
			if ( ctype_digit( $name ) && (int) $name < count( $sentences ) ) {
				$marks[ (int) $name ] = (float) ( $point['timeSeconds'] ?? 0 );
			}
		}
		ksort( $marks );
		return array(
			'audio' => $body['audio'],
			'marks' => $marks,
		);
	}

	/**
	 * SSML with a mark named after its index before each sentence.
	 *
	 * @param string[] $sentences Sentences.
	 */
	public static function ssml( array $sentences ): string {
		$parts = array();
		foreach ( array_values( $sentences ) as $i => $sentence ) {
			$parts[] = '<mark name="' . $i . '"/>' . htmlspecialchars( (string) $sentence, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		}
		return '<speak>' . implode( ' ', $parts ) . '</speak>';
	}

	/**
	 * Sends one synthesis request.
	 *
	 * @param string $endpoint v1 or v1beta1.
	 * @param array  $input    `text` or `ssml`.
	 * @param string $voice    Voice id.
	 * @param float  $speed    Speaking rate.
	 * @param array  $extra    Extra request fields.
	 * @return array{audio: string, raw: array}|\WP_Error
	 */
	private function request( string $endpoint, array $input, string $voice, float $speed, array $extra = array() ) {
		$key = self::key();
		if ( '' === $key ) {
			return new \WP_Error( 'listenly_no_key', __( 'Add a Google Cloud API key first.', 'listenly-voice' ) );
		}
		$language = implode( '-', array_slice( explode( '-', $voice ), 0, 2 ) );
		$response = wp_remote_post(
			add_query_arg( 'key', rawurlencode( $key ), $endpoint ),
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array_merge(
						array(
							'input'       => $input,
							'voice'       => array(
								'languageCode' => $language ? $language : 'en-US',
								'name'         => $voice,
							),
							'audioConfig' => array(
								'audioEncoding' => 'MP3',
								'speakingRate'  => $speed,
							),
						),
						$extra
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $body['audioContent'] ) ) {
			$message = $body['error']['message'] ?? __( 'Google Cloud did not return audio.', 'listenly-voice' );
			return new \WP_Error( 'listenly_google', (string) $message );
		}
		return array(
			'audio' => (string) base64_decode( (string) $body['audioContent'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The API returns base64 audio.
			'raw'   => $body,
		);
	}

	/** {@inheritDoc} */
	public function chunk_limit(): int {
		// Leaves room under Google's 5,000-byte limit for a <mark> before each sentence.
		return 3500;
	}

	/**
	 * The stored key, or the GOOGLE_TTS_API_KEY environment variable in local dev.
	 */
	public static function key(): string {
		$key = (string) get_option( self::KEY_OPTION, '' );
		if ( '' === $key && 'local' === wp_get_environment_type() ) {
			$key = (string) getenv( 'GOOGLE_TTS_API_KEY' );
		}
		return $key;
	}
}
