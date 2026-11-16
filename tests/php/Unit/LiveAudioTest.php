<?php
/**
 * Tests for the short cache and rate limit in front of live server-side audio.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ListenlyVoice\Hooks;
use ListenlyVoice\Tests\FakeProvider;
use ListenlyVoice\Tests\FakeTimedProvider;
use ListenlyVoice\Tests\TestCase;
use ListenlyVoice\Tts\LiveAudio;

/**
 * LiveAudio.
 */
final class LiveAudioTest extends TestCase {

	const NOW = 1700000000;

	/**
	 * In-memory transients: name => [value, ttl].
	 *
	 * @var array<string, array{0: mixed, 1: int}>
	 */
	private $transients = array();

	protected function set_up(): void {
		parent::set_up();
		$this->transients = array();
		Functions\when( 'get_transient' )->alias(
			function ( $name ) {
				return isset( $this->transients[ $name ] ) ? $this->transients[ $name ][0] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $name, $value, $ttl = 0 ) {
				$this->transients[ $name ] = array( $value, (int) $ttl );
				return true;
			}
		);
	}

	private function get( \ListenlyVoice\Tts\ProviderInterface $provider, string $text = 'Hello there.', string $ip = '203.0.113.5', int $now = self::NOW ) {
		return LiveAudio::get( $provider, $text, 'en-US-Neural2-F', 1.0, $ip, $now );
	}

	/** The audio bytes of a successful result. */
	private function audio( ...$args ) {
		$result = $this->get( ...$args );
		return is_array( $result ) ? $result['audio'] : $result;
	}

	public function test_returns_the_audio_and_caches_it_for_an_hour(): void {
		$provider = new FakeProvider( 'MP3-BYTES' );

		$this->assertSame( 'MP3-BYTES', $this->audio( $provider ) );
		$key = LiveAudio::cache_key( 'fake', 'en-US-Neural2-F', 1.0, 'Hello there.' );
		$this->assertSame( 3600, $this->transients[ $key ][1] );
	}

	public function test_a_cache_hit_does_not_call_the_provider_again(): void {
		$provider = new FakeProvider( 'MP3-BYTES' );
		$this->get( $provider );

		$this->assertSame( 'MP3-BYTES', $this->audio( $provider ) );
		$this->assertCount( 1, $provider->calls );
	}

	public function test_binary_audio_survives_the_cache(): void {
		$bytes    = "ID3\x00\xff\xfe\x01binary";
		$provider = new FakeProvider( $bytes );
		$this->get( $provider );

		$this->assertSame( $bytes, $this->audio( $provider ) );
	}

	public function test_edited_text_or_another_voice_or_speed_misses_the_cache(): void {
		$base = LiveAudio::cache_key( 'google', 'en-US-Neural2-F', 1.0, 'Hello.' );

		$this->assertNotSame( $base, LiveAudio::cache_key( 'google', 'en-US-Neural2-F', 1.0, 'Hello!' ) );
		$this->assertNotSame( $base, LiveAudio::cache_key( 'google', 'en-US-Neural2-D', 1.0, 'Hello.' ) );
		$this->assertNotSame( $base, LiveAudio::cache_key( 'google', 'en-US-Neural2-F', 1.25, 'Hello.' ) );
		$this->assertNotSame( $base, LiveAudio::cache_key( 'openai', 'en-US-Neural2-F', 1.0, 'Hello.' ) );
		$this->assertLessThanOrEqual( 172, strlen( $base ), 'Transient names longer than 172 characters fail.' );
	}

	public function test_provider_errors_get_status_502_and_are_not_cached(): void {
		$provider = new FakeProvider( new \WP_Error( 'listenly_google', 'Quota exceeded' ) );

		$result = $this->get( $provider );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 502, $result->data['status'] );
		$this->assertSame( array(), array_filter( array_keys( $this->transients ), static function ( $k ) {
			return 0 === strpos( $k, LiveAudio::CACHE_PREFIX );
		} ) );
	}

	public function test_empty_audio_is_an_error_and_is_not_cached(): void {
		$result = $this->get( new FakeProvider( '' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 502, $result->data['status'] );
	}

	public function test_a_ttl_of_zero_turns_the_cache_off(): void {
		Filters\expectApplied( Hooks::LIVE_CACHE_TTL )->andReturn( 0 );
		$provider = new FakeProvider( 'MP3' );
		$this->get( $provider );
		$this->get( $provider );

		$this->assertCount( 2, $provider->calls );
	}

	public function test_one_visitor_is_limited_after_30_new_chunks_in_10_minutes(): void {
		$provider = new FakeProvider( 'MP3' );
		for ( $i = 0; $i < 30; $i++ ) {
			$this->assertSame( 'MP3', $this->audio( $provider, "Chunk $i." ) );
		}

		$result = $this->get( $provider, 'One more.', '203.0.113.5', self::NOW + 60 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'listenly_busy', $result->get_error_code() );
		$this->assertSame( 429, $result->data['status'] );
		$this->assertSame( 540, $result->data['retry_after'] );
		$this->assertCount( 30, $provider->calls );
	}

	public function test_cached_chunks_do_not_count_towards_the_limit(): void {
		$provider = new FakeProvider( 'MP3' );
		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertSame( 'MP3', $this->audio( $provider, 'Same chunk.' ) );
		}
		$this->assertCount( 1, $provider->calls );
	}

	public function test_another_visitor_is_not_blocked_by_the_first(): void {
		$provider = new FakeProvider( 'MP3' );
		for ( $i = 0; $i < 31; $i++ ) {
			$this->get( $provider, "Chunk $i." );
		}

		$this->assertSame( 'MP3', $this->audio( $provider, 'Fresh.', '198.51.100.9' ) );
	}

	public function test_the_visitor_limit_resets_after_the_window(): void {
		$provider = new FakeProvider( 'MP3' );
		for ( $i = 0; $i < 31; $i++ ) {
			$this->get( $provider, "Chunk $i." );
		}

		$this->assertSame( 'MP3', $this->audio( $provider, 'Later.', '203.0.113.5', self::NOW + 601 ) );
	}

	public function test_the_whole_site_is_limited_after_300_new_chunks_an_hour(): void {
		$provider = new FakeProvider( 'MP3' );
		for ( $i = 0; $i < 300; $i++ ) {
			$this->get( $provider, "Chunk $i.", '10.0.' . intdiv( $i, 20 ) . '.1' );
		}

		$result = $this->get( $provider, 'Next.', '192.0.2.200' );
		$this->assertSame( 429, $result->data['status'] );
		$this->assertSame( 3600, $result->data['retry_after'] );
	}

	public function test_limits_can_be_changed_with_a_filter(): void {
		Filters\expectApplied( Hooks::LIVE_RATE_LIMITS )->andReturn(
			array(
				'visitor' => array( 'max' => 2, 'window' => 60 ),
				'site'    => array( 'max' => 1000, 'window' => 3600 ),
			)
		);
		$provider = new FakeProvider( 'MP3' );
		$this->get( $provider, 'A.' );
		$this->get( $provider, 'B.' );

		$this->assertSame( 429, $this->get( $provider, 'C.' )->data['status'] );
	}

	public function test_visitor_addresses_are_not_stored_in_plain_text(): void {
		$this->get( new FakeProvider( 'MP3' ) );

		foreach ( array_keys( $this->transients ) as $name ) {
			$this->assertStringNotContainsString( '203.0.113.5', $name );
		}
	}

	public function test_a_plain_provider_has_no_marks(): void {
		$this->assertSame( array(), $this->get( new FakeProvider( 'MP3' ) )['marks'] );
	}

	public function test_a_timed_provider_gets_the_chunk_as_sentences_and_returns_their_timings(): void {
		$provider = new FakeTimedProvider(
			array(
				'audio' => 'TIMED-MP3',
				'marks' => array(
					0 => 0.0,
					2 => 3.5,
				),
			)
		);

		$result = $this->get( $provider, 'One. Two! Three?' );

		$this->assertSame( array( array( 'One.', 'Two!', 'Three?' ), 'en-US-Neural2-F', 1.0 ), $provider->timed_calls[0] );
		$this->assertSame( 'TIMED-MP3', $result['audio'] );
		$this->assertSame(
			array(
				array( 't' => 0.0, 'text' => 'One.' ),
				array( 't' => 3.5, 'text' => 'Three?' ),
			),
			$result['marks']
		);
		$this->assertSame( array(), $provider->calls );
	}

	public function test_timings_are_cached_with_the_audio(): void {
		$provider = new FakeTimedProvider( array( 'audio' => 'TIMED-MP3', 'marks' => array( 0 => 0.0 ) ) );
		$this->get( $provider, 'One.' );

		$again = $this->get( $provider, 'One.' );
		$this->assertCount( 1, $provider->timed_calls );
		$this->assertSame( array( array( 't' => 0.0, 'text' => 'One.' ) ), $again['marks'] );
	}

	public function test_when_timed_synthesis_fails_it_falls_back_to_plain_audio_without_marks(): void {
		$provider = new FakeTimedProvider( new \WP_Error( 'listenly_ssml_too_long', 'Too long' ), 'PLAIN-MP3' );

		$result = $this->get( $provider, 'One.' );
		$this->assertSame( 'PLAIN-MP3', $result['audio'] );
		$this->assertSame( array(), $result['marks'] );
		$this->assertCount( 1, $provider->calls );
	}

	public function test_the_fallback_counts_once_against_the_rate_limit(): void {
		Filters\expectApplied( Hooks::LIVE_RATE_LIMITS )->andReturn(
			array(
				'visitor' => array( 'max' => 1, 'window' => 60 ),
				'site'    => array( 'max' => 1000, 'window' => 3600 ),
			)
		);
		$provider = new FakeTimedProvider( new \WP_Error( 'listenly_google', 'Beta down' ) );

		$this->assertSame( 'PLAIN-MP3', $this->audio( $provider, 'One.' ) );
		$this->assertSame( 429, $this->get( $provider, 'Two.' )->data['status'] );
	}

	public function test_an_old_cache_entry_in_another_format_is_a_miss(): void {
		$key                      = LiveAudio::cache_key( 'fake', 'en-US-Neural2-F', 1.0, 'Hello there.' );
		$this->transients[ $key ] = array( base64_encode( 'OLD' ), 3600 );
		$provider                 = new FakeProvider( 'NEW' );

		$this->assertSame( 'NEW', $this->audio( $provider ) );
	}
}
