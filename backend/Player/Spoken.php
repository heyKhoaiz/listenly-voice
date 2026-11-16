<?php
/**
 * Written forms that voices misread, rewritten the way people say them.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Player;

defined( 'ABSPATH' ) || exit;

/**
 * English money and number shorthand: "$12.7bn" → "12.7 billion dollars", "3–5" → "3 to 5".
 * The browser voice gets the same rules from frontend/player/spoken.ts; keep the two in step.
 */
final class Spoken {

	const CURRENCIES = array(
		'$' => 'dollars',
		'£' => 'pounds',
		'€' => 'euros',
		'¥' => 'yen',
		'₹' => 'rupees',
	);

	const MAGNITUDES = array(
		'k'        => 'thousand',
		'thousand' => 'thousand',
		'm'        => 'million',
		'mn'       => 'million',
		'million'  => 'million',
		'b'        => 'billion',
		'bn'       => 'billion',
		'billion'  => 'billion',
		't'        => 'trillion',
		'tn'       => 'trillion',
		'trillion' => 'trillion',
	);

	/**
	 * Whether the rules apply to a language: English only for now.
	 *
	 * @param string $lang Language tag, like en-US.
	 */
	public static function applies( string $lang ): bool {
		return 0 === strpos( strtolower( $lang ), 'en' );
	}

	/**
	 * The text with money and number shorthand spelled out.
	 *
	 * @param string $text Plain text.
	 */
	public static function numbers( string $text ): string {
		if ( '' === $text || ! preg_match( '/\d/', $text ) ) {
			return $text;
		}
		$num = '\d[\d,]*(?:\.\d+)?';

		// Ranges: "3–5", "10 — 20", and year ranges with a hyphen ("2020-2021", "2020-21"), not dates like 2026-11-16.
		$text = (string) preg_replace( '/(?<![\d.,])(' . $num . ')\s?[–—]\s?(?=[$£€¥₹]?\d)/u', '$1 to ', $text );
		$text = (string) preg_replace( '/(?<![\d-])((?:1[89]|20)\d{2})-(\d{2}|(?:1[89]|20)\d{2})(?![\d-])/', '$1 to $2', $text );

		// Money with a size: "$12.7bn", "€3 million", "£450k".
		$text = (string) preg_replace_callback(
			'/([$£€¥₹])\s?(' . $num . ')(?:([kKmMbBtT]|mn|bn|tn)\b|\s([Tt]housand|[Mm]illion|[Bb]illion|[Tt]rillion)\b)/u',
			static function ( $m ) {
				$size = strtolower( '' !== ( $m[3] ?? '' ) ? $m[3] : (string) ( $m[4] ?? '' ) );
				return $m[2] . ' ' . self::MAGNITUDES[ $size ] . ' ' . self::CURRENCIES[ $m[1] ];
			},
			$text
		);

		// A size with no currency, only where it can't be a unit: "12.7bn", "3tn".
		return (string) preg_replace_callback(
			'/(?<![\w.])(' . $num . ')(bn|tn)\b/',
			static function ( $m ) {
				return $m[1] . ' ' . self::MAGNITUDES[ $m[2] ];
			},
			$text
		);
	}
}
