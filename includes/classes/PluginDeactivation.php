<?php
/**
 * Class that handles the deactivation of plugins.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

/**
 * Class PluginDeactivation
 *
 * @package QalaPluginManager
 */
class PluginDeactivation {
	/**
	 * Holds the instance.
	 *
	 * @var PluginConfigurations
	 */
	private PluginConfigurations $plugin_configurations;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->plugin_configurations = new PluginConfigurations();
		add_action( 'muplugins_loaded', [ $this, 'check_plugins_for_deactivation' ] );
	}

	/**
	 * Performs the initial check if plugins should be deactivated.
	 *
	 * @return void
	 */
	public function check_plugins_for_deactivation() : void {
		$environment = wp_get_environment_type();

		$deactivate_plugins = $this->plugin_configurations->get_configuration( 'deactivate', $environment );
		if ( empty( $deactivate_plugins ) ) {
			return;
		}

		$this->deactivate_plugins( $deactivate_plugins );
	}


	/**
	 * This function acts as a wrapper for WP for deactivation of plugins programmatically.
	 *
	 * @param array $plugin_slugs
	 *
	 * @return void
	 */
	public function deactivate_plugins( array $plugin_slugs ) {
		\deactivate_plugins( $plugin_slugs, true );
	}
}
