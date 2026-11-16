<?php
/**
 * Tests for where the player is placed in a post.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Functions;
use ListenlyVoice\Player\Player;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tests\TestCase;
use ListenlyVoice\Tts\Registry;

/**
 * Player::add_player().
 */
final class PlayerTest extends TestCase {

	const CONTENT = '<p>Hello world.</p>';

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'in_the_loop' )->justReturn( true );
		Functions\when( 'is_main_query' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 7, 'post_type' => 'post' ) );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'rest_url' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'get_bloginfo' )->justReturn( 'en-US' );
		Functions\when( 'get_the_title' )->justReturn( 'Hello' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
	}

	private function settings( array $overrides ): void {
		$this->options[ Settings::OPTION ] = array_merge( Settings::defaults(), $overrides );
	}

	private function render(): string {
		return ( new Player( new Registry() ) )->add_player( self::CONTENT );
	}

	public function test_top_of_the_post_puts_the_player_before_the_content(): void {
		$this->settings( array( 'position' => 'above' ) );
		$html = $this->render();
		$this->assertStringStartsWith( '<div class="listenly-player"', $html );
		$this->assertStringEndsWith( self::CONTENT, $html );
	}

	public function test_end_of_the_post_puts_the_player_after_the_content(): void {
		$this->settings( array( 'position' => 'below' ) );
		$html = $this->render();
		$this->assertStringStartsWith( self::CONTENT, $html );
		$this->assertStringContainsString( 'class="listenly-player"', $html );
	}

	public function test_floating_adds_the_floating_class(): void {
		$this->settings( array( 'position' => 'floating' ) );
		$html = $this->render();
		$this->assertStringContainsString( 'class="listenly-player listenly-player--floating"', $html );
		$this->assertStringStartsWith( self::CONTENT, $html );
	}

	public function test_passes_the_plain_post_title_to_the_player_script(): void {
		Functions\when( 'get_the_title' )->justReturn( 'Salt &amp; <em>pepper</em>' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		$this->settings( array() );
		$this->assertStringContainsString( '&quot;title&quot;:&quot;Salt &amp; pepper&quot;', $this->render() );
	}

	public function test_passes_the_position_to_the_player_script(): void {
		$this->settings( array( 'position' => 'floating' ) );
		$this->assertStringContainsString( '&quot;position&quot;:&quot;floating&quot;', $this->render() );
	}

	public function test_leaves_post_types_that_were_not_chosen_alone(): void {
		$this->settings( array( 'post_types' => array( 'page' ) ) );
		$this->assertSame( self::CONTENT, $this->render() );
	}

	public function test_leaves_a_post_with_audio_turned_off_alone(): void {
		$this->settings( array() );
		Functions\when( 'get_post_meta' )->justReturn( '1' );
		$this->assertSame( self::CONTENT, $this->render() );
	}

	public function test_leaves_archives_alone(): void {
		$this->settings( array() );
		Functions\when( 'is_singular' )->justReturn( false );
		$this->assertSame( self::CONTENT, $this->render() );
	}
}
