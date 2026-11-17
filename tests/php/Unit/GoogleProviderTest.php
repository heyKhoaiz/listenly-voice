<?php
/**
 * Tests for Google Cloud voices: plain synthesis and sentence timings from SSML marks.
 *
 * @package PostAloud
 */

namespace PostAloud\Tests\Unit;

use Brain\Monkey\Functions;
use PostAloud\Player\Text;
use PostAloud\Tests\TestCase;
use PostAloud\Tts\GoogleProvider;

/**
 * GoogleProvider.
 */
final class GoogleProviderTest extends TestCase {

	/**
	 * The last request sent: [url, decoded body].
	 *
	 * @var array{0: string, 1: array}|null
	 */
	private $sent = null;

	protected function set_up(): void {
		parent::set_up();
		$this->sent                               = null;
		$this->options[ GoogleProvider::KEY_OPTION ] = 'AIza-test';
		Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $r ) {
				return $r['body'];
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $r ) {
				return $r['code'];
			}
		);
	}

	private function respond( int $code, array $body ): void {
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( $code, $body ) {
				$this->sent = array( $url, json_decode( $args['body'], true ) );
				return array(
					'code' => $code,
					'body' => wp_json_encode( $body ),
				);
			}
		);
	}

	public function test_ssml_puts_a_numbered_mark_before_each_sentence(): void {
		$this->assertSame(
			'<speak><mark name="0"/>One. <mark name="1"/>Two.</speak>',
			GoogleProvider::ssml( array( 'One.', 'Two.' ) )
		);
	}

	public function test_ssml_escapes_markup_in_the_text(): void {
		$ssml = GoogleProvider::ssml( array( 'Salt & pepper <3 "quotes".' ) );
		$this->assertStringContainsString( 'Salt &amp; pepper &lt;3 &quot;quotes&quot;.', $ssml );
	}

	public function test_timed_synthesis_asks_the_beta_api_for_mark_timepoints(): void {
		$this->respond(
			200,
			array(
				'audioContent' => base64_encode( 'MP3' ),
				'timepoints'   => array(
					array(
						'markName'    => '0',
						'timeSeconds' => 0,
					),
					array(
						'markName'    => '1',
						'timeSeconds' => 1.84,
					),
				),
			)
		);

		$result = ( new GoogleProvider() )->synthesize_timed( array( 'One.', 'Two.' ), 'en-US-Neural2-F', 1.25 );

		$this->assertStringStartsWith( 'https://texttospeech.googleapis.com/v1beta1/text:synthesize?key=', $this->sent[0] );
		$this->assertSame( array( 'SSML_MARK' ), $this->sent[1]['enableTimePointing'] );
		$this->assertSame( '<speak><mark name="0"/>One. <mark name="1"/>Two.</speak>', $this->sent[1]['input']['ssml'] );
		$this->assertSame( 'en-US', $this->sent[1]['voice']['languageCode'] );
		$this->assertSame( 1.25, $this->sent[1]['audioConfig']['speakingRate'] );
		$this->assertSame( 'MP3', $result['audio'] );
		$this->assertSame( array( 0 => 0.0, 1 => 1.84 ), $result['marks'] );
	}

	public function test_missing_timepoints_keep_the_ones_returned(): void {
		$this->respond(
			200,
			array(
				'audioContent' => base64_encode( 'MP3' ),
				'timepoints'   => array(
					array(
						'markName'    => '1',
						'timeSeconds' => 2.5,
					),
					array(
						'markName'    => 'junk',
						'timeSeconds' => 9,
					),
				),
			)
		);

		$result = ( new GoogleProvider() )->synthesize_timed( array( 'One.', 'Two.' ), 'en-US-Neural2-F', 1.0 );
		$this->assertSame( array( 1 => 2.5 ), $result['marks'] );
	}

	public function test_no_timepoints_at_all_still_returns_the_audio(): void {
		$this->respond( 200, array( 'audioContent' => base64_encode( 'MP3' ) ) );

		$result = ( new GoogleProvider() )->synthesize_timed( array( 'One.' ), 'en-US-Neural2-F', 1.0 );
		$this->assertSame( 'MP3', $result['audio'] );
		$this->assertSame( array(), $result['marks'] );
	}

	public function test_google_errors_come_back_as_wp_error(): void {
		$this->respond( 403, array( 'error' => array( 'message' => 'API key not valid.' ) ) );

		$result = ( new GoogleProvider() )->synthesize_timed( array( 'One.' ), 'en-US-Neural2-F', 1.0 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'postaloud_google_key_invalid', $result->get_error_code() );
		$this->assertSame( 'API key not valid.', $result->data['google'] );
	}

	/**
	 * Real Google error bodies and the code each one maps to.
	 *
	 * @return array<string, array{0: int, 1: array, 2: string, 3: string}>
	 */
	public function google_errors(): array {
		$info = static function ( string $reason ): array {
			return array(
				array(
					'@type'  => 'type.googleapis.com/google.rpc.ErrorInfo',
					'reason' => $reason,
				),
			);
		};
		return array(
			'invalid key'     => array( 400, array( 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT', 'details' => $info( 'API_KEY_INVALID' ) ), 'postaloud_google_key_invalid', 'apis/credentials' ),
			'api off'         => array( 403, array( 'message' => 'Cloud Text-to-Speech API has not been used in project 123 before or it is disabled.', 'status' => 'PERMISSION_DENIED', 'details' => $info( 'SERVICE_DISABLED' ) ), 'postaloud_google_api_disabled', 'texttospeech.googleapis.com' ),
			'api off, text'   => array( 403, array( 'message' => 'Cloud Text-to-Speech API has not been used in project 123 before or it is disabled.' ), 'postaloud_google_api_disabled', 'texttospeech.googleapis.com' ),
			'billing off'     => array( 403, array( 'message' => 'This API method requires billing to be enabled.', 'details' => $info( 'BILLING_DISABLED' ) ), 'postaloud_google_billing', 'billing' ),
			'website limited' => array( 403, array( 'message' => 'Requests from referer <empty> are blocked.', 'details' => $info( 'API_KEY_HTTP_REFERRER_BLOCKED' ) ), 'postaloud_google_key_restricted', 'apis/credentials' ),
			'api limited'     => array( 403, array( 'message' => 'Requests to this API texttospeech method are blocked.', 'details' => $info( 'API_KEY_SERVICE_BLOCKED' ) ), 'postaloud_google_key_restricted', 'apis/credentials' ),
			'quota'           => array( 429, array( 'message' => 'Quota exceeded for quota metric.', 'status' => 'RESOURCE_EXHAUSTED' ), 'postaloud_google_quota', 'quotas' ),
		);
	}

	/**
	 * @dataProvider google_errors
	 */
	public function test_each_google_error_gets_a_code_and_a_link_to_the_fix( int $status, array $error, string $code, string $fix ): void {
		$result = GoogleProvider::explain( $status, array( 'error' => $error ) );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertStringContainsString( $fix, $result->data['fix'] );
		$this->assertNotSame( $error['message'], $result->get_error_message(), 'The message says what to do, not just what Google said.' );
	}

	public function test_an_unknown_google_error_keeps_googles_words(): void {
		$result = GoogleProvider::explain( 400, array( 'error' => array( 'message' => 'Voice name xx-XX is not supported.' ) ) );
		$this->assertSame( 'postaloud_google', $result->get_error_code() );
		$this->assertSame( 'Voice name xx-XX is not supported.', $result->get_error_message() );
		$this->assertSame( '', $result->data['fix'] );
	}

	public function test_the_key_never_appears_in_an_error(): void {
		$result = GoogleProvider::explain( 400, array( 'error' => array( 'message' => 'Bad request for key AIza-secret.' ) ), 'AIza-secret' );
		$this->assertStringNotContainsString( 'AIza-secret', $result->get_error_message() );
		$this->assertStringNotContainsString( 'AIza-secret', $result->data['google'] );
	}

	public function test_a_network_failure_says_the_server_could_not_reach_google(): void {
		Functions\when( 'wp_remote_post' )->justReturn( new \WP_Error( 'http_request_failed', 'cURL error 28: timed out for ?key=AIza-test' ) );

		$result = ( new GoogleProvider() )->synthesize( 'One.', 'en-US-Neural2-F', 1.0 );
		$this->assertSame( 'postaloud_google_network', $result->get_error_code() );
		$this->assertStringNotContainsString( 'AIza-test', $result->data['google'] );
	}

	public function test_ssml_over_googles_5000_byte_limit_is_refused_without_a_request(): void {
		Functions\expect( 'wp_remote_post' )->never();
		$sentences = array_fill( 0, 400, 'Tiny bit.' );

		$result = ( new GoogleProvider() )->synthesize_timed( $sentences, 'en-US-Neural2-F', 1.0 );
		$this->assertSame( 'postaloud_ssml_too_long', $result->get_error_code() );
	}

	public function test_a_full_chunk_of_ordinary_sentences_fits_with_its_marks(): void {
		$sentence = 'Containers dry out fast in summer, so water them in the morning until it runs from the base.';
		$text     = trim( str_repeat( $sentence . ' ', 60 ) );
		$chunk    = Text::chunks( $text, ( new GoogleProvider() )->chunk_limit() )[0];

		$this->assertLessThanOrEqual( 5000, strlen( GoogleProvider::ssml( Text::sentences( $chunk ) ) ) );
	}

	public function test_without_a_key_nothing_is_sent(): void {
		unset( $this->options[ GoogleProvider::KEY_OPTION ] );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\expect( 'wp_remote_post' )->never();

		$result = ( new GoogleProvider() )->synthesize_timed( array( 'One.' ), 'en-US-Neural2-F', 1.0 );
		$this->assertSame( 'postaloud_no_key', $result->get_error_code() );
	}
}
