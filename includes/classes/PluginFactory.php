<?php
/**
 * Our plugin factory class
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

/**
 * Class PluginFactory
 *
 * @package QalaPluginManager
 */
final class PluginFactory {
	/**
	 * Get a plugin instance
	 *
	 * @return Plugin
	 */
	public function get_plugin() : Plugin {
		static $plugin = null;

		if ( $plugin === null ) {
			$plugin = new Plugin(
				new ServiceProvider()
			);
		}

		return $plugin;
	}
}
