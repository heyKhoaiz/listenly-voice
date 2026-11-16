<?php
/**
 * Tests for the admin Home's recent posts.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use ListenlyVoice\Admin\RecentPosts;
use ListenlyVoice\Hooks;
use ListenlyVoice\Tests\TestCase;

/**
 * RecentPosts.
 */
final class RecentPostsTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'get_the_title' )->alias(
			static function ( $post ) {
				return $post->post_title;
			}
		);
		Functions\when( 'get_the_date' )->justReturn( 'November 16, 2026' );
		Functions\when( 'get_permalink' )->alias(
			static function ( $post ) {
				return 'https://example.test/?p=' . $post->ID;
			}
		);
		Functions\when( 'get_edit_post_link' )->alias(
			static function ( $id ) {
				return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit';
			}
		);
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'get_post_meta' )->alias(
			static function ( $id ) {
				return 2 === $id;
			}
		);
	}

	private function posts(): array {
		return array(
			(object) array(
				'ID'         => 1,
				'post_title' => 'Tom &amp; Jerry',
			),
			(object) array(
				'ID'         => 2,
				'post_title' => 'Quiet one',
			),
		);
	}

	public function test_is_empty_without_post_types(): void {
		Functions\expect( 'get_posts' )->never();
		$this->assertSame( array(), RecentPosts::rows( array() ) );
	}

	public function test_lists_published_posts_of_the_chosen_types_with_their_status(): void {
		Functions\expect( 'get_posts' )->once()->andReturnUsing(
			function ( $args ) {
				$this->assertSame( array( 'post', 'page' ), $args['post_type'] );
				$this->assertSame( 'publish', $args['post_status'] );
				$this->assertSame( 5, $args['numberposts'] );
				$this->assertFalse( $args['has_password'] );
				return $this->posts();
			}
		);
		$rows = RecentPosts::rows( array( 'post', 'page' ) );

		$this->assertSame( 'Tom & Jerry', $rows[0]['title'] );
		$this->assertSame( 'https://example.test/?p=1', $rows[0]['viewUrl'] );
		$this->assertSame( 'live', $rows[0]['status']['id'] );
		$this->assertSame( 'off', $rows[1]['status']['id'] );
	}

	public function test_extensions_can_change_a_row(): void {
		Functions\when( 'get_posts' )->justReturn( $this->posts() );
		Filters\expectApplied( Hooks::RECENT_POST )->twice()->andReturnUsing(
			static function ( $row ) {
				$row['status'] = array(
					'id'    => 'ready',
					'label' => 'Audio ready',
				);
				return $row;
			}
		);
		$rows = RecentPosts::rows( array( 'post' ) );
		$this->assertSame( 'Audio ready', $rows[0]['status']['label'] );
	}
}
