<?php
/**
 * Tests for the voice sample used by the wizard and the Voices screen.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use ListenlyVoice\Tests\TestCase;
use ListenlyVoice\Tts\Preview;
use ListenlyVoice\Tts\ProviderInterface;

/**
 * A provider that records what it was asked to read.
 */
final class FakeProvider implements ProviderInterface {
	/** @var array<int, array<mixed>> */
	public $calls = array();
	/** @var string|\WP_Error */
	private $result;
	/** @var bool */
	private $client_side;

	public function __construct( $result, bool $client_side = false ) {
		$this->result      = $result;
		$this->client_side = $client_side;
	}
	public function id(): string {
		return 'fake';
	}
	public function label(): string {
		return 'Fake';
	}
	public function is_client_side(): bool {
		return $this->client_side;
	}
	public function is_ready(): bool {
		return true;
	}
	public function voices(): array {
		return array();
	}
	public function synthesize( string $text, string $voice, float $speed ) {
		$this->calls[] = array( $text, $voice, $speed );
		return $this->result;
	}
	public function chunk_limit(): int {
		return 100;
	}
}

/**
 * Preview.
 */
final class PreviewTest extends TestCase {

	public function test_returns_the_sample_as_an_mp3_data_url(): void {
		$result = Preview::run( new FakeProvider( 'ID3-bytes' ), 'en-US-Neural2-F' );
		$this->assertSame( 'data:audio/mpeg;base64,' . base64_encode( 'ID3-bytes' ), $result );
	}

	public function test_reads_the_short_sample_sentence_at_normal_speed(): void {
		$provider = new FakeProvider( 'x' );
		Preview::run( $provider, 'en-US-Neural2-D' );
		$this->assertSame( array( array( Preview::sample_text(), 'en-US-Neural2-D', 1.0 ) ), $provider->calls );
		$this->assertLessThan( 200, strlen( Preview::sample_text() ) );
	}

	public function test_passes_a_provider_error_through_with_a_502(): void {
		$error  = new \WP_Error( 'listenly_google', 'API key not valid.' );
		$result = Preview::run( new FakeProvider( $error ), 'v' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'API key not valid.', $result->get_error_message() );
		$this->assertSame( 502, $result->data['status'] );
	}

	public function test_refuses_a_browser_voice(): void {
		$provider = new FakeProvider( 'x', true );
		$result   = Preview::run( $provider, '' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'listenly_client_side', $result->get_error_code() );
		$this->assertSame( 400, $result->data['status'] );
		$this->assertSame( array(), $provider->calls );
	}

	public function test_treats_empty_audio_as_an_error(): void {
		$result = Preview::run( new FakeProvider( '' ), 'v' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 502, $result->data['status'] );
	}
}
