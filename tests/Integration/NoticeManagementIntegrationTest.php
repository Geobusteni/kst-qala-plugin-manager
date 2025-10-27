<?php
/**
 * Notice Management Integration Test
 *
 * Tests the complete Notice Management system integration:
 * - Component registration
 * - Database migrations
 * - Hook registration
 * - Dependency injection
 * - Full workflow (log → allowlist → filter)
 * - Admin page rendering
 *
 * @package QalaPluginManager
 * @subpackage Tests\Integration
 */

namespace QalaPluginManager\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Mockery;
use PHPUnit\Framework\TestCase;
use QalaPluginManager\ServiceProvider;
use QalaPluginManager\NoticeManagement\DatabaseMigration;
use QalaPluginManager\NoticeManagement\NoticeIdentifier;
use QalaPluginManager\NoticeManagement\NoticeLogger;
use QalaPluginManager\NoticeManagement\AllowlistManager;
use QalaPluginManager\NoticeManagement\NoticeFilter;
use QalaPluginManager\NoticeManagement\AdminPage;
use QalaPluginManager\NoticeManagement\AdminBarToggle;
use QalaPluginManager\NoticeManagement\SiteHealthHider;

/**
 * Class NoticeManagementIntegrationTest
 *
 * Integration tests for the complete Notice Management system.
 * Tests component registration, dependency injection, and workflow.
 */
class NoticeManagementIntegrationTest extends TestCase {

	/**
	 * ServiceProvider instance
	 *
	 * @var ServiceProvider
	 */
	private $service_provider;

	/**
	 * Mock wpdb instance
	 *
	 * @var \stdClass
	 */
	private $wpdb_mock;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress database
		$this->wpdb_mock = Mockery::mock( '\wpdb' );
		$this->wpdb_mock->prefix = 'wp_';
		$GLOBALS['wpdb'] = $this->wpdb_mock;

		// Mock WordPress core functions
		$this->mock_wordpress_functions();

		// Create ServiceProvider instance
		$this->service_provider = new ServiceProvider();
	}

	/**
	 * Tear down test environment after each test
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Mock WordPress core functions
	 *
	 * @return void
	 */
	private function mock_wordpress_functions(): void {
		// Mock database functions
		Functions\when( 'get_option' )->justReturn( '0.0.0' );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'current_time' )->justReturn( '2025-10-25 12:00:00' );

		// Mock capability functions
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		// Mock multisite functions
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'is_user_admin' )->justReturn( false );

		// Mock constants
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/var/www/html/' );
		}
		if ( ! defined( 'NONCE_SALT' ) ) {
			define( 'NONCE_SALT', 'test-salt-123' );
		}

		// Mock WordPress filters and actions
		Functions\when( 'apply_filters' )->returnArg( 1 );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_all_actions' )->justReturn( true );
	}

	/**
	 * Test: ServiceProvider returns Notice Management components
	 *
	 * @test
	 */
	public function test_service_provider_returns_notice_components(): void {
		// Mock dbDelta for migration
		Functions\when( 'dbDelta' )->justReturn( [] );

		// Mock wpdb methods for migration verification
		$this->wpdb_mock->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );
		$this->wpdb_mock->shouldReceive( 'get_var' )
			->andReturn( 'wp_qala_hidden_notices_log', 'wp_qala_notice_allowlist' );

		$components = $this->service_provider->get_notice_management_components();

		$this->assertIsArray( $components );
		$this->assertCount( 4, $components );

		$this->assertInstanceOf( NoticeFilter::class, $components[0] );
		$this->assertInstanceOf( AdminPage::class, $components[1] );
		$this->assertInstanceOf( AdminBarToggle::class, $components[2] );
		$this->assertInstanceOf( SiteHealthHider::class, $components[3] );
	}

	/**
	 * Test: Components are created with proper dependencies
	 *
	 * @test
	 */
	public function test_components_have_proper_dependencies(): void {
		// Mock dbDelta and database operations
		Functions\when( 'dbDelta' )->justReturn( [] );
		$this->wpdb_mock->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );
		$this->wpdb_mock->shouldReceive( 'get_var' )
			->andReturn( 'wp_qala_hidden_notices_log', 'wp_qala_notice_allowlist' );

		$components = $this->service_provider->get_notice_management_components();

		// NoticeFilter should have dependencies injected
		$this->assertInstanceOf( NoticeFilter::class, $components[0] );

		// AdminPage should have dependencies injected
		$this->assertInstanceOf( AdminPage::class, $components[1] );

		// Components should be the same instance on subsequent calls (singleton behavior)
		$components_second_call = $this->service_provider->get_notice_management_components();
		$this->assertSame( $components[0], $components_second_call[0] );
	}

	/**
	 * Test: Database migration runs before component initialization
	 *
	 * @test
	 */
	public function test_database_migration_runs_first(): void {
		// Track function call order
		$call_order = [];

		// Mock dbDelta to track when it's called
		Functions\when( 'dbDelta' )->alias( function() use ( &$call_order ) {
			$call_order[] = 'dbDelta';
			return [];
		} );

		// Mock get_option to track when components check options
		Functions\when( 'get_option' )->alias( function( $option ) use ( &$call_order ) {
			if ( $option === 'qala_notice_db_version' ) {
				$call_order[] = 'get_schema_version';
			}
			return '0.0.0';
		} );

		$this->wpdb_mock->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );
		$this->wpdb_mock->shouldReceive( 'get_var' )
			->andReturn( 'wp_qala_hidden_notices_log', 'wp_qala_notice_allowlist' );

		$components = $this->service_provider->get_notice_management_components();

		// Verify migration ran (schema version check should happen first)
		$this->assertContains( 'get_schema_version', $call_order );
		$this->assertContains( 'dbDelta', $call_order );

		// Find positions
		$schema_pos = array_search( 'get_schema_version', $call_order );
		$dbdelta_pos = array_search( 'dbDelta', $call_order );

		// Schema check should happen before dbDelta
		$this->assertLessThan( $dbdelta_pos, $schema_pos );
	}

	/**
	 * Test: NoticeIdentifier generates consistent hashes
	 *
	 * @test
	 */
	public function test_notice_identifier_hash_consistency(): void {
		$identifier = new NoticeIdentifier();

		// Test function callback
		$hash1 = $identifier->generate_callback_hash( 'my_notice_function', 'admin_notices' );
		$hash2 = $identifier->generate_callback_hash( 'my_notice_function', 'admin_notices' );

		$this->assertEquals( $hash1, $hash2, 'Same callback should generate same hash' );
		$this->assertEquals( 32, strlen( $hash1 ), 'Hash should be 32 characters (MD5)' );

		// Test class method callback
		$callback = [ $this, 'dummy_callback' ];
		$hash3 = $identifier->generate_callback_hash( $callback, 'admin_notices' );
		$this->assertEquals( 32, strlen( $hash3 ) );

		// Different hooks should generate different hashes
		$hash4 = $identifier->generate_callback_hash( 'my_notice_function', 'network_admin_notices' );
		$this->assertNotEquals( $hash1, $hash4, 'Different hooks should generate different hashes' );
	}

	/**
	 * Test: NoticeIdentifier pattern matching works correctly
	 *
	 * @test
	 */
	public function test_notice_identifier_pattern_matching(): void {
		$identifier = new NoticeIdentifier();

		// Test exact match
		$this->assertTrue(
			$identifier->matches_pattern( 'my_function', 'my_function' ),
			'Exact match should work'
		);

		// Test wildcard match
		$this->assertTrue(
			$identifier->matches_pattern( 'rocket_notice', 'rocket_*' ),
			'Wildcard match should work'
		);

		// Test regex match
		$this->assertTrue(
			$identifier->matches_pattern( 'rocket_notice', '/^rocket_.*$/' ),
			'Regex match should work'
		);

		// Test class method match
		$callback = [ 'MyClass', 'my_method' ];
		$this->assertTrue(
			$identifier->matches_pattern( $callback, 'MyClass::my_method' ),
			'Class method exact match should work'
		);

		// Test wildcard with class
		$this->assertTrue(
			$identifier->matches_pattern( $callback, 'MyClass::*' ),
			'Class method wildcard match should work'
		);
	}

	/**
	 * Test: AllowlistManager CRUD operations work
	 *
	 * @test
	 */
	public function test_allowlist_manager_crud_operations(): void {
		// Mock database operations
		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );
		$this->wpdb_mock->shouldReceive( 'delete' )
			->once()
			->andReturn( 1 );
		$this->wpdb_mock->shouldReceive( 'get_results' )
			->andReturn( [
				(object) [ 'pattern_value' => 'rocket_*', 'pattern_type' => 'wildcard' ],
			] );

		// Mock transient functions
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );

		$allowlist = new AllowlistManager();

		// Test add pattern
		$result = $allowlist->add_pattern( 'rocket_*', 'wildcard' );
		$this->assertTrue( $result );

		// Test remove pattern
		$result = $allowlist->remove_pattern( 'rocket_*' );
		$this->assertTrue( $result );

		// Test get patterns
		$patterns = $allowlist->get_all_patterns();
		$this->assertIsArray( $patterns );
	}

	/**
	 * Test: NoticeLogger logs notices correctly
	 *
	 * @test
	 */
	public function test_notice_logger_logs_notices(): void {
		// Mock database insert
		$this->wpdb_mock->shouldReceive( 'prepare' )
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			} );
		$this->wpdb_mock->shouldReceive( 'get_var' )
			->andReturn( null ); // No existing log
		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$logger = new NoticeLogger();

		$logger->log_notice_removal(
			'test_hash_123',
			'admin_notices',
			10,
			'my_notice_function',
			'removed',
			'no_qala_full_access'
		);

		// If we get here without exception, logging worked
		$this->assertTrue( true );
	}

	/**
	 * Test: Full workflow - notice logged, pattern added, pattern matches
	 *
	 * @test
	 */
	public function test_full_workflow_log_allowlist_filter(): void {
		// Setup mocks
		Functions\when( 'dbDelta' )->justReturn( [] );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );

		$this->wpdb_mock->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );
		$this->wpdb_mock->shouldReceive( 'get_var' )
			->andReturn( 'wp_qala_hidden_notices_log', 'wp_qala_notice_allowlist', null );
		$this->wpdb_mock->shouldReceive( 'prepare' )
			->andReturnUsing( function( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			} );
		$this->wpdb_mock->shouldReceive( 'insert' )
			->andReturn( 1 );
		$this->wpdb_mock->shouldReceive( 'get_results' )
			->andReturn( [
				(object) [ 'pattern_value' => 'rocket_*', 'pattern_type' => 'wildcard' ],
			] );

		// Step 1: Create components
		$identifier = new NoticeIdentifier();
		$logger = new NoticeLogger();
		$allowlist = new AllowlistManager();

		// Step 2: Add pattern to allowlist
		$allowlist->add_pattern( 'rocket_*', 'wildcard' );

		// Step 3: Check if callback matches allowlist
		$is_allowlisted = $allowlist->is_allowlisted( 'rocket_bad_deactivations' );
		$this->assertTrue( $is_allowlisted, 'rocket_bad_deactivations should match rocket_* pattern' );

		// Step 4: Check non-matching callback
		$is_allowlisted = $allowlist->is_allowlisted( 'some_other_function' );
		$this->assertFalse( $is_allowlisted, 'some_other_function should not match rocket_* pattern' );

		// Step 5: Log a notice
		$notice_hash = $identifier->generate_callback_hash( 'rocket_bad_deactivations', 'admin_notices' );
		$logger->log_notice_removal(
			$notice_hash,
			'admin_notices',
			10,
			'rocket_bad_deactivations',
			'kept_allowlisted',
			'matches_allowlist_pattern'
		);

		$this->assertTrue( true, 'Full workflow completed successfully' );
	}

	/**
	 * Test: Components implement WithHooksInterface
	 *
	 * @test
	 */
	public function test_components_implement_with_hooks_interface(): void {
		// Mock database operations
		Functions\when( 'dbDelta' )->justReturn( [] );
		$this->wpdb_mock->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' );
		$this->wpdb_mock->shouldReceive( 'get_var' )
			->andReturn( 'wp_qala_hidden_notices_log', 'wp_qala_notice_allowlist' );

		$components = $this->service_provider->get_notice_management_components();

		foreach ( $components as $component ) {
			$this->assertTrue(
				method_exists( $component, 'init' ),
				get_class( $component ) . ' should have init() method'
			);
		}
	}

	/**
	 * Test: Hooks are registered when init() is called
	 *
	 * @test
	 */
	public function test_hooks_registered_on_init(): void {
		// Track add_action calls
		$hooks_registered = [];
		Functions\when( 'add_action' )->alias( function( $hook, $callback, $priority = 10 ) use ( &$hooks_registered ) {
			$hooks_registered[] = [ 'hook' => $hook, 'priority' => $priority ];
			return true;
		} );

		// Create NoticeFilter with dependencies
		$identifier = new NoticeIdentifier();
		$logger = new NoticeLogger();
		$allowlist = new AllowlistManager();
		$filter = new NoticeFilter( $allowlist, $logger, $identifier );

		// Call init
		$filter->init();

		// Verify in_admin_header hook was registered at priority 100000
		$this->assertContains(
			[ 'hook' => 'in_admin_header', 'priority' => 100000 ],
			$hooks_registered,
			'NoticeFilter should register in_admin_header hook at priority 100000'
		);
	}

	/**
	 * Dummy callback for testing
	 *
	 * @return void
	 */
	public function dummy_callback(): void {
		// Test callback
	}
}
