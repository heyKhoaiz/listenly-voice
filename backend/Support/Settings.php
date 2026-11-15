<?php
/**
 * Plugin settings.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and saves the single settings option.
 */
final class Settings {

	const OPTION       = 'listenly_settings';
	const SETUP_OPTION = 'listenly_setup_done';

	/**
	 * Defaults: the browser voice on posts, above the content, so the player works with no setup.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'provider'   => 'browser',
			'voice'      => '',
			'speed'      => 1.0,
			'post_types' => array( 'post' ),
			'position'   => 'above',
			'highlight'  => 'sentence',
		);
	}

	/**
	 * The saved settings over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Sanitizes and saves known keys. Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> The saved settings.
	 */
	public static function save( array $input ): array {
		$current = self::all();
		$clean   = $current;

		if ( isset( $input['provider'] ) ) {
			$clean['provider'] = sanitize_key( (string) $input['provider'] );
		}
		if ( isset( $input['voice'] ) ) {
			$clean['voice'] = sanitize_text_field( (string) $input['voice'] );
		}
		if ( isset( $input['speed'] ) ) {
			$clean['speed'] = max( 0.5, min( 2.0, (float) $input['speed'] ) );
		}
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$clean['post_types'] = array_values( array_filter( array_map( 'sanitize_key', $input['post_types'] ), 'post_type_exists' ) );
		}
		if ( isset( $input['position'] ) && in_array( $input['position'], array( 'above', 'below_title', 'floating' ), true ) ) {
			$clean['position'] = $input['position'];
		}

		update_option( self::OPTION, $clean );
		return $clean;
	}
}
