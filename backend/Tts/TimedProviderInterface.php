<?php
/**
 * A voice provider that can say when each sentence starts.
 *
 * @package PostAloud
 */

namespace PostAloud\Tts;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the player highlight the sentence being read for server-side audio.
 */
interface TimedProviderInterface extends ProviderInterface {

	/**
	 * Audio for a chunk, given as its sentences, plus when each sentence starts.
	 *
	 * @param string[] $sentences The chunk's sentences in order.
	 * @param string   $voice     Voice id.
	 * @param float    $speed     Speaking rate.
	 * @return array{audio: string, marks: array<int, float>}|\WP_Error Marks map a sentence index to its
	 *         start in seconds; sentences the provider gave no time for are left out.
	 */
	public function synthesize_timed( array $sentences, string $voice, float $speed );
}
