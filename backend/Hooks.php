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

	/** Filters the text read aloud for a post. */
	const POST_TEXT = 'listenly/post_text';
}
