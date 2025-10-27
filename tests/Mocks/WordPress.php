<?php
/**
 * WordPress Mock Helpers
 *
 * This file provides reusable mock helpers and constants for testing
 * WordPress functionality without requiring a full WordPress installation.
 *
 * Use these helpers in your tests to quickly set up common WordPress
 * mocking scenarios.
 *
 * @package QalaPluginManager\Tests\Mocks
 */

namespace QalaPluginManager\Tests\Mocks;

use Brain\Monkey;

/**
 * WordPress Mock Helpers Class
 *
 * Provides static methods for common WordPress mocking scenarios.
 * These can be used in any test to quickly set up WordPress function mocks.
 */
class WordPress
{
	/**
	 * Mock WordPress core constants
	 *
	 * Defines commonly used WordPress constants if they don't already exist.
	 * Safe to call multiple times.
	 *
	 * @return void
	 */
	public static function mockConstants(): void
	{
		if (!defined('ABSPATH')) {
			define('ABSPATH', '/tmp/wordpress/');
		}

		if (!defined('WP_CONTENT_DIR')) {
			define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
		}

		if (!defined('WP_PLUGIN_DIR')) {
			define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
		}

		if (!defined('WPMU_PLUGIN_DIR')) {
			define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
		}

		if (!defined('WP_CONTENT_URL')) {
			define('WP_CONTENT_URL', 'http://example.com/wp-content');
		}

		if (!defined('WP_DEBUG')) {
			define('WP_DEBUG', true);
		}

		if (!defined('WP_DEBUG_LOG')) {
			define('WP_DEBUG_LOG', false);
		}

		if (!defined('WP_DEBUG_DISPLAY')) {
			define('WP_DEBUG_DISPLAY', true);
		}

		if (!defined('NONCE_SALT')) {
			define('NONCE_SALT', 'test-nonce-salt-for-testing-only');
		}
	}

	/**
	 * Mock admin context
	 *
	 * Sets up common mocks for WordPress admin area testing.
	 * Includes is_admin(), admin_url(), etc.
	 *
	 * @return void
	 */
	public static function mockAdminContext(): void
	{
		Monkey\Functions\when('is_admin')->justReturn(true);
		Monkey\Functions\when('is_network_admin')->justReturn(false);
		Monkey\Functions\when('is_user_admin')->justReturn(false);

		// Mock admin_url() to return a test URL
		Monkey\Functions\when('admin_url')->alias(function ($path = '') {
			return 'http://example.com/wp-admin/' . ltrim($path, '/');
		});

		// Mock current_user_can() to return true by default
		Monkey\Functions\when('current_user_can')->justReturn(true);

		// Mock is_user_logged_in() to return true
		Monkey\Functions\when('is_user_logged_in')->justReturn(true);

		// Mock get_current_user_id() to return test user ID
		Monkey\Functions\when('get_current_user_id')->justReturn(1);
	}

	/**
	 * Mock multisite context
	 *
	 * Sets up common mocks for WordPress multisite testing.
	 *
	 * @param bool $is_multisite Whether this is a multisite installation
	 * @return void
	 */
	public static function mockMultisiteContext(bool $is_multisite = true): void
	{
		Monkey\Functions\when('is_multisite')->justReturn($is_multisite);

		if ($is_multisite) {
			Monkey\Functions\when('is_network_admin')->justReturn(false);
			Monkey\Functions\when('is_super_admin')->justReturn(true);
			Monkey\Functions\when('get_current_blog_id')->justReturn(1);
			Monkey\Functions\when('get_current_network_id')->justReturn(1);

			// Mock network_admin_url()
			Monkey\Functions\when('network_admin_url')->alias(function ($path = '') {
				return 'http://example.com/wp-admin/network/' . ltrim($path, '/');
			});
		}
	}

	/**
	 * Mock WordPress database globals
	 *
	 * Creates a mock $wpdb object with common properties and methods.
	 *
	 * @return \Mockery\MockInterface
	 */
	public static function mockWpdb(): \Mockery\MockInterface
	{
		$wpdb = \Mockery::mock('wpdb');

		// Set common properties
		$wpdb->prefix = 'wp_';
		$wpdb->base_prefix = 'wp_';

		// Mock prepare() to return the query (simplified)
		$wpdb->shouldReceive('prepare')->andReturnUsing(function ($query) {
			return $query;
		});

		// Mock get_results() to return empty array by default
		$wpdb->shouldReceive('get_results')->andReturn([]);

		// Mock get_var() to return null by default
		$wpdb->shouldReceive('get_var')->andReturn(null);

		// Mock get_row() to return null by default
		$wpdb->shouldReceive('get_row')->andReturn(null);

		// Mock insert() to return 1 (rows affected)
		$wpdb->shouldReceive('insert')->andReturn(1);

		// Mock update() to return 1 (rows affected)
		$wpdb->shouldReceive('update')->andReturn(1);

		// Mock delete() to return 1 (rows affected)
		$wpdb->shouldReceive('delete')->andReturn(1);

		// Mock query() to return true
		$wpdb->shouldReceive('query')->andReturn(true);

		// Mock insert_id property
		$wpdb->insert_id = 1;

		// Mock last_error property
		$wpdb->last_error = '';

		return $wpdb;
	}

	/**
	 * Mock WordPress options API
	 *
	 * Sets up mocks for get_option(), update_option(), delete_option().
	 * Options are stored in a static array during the test.
	 *
	 * @return void
	 */
	public static function mockOptionsApi(): void
	{
		static $options = [];

		// Mock get_option()
		Monkey\Functions\when('get_option')->alias(
			function ($option_name, $default = false) use (&$options) {
				return $options[$option_name] ?? $default;
			}
		);

		// Mock update_option()
		Monkey\Functions\when('update_option')->alias(
			function ($option_name, $value) use (&$options) {
				$options[$option_name] = $value;
				return true;
			}
		);

		// Mock delete_option()
		Monkey\Functions\when('delete_option')->alias(
			function ($option_name) use (&$options) {
				unset($options[$option_name]);
				return true;
			}
		);

		// Mock add_option()
		Monkey\Functions\when('add_option')->alias(
			function ($option_name, $value) use (&$options) {
				if (!isset($options[$option_name])) {
					$options[$option_name] = $value;
					return true;
				}
				return false;
			}
		);
	}

	/**
	 * Mock WordPress user meta API
	 *
	 * Sets up mocks for get_user_meta(), update_user_meta(), delete_user_meta().
	 * User meta is stored in a static array during the test.
	 *
	 * @return void
	 */
	public static function mockUserMetaApi(): void
	{
		static $user_meta = [];

		// Mock get_user_meta()
		Monkey\Functions\when('get_user_meta')->alias(
			function ($user_id, $meta_key = '', $single = false) use (&$user_meta) {
				if (empty($meta_key)) {
					return $user_meta[$user_id] ?? [];
				}

				$value = $user_meta[$user_id][$meta_key] ?? '';

				if ($single) {
					return $value;
				}

				return [$value];
			}
		);

		// Mock update_user_meta()
		Monkey\Functions\when('update_user_meta')->alias(
			function ($user_id, $meta_key, $meta_value) use (&$user_meta) {
				if (!isset($user_meta[$user_id])) {
					$user_meta[$user_id] = [];
				}
				$user_meta[$user_id][$meta_key] = $meta_value;
				return true;
			}
		);

		// Mock delete_user_meta()
		Monkey\Functions\when('delete_user_meta')->alias(
			function ($user_id, $meta_key) use (&$user_meta) {
				unset($user_meta[$user_id][$meta_key]);
				return true;
			}
		);
	}

	/**
	 * Mock WordPress transients API
	 *
	 * Sets up mocks for get_transient(), set_transient(), delete_transient().
	 * Transients are stored in a static array during the test.
	 *
	 * @return void
	 */
	public static function mockTransientsApi(): void
	{
		static $transients = [];

		// Mock get_transient()
		Monkey\Functions\when('get_transient')->alias(
			function ($transient_name) use (&$transients) {
				return $transients[$transient_name] ?? false;
			}
		);

		// Mock set_transient()
		Monkey\Functions\when('set_transient')->alias(
			function ($transient_name, $value, $expiration = 0) use (&$transients) {
				$transients[$transient_name] = $value;
				return true;
			}
		);

		// Mock delete_transient()
		Monkey\Functions\when('delete_transient')->alias(
			function ($transient_name) use (&$transients) {
				unset($transients[$transient_name]);
				return true;
			}
		);
	}

	/**
	 * Mock WordPress nonce functions
	 *
	 * Sets up mocks for wp_create_nonce(), wp_verify_nonce(), check_admin_referer().
	 * Nonces always validate successfully in tests.
	 *
	 * @return void
	 */
	public static function mockNonces(): void
	{
		// Mock wp_create_nonce() to return a test nonce
		Monkey\Functions\when('wp_create_nonce')->alias(function ($action = -1) {
			return 'test_nonce_' . md5($action);
		});

		// Mock wp_verify_nonce() to return true (valid nonce)
		Monkey\Functions\when('wp_verify_nonce')->justReturn(1);

		// Mock check_admin_referer() to return true
		Monkey\Functions\when('check_admin_referer')->justReturn(true);

		// Mock check_ajax_referer() to return true
		Monkey\Functions\when('check_ajax_referer')->justReturn(true);
	}

	/**
	 * Mock WordPress AJAX functions
	 *
	 * Sets up mocks for wp_send_json_success(), wp_send_json_error(), etc.
	 *
	 * @return void
	 */
	public static function mockAjax(): void
	{
		// Mock wp_send_json_success()
		Monkey\Functions\when('wp_send_json_success')->alias(function ($data = null) {
			throw new \Exception('wp_send_json_success: ' . json_encode($data));
		});

		// Mock wp_send_json_error()
		Monkey\Functions\when('wp_send_json_error')->alias(function ($data = null) {
			throw new \Exception('wp_send_json_error: ' . json_encode($data));
		});

		// Mock wp_send_json()
		Monkey\Functions\when('wp_send_json')->alias(function ($response) {
			throw new \Exception('wp_send_json: ' . json_encode($response));
		});
	}

	/**
	 * Mock WordPress datetime functions
	 *
	 * Sets up mocks for current_time(), gmdate(), etc.
	 *
	 * @param string $fixed_time A fixed timestamp for testing (optional)
	 * @return void
	 */
	public static function mockDatetime(string $fixed_time = '2025-10-25 12:00:00'): void
	{
		// Mock current_time()
		Monkey\Functions\when('current_time')->alias(
			function ($type, $gmt = 0) use ($fixed_time) {
				if ($type === 'timestamp') {
					return strtotime($fixed_time);
				}
				return $fixed_time;
			}
		);

		// Mock gmdate() wrapper
		Monkey\Functions\when('gmdate')->alias(function ($format, $timestamp = null) use ($fixed_time) {
			if ($timestamp === null) {
				$timestamp = strtotime($fixed_time);
			}
			return gmdate($format, $timestamp);
		});
	}

	/**
	 * Mock WordPress sanitization functions
	 *
	 * Sets up mocks for sanitize_text_field(), sanitize_email(), etc.
	 * These return the input unchanged (or slightly modified) for simplicity.
	 *
	 * @return void
	 */
	public static function mockSanitization(): void
	{
		// Mock sanitize_text_field() - return input unchanged
		Monkey\Functions\when('sanitize_text_field')->returnArg();

		// Mock sanitize_email() - return input unchanged
		Monkey\Functions\when('sanitize_email')->returnArg();

		// Mock sanitize_key() - return lowercase alphanumeric
		Monkey\Functions\when('sanitize_key')->alias(function ($key) {
			return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key));
		});

		// Mock sanitize_title() - return lowercase with hyphens
		Monkey\Functions\when('sanitize_title')->alias(function ($title) {
			return strtolower(str_replace(' ', '-', $title));
		});

		// Mock wp_kses_post() - return input unchanged (allow all HTML in tests)
		Monkey\Functions\when('wp_kses_post')->returnArg();
	}

	/**
	 * Mock WordPress escaping functions
	 *
	 * Sets up mocks for esc_html(), esc_attr(), esc_url(), etc.
	 * These return the input unchanged for simplicity in tests.
	 *
	 * @return void
	 */
	public static function mockEscaping(): void
	{
		Monkey\Functions\when('esc_html')->returnArg();
		Monkey\Functions\when('esc_attr')->returnArg();
		Monkey\Functions\when('esc_url')->returnArg();
		Monkey\Functions\when('esc_js')->returnArg();
		Monkey\Functions\when('esc_textarea')->returnArg();
		Monkey\Functions\when('esc_sql')->returnArg();
	}

	/**
	 * Mock WordPress translation functions
	 *
	 * Sets up mocks for __(), _e(), _x(), etc.
	 * These return the text unchanged (no translation in tests).
	 *
	 * @return void
	 */
	public static function mockTranslation(): void
	{
		// Mock __() - return the text
		Monkey\Functions\when('__')->returnArg(1);

		// Mock _e() - echo the text
		Monkey\Functions\when('_e')->alias(function ($text) {
			echo $text;
		});

		// Mock _x() - return the text
		Monkey\Functions\when('_x')->returnArg(1);

		// Mock _n() - return singular or plural based on number
		Monkey\Functions\when('_n')->alias(function ($single, $plural, $number) {
			return $number === 1 ? $single : $plural;
		});

		// Mock esc_html__() - return the text
		Monkey\Functions\when('esc_html__')->returnArg(1);

		// Mock esc_attr__() - return the text
		Monkey\Functions\when('esc_attr__')->returnArg(1);
	}

	/**
	 * Set up all common WordPress mocks
	 *
	 * Convenience method that sets up all common WordPress function mocks.
	 * Call this in your test setUp() method for comprehensive mocking.
	 *
	 * @return void
	 */
	public static function mockAll(): void
	{
		self::mockConstants();
		self::mockEscaping();
		self::mockTranslation();
		self::mockSanitization();
		self::mockAdminContext();
		self::mockNonces();
	}
}
