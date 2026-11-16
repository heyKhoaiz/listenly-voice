<?php
/**
 * Money and number shorthand.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use ListenlyVoice\Player\Say;
use ListenlyVoice\Player\Spoken;
use ListenlyVoice\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Spoken::numbers and Say. The cases are shared with tests/js/spoken.test.ts.
 */
final class SpokenTest extends TestCase {

	public function test_shared_cases(): void {
		$cases = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/spoken.json' ), true );
		foreach ( $cases as $case ) {
			self::assertSame( $case[1], Spoken::numbers( $case[0] ), $case[0] );
		}
	}

	public function test_english_only(): void {
		self::assertTrue( Spoken::applies( 'en-GB' ) );
		self::assertFalse( Spoken::applies( 'de-DE' ) );
	}

	public function test_say_replaces_whole_words_once_longest_first(): void {
		$pairs = array( array( 'WP', 'WordPress' ), array( 'WP CLI', 'W P command line' ) );
		self::assertSame( 'W P command line runs on WordPress; WPX stays.', Say::apply( 'wp cli runs on WP; WPX stays.', $pairs ) );
		self::assertSame( 'same', Say::apply( 'same', array( array( '', 'x' ), 'junk' ) ) );
	}

	public function test_say_sanitize_drops_empty_and_duplicates_and_keeps_the_limit(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();
		$raw = array( array( 'WP', 'WordPress' ), array( 'wp', 'double' ), array( '', 'x' ), 'junk' );
		for ( $i = 0; $i < 20; $i++ ) {
			$raw[] = array( "w$i", "word $i" );
		}
		self::assertSame( 200, Say::LIMIT );
		\Brain\Monkey\Filters\expectApplied( 'listenly/say_limit' )->andReturn( 5 );
		$clean = Say::sanitize( $raw );
		self::assertCount( 5, $clean );
		self::assertSame( array( 'WP', 'WordPress' ), $clean[0] );
	}
}
