<?php
/**
 * Class that handles the translations of the plugin.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

use QalaPluginManager\Interfaces\WithHooksInterface;

/**
 * Class TranslationsLoader
 *
 * @package QalaPluginManager
 */
class TranslationsLoader implements WithHooksInterface {
	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() : void {
		add_action( 'init', [ $this, 'register_t10ns' ] );
	}

	/**
	 * Register the plugin t10ns.
	 */
	public function register_t10ns() : void {
		load_plugin_textdomain(
			Plugin::get_slug(),
			false,
			basename( Plugin::get_path() ) . '/languages/'
		);
	}
}
