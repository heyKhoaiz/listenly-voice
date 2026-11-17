<?php
/**
 * Pronunciation: how the voices say particular words.
 *
 * @package PostAloud
 */

namespace PostAloud\Player;

use PostAloud\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Word → spoken form pairs from the settings. The server voices get them in Text::for_post, the
 * browser voice through the player data (applySay in frontend/player/mount.ts).
 */
final class Say {

	/** Pairs kept: a sanity cap, not a paywall (filterable through postaloud/say_limit). */
	const LIMIT = 200;

	/**
	 * How many pairs can be saved.
	 */
	public static function limit(): int {
		return max( 1, (int) apply_filters( Hooks::SAY_LIMIT, self::LIMIT ) );
	}

	/**
	 * Clean pairs from raw input: text only, no duplicates (case-insensitive), at most limit().
	 *
	 * @param mixed $value Raw pairs: [[written, spoken], …].
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function sanitize( $value ): array {
		$out = array();
		foreach ( (array) $value as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$pair    = array_values( $pair );
			$written = trim( sanitize_text_field( (string) ( $pair[0] ?? '' ) ) );
			$spoken  = trim( sanitize_text_field( (string) ( $pair[1] ?? '' ) ) );
			$key     = mb_strtolower( $written );
			if ( '' !== $written && '' !== $spoken && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array( mb_substr( $written, 0, 80 ), mb_substr( $spoken, 0, 160 ) );
			}
		}
		return array_slice( array_values( $out ), 0, self::limit() );
	}

	/**
	 * The saved pairs, longest written form first, so "AWS Lambda" wins over "AWS".
	 *
	 * @param mixed $pairs Saved pairs.
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function ordered( $pairs ): array {
		$out = array();
		foreach ( (array) $pairs as $pair ) {
			if ( is_array( $pair ) && isset( $pair[0], $pair[1] ) && '' !== trim( (string) $pair[0] ) && '' !== trim( (string) $pair[1] ) ) {
				$out[] = array( trim( (string) $pair[0] ), trim( (string) $pair[1] ) );
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return mb_strlen( $b[0] ) <=> mb_strlen( $a[0] );
			}
		);
		return $out;
	}

	/**
	 * The text with each written word replaced by how it's said: whole words, any case, in one
	 * pass (a replacement is never replaced again).
	 *
	 * @param string $text  Plain text.
	 * @param mixed  $pairs Pairs.
	 */
	public static function apply( string $text, $pairs ): string {
		$pairs = self::ordered( $pairs );
		if ( ! $pairs || '' === $text ) {
			return $text;
		}
		$map   = array();
		$parts = array();
		foreach ( $pairs as $pair ) {
			$map[ mb_strtolower( $pair[0] ) ] = $pair[1];
			$parts[]                          = preg_quote( $pair[0], '/' );
		}
		$out = preg_replace_callback(
			'/(?<![\p{L}\p{N}])(?:' . implode( '|', $parts ) . ')(?![\p{L}\p{N}])/iu',
			static function ( $m ) use ( $map ) {
				return $map[ mb_strtolower( $m[0] ) ] ?? $m[0];
			},
			$text
		);
		return is_string( $out ) ? $out : $text;
	}
}
