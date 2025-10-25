<?php
/**
 * Class that handles the configurations for plugins.
 * This should likely be extended at some point with some hooks and maybe even an admin interface?
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

/**
 * Class PluginConfigurations
 *
 * @package QalaPluginManager
 */
class PluginConfigurations {
	/**
	 * Holds a cache of the configuration for this class instance.
	 * Not very useful right now but if we add hooks etc. it will be.
	 *
	 * @var array
	 */
	private array $configuration_cache = [];

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->set_configuration();
	}

	/**
	 * Sets our initial configuration of plugin management.
	 *
	 * @return void
	 */
	public function set_configuration() : void {
		$deactivation_plugins = [
			'production'  => [
				'code-snippets/code-snippets.php', // https://wordpress.org/plugins/code-snippets/.
			],
			'staging'     => [],
			'development' => [],
			'local'       => [],
		];

		$activation_plugins = [
			'production'  => [],
			'staging'     => [
				'code-snippets/code-snippets.php', // https://wordpress.org/plugins/code-snippets/.
			],
			'development' => [],
			'local'       => [],
		];

		$all_configurations = [
			'deactivate' => $deactivation_plugins,
			'activate'   => $activation_plugins,
		];

		/**
		 * Allows for filtering all configurations when they are set.
		 *
		 * Use this to modify all configurations when they are set the first time.
		 *
		 * @param array $all_configurations the configuration array.
		 */
		$all_configurations        = apply_filters( 'qala_plugin_manager/filter/all_configurations', $all_configurations );
		$this->configuration_cache = $all_configurations;
	}

	/**
	 * Returns the desired configuration.
	 *
	 * @param string $type
	 * @param string $env
	 *
	 * @return array
	 */
	public function get_configuration( string $type = 'deactivate', string $env = 'production' ) : array {
		if ( empty( $this->configuration_cache ) ) {
			$this->set_configuration();
		}

		$configuration = $this->configuration_cache[ $type ][ $env ] ?? [];

		/**
		 * Allows for filtering specific configuration when they are get.
		 *
		 * @param array $configuration the configuration array.
		 * @param string $type the type of config we are getting, for example "deactivate".
		 * @param string $env the environment we are in, for example "production".
		 */
		$configuration = apply_filters( 'qala_plugin_manager/filter/get_configuration', $configuration, $type, $env );

		// Ensure the individual plugins exist before returning.
		$configuration = array_filter(
			$configuration,
      function ( $plugin ) {
        return file_exists( WP_PLUGIN_DIR. '/'. $plugin );
      }
		);

		return $configuration;
	}
}
