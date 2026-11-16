<?php
/**
 * Tests for play counts and the free extension filters.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ListenlyVoice\Admin\Menu;
use ListenlyVoice\Editor\PostToggle;
use ListenlyVoice\Hooks;
use ListenlyVoice\Player\Player;
use ListenlyVoice\Player\Text;
use ListenlyVoice\Stats\Plays;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tests\TestCase;
use ListenlyVoice\Tts\Registry;

/**
 * Plays, POST_DISABLED, AUTO_INSERT, POST_CONTENT and BRAND.
 */
final class PlaysTest extends TestCase {

	/** @var array<string, mixed> */
	private $transients = array();

	/** @var array<int, array<string, mixed>> */
	private $meta = array();

	protected function set_up(): void {
		parent::set_up();
		$this->transients = array();
		$this->meta       = array();
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value ) {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key ) {
				return $this->meta[ $id ][ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( $id, $key, $value ) {
				$this->meta[ $id ][ $key ] = $value;
				return true;
			}
		);
	}

	public function test_counts_a_visitor_once_per_post_in_the_window(): void {
		Actions\expectDone( Hooks::PLAY )->once()->with( 7, 1 );
		$this->assertTrue( Plays::record( 7, '1.2.3.4' ) );
		$this->assertFalse( Plays::record( 7, '1.2.3.4' ) );
		$this->assertSame( 1, Plays::for_post( 7 ) );
		$this->assertSame( 1, Plays::total() );
	}

	public function test_other_visitors_and_posts_count_separately(): void {
		Plays::record( 7, '1.2.3.4' );
		Plays::record( 7, '5.6.7.8' );
		Plays::record( 8, '1.2.3.4' );
		$this->assertSame( 2, Plays::for_post( 7 ) );
		$this->assertSame( 1, Plays::for_post( 8 ) );
		$this->assertSame( 3, Plays::total() );
	}

	public function test_never_stores_the_address(): void {
		Plays::record( 7, '1.2.3.4' );
		$this->assertStringNotContainsString( '1.2.3.4', (string) json_encode( array( $this->transients, $this->meta, $this->options ) ) );
	}

	public function test_extensions_can_switch_audio_off_for_a_post(): void {
		Filters\expectApplied( Hooks::POST_DISABLED )->once()->with( false, 7 )->andReturn( true );
		$this->assertTrue( PostToggle::is_disabled( 7 ) );
	}

	public function test_extensions_can_stop_automatic_placement(): void {
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'in_the_loop' )->justReturn( true );
		Functions\when( 'is_main_query' )->justReturn( true );
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 7, 'post_type' => 'post' ) );
		$this->options[ Settings::OPTION ] = Settings::defaults();
		Filters\expectApplied( Hooks::AUTO_INSERT )->once()->andReturn( false );
		$this->assertSame( '<p>Hi.</p>', ( new Player( new Registry() ) )->add_player( '<p>Hi.</p>' ) );
	}

	public function test_extensions_can_change_the_content_before_it_becomes_text(): void {
		$post = (object) array(
			'ID'           => 7,
			'post_content' => '<p>Keep this.</p><div class="ad">Skip this.</div>',
		);
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'strip_shortcodes' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'get_the_title' )->justReturn( 'Title' );
		Filters\expectApplied( Hooks::POST_CONTENT )->once()->andReturn( '<p>Keep this.</p>' );
		$this->assertSame( 'Title. Keep this.', Text::for_post( 7 ) );
	}

	public function test_brand_falls_back_when_blank(): void {
		$this->assertSame( 'Listenly Voice', Menu::brand() );
		Filters\expectApplied( Hooks::BRAND )->andReturn( '  ' );
		$this->assertSame( 'Listenly Voice', Menu::brand() );
	}
}
