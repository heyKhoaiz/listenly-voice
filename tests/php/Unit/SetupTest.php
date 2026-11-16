<?php
/**
 * Tests for the first-run setup state and activation redirect.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Functions;
use ListenlyVoice\Support\Setup;
use ListenlyVoice\Tests\TestCase;

/**
 * Setup.
 */
final class SetupTest extends TestCase {

	public function test_is_not_done_on_a_fresh_install(): void {
		$this->assertFalse( Setup::is_done() );
	}

	public function test_complete_marks_setup_done(): void {
		Setup::complete();
		$this->assertTrue( Setup::is_done() );
	}

	public function test_reset_lets_the_wizard_run_again(): void {
		Setup::complete();
		Setup::reset();
		$this->assertFalse( Setup::is_done() );
	}

	public function test_activation_queues_a_redirect_when_setup_is_not_done(): void {
		Functions\expect( 'set_transient' )->once()->with( Setup::REDIRECT_TRANSIENT, 1, 30 );
		Setup::queue_redirect();
	}

	public function test_activation_does_not_queue_a_redirect_after_setup(): void {
		Setup::complete();
		Functions\expect( 'set_transient' )->never();
		Setup::queue_redirect();
	}

	public function test_redirects_once_after_a_single_activation(): void {
		$this->assertTrue( Setup::should_redirect( true, false, false, false, true ) );
	}

	/**
	 * @dataProvider no_redirect_cases
	 */
	public function test_does_not_redirect( bool $queued, bool $network, bool $ajax, bool $bulk, bool $can_manage ): void {
		$this->assertFalse( Setup::should_redirect( $queued, $network, $ajax, $bulk, $can_manage ) );
	}

	/**
	 * @return array<string, array<bool>>
	 */
	public function no_redirect_cases(): array {
		return array(
			'nothing queued'          => array( false, false, false, false, true ),
			'network admin'           => array( true, true, false, false, true ),
			'ajax request'            => array( true, false, true, false, true ),
			'bulk activation'         => array( true, false, false, true, true ),
			'user cannot manage site' => array( true, false, false, false, false ),
		);
	}

	public function test_view_url_is_the_newest_post_of_the_chosen_types(): void {
		Functions\expect( 'get_posts' )->once()->andReturnUsing(
			function ( $args ) {
				$this->assertSame( array( 'post', 'page' ), $args['post_type'] );
				$this->assertSame( 'publish', $args['post_status'] );
				$this->assertSame( 1, $args['numberposts'] );
				return array( 42 );
			}
		);
		Functions\when( 'get_permalink' )->alias(
			static function ( $id ) {
				return 'https://example.test/?p=' . $id;
			}
		);
		$this->assertSame( 'https://example.test/?p=42', Setup::view_url( array( 'post', 'page' ) ) );
	}

	public function test_view_url_falls_back_to_the_home_page(): void {
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'home_url' )->justReturn( 'https://example.test/' );
		$this->assertSame( 'https://example.test/', Setup::view_url( array( 'post' ) ) );
	}

	public function test_view_url_with_no_post_types_is_the_home_page(): void {
		Functions\expect( 'get_posts' )->never();
		Functions\when( 'home_url' )->justReturn( 'https://example.test/' );
		$this->assertSame( 'https://example.test/', Setup::view_url( array() ) );
	}
}
