<?php
/**
 * Main plugin handler class.
 *
 * Also contains some useful getters for things like plugin path etc.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

use QalaPluginManager\Interfaces\WithHooksInterface;
/**
 * Class Plugin
 *
 * @package QalaPluginManager
 */
class Plugin {
	/**
	 * URL to the plugin.
	 *
	 * @var string
	 */
	protected static $plugin_url;

	/**
	 * Path to the plugin directory.
	 *
	 * @var string
	 */
	protected static $plugin_path;

	/**
	 * Plugin slug (used as ID for the enqueued assets).
	 *
	 * @var string
	 */
	protected static $plugin_slug = '';

	/**
	 * Path to the plugins views directory.
	 *
	 * @var string
	 */
	protected static $plugin_template_path = '';

	/**
	 * The service provider instance.
	 *
	 * @var ServiceProvider
	 */
	protected $service_provider;

	/**
	 * Plugin constructor.
	 *
	 * @param ServiceProvider $service_provider
	 */
	public function __construct( ServiceProvider $service_provider ) {
		$this->service_provider = $service_provider;

		// Turns "QalaPluginManager" into "qala-plugin-manager".
		self::$plugin_slug = strtolower(
			preg_replace(
				'/(?<=[a-z])([A-Z]+)/',
				'-$1',
				__NAMESPACE__
			)
		);
			// Set some useful variables.
		// Calculate paths dynamically to support both regular plugins and MU plugins
		$plugin_file = dirname( dirname( __FILE__ ) ); // Path to plugin root from includes/classes

		// Determine if running as MU plugin or regular plugin
		if ( defined( 'WPMU_PLUGIN_DIR' ) && strpos( $plugin_file, WPMU_PLUGIN_DIR ) !== false ) {
			// Running as MU plugin
			self::$plugin_path = $plugin_file;
			self::$plugin_url  = str_replace(
				untrailingslashit( ABSPATH ),
				untrailingslashit( site_url() ),
				$plugin_file
			);
		} else {
			// Running as regular plugin
			self::$plugin_url  = dirname( dirname( untrailingslashit( plugins_url( '/', __FILE__ ) ) ) );
			self::$plugin_path = dirname( dirname( untrailingslashit( plugin_dir_path( __FILE__ ) ) ) );
		}

		self::$plugin_template_path = trailingslashit( self::$plugin_path ) . 'views';

		/**
		 * Hey there fellow Angryite!
		 *
		 * Pleasez DO NOT add hooks here! Read the readme for instructions
		 * on how to create a new class where you can register your hooks.
		 */
		$this->register_classes();
	}

	/**
	 * The path to the main directory.
	 *
	 * @return string
	 */
	public static function get_path() : string {
		return self::$plugin_path;
	}

	/**
	 * The path to the template directory.
	 *
	 * @return string
	 */
	public static function get_template_path() : string {
		return self::$plugin_template_path;
	}

	/**
	 * The URL to the plugin directory.
	 *
	 * @return string
	 */
	public static function get_url() : string {
		return self::$plugin_url;
	}

	/**
	 * The plugin slug.
	 *
	 * @return string
	 */
	public static function get_slug() : string {
		return self::$plugin_slug;
	}

	/**
	 * Register the hooks for the plugin classes where appropriate.
	 *
	 * @return void
	 */
	protected function register_classes() : void {
		// Register regular classes (simple instantiation)
		$classes = $this->service_provider->get_registered_classes();
		if ( ! empty( $classes ) ) {
			foreach ( $classes as $class ) {
				$instance = new $class();

				if ( $instance instanceof WithHooksInterface ) {
					$instance->init();
				}
			}
		}

		// Register Notice Management components (with dependency injection)
		$notice_components = $this->service_provider->get_notice_management_components();
		if ( ! empty( $notice_components ) ) {
			foreach ( $notice_components as $instance ) {
				if ( $instance instanceof WithHooksInterface ) {
					$instance->init();
				}
			}
		}
	}
}
