<?php
/**
 * A short voice sample, used by the setup wizard to let admins hear a voice and test a key.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * Synthesizes one short sentence with a server-side provider.
 */
final class Preview {

	/**
	 * The sentence read for a sample. Short, so a sample costs a fraction of a cent.
	 */
	public static function sample_text(): string {
		return __( 'Hello. This is how your posts will sound when visitors press play.', 'listenly-voice' );
	}

	/**
	 * The sample as a data URL, or an error with an HTTP status.
	 *
	 * @param ProviderInterface $provider Provider.
	 * @param string            $voice    Voice ID.
	 * @return string|\WP_Error
	 */
	public static function run( ProviderInterface $provider, string $voice ) {
		if ( $provider->is_client_side() ) {
			return new \WP_Error( 'listenly_client_side', __( 'This voice plays in the browser.', 'listenly-voice' ), array( 'status' => 400 ) );
		}
		$audio = $provider->synthesize( self::sample_text(), $voice, 1.0 );
		if ( is_wp_error( $audio ) ) {
			$audio->add_data( array( 'status' => 502 ) );
			return $audio;
		}
		if ( '' === (string) $audio ) {
			return new \WP_Error( 'listenly_no_audio', __( 'The voice service returned no audio.', 'listenly-voice' ), array( 'status' => 502 ) );
		}
		return 'data:audio/mpeg;base64,' . base64_encode( (string) $audio ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A data URL for the admin's audio element.
	}
}
