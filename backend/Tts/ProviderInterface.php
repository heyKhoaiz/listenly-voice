<?php
/**
 * A voice provider.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * Turns text into speech. Free ships the browser and Google providers; Pro adds more
 * through the `listenly/providers` filter.
 */
interface ProviderInterface {

	/** Stable id saved in settings, e.g. "google". */
	public function id(): string;

	/** Name shown in the admin. */
	public function label(): string;

	/** True when the voice is spoken by the visitor's browser, not by a server API. */
	public function is_client_side(): bool;

	/** True when the provider has what it needs (an API key, for example). */
	public function is_ready(): bool;

	/**
	 * Voices: list of array{id: string, label: string, language: string}.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function voices(): array;

	/**
	 * MP3 bytes for the text, or a WP_Error.
	 *
	 * @param string $text  Plain text, at most chunk_limit() characters.
	 * @param string $voice Voice id.
	 * @param float  $speed 0.5 to 2.
	 * @return string|\WP_Error
	 */
	public function synthesize( string $text, string $voice, float $speed );

	/** The most characters one synthesize() call accepts. */
	public function chunk_limit(): int;
}
