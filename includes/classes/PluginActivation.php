<?php
/**
 * Class that handles the activation of plugins.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

use QalaPluginManager\NoticeManagement\DatabaseMigration;

/**
 * Class PluginActivation
 *
 * @package QalaPluginManager
 */
class PluginActivation {
	/**
	 * Holds the instance.
	 *
	 * @var PluginConfigurations
	 */
	private PluginConfigurations $plugin_configurations;

	/**
	 * Database migration instance.
	 *
	 * @var DatabaseMigration
	 */
	private DatabaseMigration $database_migration;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->plugin_configurations = new PluginConfigurations();
		$this->database_migration    = new DatabaseMigration();
		add_action( 'muplugins_loaded', [ $this, 'check_plugins_for_activation' ] );
		add_action( 'muplugins_loaded', [ $this, 'run_database_migrations' ], 1 );
	}

	/**
	 * Run database migrations for notice management
	 *
	 * Executes at priority 1 on muplugins_loaded to ensure tables exist
	 * before other components attempt to use them.
	 *
	 * @return void
	 */
	public function run_database_migrations(): void {
		// Only run migrations if needed
		if ( $this->database_migration->needs_migration() ) {
			$this->database_migration->run_migrations();
		}
	}

	/**
	 * Performs the initial check if plugins should be deactivated.
	 *
	 * @return void
	 */
	public function check_plugins_for_activation() : void {
		$environment = wp_get_environment_type();

		$activate_plugins = $this->plugin_configurations->get_configuration( 'activate', $environment );
		if ( empty( $activate_plugins ) ) {
			return;
		}

		$this->activate_plugins( $activate_plugins );
	}


	/**
	 * This function acts as a wrapper for WP for activation of plugins programmatically.
	 *
	 * @param array $plugin_slugs
	 *
	 * @return void
	 */
	public function activate_plugins( array $plugin_slugs ) {
		\activate_plugins( $plugin_slugs, '', false, true );
	}
}
