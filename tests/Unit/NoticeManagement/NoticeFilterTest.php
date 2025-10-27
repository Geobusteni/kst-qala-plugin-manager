<?php
/**
 * NoticeFilter Test
 *
 * Comprehensive tests for the NoticeFilter class - the CORE notice management component.
 * Tests hook registration, callback removal, allowlist integration, and logging.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;
use QalaPluginManager\NoticeManagement\NoticeFilter;
use QalaPluginManager\NoticeManagement\AllowlistManager;
use QalaPluginManager\NoticeManagement\NoticeLogger;
use QalaPluginManager\NoticeManagement\NoticeIdentifier;
use QalaPluginManager\Tests\Unit\TestCase;

/**
 * Test case for NoticeFilter class
 *
 * Covers:
 * - Hook registration on in_admin_header at priority 100000
 * - Removal of callbacks from notice hooks
 * - Integration with AllowlistManager (preserved callbacks)
 * - Integration with NoticeLogger (logged removals)
 * - All 4 notice hook types
 * - Global $wp_filter manipulation
 * - Different callback priorities
 * - Edge cases (no callbacks, empty hooks, closures)
 *
 * @group notice-management
 * @group notice-filter
 * @group unit
 */
class NoticeFilterTest extends TestCase {

	/**
	 * Mock AllowlistManager instance
	 *
	 * @var Mockery\MockInterface
	 */
	private $allowlist_mock;

	/**
	 * Mock NoticeLogger instance
	 *
	 * @var Mockery\MockInterface
	 */
	private $logger_mock;

	/**
	 * Mock NoticeIdentifier instance
	 *
	 * @var Mockery\MockInterface
	 */
	private $identifier_mock;

	/**
	 * NoticeFilter instance for testing
	 *
	 * @var NoticeFilter
	 */
	private $filter;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks for dependencies
		$this->allowlist_mock  = Mockery::mock( AllowlistManager::class );
		$this->logger_mock     = Mockery::mock( NoticeLogger::class );
		$this->identifier_mock = Mockery::mock( NoticeIdentifier::class );

		// Create NoticeFilter instance with mocked dependencies
		$this->filter = new NoticeFilter(
			$this->allowlist_mock,
			$this->logger_mock,
			$this->identifier_mock
		);

		// Mock get_current_user_id
		$this->mockGetCurrentUserId( 1 );

		// Mock get_current_blog_id
		$this->mockGetCurrentBlogId( 1 );

		// Mock get_option for global toggle
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) {
				if ( $option === 'qala_notices_enabled' ) {
					  return 'yes';
				}
				return $default;
			}
		);

		// Mock get_user_meta for per-user toggle
		Functions\when( 'get_user_meta' )->justReturn( '' );
	}

	/**
	 * Test: init() registers hooks correctly
	 *
	 * @return void
	 */
	public function test_init_registers_in_admin_header_hook(): void {
		Actions\expectAdded( 'in_admin_header' )
			->once()
			->whenHappen(
				function ( $callback, $priority ) {
					$this->assertEquals( 100000, $priority, 'Priority should be 100000' );
				}
			);

		$this->filter->init();
	}

	/**
	 * Test: init() is called when implementing WithHooksInterface
	 *
	 * @return void
	 */
	public function test_implements_with_hooks_interface(): void {
		$this->assertInstanceOf(
			\QalaPluginManager\Interfaces\WithHooksInterface::class,
			$this->filter,
			'NoticeFilter should implement WithHooksInterface'
		);
	}

	/**
	 * Test: get_notice_hooks() returns all 4 notice hook names
	 *
	 * @return void
	 */
	public function test_get_notice_hooks_returns_all_four_hooks(): void {
		$hooks = $this->filter->get_notice_hooks();

		$this->assertIsArray( $hooks, 'Should return an array' );
		$this->assertCount( 4, $hooks, 'Should return exactly 4 hooks' );
		$this->assertContains( 'admin_notices', $hooks );
		$this->assertContains( 'network_admin_notices', $hooks );
		$this->assertContains( 'user_admin_notices', $hooks );
		$this->assertContains( 'all_admin_notices', $hooks );
	}

	/**
	 * Test: filter_notices() does nothing if user has qala_full_access capability
	 *
	 * @return void
	 */
	public function test_filter_notices_skips_for_qala_full_access_users(): void {
		// Mock user with qala_full_access capability
		Functions\when( 'current_user_can' )->justReturn( true );

		// Global $wp_filter should not be accessed
		// Logger should not be called
		$this->logger_mock->shouldNotReceive( 'log_removal' );

		$this->filter->filter_notices();

		// No exceptions = success
		$this->assertTrue( true, 'Should skip filtering for users with qala_full_access' );
	}

	/**
	 * Test: filter_notices() skips if global toggle is disabled
	 *
	 * @return void
	 */
	public function test_filter_notices_skips_when_global_toggle_disabled(): void {
		// Mock global toggle as disabled
		Functions\expect( 'get_option' )
			->with( 'qala_notices_enabled', 'yes' )
			->andReturn( 'no' );

		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Logger should not be called
		$this->logger_mock->shouldNotReceive( 'log_removal' );

		$this->filter->filter_notices();

		$this->assertTrue( true, 'Should skip filtering when global toggle is disabled' );
	}

	/**
	 * Test: filter_notices() skips if user has enabled notices via meta
	 *
	 * @return void
	 */
	public function test_filter_notices_skips_when_user_meta_enabled(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Mock user meta to show notices
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( 'yes' );

		// Logger should not be called
		$this->logger_mock->shouldNotReceive( 'log_removal' );

		$this->filter->filter_notices();

		$this->assertTrue( true, 'Should skip filtering when user meta enabled' );
	}

	/**
	 * Test: filter_notices() removes callbacks from admin_notices hook
	 *
	 * @return void
	 */
	public function test_filter_notices_removes_callbacks_from_admin_notices(): void {
		// Ensure is_admin and is_user_logged_in return true
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		// Mock user without qala_full_access - use when() instead of expect() to override default
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter global with callbacks
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook(
			[
				10 => [
					'test_callback_1' => [
						'function'      => 'test_callback_function',
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist to NOT match
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->with( 'test_callback_function' )
			->andReturn( false );

		// Mock identifier to return callback name
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->with( 'test_callback_function' )
			->andReturn( 'test_callback_function' );

		// Expect logger to be called for removal
		$this->logger_mock->shouldReceive( 'log_removal' )
			->once()
			->with(
				'test_callback_function',
				'admin_notices',
				10,
				'removed',
				Mockery::any()
			);

		$this->filter->filter_notices();

		// Verify callback was removed
		$this->assertEmpty(
			$wp_filter['admin_notices']->callbacks[10],
			'Callback should be removed from admin_notices hook'
		);
	}

	/**
	 * Test: filter_notices() preserves allowlisted callbacks
	 *
	 * @return void
	 */
	public function test_filter_notices_preserves_allowlisted_callbacks(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter global with callbacks
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook(
			[
				10 => [
					'allowlisted_callback' => [
						'function'      => 'rocket_bad_deactivations',
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist to MATCH
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->with( 'rocket_bad_deactivations' )
			->andReturn( true );

		// Mock identifier to return callback name
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->with( 'rocket_bad_deactivations' )
			->andReturn( 'rocket_bad_deactivations' );

		// Expect logger to be called for allowlisted notice
		$this->logger_mock->shouldReceive( 'log_removal' )
			->once()
			->with(
				'rocket_bad_deactivations',
				'admin_notices',
				10,
				'kept_allowlisted',
				Mockery::any()
			);

		$this->filter->filter_notices();

		// Verify callback was NOT removed
		$this->assertNotEmpty(
			$wp_filter['admin_notices']->callbacks[10],
			'Allowlisted callback should be preserved'
		);
		$this->assertArrayHasKey(
			'allowlisted_callback',
			$wp_filter['admin_notices']->callbacks[10],
			'Allowlisted callback should remain in hook'
		);
	}

	/**
	 * Test: filter_notices() processes all 4 notice hooks
	 *
	 * @return void
	 */
	public function test_filter_notices_processes_all_four_hooks(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter global with callbacks on all 4 hooks
		global $wp_filter;
		$wp_filter = [];

		$hooks = [ 'admin_notices', 'network_admin_notices', 'user_admin_notices', 'all_admin_notices' ];
		foreach ( $hooks as $hook_name ) {
			$wp_filter[ $hook_name ] = $this->createMockWpFilterHook(
				[
					10 => [
						'test_callback' => [
							'function'      => 'test_function_' . $hook_name,
							'accepted_args' => 1,
						],
					],
				]
			);

			// Mock allowlist to NOT match
			$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
				->with( 'test_function_' . $hook_name )
				->andReturn( false );

			// Mock identifier
			$this->identifier_mock->shouldReceive( 'get_callback_name' )
				->with( 'test_function_' . $hook_name )
				->andReturn( 'test_function_' . $hook_name );

			// Expect logger to be called
			$this->logger_mock->shouldReceive( 'log_removal' )
				->once()
				->with(
					'test_function_' . $hook_name,
					$hook_name,
					10,
					'removed',
					Mockery::any()
				);
		}

		$this->filter->filter_notices();

		// Verify all 4 hooks processed
		foreach ( $hooks as $hook_name ) {
			$this->assertEmpty(
				$wp_filter[ $hook_name ]->callbacks[10],
				"Callbacks should be removed from {$hook_name} hook"
			);
		}
	}

	/**
	 * Test: filter_notices() handles callbacks with different priorities
	 *
	 * @return void
	 */
	public function test_filter_notices_handles_multiple_priorities(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter with callbacks at different priorities
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook(
			[
				5   => [
					'early_callback' => [
						'function'      => 'early_function',
						'accepted_args' => 1,
					],
				],
				10  => [
					'default_callback' => [
						'function'      => 'default_function',
						'accepted_args' => 1,
					],
				],
				999 => [
					'late_callback' => [
						'function'      => 'late_function',
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist to NOT match any
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->andReturn( false );

		// Mock identifier for all callbacks
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->andReturnUsing(
				function ( $callback ) {
					return $callback;
				}
			);

		// Expect logger to be called for all 3 priorities
		$this->logger_mock->shouldReceive( 'log_removal' )
			->times( 3 );

		$this->filter->filter_notices();

		// Verify all priorities processed
		$this->assertEmpty( $wp_filter['admin_notices']->callbacks[5] );
		$this->assertEmpty( $wp_filter['admin_notices']->callbacks[10] );
		$this->assertEmpty( $wp_filter['admin_notices']->callbacks[999] );
	}

	/**
	 * Test: filter_notices() handles hook with no callbacks
	 *
	 * @return void
	 */
	public function test_filter_notices_handles_empty_hook(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter with empty hook
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook( [] );

		// Logger should not be called for empty hook
		$this->logger_mock->shouldNotReceive( 'log_removal' );

		$this->filter->filter_notices();

		// No exceptions = success
		$this->assertTrue( true, 'Should handle empty hooks gracefully' );
	}

	/**
	 * Test: filter_notices() handles missing hook in $wp_filter
	 *
	 * @return void
	 */
	public function test_filter_notices_handles_missing_hook(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter with no admin_notices key
		global $wp_filter;
		$wp_filter = [];

		// Logger should not be called
		$this->logger_mock->shouldNotReceive( 'log_removal' );

		$this->filter->filter_notices();

		// No exceptions = success
		$this->assertTrue( true, 'Should handle missing hooks gracefully' );
	}

	/**
	 * Test: filter_notices() handles closure callbacks
	 *
	 * @return void
	 */
	public function test_filter_notices_handles_closures(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create closure callback
		$closure = function () {
			return 'test';
		};

		// Create mock $wp_filter with closure
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook(
			[
				10 => [
					'closure_callback' => [
						'function'      => $closure,
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist to NOT match closures
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->with( 'Closure' )
			->andReturn( false );

		// Mock identifier to return 'Closure'
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->with( $closure )
			->andReturn( 'Closure' );

		// Expect logger to be called
		$this->logger_mock->shouldReceive( 'log_removal' )
			->once()
			->with(
				'Closure',
				'admin_notices',
				10,
				'removed',
				Mockery::any()
			);

		$this->filter->filter_notices();

		// Verify closure was removed
		$this->assertEmpty(
			$wp_filter['admin_notices']->callbacks[10],
			'Closure callbacks should be removed'
		);
	}

	/**
	 * Test: filter_notices() handles class method callbacks
	 *
	 * @return void
	 */
	public function test_filter_notices_handles_class_method_callbacks(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create class method callback
		$object   = new \stdClass();
		$callback = [ $object, 'method_name' ];

		// Create mock $wp_filter with class method
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook(
			[
				10 => [
					'class_callback' => [
						'function'      => $callback,
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist to NOT match
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->with( 'stdClass::method_name' )
			->andReturn( false );

		// Mock identifier
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->with( $callback )
			->andReturn( 'stdClass::method_name' );

		// Expect logger to be called
		$this->logger_mock->shouldReceive( 'log_removal' )
			->once()
			->with(
				'stdClass::method_name',
				'admin_notices',
				10,
				'removed',
				Mockery::any()
			);

		$this->filter->filter_notices();

		// Verify class method callback was removed
		$this->assertEmpty(
			$wp_filter['admin_notices']->callbacks[10],
			'Class method callbacks should be removed'
		);
	}

	/**
	 * Test: filter_notices() removes some callbacks but keeps others based on allowlist
	 *
	 * @return void
	 */
	public function test_filter_notices_mixed_removal_and_preservation(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter with multiple callbacks
		global $wp_filter;
		$wp_filter                  = [];
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook(
			[
				10 => [
					'remove_this'     => [
						'function'      => 'remove_function',
						'accepted_args' => 1,
					],
					'keep_this'       => [
						'function'      => 'rocket_bad_deactivations',
						'accepted_args' => 1,
					],
					'remove_this_too' => [
						'function'      => 'another_remove_function',
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist - only 'rocket_bad_deactivations' matches
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->andReturnUsing(
				function ( $callback_name ) {
					return $callback_name === 'rocket_bad_deactivations';
				}
			);

		// Mock identifier
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->andReturnUsing(
				function ( $callback ) {
					return $callback;
				}
			);

		// Expect logger to be called for all 3 callbacks
		$this->logger_mock->shouldReceive( 'log_removal' )
			->times( 3 );

		$this->filter->filter_notices();

		// Verify correct callbacks removed and kept
		$this->assertArrayNotHasKey(
			'remove_this',
			$wp_filter['admin_notices']->callbacks[10],
			'Non-allowlisted callback should be removed'
		);
		$this->assertArrayHasKey(
			'keep_this',
			$wp_filter['admin_notices']->callbacks[10],
			'Allowlisted callback should be kept'
		);
		$this->assertArrayNotHasKey(
			'remove_this_too',
			$wp_filter['admin_notices']->callbacks[10],
			'Non-allowlisted callback should be removed'
		);
	}

	/**
	 * Test: should_keep_callback() returns true for allowlisted callbacks
	 *
	 * @return void
	 */
	public function test_should_keep_callback_returns_true_for_allowlisted(): void {
		$callback = 'rocket_bad_deactivations';

		// Mock allowlist to match
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->with( 'rocket_bad_deactivations' )
			->andReturn( true );

		// Mock identifier
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->with( $callback )
			->andReturn( 'rocket_bad_deactivations' );

		$result = $this->filter->should_keep_callback( $callback, 'admin_notices' );

		$this->assertTrue( $result, 'Should keep allowlisted callbacks' );
	}

	/**
	 * Test: should_keep_callback() returns false for non-allowlisted callbacks
	 *
	 * @return void
	 */
	public function test_should_keep_callback_returns_false_for_non_allowlisted(): void {
		$callback = 'some_random_callback';

		// Mock allowlist to NOT match
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->with( 'some_random_callback' )
			->andReturn( false );

		// Mock identifier
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->with( $callback )
			->andReturn( 'some_random_callback' );

		$result = $this->filter->should_keep_callback( $callback, 'admin_notices' );

		$this->assertFalse( $result, 'Should not keep non-allowlisted callbacks' );
	}

	/**
	 * Test: remove_notice_callbacks() removes all callbacks from specific hook
	 *
	 * @return void
	 */
	public function test_remove_notice_callbacks_removes_from_specific_hook(): void {
		// Create mock $wp_filter with callbacks
		global $wp_filter;
		$wp_filter                      = [];
		$wp_filter['admin_notices']     = $this->createMockWpFilterHook(
			[
				10 => [
					'callback_1' => [
						'function'      => 'test_1',
						'accepted_args' => 1,
					],
					'callback_2' => [
						'function'      => 'test_2',
						'accepted_args' => 1,
					],
				],
			]
		);
		$wp_filter['all_admin_notices'] = $this->createMockWpFilterHook(
			[
				10 => [
					'callback_3' => [
						'function'      => 'test_3',
						'accepted_args' => 1,
					],
				],
			]
		);

		// Mock allowlist to NOT match
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->andReturn( false );

		// Mock identifier
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->andReturnUsing(
				function ( $callback ) {
					return $callback;
				}
			);

		// Mock logger
		$this->logger_mock->shouldReceive( 'log_removal' )
			->times( 2 ); // Only 2 from admin_notices

		// Remove only admin_notices callbacks
		$this->filter->remove_notice_callbacks( 'admin_notices' );

		// Verify admin_notices cleared but all_admin_notices untouched
		$this->assertEmpty(
			$wp_filter['admin_notices']->callbacks[10],
			'admin_notices should be empty'
		);
		$this->assertNotEmpty(
			$wp_filter['all_admin_notices']->callbacks[10],
			'all_admin_notices should remain untouched'
		);
	}

	/**
	 * Test: performance with many callbacks
	 *
	 * @return void
	 */
	public function test_performance_with_many_callbacks(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Create mock $wp_filter with 100 callbacks
		global $wp_filter;
		$wp_filter = [];
		$callbacks = [];
		for ( $i = 0; $i < 100; $i++ ) {
			$callbacks[ "callback_{$i}" ] = [
				'function'      => "test_function_{$i}",
				'accepted_args' => 1,
			];
		}
		$wp_filter['admin_notices'] = $this->createMockWpFilterHook( [ 10 => $callbacks ] );

		// Mock allowlist to NOT match
		$this->allowlist_mock->shouldReceive( 'matches_allowlist' )
			->andReturn( false );

		// Mock identifier
		$this->identifier_mock->shouldReceive( 'get_callback_name' )
			->andReturnUsing(
				function ( $callback ) {
					return $callback;
				}
			);

		// Mock logger
		$this->logger_mock->shouldReceive( 'log_removal' )
			->times( 100 );

		$start = microtime( true );
		$this->filter->filter_notices();
		$elapsed = microtime( true ) - $start;

		// Should complete in less than 100ms
		$this->assertLessThan( 0.1, $elapsed, 'Should handle 100 callbacks efficiently' );
	}

	/**
	 * Helper: Create a mock WP_Hook object with callbacks
	 *
	 * @param array $callbacks Callbacks array organized by priority.
	 *
	 * @return object Mock WP_Hook object
	 */
	private function createMockWpFilterHook( array $callbacks ): object {
		$hook            = new \stdClass();
		$hook->callbacks = $callbacks;
		return $hook;
	}
}
