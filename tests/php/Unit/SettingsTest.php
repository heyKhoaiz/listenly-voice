<?php
/**
 * Tests for saving settings.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tests\TestCase;

/**
 * Settings::save().
 */
final class SettingsTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'post_type_exists' )->alias(
			static function ( $type ) {
				return in_array( $type, array( 'post', 'page' ), true );
			}
		);
	}

	/**
	 * @dataProvider positions
	 */
	public function test_saves_each_placement_the_wizard_offers( string $position ): void {
		$this->assertSame( $position, Settings::save( array( 'position' => $position ) )['position'] );
	}

	/**
	 * @return array<string, array<string>>
	 */
	public function positions(): array {
		return array(
			'top of the post' => array( 'above' ),
			'end of the post' => array( 'below' ),
			'floating button' => array( 'floating' ),
		);
	}

	public function test_ignores_an_unknown_placement(): void {
		Settings::save( array( 'position' => 'below' ) );
		$this->assertSame( 'below', Settings::save( array( 'position' => 'sideways' ) )['position'] );
	}

	public function test_keeps_only_post_types_that_exist(): void {
		$saved = Settings::save( array( 'post_types' => array( 'post', 'page', 'nope', '<b>' ) ) );
		$this->assertSame( array( 'post', 'page' ), $saved['post_types'] );
	}

	public function test_clamps_the_speed(): void {
		$this->assertSame( 2.0, Settings::save( array( 'speed' => 9 ) )['speed'] );
		$this->assertSame( 0.5, Settings::save( array( 'speed' => 0.1 ) )['speed'] );
	}

	public function test_drops_unknown_keys(): void {
		$this->assertArrayNotHasKey( 'evil', Settings::save( array( 'evil' => 1 ) ) );
	}

	public function test_saves_only_known_providers(): void {
		$this->assertSame( 'google', Settings::save( array( 'provider' => 'google' ) )['provider'] );
		$this->assertSame( 'google', Settings::save( array( 'provider' => 'Not A Provider!' ) )['provider'] );
	}

	public function test_turns_sentence_highlighting_off_and_on(): void {
		$this->assertSame( 'off', Settings::save( array( 'highlight' => 'off' ) )['highlight'] );
		$this->assertSame( 'sentence', Settings::save( array( 'highlight' => 'sentence' ) )['highlight'] );
	}

	public function test_rejects_a_highlight_mode_nothing_provides(): void {
		$this->assertSame( 'sentence', Settings::save( array( 'highlight' => 'word' ) )['highlight'] );
	}

	public function test_an_extension_can_add_a_highlight_mode(): void {
		Filters\expectApplied( 'listenly/highlight_modes' )->andReturn( array( 'sentence', 'off', 'word' ) );
		$this->assertSame( 'word', Settings::save( array( 'highlight' => 'word' ) )['highlight'] );
	}

	public function test_player_colours_default_to_auto(): void {
		$this->assertSame( 'auto', Settings::all()['theme'] );
	}

	public function test_saves_light_and_dark_and_rejects_other_colours(): void {
		$this->assertSame( 'dark', Settings::save( array( 'theme' => 'dark' ) )['theme'] );
		$this->assertSame( 'light', Settings::save( array( 'theme' => 'light' ) )['theme'] );
		$this->assertSame( 'light', Settings::save( array( 'theme' => 'purple' ) )['theme'] );
	}
}
