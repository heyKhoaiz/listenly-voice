<?php
/**
 * Voice provider registry.
 *
 * @package ListenlyVoice
 */

namespace ListenlyVoice\Tts;

use ListenlyVoice\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the providers. Pro adds its own through the `listenly/providers` filter.
 */
final class Registry {

	/**
	 * Providers by id, built on first use so filters added late still count.
	 *
	 * @var array<string, ProviderInterface>|null
	 */
	private $providers = null;

	/**
	 * Every provider.
	 *
	 * @return array<string, ProviderInterface>
	 */
	public function all(): array {
		if ( null === $this->providers ) {
			$base = array(
				'browser' => new BrowserProvider(),
				'google'  => new GoogleProvider(),
			);
			$list = apply_filters( Hooks::PROVIDERS, $base );

			$this->providers = array();
			foreach ( (array) $list as $provider ) {
				if ( $provider instanceof ProviderInterface ) {
					$this->providers[ $provider->id() ] = $provider;
				}
			}
		}
		return $this->providers;
	}

	/**
	 * One provider, falling back to the browser voice.
	 *
	 * @param string $id Provider id.
	 */
	public function get( string $id ): ProviderInterface {
		$all = $this->all();
		return $all[ $id ] ?? $all['browser'];
	}
}
