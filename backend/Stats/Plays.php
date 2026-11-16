<?php
/**
 * Play counts: how many times visitors pressed play.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Stats;

use ListenlyVoice\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * The player calls POST /listenly/v1/play/{id} on the first play of a page view. Each visitor
 * counts once per post per half hour. Nothing about the visitor is stored: the address is hashed
 * into a short-lived transient key and forgotten when it expires.
 */
final class Plays {

	const META   = '_listenly_plays';
	const OPTION = 'listenly_plays_total';
	// One play per visitor per post in this many seconds.
	const WINDOW = 1800;

	/**
	 * Counts a play unless this visitor already played the post recently.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $ip      Visitor address (hashed, never stored).
	 * @return bool Whether the play was counted.
	 */
	public static function record( int $post_id, string $ip ): bool {
		$key = 'listenly_play_' . substr( md5( $ip . '|' . $post_id . '|' . wp_salt( 'nonce' ) ), 0, 16 );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, self::WINDOW );

		$plays = self::for_post( $post_id ) + 1;
		update_post_meta( $post_id, self::META, $plays );
		update_option( self::OPTION, self::total() + 1, false );

		do_action( Hooks::PLAY, $post_id, $plays );
		return true;
	}

	/**
	 * Plays of one post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function for_post( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::META, true );
	}

	/**
	 * Plays across the site.
	 */
	public static function total(): int {
		return (int) get_option( self::OPTION, 0 );
	}
}
