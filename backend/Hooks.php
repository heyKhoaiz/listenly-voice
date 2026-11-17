<?php
/**
 * Public action and filter names. Pro and third-party code extend the plugin through these.
 *
 * @package PostAloud
 */

namespace PostAloud;

defined( 'ABSPATH' ) || exit;

/**
 * Hook names, in one place so they never drift.
 */
final class Hooks {

	/** Fires after the plugin boots. Receives the Plugin instance. */
	const LOADED = 'postaloud/loaded';

	/** Filters the voice providers: array<string, Tts\ProviderInterface>. */
	const PROVIDERS = 'postaloud/providers';

	/** Filters the data passed to the admin app as window.postaloudConfig. */
	const ADMIN_CONFIG = 'postaloud/admin_config';

	/** Filters the script handles the admin app depends on. */
	const ADMIN_SCRIPT_DEPENDENCIES = 'postaloud/admin_script_dependencies';

	/** Filters the player data printed on a post. */
	const PLAYER_DATA = 'postaloud/player_data';

	/** Filters the allowed read-along highlight modes. Free: sentence, off. */
	const HIGHLIGHT_MODES = 'postaloud/highlight_modes';

	/** Filters one row of the admin Home's recent posts: ['id', 'title', 'date', 'viewUrl', 'editUrl', 'status' => ['id', 'label']]. */
	const RECENT_POST = 'postaloud/recent_post';

	/** Filters the text read aloud for a post. */
	const POST_TEXT = 'postaloud/post_text';

	/** Filters how many seconds a live audio chunk stays cached (default 3600; 0 turns the cache off). */
	const LIVE_CACHE_TTL = 'postaloud/live_cache_ttl';

	/** Filters the live audio rate limits: ['visitor' => ['max', 'window'], 'site' => ['max', 'window']]. */
	const LIVE_RATE_LIMITS = 'postaloud/live_rate_limits';

	/** Filters the visitor address used for the rate limit (default REMOTE_ADDR). */
	const CLIENT_IP = 'postaloud/client_ip';

	/** Filters a post's raw content before it becomes the text read aloud. Receives ( string $content, WP_Post $post ). */
	const POST_CONTENT = 'postaloud/post_content';

	/** Filters whether audio is off for a post. Receives ( bool $disabled, int $post_id ). */
	const POST_DISABLED = 'postaloud/post_disabled';

	/** Filters whether the player is added to a post's content automatically. Receives ( bool $insert, WP_Post $post ). */
	const AUTO_INSERT = 'postaloud/auto_insert';

	/** Fires after a play is counted. Receives ( int $post_id, int $plays ). */
	const PLAY = 'postaloud/play';

	/** Filters how many pronunciation pairs can be saved (default 200). */
	const SAY_LIMIT = 'postaloud/say_limit';

	/** Filters whether money and number shorthand is spelled out ("$12.7bn" → "12.7 billion dollars"). Receives ( bool $on, WP_Post $post ); default on for English sites. */
	const READ_NUMBERS = 'postaloud/read_numbers';

	/** Filters the plugin name shown in the admin menu and app header. */
	const BRAND = 'postaloud/brand';
}
