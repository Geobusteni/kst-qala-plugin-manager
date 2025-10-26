<?php
/**
 * Plugin Name: Qala Plugin Manager
 * Plugin URI: https://angrycreative.se
 * Description: Plugin management and comprehensive admin notice control with nuclear hide-all approach
 * Version: 1.0.7
 * Author: Angry Creative
 * Author URI: https://angrycreative.com
 * License: GPL2
 * Text Domain: qala-plugin-manager
 *
 * @package QalaPluginManager
 */

if ( file_exists( __DIR__ . '/dependencies/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/dependencies/vendor/autoload.php';
}

/**
 * initialize_ac_plugin_boilerplate
 *
 * Boots up our plugin.
 */
function initialize_ac_plugin_boilerplate() {
	( new QalaPluginManager\PluginFactory() )->get_plugin();
}

// Only run when WP is installed and not during WP CLI.
if ( ! wp_installing() && ( ! defined( 'WP_CLI' ) || WP_CLI === false ) ) {
	initialize_ac_plugin_boilerplate();
}
