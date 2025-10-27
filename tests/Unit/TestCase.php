<?php
/**
 * Base TestCase for Unit Tests
 *
 * This class provides common setup and teardown functionality for all unit tests.
 * All unit test classes should extend this class to ensure proper Brain Monkey
 * initialization and cleanup.
 *
 * @package QalaPluginManager\Tests\Unit
 */

namespace QalaPluginManager\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Base test case class for unit tests
 *
 * Provides:
 * - Brain Monkey setup and teardown
 * - Mockery integration
 * - Common WordPress function mocks
 * - Helper methods for testing
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * This trait integrates Mockery with PHPUnit
	 * Ensures Mockery expectations are verified after each test
	 */
	use MockeryPHPUnitIntegration;

	/**
	 * Set up the test environment before each test
	 *
	 * This method is called before each test method is run.
	 * It initializes Brain Monkey and sets up common WordPress mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Initialize Brain Monkey
		Monkey\setUp();

		// Set up common WordPress functions that are frequently used
		$this->setUpCommonWordPressMocks();
	}

	/**
	 * Tear down the test environment after each test
	 *
	 * This method is called after each test method completes.
	 * It tears down Brain Monkey and Mockery.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Tear down Brain Monkey
		Monkey\tearDown();

		parent::tearDown();
	}

	/**
	 * Set up common WordPress function mocks
	 *
	 * These are the most commonly used WordPress functions that appear
	 * throughout the plugin. We provide sensible defaults here.
	 *
	 * Tests can override these by calling Monkey\Functions\when() again.
	 *
	 * @return void
	 */
	protected function setUpCommonWordPressMocks(): void {
		// Define WordPress constants if not already defined
		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}
		if ( ! defined( 'OBJECT' ) ) {
			define( 'OBJECT', 'OBJECT' );
		}
		if ( ! defined( 'ARRAY_N' ) ) {
			define( 'ARRAY_N', 'ARRAY_N' );
		}

		// Mock esc_html() - returns the input unchanged
		Monkey\Functions\when( 'esc_html' )->returnArg();

		// Mock esc_attr() - returns the input unchanged
		Monkey\Functions\when( 'esc_attr' )->returnArg();

		// Mock esc_url() - returns the input unchanged
		Monkey\Functions\when( 'esc_url' )->returnArg();

		// Mock esc_html__() - returns the first argument (the text)
		Monkey\Functions\when( 'esc_html__' )->returnArg( 1 );

		// Mock __() - returns the first argument (the text)
		Monkey\Functions\when( '__' )->returnArg( 1 );

		// Mock _e() - echoes the first argument (the text)
		Monkey\Functions\when( '_e' )->alias(
			function ( $text ) {
				echo $text;
			}
		);

		// Mock sanitize_text_field() - returns the input unchanged for tests
		Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

		// Mock wp_unslash() - strips slashes (simulate WordPress behavior)
		Monkey\Functions\when( 'wp_unslash' )->alias(
			function ( $value ) {
				return is_string( $value ) ? stripslashes( $value ) : $value;
			}
		);

		// Mock current_user_can() - defaults to true (tests can override)
		Monkey\Functions\when( 'current_user_can' )->justReturn( true );

		// Mock is_admin() - defaults to true for admin context tests
		Monkey\Functions\when( 'is_admin' )->justReturn( true );

		// Mock is_user_logged_in() - defaults to true
		Monkey\Functions\when( 'is_user_logged_in' )->justReturn( true );

		// Mock wp_die() - throws an exception so we can test it
		Monkey\Functions\when( 'wp_die' )->alias(
			function ( $message = '' ) {
				throw new \Exception( 'wp_die called: ' . $message );
			}
		);
	}

	/**
	 * Helper: Mock a WordPress filter
	 *
	 * Convenience method for setting up filter expectations.
	 *
	 * @param string $filter_name The filter name
	 * @param mixed  $return_value The value to return (defaults to first argument)
	 * @return void
	 */
	protected function mockFilter( string $filter_name, $return_value = null ): void {
		if ( $return_value === null ) {
			Monkey\Filters\expectApplied( $filter_name )
				->andReturnFirstArg();
		} else {
			Monkey\Filters\expectApplied( $filter_name )
				->andReturn( $return_value );
		}
	}

	/**
	 * Helper: Mock a WordPress action
	 *
	 * Convenience method for setting up action expectations.
	 *
	 * @param string $action_name The action name
	 * @param int    $times Number of times action should be called (optional)
	 * @return void
	 */
	protected function mockAction( string $action_name, int $times = 1 ): void {
		Monkey\Actions\expectDone( $action_name )
			->times( $times );
	}

	/**
	 * Helper: Assert that a WordPress action was added
	 *
	 * Verifies that add_action() was called with the expected parameters.
	 *
	 * @param string $hook The hook name
	 * @param mixed  $callback The callback (can be string, array, or closure)
	 * @param int    $priority The priority (default: 10)
	 * @param int    $accepted_args The number of accepted arguments (default: 1)
	 * @return void
	 */
	protected function assertActionAdded(
		string $hook,
		$callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		Monkey\Actions\expectAdded( $hook )
			->with( $callback, $priority, $accepted_args )
			->once();
	}

	/**
	 * Helper: Assert that a WordPress filter was added
	 *
	 * Verifies that add_filter() was called with the expected parameters.
	 *
	 * @param string $hook The hook name
	 * @param mixed  $callback The callback (can be string, array, or closure)
	 * @param int    $priority The priority (default: 10)
	 * @param int    $accepted_args The number of accepted arguments (default: 1)
	 * @return void
	 */
	protected function assertFilterAdded(
		string $hook,
		$callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		Monkey\Filters\expectAdded( $hook )
			->with( $callback, $priority, $accepted_args )
			->once();
	}

	/**
	 * Helper: Mock get_option()
	 *
	 * Sets up a mock for WordPress get_option() function.
	 *
	 * @param string $option_name The option name
	 * @param mixed  $return_value The value to return
	 * @return void
	 */
	protected function mockGetOption( string $option_name, $return_value ): void {
		Monkey\Functions\expect( 'get_option' )
			->with( $option_name )
			->andReturn( $return_value );
	}

	/**
	 * Helper: Mock update_option()
	 *
	 * Sets up a mock for WordPress update_option() function.
	 *
	 * @param string $option_name The option name
	 * @param bool   $return_value Whether the update succeeds (default: true)
	 * @return void
	 */
	protected function mockUpdateOption( string $option_name, bool $return_value = true ): void {
		Monkey\Functions\expect( 'update_option' )
			->once()
			->with( $option_name, \Mockery::any() )
			->andReturn( $return_value );
	}

	/**
	 * Helper: Mock get_user_meta()
	 *
	 * Sets up a mock for WordPress get_user_meta() function.
	 *
	 * @param int    $user_id The user ID
	 * @param string $meta_key The meta key
	 * @param mixed  $return_value The value to return
	 * @return void
	 */
	protected function mockGetUserMeta( int $user_id, string $meta_key, $return_value ): void {
		Monkey\Functions\expect( 'get_user_meta' )
			->with( $user_id, $meta_key, true )
			->andReturn( $return_value );
	}

	/**
	 * Helper: Mock update_user_meta()
	 *
	 * Sets up a mock for WordPress update_user_meta() function.
	 *
	 * @param int    $user_id The user ID
	 * @param string $meta_key The meta key
	 * @param bool   $return_value Whether the update succeeds (default: true)
	 * @return void
	 */
	protected function mockUpdateUserMeta(
		int $user_id,
		string $meta_key,
		bool $return_value = true
	): void {
		Monkey\Functions\expect( 'update_user_meta' )
			->once()
			->with( $user_id, $meta_key, \Mockery::any() )
			->andReturn( $return_value );
	}

	/**
	 * Helper: Mock current_user_can() for specific capability
	 *
	 * Override the default current_user_can() mock for a specific capability.
	 *
	 * @param string $capability The capability to check
	 * @param bool   $can_user Whether the user has the capability
	 * @return void
	 */
	protected function mockCurrentUserCan( string $capability, bool $can_user ): void {
		Monkey\Functions\expect( 'current_user_can' )
			->with( $capability )
			->andReturn( $can_user );
	}

	/**
	 * Helper: Mock get_current_user_id()
	 *
	 * Sets up a mock for WordPress get_current_user_id() function.
	 *
	 * @param int $user_id The user ID to return
	 * @return void
	 */
	protected function mockGetCurrentUserId( int $user_id ): void {
		Monkey\Functions\when( 'get_current_user_id' )->justReturn( $user_id );
	}

	/**
	 * Helper: Mock get_current_blog_id()
	 *
	 * Sets up a mock for WordPress get_current_blog_id() function.
	 *
	 * @param int $blog_id The blog ID to return
	 * @return void
	 */
	protected function mockGetCurrentBlogId( int $blog_id ): void {
		Monkey\Functions\when( 'get_current_blog_id' )->justReturn( $blog_id );
	}

	/**
	 * Helper: Create a mock global $wpdb object
	 *
	 * Returns a Mockery mock of the wpdb class for database testing.
	 *
	 * @return \Mockery\MockInterface
	 */
	protected function createWpdbMock(): \Mockery\MockInterface {
		$wpdb         = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $query ) {
				return $query;
			}
		);

		return $wpdb;
	}
}
