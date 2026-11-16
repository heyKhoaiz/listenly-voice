<?php
/**
 * The frontend audio player.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Player;

use ListenlyVoice\Editor\PostToggle;
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
	 * Posts whose player is already on the page.
	 *
	 * @var array<int, bool>
	 */
	private $printed = array();

	/**
	 * Why auto-insert skipped the page's post, when it did: 'loop' (the content was shown outside the main loop).
	 *
	 * @var string
	 */
	private $skipped = '';

	/** Option: the last post where the player should have shown but didn't. */
	const MISS_OPTION = 'listenly_insert_miss';

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
		add_action( 'wp_footer', array( $this, 'footer' ), 5 );
	}

	/**
	 * Whether the player belongs on this post automatically.
	 *
	 * @param \WP_Post|null $post Post.
	 */
	private function wanted( $post ): bool {
		return $post && in_array( $post->post_type, (array) Settings::get( 'post_types' ), true ) && ! PostToggle::is_disabled( $post->ID )
			&& apply_filters( Hooks::AUTO_INSERT, true, $post );
	}

	/**
	 * Adds the player to singular posts of the chosen types.
	 *
	 * @param string $content Post content.
	 */
	public function add_player( string $content ): string {
		if ( ! is_singular() ) {
			return $content;
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			if ( get_the_ID() === get_queried_object_id() ) {
				$this->skipped = 'loop';
			}
			return $content;
		}
		$post = get_post();
		if ( ! $this->wanted( $post ) || isset( $this->printed[ $post->ID ] ) ) {
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
	 * When the theme or a page builder showed the post without the usual content filter, a
	 * floating player still goes in the footer; for the other positions the miss is recorded so
	 * the admin can say what happened and how to fix it.
	 */
	public function footer(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! $this->wanted( $post ) ) {
			return;
		}
		if ( isset( $this->printed[ $post->ID ] ) ) {
			self::clear_miss( $post->ID );
			return;
		}
		if ( 'floating' === Settings::get( 'position' ) ) {
			echo $this->markup( $post->ID, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in markup().
			self::clear_miss( $post->ID );
			return;
		}
		self::record_miss( $post->ID, '' !== $this->skipped ? $this->skipped : 'content' );
	}

	/**
	 * Remembers a miss. Writes at most once a day for the same post and reason.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $reason  'loop' or 'content'.
	 */
	public static function record_miss( int $post_id, string $reason ): void {
		$old = get_option( self::MISS_OPTION, array() );
		if ( is_array( $old ) && ( $old['post'] ?? 0 ) === $post_id && ( $old['reason'] ?? '' ) === $reason && time() - (int) ( $old['time'] ?? 0 ) < DAY_IN_SECONDS ) {
			return;
		}
		update_option(
			self::MISS_OPTION,
			array(
				'post'   => $post_id,
				'reason' => $reason,
				'time'   => time(),
			),
			false
		);
	}

	/**
	 * Forgets the miss once the player shows on that post, or on any post when $post_id is 0.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function clear_miss( int $post_id = 0 ): void {
		$old = get_option( self::MISS_OPTION, array() );
		if ( is_array( $old ) && $old && ( 0 === $post_id || ( $old['post'] ?? 0 ) === $post_id ) ) {
			delete_option( self::MISS_OPTION );
		}
	}

	/**
	 * The recorded miss for the admin, or null.
	 *
	 * @return array{title: string, viewUrl: string, editUrl: string, reason: string}|null
	 */
	public static function miss(): ?array {
		$old = get_option( self::MISS_OPTION, array() );
		if ( ! is_array( $old ) || empty( $old['post'] ) ) {
			return null;
		}
		$post = get_post( (int) $old['post'] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}
		return array(
			'title'   => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
			'viewUrl' => (string) get_permalink( $post ),
			'editUrl' => (string) get_edit_post_link( $post->ID, 'raw' ),
			'reason'  => 'loop' === ( $old['reason'] ?? '' ) ? 'loop' : 'content',
		);
	}

	/**
	 * [listenly_player] for manual placement. Empty when audio is turned off for the post.
	 */
	public function shortcode(): string {
		$post = get_post();
		return $post && ! PostToggle::is_disabled( $post->ID ) ? $this->markup( $post->ID ) : '';
	}

	/**
	 * The mount point, with the data the player script reads.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $floating Fixed to the corner of the screen.
	 */
	private function markup( int $post_id, bool $floating = false ): string {
		$this->enqueue();
		$this->printed[ $post_id ] = true;
		$settings                  = Settings::all();
		$provider                  = $this->providers->get( (string) $settings['provider'] );
		$data                      = apply_filters(
			Hooks::PLAYER_DATA,
			array(
				'post'       => $post_id,
				'title'      => html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ),
				'clientSide' => $provider->is_client_side() || ! $provider->is_ready(),
				'audioUrl'   => esc_url_raw( rest_url( 'listenly/v1/audio/' . $post_id . '/' ) ),
				'playUrl'    => esc_url_raw( rest_url( 'listenly/v1/play/' . $post_id ) ),
				'speed'      => (float) $settings['speed'],
				'highlight'  => (string) $settings['highlight'],
				'position'   => (string) $settings['position'],
				'theme'      => (string) $settings['theme'],
				'lang'       => get_bloginfo( 'language' ),
				'say'        => Say::ordered( $settings['say'] ),
				'numbers'    => (bool) apply_filters( Hooks::READ_NUMBERS, Spoken::applies( (string) get_bloginfo( 'language' ) ), get_post( $post_id ) ),
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
