<?php
/**
 * Short cache and rate limit in front of live server-side audio.
 *
 * @package PostAloud
 */

namespace PostAloud\Tts;

use PostAloud\Hooks;
use PostAloud\Player\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Free plays server-side voices live, one chunk per request, paid for with the site owner's
 * key. A short cache means a popular post isn't synthesized again for every listener, and a
 * rate limit on cache misses stops a bot from running up the owner's bill.
 */
final class LiveAudio {

	const CACHE_PREFIX = 'postaloud_a_';
	const LIMIT_PREFIX = 'postaloud_rl_';

	/**
	 * One chunk of audio, from the cache or the provider, with when each sentence starts if the
	 * provider can tell (see TimedProviderInterface).
	 *
	 * @param ProviderInterface $provider Provider.
	 * @param string            $text     Chunk text.
	 * @param string            $voice    Voice ID.
	 * @param float             $speed    Speaking rate.
	 * @param string            $ip       Visitor address, used only as a hashed counter key.
	 * @param int|null          $now      Current time, for tests.
	 * @return array{audio: string, marks: array<int, array{t: float, text: string}>}|\WP_Error
	 *         An error has a `status` (429 or 502).
	 */
	public static function get( ProviderInterface $provider, string $text, string $voice, float $speed, string $ip, ?int $now = null ) {
		$now    = $now ?? time();
		$key    = self::cache_key( $provider->id(), $voice, $speed, $text );
		$cached = self::from_cache( get_transient( $key ) );
		if ( $cached ) {
			return $cached;
		}

		$wait = self::take( $ip, $now );
		if ( $wait > 0 ) {
			return new \WP_Error(
				'postaloud_busy',
				__( 'Too many people are listening right now. Try again in a few minutes.', 'postaloud' ),
				array(
					'status'      => 429,
					'retry_after' => $wait,
				)
			);
		}

		$result = self::synthesize( $provider, $text, $voice, $speed );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 502 ) );
			return $result;
		}
		if ( '' === $result['audio'] ) {
			return new \WP_Error( 'postaloud_empty_audio', __( 'The voice provider returned no audio.', 'postaloud' ), array( 'status' => 502 ) );
		}

		$ttl = self::ttl();
		if ( $ttl > 0 ) {
			// Base64: transients live in a text column, which can mangle raw bytes.
			set_transient(
				$key,
				array(
					'a' => base64_encode( $result['audio'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary MP3 stored as text.
					'm' => $result['marks'],
				),
				$ttl
			);
		}
		return $result;
	}

	/**
	 * Asks the provider for audio. A timed provider gets the chunk's sentences; if that fails
	 * (too long for marks, or the timing API is down) the chunk is made again without timings.
	 *
	 * @param ProviderInterface $provider Provider.
	 * @param string            $text     Chunk text.
	 * @param string            $voice    Voice ID.
	 * @param float             $speed    Speaking rate.
	 * @return array{audio: string, marks: array<int, array{t: float, text: string}>}|\WP_Error
	 */
	private static function synthesize( ProviderInterface $provider, string $text, string $voice, float $speed ) {
		if ( $provider instanceof TimedProviderInterface ) {
			$sentences = Text::sentences( $text );
			$timed     = $provider->synthesize_timed( $sentences, $voice, $speed );
			if ( ! is_wp_error( $timed ) ) {
				$marks = array();
				foreach ( (array) $timed['marks'] as $i => $seconds ) {
					if ( isset( $sentences[ $i ] ) ) {
						$marks[] = array(
							't'    => (float) $seconds,
							'text' => $sentences[ $i ],
						);
					}
				}
				return array(
					'audio' => (string) $timed['audio'],
					'marks' => $marks,
				);
			}
		}
		$audio = $provider->synthesize( $text, $voice, $speed );
		return is_wp_error( $audio ) ? $audio : array(
			'audio' => (string) $audio,
			'marks' => array(),
		);
	}

	/**
	 * A cached chunk, or null when missing or in an older format.
	 *
	 * @param mixed $cached Transient value.
	 * @return array{audio: string, marks: array}|null
	 */
	private static function from_cache( $cached ): ?array {
		if ( ! is_array( $cached ) || ! isset( $cached['a'] ) || ! is_string( $cached['a'] ) ) {
			return null;
		}
		$audio = base64_decode( $cached['a'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary MP3 stored as text.
		if ( false === $audio || '' === $audio ) {
			return null;
		}
		return array(
			'audio' => $audio,
			'marks' => is_array( $cached['m'] ?? null ) ? $cached['m'] : array(),
		);
	}

	/**
	 * Cache key for one chunk. Editing the post or changing voice or speed gives a new key.
	 *
	 * @param string $provider Provider ID.
	 * @param string $voice    Voice ID.
	 * @param float  $speed    Speaking rate.
	 * @param string $text     Chunk text.
	 */
	public static function cache_key( string $provider, string $voice, float $speed, string $text ): string {
		return self::CACHE_PREFIX . md5( $provider . '|' . $voice . '|' . $speed . '|' . $text );
	}

	/**
	 * Seconds a chunk stays cached. 0 turns the cache off.
	 */
	public static function ttl(): int {
		return max( 0, (int) apply_filters( Hooks::LIVE_CACHE_TTL, 3600 ) );
	}

	/**
	 * New chunks allowed per visitor and per site, each in a fixed time window (seconds).
	 *
	 * @return array{visitor: array{max: int, window: int}, site: array{max: int, window: int}}
	 */
	public static function limits(): array {
		$defaults = array(
			'visitor' => array(
				'max'    => 30,
				'window' => 600,
			),
			'site'    => array(
				'max'    => 300,
				'window' => 3600,
			),
		);
		$limits   = (array) apply_filters( Hooks::LIVE_RATE_LIMITS, $defaults );
		foreach ( $defaults as $scope => $default ) {
			$limits[ $scope ] = array(
				'max'    => max( 1, (int) ( $limits[ $scope ]['max'] ?? $default['max'] ) ),
				'window' => max( 1, (int) ( $limits[ $scope ]['window'] ?? $default['window'] ) ),
			);
		}
		return $limits;
	}

	/**
	 * Counts one new chunk against the visitor and site limits.
	 *
	 * @param string $ip  Visitor address.
	 * @param int    $now Current time.
	 * @return int 0 when allowed, else seconds until the fullest window resets.
	 */
	private static function take( string $ip, int $now ): int {
		$limits   = self::limits();
		$counters = array(
			self::LIMIT_PREFIX . 'v_' . md5( $ip ) => $limits['visitor'],
			self::LIMIT_PREFIX . 'site'            => $limits['site'],
		);

		$state = array();
		$wait  = 0;
		foreach ( $counters as $name => $limit ) {
			$saved = get_transient( $name );
			$entry = is_array( $saved ) && isset( $saved['count'], $saved['start'] ) && $now - (int) $saved['start'] < $limit['window']
				? array(
					'count' => (int) $saved['count'],
					'start' => (int) $saved['start'],
				)
				: array(
					'count' => 0,
					'start' => $now,
				);
			if ( $entry['count'] >= $limit['max'] ) {
				$wait = max( $wait, $entry['start'] + $limit['window'] - $now );
			}
			$state[ $name ] = $entry;
		}
		if ( $wait > 0 ) {
			return $wait;
		}

		foreach ( $state as $name => $entry ) {
			++$entry['count'];
			set_transient( $name, $entry, $counters[ $name ]['window'] );
		}
		return 0;
	}
}
