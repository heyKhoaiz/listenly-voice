<?php
/**
 * Tests for Google Cloud voices: plain synthesis and sentence timings from SSML marks.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Functions;
use ListenlyVoice\Player\Text;
use ListenlyVoice\Tests\TestCase;
use ListenlyVoice\Tts\GoogleProvider;

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
		$this->assertSame( 'API key not valid.', $result->get_error_message() );
	}

	public function test_ssml_over_googles_5000_byte_limit_is_refused_without_a_request(): void {
		Functions\expect( 'wp_remote_post' )->never();
		$sentences = array_fill( 0, 400, 'Tiny bit.' );

		$result = ( new GoogleProvider() )->synthesize_timed( $sentences, 'en-US-Neural2-F', 1.0 );
		$this->assertSame( 'listenly_ssml_too_long', $result->get_error_code() );
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
		$this->assertSame( 'listenly_no_key', $result->get_error_code() );
	}
}
