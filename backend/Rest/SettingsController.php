<?php
/**
 * Settings and audio REST routes.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Rest;

use ListenlyVoice\Player\Text;
use ListenlyVoice\Plugin;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tts\GoogleProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /listenly/v1.
 */
final class SettingsController {

	const NAMESPACE = 'listenly/v1';

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static function () {
						return rest_ensure_response( Settings::all() );
					},
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => static function ( \WP_REST_Request $request ) {
						return rest_ensure_response( Settings::save( (array) $request->get_json_params() ) );
					},
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/google-key',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( \WP_REST_Request $request ) {
					$key = sanitize_text_field( (string) $request->get_param( 'key' ) );
					update_option( GoogleProvider::KEY_OPTION, $key, false );
					return rest_ensure_response( array( 'connected' => '' !== $key ) );
				},
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// How many audio parts a post has. Cheap: no synthesis.
		register_rest_route(
			self::NAMESPACE,
			'/audio/(?P<id>\\d+)/info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'info' ),
				'permission_callback' => array( $this, 'is_public_post' ),
			)
		);

		// One chunk of a post's audio, for server-side voices. Free plays it live; Pro caches MP3s.
		register_rest_route(
			self::NAMESPACE,
			'/audio/(?P<id>\d+)/(?P<part>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'audio' ),
				'permission_callback' => array( $this, 'is_public_post' ),
			)
		);
	}

	/**
	 * Only admins change settings.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Audio is only served for published posts without a password.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function is_public_post( \WP_REST_Request $request ): bool {
		$post = get_post( (int) $request['id'] );
		return $post && 'publish' === $post->post_status && empty( $post->post_password );
	}

	/**
	 * The number of parts for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function info( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = Plugin::instance()->providers->get( (string) Settings::get( 'provider' ) );
		$chunks   = Text::chunks( Text::for_post( (int) $request['id'] ), $provider->chunk_limit() );
		return rest_ensure_response( array( 'parts' => count( $chunks ) ) );
	}

	/**
	 * Streams one MP3 chunk.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_Error|void
	 */
	public function audio( \WP_REST_Request $request ) {
		$settings = Settings::all();
		$provider = Plugin::instance()->providers->get( (string) $settings['provider'] );
		if ( $provider->is_client_side() ) {
			return new \WP_Error( 'listenly_client_side', __( 'This voice plays in the browser.', 'listenly-voice' ), array( 'status' => 400 ) );
		}
		$chunks = Text::chunks( Text::for_post( (int) $request['id'] ), $provider->chunk_limit() );
		$part   = (int) $request['part'];
		if ( ! isset( $chunks[ $part ] ) ) {
			return new \WP_Error( 'listenly_no_part', __( 'No such part.', 'listenly-voice' ), array( 'status' => 404 ) );
		}
		$audio = $provider->synthesize( $chunks[ $part ], (string) $settings['voice'], (float) $settings['speed'] );
		if ( is_wp_error( $audio ) ) {
			$audio->add_data( array( 'status' => 502 ) );
			return $audio;
		}
		header( 'Content-Type: audio/mpeg' );
		header( 'X-Listenly-Parts: ' . count( $chunks ) );
		echo $audio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary MP3.
		exit;
	}
}
