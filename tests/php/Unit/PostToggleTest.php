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

	public function test_adds_a_classic_editor_box_only_on_chosen_types(): void {
		$added = array();
		Functions\when( 'add_meta_box' )->alias(
			static function ( $id, $title, $callback, $screen, $context, $priority, $args ) use ( &$added ) {
				$added[ $screen ] = array( $id, $context, $args );
			}
		);

		$toggle = new PostToggle();
		$toggle->add_meta_box( 'post' );
		$toggle->add_meta_box( 'page' );

		$this->assertSame( array( 'post' ), array_keys( $added ) );
		$this->assertSame( 'listenly-voice', $added['post'][0] );
		$this->assertSame( 'side', $added['post'][1] );
		$this->assertTrue( $added['post'][2]['__back_compat_meta_box'], 'The block editor has its own panel, so hide the box there.' );
	}

	public function test_the_box_shows_a_ticked_checkbox_and_a_nonce_when_audio_is_on(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'wp_nonce_field' )->alias(
			static function ( $action, $name ) {
				echo '<input type="hidden" name="' . $name . '" value="nonce-for-' . $action . '">';
			}
		);
		Functions\when( 'checked' )->alias(
			static function ( $value ) {
				echo $value ? ' checked="checked"' : '';
			}
		);

		ob_start();
		( new PostToggle() )->render_meta_box( (object) array( 'ID' => 7 ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="listenly_audio_on" value="1" checked="checked"', $html );
		$this->assertStringContainsString( 'Audio player on this post', $html );
		$this->assertStringContainsString( 'name="listenly_toggle_nonce"', $html );
	}

	public function test_the_box_is_unticked_when_audio_is_off(): void {
		Functions\when( 'get_post_meta' )->justReturn( '1' );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'checked' )->alias(
			static function ( $value ) {
				echo $value ? ' checked="checked"' : '';
			}
		);

		ob_start();
		( new PostToggle() )->render_meta_box( (object) array( 'ID' => 7 ) );
		$this->assertStringNotContainsString( 'checked=', (string) ob_get_clean() );
	}

	/** Simulates the classic editor form being submitted. */
	private function submit( array $post, bool $nonce_ok = true, bool $can_edit = true, bool $autosave = false ): void {
		$_POST = $post;
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_verify_nonce' )->justReturn( $nonce_ok ? 1 : false );
		Functions\when( 'current_user_can' )->justReturn( $can_edit );
		Functions\when( 'wp_is_post_autosave' )->justReturn( $autosave );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		( new PostToggle() )->save_meta_box( 7 );
	}

	protected function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	public function test_unticking_the_box_turns_audio_off(): void {
		Functions\expect( 'update_post_meta' )->once()->with( 7, PostToggle::META, true );
		$this->submit( array( 'listenly_toggle_nonce' => 'n' ) );
	}

	public function test_ticking_the_box_turns_audio_back_on(): void {
		Functions\expect( 'delete_post_meta' )->once()->with( 7, PostToggle::META );
		$this->submit( array( 'listenly_toggle_nonce' => 'n', 'listenly_audio_on' => '1' ) );
	}

	public function test_saves_from_elsewhere_without_the_box_change_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();
		Functions\expect( 'delete_post_meta' )->never();
		$this->submit( array() );
	}

	public function test_a_bad_nonce_changes_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();
		$this->submit( array( 'listenly_toggle_nonce' => 'forged' ), false );
	}

	public function test_someone_who_cannot_edit_the_post_changes_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();
		$this->submit( array( 'listenly_toggle_nonce' => 'n' ), true, false );
	}

	public function test_an_autosave_changes_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();
		$this->submit( array( 'listenly_toggle_nonce' => 'n' ), true, true, true );
	}
}
