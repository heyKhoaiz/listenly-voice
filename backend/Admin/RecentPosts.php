<?php
/**
 * Recent posts for the admin Home.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Admin;

use ListenlyVoice\Editor\PostToggle;
use ListenlyVoice\Hooks;
use ListenlyVoice\Stats\Plays;

defined( 'ABSPATH' ) || exit;

/**
 * The newest published posts of the chosen types and whether each one shows the player.
 */
final class RecentPosts {

	/**
	 * Rows for `window.listenlyConfig.recentPosts`.
	 *
	 * @param string[] $post_types Post types that show the player.
	 * @param int      $limit      How many posts.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( array $post_types, int $limit = 5 ): array {
		if ( ! $post_types ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'        => $post_types,
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'has_password'     => false,
				'suppress_filters' => false,
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			$off    = PostToggle::is_disabled( (int) $post->ID );
			$row    = array(
				'id'      => (int) $post->ID,
				'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'date'    => (string) get_the_date( '', $post ),
				'viewUrl' => esc_url_raw( (string) get_permalink( $post ) ),
				'editUrl' => esc_url_raw( (string) get_edit_post_link( $post->ID, 'raw' ) ),
				'plays'   => Plays::for_post( (int) $post->ID ),
				'status'  => $off
					? array(
						'id'    => 'off',
						'label' => __( 'Switched off', 'listenly-voice' ),
					)
					: array(
						'id'    => 'live',
						'label' => __( 'Plays live', 'listenly-voice' ),
					),
			);
			$rows[] = (array) apply_filters( Hooks::RECENT_POST, $row, $post );
		}
		return $rows;
	}
}
