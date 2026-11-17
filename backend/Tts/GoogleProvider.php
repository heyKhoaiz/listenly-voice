<?php
/**
 * Google Cloud Text-to-Speech, with the site owner's own API key.
 *
 * @package PostAloud
 */

namespace PostAloud\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * Calls texttospeech.googleapis.com. The key is stored in its own option (not autoloaded)
 * and never sent to the browser.
 */
final class GoogleProvider implements TimedProviderInterface {

	const KEY_OPTION = 'postaloud_google_key';
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
		return __( 'Google Cloud', 'postaloud' );
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
			return new \WP_Error( 'postaloud_ssml_too_long', __( 'This part is too long to time sentence by sentence.', 'postaloud' ) );
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
			return new \WP_Error( 'postaloud_no_key', __( 'Add a Google Cloud API key first.', 'postaloud' ) );
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
			return self::network_error( $response, $key );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $body['audioContent'] ) ) {
			return self::explain( (int) wp_remote_retrieve_response_code( $response ), is_array( $body ) ? $body : array(), $key );
		}
		return array(
			'audio' => (string) base64_decode( (string) $body['audioContent'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The API returns base64 audio.
			'raw'   => $body,
		);
	}

	/**
	 * A plain error for a failed Google response: what went wrong and how to fix it.
	 *
	 * The code is stable (the admin app reads it) and `data.fix` links to the Google Cloud page
	 * where the fix is made. Google's own text is kept only for errors not recognized here.
	 *
	 * @param int    $status HTTP status.
	 * @param array  $body   Decoded response body.
	 * @param string $key    The API key, removed from any text that is passed on.
	 */
	public static function explain( int $status, array $body, string $key = '' ): \WP_Error {
		$error   = is_array( $body['error'] ?? null ) ? $body['error'] : array();
		$message = (string) ( $error['message'] ?? '' );
		$reasons = array();
		foreach ( (array) ( $error['details'] ?? array() ) as $detail ) {
			if ( is_array( $detail ) && isset( $detail['reason'] ) ) {
				$reasons[] = (string) $detail['reason'];
			}
		}
		$has = static function ( array $needles ) use ( $reasons, $message ): bool {
			foreach ( $needles as $needle ) {
				if ( in_array( $needle, $reasons, true ) || false !== stripos( $message, $needle ) ) {
					return true;
				}
			}
			return false;
		};

		$console = 'https://console.cloud.google.com/';
		if ( $has( array( 'API_KEY_INVALID', 'API key not valid', 'API_KEY_EXPIRED', 'expired' ) ) ) {
			$code = 'postaloud_google_key_invalid';
			$text = __( 'Google doesn\'t recognize this key. Copy it again from Credentials in Google Cloud (it starts with "AIza") and paste the whole key.', 'postaloud' );
			$fix  = $console . 'apis/credentials';
		} elseif ( $has( array( 'BILLING_DISABLED', 'billing' ) ) ) {
			$code = 'postaloud_google_billing';
			$text = __( 'Billing is off for this Google Cloud project. Turn it on under Billing; Google still gives a free monthly allowance, and you pay only past it.', 'postaloud' );
			$fix  = $console . 'billing';
		} elseif ( $has( array( 'SERVICE_DISABLED', 'has not been used in project', 'is disabled' ) ) ) {
			$code = 'postaloud_google_api_disabled';
			$text = __( 'The Cloud Text-to-Speech API is off for this project. Open it in Google Cloud, press Enable, wait a minute, then test again.', 'postaloud' );
			$fix  = $console . 'apis/library/texttospeech.googleapis.com';
		} elseif ( $has( array( 'API_KEY_HTTP_REFERRER_BLOCKED', 'API_KEY_IP_ADDRESS_BLOCKED', 'API_KEY_SERVICE_BLOCKED', 'referer', 'referrer', 'are blocked' ) ) ) {
			$code = 'postaloud_google_key_restricted';
			$text = __( 'This key is restricted so it can\'t be used here. In Credentials, edit the key: set "Application restrictions" to None and allow the Cloud Text-to-Speech API under "API restrictions".', 'postaloud' );
			$fix  = $console . 'apis/credentials';
		} elseif ( 429 === $status || $has( array( 'RESOURCE_EXHAUSTED', 'RATE_LIMIT_EXCEEDED', 'quota' ) ) ) {
			$code = 'postaloud_google_quota';
			$text = __( 'Google says this project hit its quota or rate limit. Wait a few minutes and test again, or check the quotas for the Text-to-Speech API.', 'postaloud' );
			$fix  = $console . 'apis/api/texttospeech.googleapis.com/quotas';
		} else {
			$code = 'postaloud_google';
			$text = '' !== $message ? $message : __( 'Google Cloud did not return audio.', 'postaloud' );
			$fix  = '';
		}
		return new \WP_Error(
			$code,
			self::without_key( $text, $key ),
			array(
				'fix'    => $fix,
				'google' => self::without_key( $message, $key ),
			)
		);
	}

	/**
	 * A plain error when Google couldn't be reached at all.
	 *
	 * @param \WP_Error $error The HTTP API error.
	 * @param string    $key   The API key, removed from the detail.
	 */
	public static function network_error( \WP_Error $error, string $key = '' ): \WP_Error {
		return new \WP_Error(
			'postaloud_google_network',
			__( 'Your server couldn\'t reach Google Cloud. Try again in a minute; if it keeps failing, ask your host whether outgoing connections to texttospeech.googleapis.com are allowed.', 'postaloud' ),
			array(
				'fix'    => '',
				'google' => self::without_key( $error->get_error_message(), $key ),
			)
		);
	}

	/**
	 * The text with the key taken out, in case an error ever repeats the request URL.
	 *
	 * @param string $text Text.
	 * @param string $key  API key.
	 */
	private static function without_key( string $text, string $key ): string {
		return '' === $key ? $text : str_replace( array( $key, rawurlencode( $key ) ), '[key]', $text );
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
