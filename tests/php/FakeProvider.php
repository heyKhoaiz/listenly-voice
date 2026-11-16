<?php
/**
 * Test double for voice providers.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests;

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
 * A provider that also returns sentence timings, like Google with SSML marks.
 */
final class FakeTimedProvider implements \ListenlyVoice\Tts\TimedProviderInterface {
	/** @var array<int, array<mixed>> */
	public $calls = array();
	/** @var array<int, array<mixed>> */
	public $timed_calls = array();
	/** @var array|\WP_Error */
	private $timed;
	/** @var string|\WP_Error */
	private $plain;

	public function __construct( $timed, $plain = 'PLAIN-MP3' ) {
		$this->timed = $timed;
		$this->plain = $plain;
	}
	public function id(): string {
		return 'faketimed';
	}
	public function label(): string {
		return 'Fake timed';
	}
	public function is_client_side(): bool {
		return false;
	}
	public function is_ready(): bool {
		return true;
	}
	public function voices(): array {
		return array();
	}
	public function synthesize( string $text, string $voice, float $speed ) {
		$this->calls[] = array( $text, $voice, $speed );
		return $this->plain;
	}
	public function synthesize_timed( array $sentences, string $voice, float $speed ) {
		$this->timed_calls[] = array( $sentences, $voice, $speed );
		return $this->timed;
	}
	public function chunk_limit(): int {
		return 100;
	}
}
