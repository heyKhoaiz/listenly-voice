<?php
/**
 * Tests for the text chunker.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use ListenlyVoice\Player\Text;
use PHPUnit\Framework\TestCase;

/**
 * Text::chunks().
 */
final class TextTest extends TestCase {

	public function test_short_text_is_one_chunk(): void {
		$this->assertSame( array( 'Hello there.' ), Text::chunks( 'Hello there.', 100 ) );
	}

	public function test_splits_at_sentence_ends_within_the_limit(): void {
		$chunks = Text::chunks( 'One two. Three four. Five six.', 20 );
		$this->assertSame( array( 'One two. Three four.', 'Five six.' ), $chunks );
		foreach ( $chunks as $chunk ) {
			$this->assertLessThanOrEqual( 20, strlen( $chunk ) );
		}
	}

	public function test_long_sentence_is_cut(): void {
		$chunks = Text::chunks( str_repeat( 'a', 25 ), 10 );
		$this->assertSame( array( 'aaaaaaaaaa', 'aaaaaaaaaa', 'aaaaa' ), $chunks );
	}

	public function test_empty_text_has_no_chunks(): void {
		$this->assertSame( array(), Text::chunks( '', 10 ) );
	}
}
