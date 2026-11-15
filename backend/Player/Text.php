<?php
/**
 * The text read aloud.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Player;

use ListenlyVoice\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a post into plain text and splits it into provider-sized chunks.
 */
final class Text {

	/**
	 * The title and content of a post as plain text.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function for_post( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$content = (string) $post->post_content;
		$content = preg_replace( '/<!--.*?-->/s', '', $content );
		$content = strip_shortcodes( (string) $content );
		$content = wp_strip_all_tags( str_replace( array( '</p>', '</h2>', '</h3>', '</li>' ), ". \n", $content ) );
		$text    = get_the_title( $post ) . ". \n" . $content;
		$text    = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		$text    = (string) preg_replace( '/\.\s*\./', '.', $text );
		return (string) apply_filters( Hooks::POST_TEXT, $text, $post );
	}

	/**
	 * Splits text at sentence ends so each chunk has at most $limit characters.
	 *
	 * @param string $text  Plain text.
	 * @param int    $limit Characters per chunk; 0 means one chunk.
	 * @return string[]
	 */
	public static function chunks( string $text, int $limit ): array {
		if ( '' === $text ) {
			return array();
		}
		if ( $limit <= 0 || strlen( $text ) <= $limit ) {
			return array( $text );
		}
		$sentences = preg_split( '/(?<=[.!?])\s+/', $text );
		$chunks    = array();
		$current   = '';
		foreach ( (array) $sentences as $sentence ) {
			$length = strlen( $sentence );
			while ( $length > $limit ) {
				$chunks[] = substr( $sentence, 0, $limit );
				$sentence = substr( $sentence, $limit );
				$length   = strlen( $sentence );
			}
			if ( '' !== $current && strlen( $current ) + 1 + strlen( $sentence ) > $limit ) {
				$chunks[] = $current;
				$current  = '';
			}
			$current = '' === $current ? $sentence : $current . ' ' . $sentence;
		}
		if ( '' !== $current ) {
			$chunks[] = $current;
		}
		return $chunks;
	}
}
