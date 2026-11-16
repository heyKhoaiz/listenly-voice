<?php
/**
 * Settings and audio REST routes.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Rest;

use ListenlyVoice\Hooks;
use ListenlyVoice\Player\Text;
use ListenlyVoice\Plugin;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Support\Setup;
use ListenlyVoice\Tts\GoogleProvider;
use ListenlyVoice\Tts\LiveAudio;
use ListenlyVoice\Tts\Preview;

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

		// Finish, skip or restart the setup wizard.
		register_rest_route(
			self::NAMESPACE,
			'/setup',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'setup' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'done' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		// A short sample in a server-side voice. Also proves the API key works.
		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'provider' => array(
						'type'     => 'string',
						'required' => true,
					),
					'voice'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
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

		// The same chunk as JSON with sentence timings, for read-along highlighting.
		register_rest_route(
			self::NAMESPACE,
			'/audio/(?P<id>\\d+)/(?P<part>\\d+)/timed',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'timed' ),
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
	 * Marks setup done (and returns where "View a post" should go) or resets it.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function setup( \WP_REST_Request $request ): \WP_REST_Response {
		if ( $request->get_param( 'done' ) ) {
			Setup::complete();
		} else {
			Setup::reset();
		}
		return rest_ensure_response(
			array(
				'setupDone' => Setup::is_done(),
				'viewUrl'   => esc_url_raw( Setup::view_url( (array) Settings::get( 'post_types' ) ) ),
			)
		);
	}

	/**
	 * A voice sample as a data URL.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( \WP_REST_Request $request ) {
		$providers = Plugin::instance()->providers->all();
		$id        = sanitize_key( (string) $request->get_param( 'provider' ) );
		if ( ! isset( $providers[ $id ] ) ) {
			return new \WP_Error( 'listenly_no_provider', __( 'Unknown voice provider.', 'listenly-voice' ), array( 'status' => 400 ) );
		}
		$audio = Preview::run( $providers[ $id ], sanitize_text_field( (string) $request->get_param( 'voice' ) ) );
		return is_wp_error( $audio ) ? $audio : rest_ensure_response( array( 'audio' => $audio ) );
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
	 * @return \WP_REST_Response|\WP_Error|void
	 */
	public function audio( \WP_REST_Request $request ) {
		$chunk = $this->chunk( $request );
		if ( $chunk instanceof \WP_REST_Response || is_wp_error( $chunk ) ) {
			return $chunk;
		}
		header( 'Content-Type: audio/mpeg' );
		header( 'X-Listenly-Parts: ' . $chunk['parts'] );
		// Browsers and page caches may keep a chunk briefly; an edited post changes the text, not the URL.
		header( 'Cache-Control: public, max-age=600' );
		echo $chunk['audio']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary MP3.
		exit;
	}

	/**
	 * One chunk as JSON: base64 MP3, when each sentence starts, and the number of parts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function timed( \WP_REST_Request $request ) {
		$chunk = $this->chunk( $request );
		if ( $chunk instanceof \WP_REST_Response || is_wp_error( $chunk ) ) {
			return $chunk;
		}
		$response = rest_ensure_response(
			array(
				'audio' => base64_encode( $chunk['audio'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary MP3 in JSON.
				'marks' => $chunk['marks'],
				'parts' => $chunk['parts'],
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=600' );
		return $response;
	}

	/**
	 * The requested chunk's audio and timings, or the error response to send.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array{audio: string, marks: array, parts: int}|\WP_REST_Response|\WP_Error
	 */
	private function chunk( \WP_REST_Request $request ) {
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
		$result = LiveAudio::get( $provider, $chunks[ $part ], (string) $settings['voice'], (float) $settings['speed'], self::client_ip() );
		if ( is_wp_error( $result ) ) {
			$response = rest_convert_error_to_response( $result );
			$data     = (array) $result->get_error_data();
			if ( isset( $data['retry_after'] ) ) {
				$response->header( 'Retry-After', (string) (int) $data['retry_after'] );
			}
			return $response;
		}
		$result['parts'] = count( $chunks );
		return $result;
	}

	/**
	 * The visitor's address for the rate limit. Only REMOTE_ADDR by default: proxy headers are
	 * easy to fake. Sites behind a trusted proxy can use the `listenly/client_ip` filter.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( Hooks::CLIENT_IP, $ip );
	}
}
