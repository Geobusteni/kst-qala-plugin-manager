<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for unit and integration tests.
 * - Unit tests: Use Brain Monkey for mocking WordPress functions
 * - Integration tests: Use WordPress test suite
 *
 * @package QalaPluginManager\Tests
 */

// Define test mode constants
define('QALA_PLUGIN_MANAGER_TESTS', true);
define('ABSPATH', '/tmp/wordpress/');
define('WP_DEBUG', true);

// Define plugin paths
// The plugin source is located in sources/qala-manager/qala-plugin-manager/
define('QALA_PLUGIN_DIR', dirname(__DIR__) . '/sources/qala-manager/qala-plugin-manager/');
define('QALA_PLUGIN_FILE', QALA_PLUGIN_DIR . 'index.php');

// Determine if running WordPress integration tests
$is_integration_test = isset($_SERVER['argv']) && in_array('--testsuite=integration', $_SERVER['argv'], true);

if ($is_integration_test) {
	// ========================================
	// Integration Tests: WordPress Test Suite
	// ========================================

	$wp_tests_dir = getenv('WP_TESTS_DIR');

	if (!$wp_tests_dir) {
		$wp_tests_dir = '/tmp/wordpress-tests-lib';
	}

	if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
		echo "WordPress test suite not found at: {$wp_tests_dir}\n";
		echo "Please install it first. See: https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/\n";
		exit(1);
	}

	// Give access to tests_add_filter() function
	require_once $wp_tests_dir . '/includes/functions.php';

	/**
	 * Manually load the plugin being tested
	 */
	function _manually_load_qala_plugin() {
		require QALA_PLUGIN_FILE;
	}
	tests_add_filter('muplugins_loaded', '_manually_load_qala_plugin');

	// Start up the WP testing environment
	require $wp_tests_dir . '/includes/bootstrap.php';

} else {
	// ========================================
	// Unit Tests: Brain Monkey Setup
	// ========================================

	// Load Composer autoloader from the ROOT vendor directory to avoid conflicts
	$root_autoloader_path = dirname(__DIR__) . '/dependencies/vendor/autoload.php';

	if (!file_exists($root_autoloader_path)) {
		echo "Composer autoloader not found at: {$root_autoloader_path}\n";
		echo "Please run 'composer install' in the project root first.\n";
		exit(1);
	}

	// Load root autoloader (this avoids the duplicate autoloader class issue)
	if (!class_exists('Composer\Autoload\ClassLoader')) {
		$autoloader = require_once $root_autoloader_path;
	} else {
		// Get the existing autoloader instance
		$autoloader = require $root_autoloader_path;
	}

	// Add PSR-4 autoloading for plugin classes (if not already set up)
	if (is_object($autoloader) && method_exists($autoloader, 'addPsr4')) {
		$autoloader->addPsr4('QalaPluginManager\\', QALA_PLUGIN_DIR . 'includes/classes');
		$autoloader->addPsr4('QalaPluginManager\\Tests\\', __DIR__);
	}

	// Brain Monkey initialization happens in individual test classes
	// Each test class should call \Brain\Monkey\setUp() in its setUp() method
	// and \Brain\Monkey\tearDown() in its tearDown() method

	// Define commonly needed WordPress constants for testing
	if (!defined('WP_CONTENT_DIR')) {
		define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
	}

	if (!defined('WP_PLUGIN_DIR')) {
		define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
	}

	if (!defined('WPMU_PLUGIN_DIR')) {
		define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
	}

	// Define test-specific constants
	if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
		define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', false);
	}
}

// Echo confirmation message (helpful for debugging)
if (defined('PHPUNIT_COMPOSER_INSTALL')) {
	$test_type = $is_integration_test ? 'integration' : 'unit';
	echo "\nQala Plugin Manager Test Bootstrap Loaded ({$test_type} tests)\n";
}
