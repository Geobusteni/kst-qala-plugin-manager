<?php
/**
 * Smoke Test
 *
 * This test verifies that the PHPUnit and Brain Monkey setup is working correctly.
 * It's a simple test to ensure the testing infrastructure is functional.
 *
 * This file can be deleted later once real tests are in place.
 *
 * @package QalaPluginManager\Tests\Unit
 */

namespace QalaPluginManager\Tests\Unit;

use Brain\Monkey;
use QalaPluginManager\Tests\Mocks\WordPress as WPMock;

/**
 * Smoke Test Class
 *
 * Verifies the basic functionality of the test infrastructure:
 * - PHPUnit is working
 * - Brain Monkey is initialized
 * - Composer autoloading works
 * - WordPress mocks work
 * - Custom test helpers work
 */
class SmokeTest extends TestCase
{
	/**
	 * Test that PHPUnit is working
	 *
	 * The simplest possible test - just verify assertions work.
	 *
	 * @test
	 * @return void
	 */
	public function phpunit_is_working(): void
	{
		$this->assertTrue(true);
		$this->assertFalse(false);
		$this->assertEquals(1, 1);
		$this->assertNotEquals(1, 2);
	}

	/**
	 * Test that Brain Monkey is initialized
	 *
	 * Verifies that Brain Monkey setUp() was called in parent TestCase.
	 *
	 * @test
	 * @return void
	 */
	public function brain_monkey_is_initialized(): void
	{
		// Brain Monkey should be set up by TestCase::setUp()
		// If it's not, this test will fail with an error

		// Mock a simple WordPress function
		Monkey\Functions\when('test_function')->justReturn('test_value');

		// Call the mocked function
		$result = test_function();

		// Verify the mock worked
		$this->assertEquals('test_value', $result);
	}

	/**
	 * Test that Composer autoloading works
	 *
	 * Verifies that classes can be autoloaded from the plugin.
	 *
	 * @test
	 * @return void
	 */
	public function composer_autoloading_works(): void
	{
		// Verify that the Plugin class exists and can be loaded
		$this->assertTrue(class_exists('QalaPluginManager\Plugin'));

		// Verify that the TestCase class exists
		$this->assertTrue(class_exists('QalaPluginManager\Tests\Unit\TestCase'));

		// Verify that the WordPress mock helper class exists
		$this->assertTrue(class_exists('QalaPluginManager\Tests\Mocks\WordPress'));
	}

	/**
	 * Test that WordPress function mocks work
	 *
	 * Verifies that common WordPress functions are mocked by TestCase.
	 *
	 * @test
	 * @return void
	 */
	public function wordpress_function_mocks_work(): void
	{
		// These functions are mocked in TestCase::setUpCommonWordPressMocks()

		// Test esc_html()
		$this->assertEquals('test', esc_html('test'));

		// Test __() translation function
		$this->assertEquals('Hello World', __('Hello World', 'test-domain'));

		// Test is_admin()
		$this->assertTrue(is_admin());

		// Test current_user_can()
		$this->assertTrue(current_user_can('manage_options'));
	}

	/**
	 * Test that custom WordPress mock helpers work
	 *
	 * Verifies that the WordPress mock helper class works correctly.
	 *
	 * @test
	 * @return void
	 */
	public function wordpress_mock_helpers_work(): void
	{
		// Mock WordPress constants
		WPMock::mockConstants();

		// Verify constants are defined
		$this->assertTrue(defined('ABSPATH'));
		$this->assertTrue(defined('WP_CONTENT_DIR'));
		$this->assertTrue(defined('WP_PLUGIN_DIR'));

		// Mock admin context
		WPMock::mockAdminContext();

		// Verify admin functions work
		$this->assertTrue(is_admin());
		$this->assertStringContainsString('wp-admin', admin_url('index.php'));
	}

	/**
	 * Test that Brain Monkey action expectations work
	 *
	 * Verifies that we can test WordPress actions.
	 *
	 * @test
	 * @return void
	 */
	public function brain_monkey_actions_work(): void
	{
		// Expect an action to be added
		Monkey\Actions\expectAdded('init')
			->once()
			->with('my_callback', 10, 1);

		// Simulate adding the action
		add_action('init', 'my_callback', 10, 1);

		// The expectation will be verified automatically by Mockery
	}

	/**
	 * Test that Brain Monkey filter expectations work
	 *
	 * Verifies that we can test WordPress filters.
	 *
	 * @test
	 * @return void
	 */
	public function brain_monkey_filters_work(): void
	{
		// Expect a filter to be applied
		Monkey\Filters\expectApplied('the_content')
			->once()
			->with('Test content')
			->andReturn('Modified content');

		// Apply the filter
		$result = apply_filters('the_content', 'Test content');

		// Verify the result
		$this->assertEquals('Modified content', $result);
	}

	/**
	 * Test that helper methods from TestCase work
	 *
	 * Verifies that custom helper methods in TestCase are functional.
	 *
	 * @test
	 * @return void
	 */
	public function test_case_helper_methods_work(): void
	{
		// Test mockGetOption helper
		$this->mockGetOption('test_option', 'test_value');
		$this->assertEquals('test_value', get_option('test_option'));

		// Test mockGetCurrentUserId helper
		$this->mockGetCurrentUserId(42);
		$this->assertEquals(42, get_current_user_id());

		// Test mockGetCurrentBlogId helper
		$this->mockGetCurrentBlogId(3);
		$this->assertEquals(3, get_current_blog_id());
	}

	/**
	 * Test that $wpdb mocking works
	 *
	 * Verifies that we can create and use a mock $wpdb object.
	 *
	 * @test
	 * @return void
	 */
	public function wpdb_mocking_works(): void
	{
		// Create a mock $wpdb object
		$wpdb = $this->createWpdbMock();

		// Verify basic properties
		$this->assertEquals('wp_', $wpdb->prefix);

		// Verify prepare() method works
		$query = $wpdb->prepare('SELECT * FROM table WHERE id = %d', 1);
		$this->assertIsString($query);

		// Set up a specific expectation
		$wpdb->shouldReceive('get_results')
			->once()
			->andReturn([
				(object) ['id' => 1, 'name' => 'Test'],
			]);

		// Call the method
		$results = $wpdb->get_results('SELECT * FROM table');

		// Verify the results
		$this->assertIsArray($results);
		$this->assertCount(1, $results);
		$this->assertEquals(1, $results[0]->id);
		$this->assertEquals('Test', $results[0]->name);
	}

	/**
	 * Test that Mockery integration works
	 *
	 * Verifies that Mockery mocks can be created and used.
	 *
	 * @test
	 * @return void
	 */
	public function mockery_integration_works(): void
	{
		// Create a simple mock object
		$mock = \Mockery::mock('stdClass');

		// Set an expectation
		$mock->shouldReceive('someMethod')
			->once()
			->with('test_arg')
			->andReturn('test_result');

		// Call the method
		$result = $mock->someMethod('test_arg');

		// Verify the result
		$this->assertEquals('test_result', $result);

		// Mockery expectations will be verified automatically
	}

	/**
	 * Test that exceptions can be tested
	 *
	 * Verifies that we can test code that throws exceptions.
	 *
	 * @test
	 * @return void
	 */
	public function exceptions_can_be_tested(): void
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Test exception');

		throw new \Exception('Test exception');
	}

	/**
	 * Test that wp_die() throws an exception
	 *
	 * Verifies that wp_die() is mocked to throw an exception in tests.
	 *
	 * @test
	 * @return void
	 */
	public function wp_die_throws_exception(): void
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('wp_die called: Access denied');

		// This should throw an exception because wp_die is mocked
		wp_die('Access denied');
	}

	/**
	 * Test that constants are defined
	 *
	 * Verifies that important test constants are set up.
	 *
	 * @test
	 * @return void
	 */
	public function test_constants_are_defined(): void
	{
		// These should be defined in bootstrap.php
		$this->assertTrue(defined('QALA_PLUGIN_MANAGER_TESTS'));
		$this->assertTrue(defined('ABSPATH'));
		$this->assertTrue(defined('WP_DEBUG'));
		$this->assertTrue(defined('QALA_PLUGIN_DIR'));
		$this->assertTrue(defined('QALA_PLUGIN_FILE'));
	}

	/**
	 * Test that plugin directory paths exist
	 *
	 * Verifies that the plugin directory structure is accessible.
	 *
	 * @test
	 * @return void
	 */
	public function plugin_directory_exists(): void
	{
		// Verify plugin directory constant points to a real directory
		$this->assertDirectoryExists(QALA_PLUGIN_DIR);

		// Verify the main plugin file exists
		$this->assertFileExists(QALA_PLUGIN_FILE);

		// Verify the includes/classes directory exists
		$this->assertDirectoryExists(QALA_PLUGIN_DIR . 'includes/classes');
	}
}
