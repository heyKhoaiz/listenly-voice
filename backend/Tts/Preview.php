<?php
/**
 * A short voice sample, used by the setup wizard to let admins hear a voice and test a key.
 *
 * @package PostAloud
 */

namespace PostAloud\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * Synthesizes one short sentence with a server-side provider.
 */
final class Preview {

	/**
	 * The sentence read for a sample. Short, so a sample costs a fraction of a cent.
	 */
	public static function sample_text(): string {
		return __( 'Hello. This is how your posts will sound when visitors press play.', 'postaloud' );
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
			return new \WP_Error( 'postaloud_client_side', __( 'This voice plays in the browser.', 'postaloud' ), array( 'status' => 400 ) );
		}
		$audio = $provider->synthesize( self::sample_text(), $voice, 1.0 );
		if ( is_wp_error( $audio ) ) {
			// Keep the provider's data (for example the link to the fix); only add the HTTP status.
			$data = $audio->get_error_data();
			$audio->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => 502 ) ) );
			return $audio;
		}
		if ( '' === (string) $audio ) {
			return new \WP_Error( 'postaloud_no_audio', __( 'The voice service returned no audio.', 'postaloud' ), array( 'status' => 502 ) );
		}
		return 'data:audio/mpeg;base64,' . base64_encode( (string) $audio ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A data URL for the admin's audio element.
	}
}
