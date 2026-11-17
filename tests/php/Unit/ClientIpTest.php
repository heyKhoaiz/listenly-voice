<?php
/**
 * Tests for the visitor address used by the live audio rate limit.
 *
 * @package PostAloud
 */

namespace PostAloud\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PostAloud\Hooks;
use PostAloud\Rest\SettingsController;
use PostAloud\Tests\TestCase;

/**
 * SettingsController::client_ip().
 */
final class ClientIpTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
	}

	protected function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tear_down();
	}

	public function test_uses_the_connection_address(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
		$this->assertSame( '203.0.113.5', SettingsController::client_ip() );
	}

	public function test_ignores_proxy_headers_a_bot_could_fake(): void {
		$_SERVER['REMOTE_ADDR']          = '203.0.113.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
		$this->assertSame( '203.0.113.5', SettingsController::client_ip() );
	}

	public function test_a_site_behind_a_trusted_proxy_can_override_it(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		Filters\expectApplied( Hooks::CLIENT_IP )->once()->with( '10.0.0.1' )->andReturn( '198.51.100.7' );
		$this->assertSame( '198.51.100.7', SettingsController::client_ip() );
	}

	public function test_is_empty_without_an_address(): void {
		$this->assertSame( '', SettingsController::client_ip() );
	}
}
