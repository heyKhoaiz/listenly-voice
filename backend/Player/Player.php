<?php
/**
 * The frontend audio player.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Player;

use ListenlyVoice\Hooks;
use ListenlyVoice\Support\Settings;
use ListenlyVoice\Tts\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the player to posts and loads build/player/.
 */
final class Player {

	/**
	 * Voice providers.
	 *
	 * @var Registry
	 */
	private $providers;

	/**
	 * Constructor.
	 *
	 * @param Registry $providers Providers.
	 */
	public function __construct( Registry $providers ) {
		$this->providers = $providers;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'the_content', array( $this, 'add_player' ), 5 );
		add_shortcode( 'listenly_player', array( $this, 'shortcode' ) );
	}

	/**
	 * Adds the player to singular posts of the chosen types.
	 *
	 * @param string $content Post content.
	 */
	public function add_player( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, (array) Settings::get( 'post_types' ), true ) || get_post_meta( $post->ID, '_listenly_disabled', true ) ) {
			return $content;
		}
		$position = (string) Settings::get( 'position' );
		if ( 'above' === $position ) {
			return $this->markup( $post->ID ) . $content;
		}
		// End of the post, or floating: the floating bar is fixed on screen, so its place in the markup only sets reading order.
		return $content . $this->markup( $post->ID, 'floating' === $position );
	}

	/**
	 * [listenly_player] for manual placement.
	 */
	public function shortcode(): string {
		$post = get_post();
		return $post ? $this->markup( $post->ID ) : '';
	}

	/**
	 * The mount point, with the data the player script reads.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $floating Fixed to the corner of the screen.
	 */
	private function markup( int $post_id, bool $floating = false ): string {
		$this->enqueue();
		$settings = Settings::all();
		$provider = $this->providers->get( (string) $settings['provider'] );
		$data     = apply_filters(
			Hooks::PLAYER_DATA,
			array(
				'post'       => $post_id,
				'clientSide' => $provider->is_client_side() || ! $provider->is_ready(),
				'audioUrl'   => esc_url_raw( rest_url( 'listenly/v1/audio/' . $post_id . '/' ) ),
				'speed'      => (float) $settings['speed'],
				'highlight'  => (string) $settings['highlight'],
				'position'   => (string) $settings['position'],
				'lang'       => get_bloginfo( 'language' ),
			),
			$post_id
		);
		return sprintf(
			'<div class="%s" data-listenly="%s"><noscript>%s</noscript></div>',
			$floating ? 'listenly-player listenly-player--floating' : 'listenly-player',
			esc_attr( (string) wp_json_encode( $data ) ),
			esc_html__( 'Turn on JavaScript to listen to this article.', 'listenly-voice' )
		);
	}

	/**
	 * Loads the player script and style once.
	 */
	private function enqueue(): void {
		wp_enqueue_script( 'listenly-player', LISTENLY_URL . 'build/player/player.js', array( 'wp-i18n' ), LISTENLY_VERSION, true );
		wp_enqueue_style( 'listenly-player', LISTENLY_URL . 'build/player/player.css', array(), LISTENLY_VERSION );
		wp_set_script_translations( 'listenly-player', 'listenly-voice', LISTENLY_PATH . 'languages' );
	}
}
