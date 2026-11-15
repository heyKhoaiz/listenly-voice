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
final class GoogleProvider implements ProviderInterface {

	const KEY_OPTION = 'listenly_google_key';
	const ENDPOINT   = 'https://texttospeech.googleapis.com/v1/text:synthesize';

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
		$key = self::key();
		if ( '' === $key ) {
			return new \WP_Error( 'listenly_no_key', __( 'Add a Google Cloud API key first.', 'listenly-voice' ) );
		}
		$language = implode( '-', array_slice( explode( '-', $voice ), 0, 2 ) );
		$response = wp_remote_post(
			add_query_arg( 'key', rawurlencode( $key ), self::ENDPOINT ),
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'input'       => array( 'text' => $text ),
						'voice'       => array(
							'languageCode' => $language ? $language : 'en-US',
							'name'         => $voice,
						),
						'audioConfig' => array(
							'audioEncoding' => 'MP3',
							'speakingRate'  => $speed,
						),
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
		return (string) base64_decode( (string) $body['audioContent'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The API returns base64 audio.
	}

	/** {@inheritDoc} */
	public function chunk_limit(): int {
		return 4500;
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
