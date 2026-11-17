<?php
/**
 * Base test case with Brain Monkey set up.
 *
 * @package PostAloud
 */

namespace PostAloud\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Stubs translation, escaping and is_wp_error for every test.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * Options written during a test.
	 *
	 * @var array<string, mixed>
	 */
	protected $options = array();

	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		// An English site, unless a test says otherwise.
		Functions\when( 'get_bloginfo' )->alias(
			static function ( $show = '' ) {
				return 'language' === $show ? 'en-US' : '';
			}
		);

		// A tiny in-memory options table.
		$this->options = array();
		Functions\when( 'get_option' )->alias(
			function ( $name, $fallback = false ) {
				return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			function ( $name, $value ) {
				if ( ! array_key_exists( $name, $this->options ) ) {
					$this->options[ $name ] = $value;
				}
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $name ) {
				unset( $this->options[ $name ] );
				return true;
			}
		);
	}

	protected function tear_down(): void {
		// Count Brain Monkey expectations (expect()->once(), ->never()) as assertions.
		$this->addToAssertionCount( \Mockery::getContainer()->mockery_getExpectationCount() );
		Monkey\tearDown();
		parent::tear_down();
	}
}
