<?php
/**
 * Tests for the per-post "audio on/off" switch.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tests\Unit;

use Brain\Monkey\Functions;
use ListenlyVoice\Editor\PostToggle;
use ListenlyVoice\Player\Player;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tests\TestCase;
use ListenlyVoice\Tts\Registry;

/**
 * PostToggle and how the player honours it.
 */
final class PostToggleTest extends TestCase {

	/**
	 * Arguments passed to register_post_meta(), keyed by meta key.
	 *
	 * @var array<string, array>
	 */
	private $registered = array();

	protected function set_up(): void {
		parent::set_up();
		$this->registered = array();
		Functions\when( 'register_post_meta' )->alias(
			function ( $type, $key, $args ) {
				$this->registered[ $key ] = array_merge( $args, array( 'object_subtype' => $type ) );
				return true;
			}
		);
		$this->options[ Settings::OPTION ] = array_merge( Settings::defaults(), array( 'post_types' => array( 'post', 'recipe' ) ) );
	}

	private function screen( string $base, string $post_type ): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'base' => $base, 'post_type' => $post_type ) );
	}

	public function test_registers_the_meta_as_a_boolean_shown_in_rest(): void {
		Functions\when( 'post_type_supports' )->justReturn( true );
		( new PostToggle() )->register_meta();

		$args = $this->registered[ PostToggle::META ];
		$this->assertSame( '_listenly_disabled', PostToggle::META );
		$this->assertSame( 'boolean', $args['type'] );
		$this->assertTrue( $args['single'] );
		$this->assertFalse( $args['default'] );
		$this->assertTrue( $args['show_in_rest'] );
		$this->assertSame( '', $args['object_subtype'] );
	}

	public function test_only_people_who_can_edit_the_post_can_change_it(): void {
		Functions\when( 'post_type_supports' )->justReturn( true );
		Functions\expect( 'current_user_can' )->twice()->andReturnUsing(
			static function ( $cap, $post_id ) {
				return 'edit_post' === $cap && 7 === $post_id;
			}
		);
		( new PostToggle() )->register_meta();
		$auth = $this->registered[ PostToggle::META ]['auth_callback'];

		$this->assertTrue( $auth( false, PostToggle::META, 7 ) );
		$this->assertFalse( $auth( false, PostToggle::META, 8 ) );
	}

	public function test_saved_values_are_cleaned_to_true_or_false(): void {
		Functions\when( 'post_type_supports' )->justReturn( true );
		Functions\when( 'rest_sanitize_boolean' )->alias(
			static function ( $value ) {
				return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
		);
		( new PostToggle() )->register_meta();
		$clean = $this->registered[ PostToggle::META ]['sanitize_callback'];

		$this->assertTrue( $clean( '1' ) );
		$this->assertFalse( $clean( 'nope' ) );
	}

	public function test_adds_custom_fields_support_to_chosen_types_that_lack_it(): void {
		Functions\when( 'post_type_supports' )->alias(
			static function ( $type ) {
				return 'post' === $type;
			}
		);
		Functions\expect( 'add_post_type_support' )->once()->with( 'recipe', 'custom-fields' );

		( new PostToggle() )->register_meta();
	}

	public function test_loads_the_editor_script_for_a_chosen_post_type(): void {
		$this->screen( 'post', 'recipe' );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\expect( 'wp_enqueue_script' )->once()->andReturnUsing(
			function ( $handle, $src, $deps ) {
				$this->assertSame( PostToggle::HANDLE, $handle );
				$this->assertStringEndsWith( 'build/editor/editor.js', $src );
				$this->assertContains( 'wp-plugins', $deps );
				$this->assertContains( 'wp-data', $deps );
			}
		);

		( new PostToggle() )->enqueue();
	}

	public function test_skips_post_types_the_player_is_off_for(): void {
		$this->screen( 'post', 'page' );
		Functions\expect( 'wp_enqueue_script' )->never();

		( new PostToggle() )->enqueue();
	}

	public function test_skips_the_site_editor(): void {
		$this->screen( 'site-editor', '' );
		Functions\expect( 'wp_enqueue_script' )->never();

		( new PostToggle() )->enqueue();
	}

	public function test_is_disabled_reads_the_meta(): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( $id ) {
				return 7 === $id ? '1' : '';
			}
		);

		$this->assertTrue( PostToggle::is_disabled( 7 ) );
		$this->assertFalse( PostToggle::is_disabled( 8 ) );
	}

	public function test_the_shortcode_is_empty_on_a_post_with_audio_off(): void {
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 7, 'post_type' => 'post' ) );
		Functions\when( 'get_post_meta' )->justReturn( '1' );
		Functions\expect( 'wp_enqueue_script' )->never();

		$this->assertSame( '', ( new Player( new Registry() ) )->shortcode() );
	}
}
