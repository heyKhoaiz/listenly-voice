<?php
/**
 * Public action and filter names. Pro and third-party code extend the plugin through these.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice;

defined( 'ABSPATH' ) || exit;

/**
 * Hook names, in one place so they never drift.
 */
final class Hooks {

	/** Fires after the plugin boots. Receives the Plugin instance. */
	const LOADED = 'listenly/loaded';

	/** Filters the voice providers: array<string, Tts\ProviderInterface>. */
	const PROVIDERS = 'listenly/providers';

	/** Filters the data passed to the admin app as window.listenlyConfig. */
	const ADMIN_CONFIG = 'listenly/admin_config';

	/** Filters the script handles the admin app depends on. */
	const ADMIN_SCRIPT_DEPENDENCIES = 'listenly/admin_script_dependencies';

	/** Filters the player data printed on a post. */
	const PLAYER_DATA = 'listenly/player_data';

	/** Filters the allowed read-along highlight modes. Free: sentence, off. */
	const HIGHLIGHT_MODES = 'listenly/highlight_modes';

	/** Filters the text read aloud for a post. */
	const POST_TEXT = 'listenly/post_text';

	/** Filters how many seconds a live audio chunk stays cached (default 3600; 0 turns the cache off). */
	const LIVE_CACHE_TTL = 'listenly/live_cache_ttl';

	/** Filters the live audio rate limits: ['visitor' => ['max', 'window'], 'site' => ['max', 'window']]. */
	const LIVE_RATE_LIMITS = 'listenly/live_rate_limits';

	/** Filters the visitor address used for the rate limit (default REMOTE_ADDR). */
	const CLIENT_IP = 'listenly/client_ip';
}
