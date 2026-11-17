<?php
/**
 * Tests for where the player is placed in a post.
 *
 * @package PostAloud
 */

namespace PostAloud\Tests\Unit;

use Brain\Monkey\Functions;
use PostAloud\Player\Player;
use PostAloud\Support\Settings;
use PostAloud\Tests\TestCase;
use PostAloud\Tts\Registry;

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
		Functions\when( 'get_post' )->justReturn(
			(object) array(
				'ID'        => 7,
				'post_type' => 'post',
			)
		);
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
		$this->assertStringStartsWith( '<div class="postaloud-player"', $html );
		$this->assertStringEndsWith( self::CONTENT, $html );
	}

	public function test_end_of_the_post_puts_the_player_after_the_content(): void {
		$this->settings( array( 'position' => 'below' ) );
		$html = $this->render();
		$this->assertStringStartsWith( self::CONTENT, $html );
		$this->assertStringContainsString( 'class="postaloud-player"', $html );
	}

	public function test_floating_adds_the_floating_class(): void {
		$this->settings( array( 'position' => 'floating' ) );
		$html = $this->render();
		$this->assertStringContainsString( 'class="postaloud-player postaloud-player--floating"', $html );
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

	private function footer( Player $player ): string {
		$post            = new \WP_Post();
		$post->ID        = 7;
		$post->post_type = 'post';
		Functions\when( 'get_queried_object' )->justReturn( $post );
		ob_start();
		$player->footer();
		return (string) ob_get_clean();
	}

	public function test_a_floating_player_the_theme_never_placed_goes_in_the_footer(): void {
		$this->settings( array( 'position' => 'floating' ) );
		$this->options[ Player::MISS_OPTION ] = array(
			'post'   => 7,
			'reason' => 'content',
			'time'   => time(),
		);
		$html                                 = $this->footer( new Player( new Registry() ) );
		$this->assertStringContainsString( 'postaloud-player--floating', $html );
		$this->assertArrayNotHasKey( Player::MISS_OPTION, $this->options );
	}

	public function test_a_missed_top_player_is_recorded_not_printed(): void {
		$this->settings( array( 'position' => 'above' ) );
		$this->assertSame( '', $this->footer( new Player( new Registry() ) ) );
		$this->assertSame( 7, $this->options[ Player::MISS_OPTION ]['post'] );
		$this->assertSame( 'content', $this->options[ Player::MISS_OPTION ]['reason'] );
	}

	public function test_content_shown_outside_the_main_loop_is_recorded_as_loop(): void {
		$this->settings( array( 'position' => 'above' ) );
		Functions\when( 'in_the_loop' )->justReturn( false );
		Functions\when( 'get_the_ID' )->justReturn( 7 );
		Functions\when( 'get_queried_object_id' )->justReturn( 7 );
		$player = new Player( new Registry() );
		$this->assertSame( self::CONTENT, $player->add_player( self::CONTENT ) );
		$this->footer( $player );
		$this->assertSame( 'loop', $this->options[ Player::MISS_OPTION ]['reason'] );
	}

	public function test_a_placed_player_prints_nothing_more_and_clears_the_miss(): void {
		$this->settings( array( 'position' => 'above' ) );
		$this->options[ Player::MISS_OPTION ] = array(
			'post'   => 7,
			'reason' => 'content',
			'time'   => time(),
		);
		$player                               = new Player( new Registry() );
		$player->add_player( self::CONTENT );
		$this->assertSame( '', $this->footer( $player ) );
		$this->assertArrayNotHasKey( Player::MISS_OPTION, $this->options );
	}

	public function test_the_same_miss_is_written_once_a_day(): void {
		$this->options[ Player::MISS_OPTION ] = array(
			'post'   => 7,
			'reason' => 'content',
			'time'   => 123 + time() - 60,
		);
		$before                               = $this->options[ Player::MISS_OPTION ];
		Player::record_miss( 7, 'content' );
		$this->assertSame( $before, $this->options[ Player::MISS_OPTION ] );
		Player::record_miss( 7, 'loop' );
		$this->assertSame( 'loop', $this->options[ Player::MISS_OPTION ]['reason'] );
	}
}
