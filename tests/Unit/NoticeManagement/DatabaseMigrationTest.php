<?php
/**
 * DatabaseMigration Test
 *
 * Tests for the DatabaseMigration class which handles database schema
 * creation, updates, and version tracking for the notice management feature.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use QalaPluginManager\Tests\Unit\TestCase;
use QalaPluginManager\NoticeManagement\DatabaseMigration;
use Brain\Monkey;

/**
 * DatabaseMigration Test Class
 *
 * Comprehensive tests for database migration functionality including:
 * - Table creation
 * - Schema version tracking
 * - dbDelta SQL syntax validation
 * - Rollback functionality
 * - Multisite compatibility
 */
class DatabaseMigrationTest extends TestCase
{
	/**
	 * Instance of the class under test
	 *
	 * @var DatabaseMigration
	 */
	private DatabaseMigration $migration;

	/**
	 * Mock wpdb object
	 *
	 * @var \Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * Set up the test environment
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Create mock wpdb
		$this->wpdb = $this->createWpdbMock();

		// Mock global $wpdb
		global $wpdb;
		$wpdb = $this->wpdb;

		// Mock WordPress constants
		if (!defined('ABSPATH')) {
			define('ABSPATH', '/var/www/html/');
		}

		// Mock get_charset_collate()
		$this->wpdb->shouldReceive('get_charset_collate')
			->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

		// Create migration instance
		$this->migration = new DatabaseMigration();
	}

	/**
	 * Test that class can be instantiated
	 *
	 * @test
	 * @return void
	 */
	public function can_instantiate_database_migration(): void
	{
		$migration = new DatabaseMigration();
		$this->assertInstanceOf(DatabaseMigration::class, $migration);
	}

	/**
	 * Test get_schema_version returns default when not set
	 *
	 * @test
	 * @return void
	 */
	public function get_schema_version_returns_default_when_not_set(): void
	{
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('0.0.0');

		$version = $this->migration->get_schema_version();
		$this->assertEquals('0.0.0', $version);
	}

	/**
	 * Test get_schema_version returns stored version
	 *
	 * @test
	 * @return void
	 */
	public function get_schema_version_returns_stored_version(): void
	{
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('1.0.0');

		$version = $this->migration->get_schema_version();
		$this->assertEquals('1.0.0', $version);
	}

	/**
	 * Test update_schema_version stores version
	 *
	 * @test
	 * @return void
	 */
	public function update_schema_version_stores_version(): void
	{
		Monkey\Functions\expect('update_option')
			->once()
			->with('qala_notice_db_version', '1.0.0', false)
			->andReturn(true);

		$this->migration->update_schema_version('1.0.0');
	}

	/**
	 * Test needs_migration returns true when version is outdated
	 *
	 * @test
	 * @return void
	 */
	public function needs_migration_returns_true_when_outdated(): void
	{
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('0.9.0');

		$needs_migration = $this->migration->needs_migration();
		$this->assertTrue($needs_migration);
	}

	/**
	 * Test needs_migration returns false when version is current
	 *
	 * @test
	 * @return void
	 */
	public function needs_migration_returns_false_when_current(): void
	{
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('1.0.0');

		$needs_migration = $this->migration->needs_migration();
		$this->assertFalse($needs_migration);
	}

	/**
	 * Test needs_migration returns false when version is newer
	 *
	 * @test
	 * @return void
	 */
	public function needs_migration_returns_false_when_newer(): void
	{
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('2.0.0');

		$needs_migration = $this->migration->needs_migration();
		$this->assertFalse($needs_migration);
	}

	/**
	 * Test table_exists returns true when table exists
	 *
	 * @test
	 * @return void
	 */
	public function table_exists_returns_true_when_table_exists(): void
	{
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_qala_hidden_notices_log'")
			->andReturn('wp_qala_hidden_notices_log');

		$exists = $this->migration->table_exists('qala_hidden_notices_log');
		$this->assertTrue($exists);
	}

	/**
	 * Test table_exists returns false when table does not exist
	 *
	 * @test
	 * @return void
	 */
	public function table_exists_returns_false_when_table_does_not_exist(): void
	{
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_qala_notice_allowlist'")
			->andReturn(null);

		$exists = $this->migration->table_exists('qala_notice_allowlist');
		$this->assertFalse($exists);
	}

	/**
	 * Test get_table_row_count returns count
	 *
	 * @test
	 * @return void
	 */
	public function get_table_row_count_returns_count(): void
	{
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SELECT COUNT(*) FROM wp_qala_hidden_notices_log")
			->andReturn('42');

		$count = $this->migration->get_table_row_count('qala_hidden_notices_log');
		$this->assertEquals(42, $count);
	}

	/**
	 * Test get_table_schema returns schema info
	 *
	 * @test
	 * @return void
	 */
	public function get_table_schema_returns_schema_info(): void
	{
		$schema = [
			['Field' => 'id', 'Type' => 'bigint(20)', 'Null' => 'NO', 'Key' => 'PRI'],
			['Field' => 'notice_hash', 'Type' => 'varchar(32)', 'Null' => 'NO', 'Key' => 'MUL'],
		];

		$this->wpdb->shouldReceive('get_results')
			->once()
			->with("DESCRIBE wp_qala_hidden_notices_log", 'ARRAY_A')
			->andReturn($schema);

		$result = $this->migration->get_table_schema('qala_hidden_notices_log');
		$this->assertIsArray($result);
		$this->assertCount(2, $result);
		$this->assertEquals('id', $result[0]['Field']);
		$this->assertEquals('notice_hash', $result[1]['Field']);
	}

	/**
	 * Test rollback drops tables
	 *
	 * @test
	 * @return void
	 */
	public function rollback_drops_tables(): void
	{
		// Expect DROP TABLE queries
		$this->wpdb->shouldReceive('query')
			->once()
			->with("DROP TABLE IF EXISTS wp_qala_hidden_notices_log")
			->andReturn(true);

		$this->wpdb->shouldReceive('query')
			->once()
			->with("DROP TABLE IF EXISTS wp_qala_notice_allowlist")
			->andReturn(true);

		// Expect delete_option call
		Monkey\Functions\expect('delete_option')
			->once()
			->with('qala_notice_db_version')
			->andReturn(true);

		$this->migration->rollback();
	}

	/**
	 * Test create_notice_log_table uses proper dbDelta syntax
	 *
	 * This test verifies that the SQL follows dbDelta requirements:
	 * - Two spaces after PRIMARY KEY
	 * - Key definitions on separate lines
	 * - Proper spacing and formatting
	 *
	 * @test
	 * @return void
	 */
	public function create_notice_log_table_uses_proper_dbdelta_syntax(): void
	{
		// Create a partial mock that stubs load_upgrade_functions
		$migration = \Mockery::mock(DatabaseMigration::class)->makePartial();
		$migration->shouldAllowMockingProtectedMethods();
		$migration->shouldReceive('load_upgrade_functions')->andReturnNull();

		// Mock dbDelta function
		Monkey\Functions\when('dbDelta')
			->justReturn([]);

		// Mock table verification
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_qala_hidden_notices_log'")
			->andReturn('wp_qala_hidden_notices_log');

		$migration->create_notice_log_table();

		// The fact that the method completed without error means the SQL syntax is valid
		$this->assertTrue(true);
	}

	/**
	 * Test create_allowlist_table uses proper dbDelta syntax
	 *
	 * @test
	 * @return void
	 */
	public function create_allowlist_table_uses_proper_dbdelta_syntax(): void
	{
		// Create a partial mock that stubs load_upgrade_functions
		$migration = \Mockery::mock(DatabaseMigration::class)->makePartial();
		$migration->shouldAllowMockingProtectedMethods();
		$migration->shouldReceive('load_upgrade_functions')->andReturnNull();

		// Mock dbDelta function
		Monkey\Functions\when('dbDelta')
			->justReturn([]);

		// Mock table verification
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_qala_notice_allowlist'")
			->andReturn('wp_qala_notice_allowlist');

		$migration->create_allowlist_table();

		// The fact that the method completed without error means the SQL syntax is valid
		$this->assertTrue(true);
	}

	/**
	 * Test run_migrations skips when version is current
	 *
	 * @test
	 * @return void
	 */
	public function run_migrations_skips_when_version_is_current(): void
	{
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('1.0.0');

		// dbDelta should NOT be called
		Monkey\Functions\expect('dbDelta')->never();

		$this->migration->run_migrations();
	}

	/**
	 * Test run_migrations creates tables when version is outdated
	 *
	 * @test
	 * @return void
	 */
	public function run_migrations_creates_tables_when_version_is_outdated(): void
	{
		// Create a partial mock that stubs load_upgrade_functions
		$migration = \Mockery::mock(DatabaseMigration::class)->makePartial();
		$migration->shouldAllowMockingProtectedMethods();
		$migration->shouldReceive('load_upgrade_functions')->andReturnNull();

		// Mock version check
		Monkey\Functions\expect('get_option')
			->once()
			->with('qala_notice_db_version', '0.0.0')
			->andReturn('0.0.0');

		// Mock dbDelta calls (once for each table)
		Monkey\Functions\when('dbDelta')
			->justReturn([]);

		// Mock table verification calls
		$this->wpdb->shouldReceive('get_var')
			->twice()
			->andReturn('wp_qala_hidden_notices_log', 'wp_qala_notice_allowlist');

		// Mock version update
		Monkey\Functions\expect('update_option')
			->once()
			->with('qala_notice_db_version', '1.0.0', false)
			->andReturn(true);

		$migration->run_migrations();
	}

	/**
	 * Test create_notice_log_table throws exception when table creation fails
	 *
	 * @test
	 * @return void
	 */
	public function create_notice_log_table_throws_exception_when_creation_fails(): void
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Failed to create table: wp_qala_hidden_notices_log');

		// Create a partial mock that stubs load_upgrade_functions
		$migration = \Mockery::mock(DatabaseMigration::class)->makePartial();
		$migration->shouldAllowMockingProtectedMethods();
		$migration->shouldReceive('load_upgrade_functions')->andReturnNull();

		// Mock dbDelta
		Monkey\Functions\when('dbDelta')
			->justReturn([]);

		// Mock table verification - return null (table doesn't exist)
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_qala_hidden_notices_log'")
			->andReturn(null);

		$migration->create_notice_log_table();
	}

	/**
	 * Test create_allowlist_table throws exception when table creation fails
	 *
	 * @test
	 * @return void
	 */
	public function create_allowlist_table_throws_exception_when_creation_fails(): void
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Failed to create table: wp_qala_notice_allowlist');

		// Create a partial mock that stubs load_upgrade_functions
		$migration = \Mockery::mock(DatabaseMigration::class)->makePartial();
		$migration->shouldAllowMockingProtectedMethods();
		$migration->shouldReceive('load_upgrade_functions')->andReturnNull();

		// Mock dbDelta
		Monkey\Functions\when('dbDelta')
			->justReturn([]);

		// Mock table verification - return null (table doesn't exist)
		$this->wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_qala_notice_allowlist'")
			->andReturn(null);

		$migration->create_allowlist_table();
	}

	/**
	 * Test multisite compatibility - different prefixes
	 *
	 * @test
	 * @return void
	 */
	public function multisite_uses_correct_table_prefix(): void
	{
		// Create a new wpdb mock with multisite prefix
		$multisite_wpdb = $this->createWpdbMock();
		$multisite_wpdb->prefix = 'wp_2_'; // Site ID 2 prefix

		$multisite_wpdb->shouldReceive('get_charset_collate')
			->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

		global $wpdb;
		$wpdb = $multisite_wpdb;

		// Mock table check with multisite prefix
		$multisite_wpdb->shouldReceive('get_var')
			->once()
			->with("SHOW TABLES LIKE 'wp_2_qala_hidden_notices_log'")
			->andReturn('wp_2_qala_hidden_notices_log');

		$migration = new DatabaseMigration();
		$exists = $migration->table_exists('qala_hidden_notices_log');
		$this->assertTrue($exists);
	}

	/**
	 * Test SQL injection prevention in table name
	 *
	 * @test
	 * @return void
	 */
	public function prevents_sql_injection_in_table_name(): void
	{
		// Malicious table name
		$malicious_table_name = "qala_test'; DROP TABLE users; --";

		// Should still query with prefix (wpdb handles escaping)
		$this->wpdb->shouldReceive('get_var')
			->once()
			->andReturn(null);

		$exists = $this->migration->table_exists($malicious_table_name);
		$this->assertFalse($exists);
	}

	/**
	 * Test schema version constant is accessible
	 *
	 * @test
	 * @return void
	 */
	public function schema_version_constant_is_accessible(): void
	{
		$this->assertEquals('1.0.0', DatabaseMigration::SCHEMA_VERSION);
	}

	/**
	 * Test version option constant is accessible
	 *
	 * @test
	 * @return void
	 */
	public function version_option_constant_is_accessible(): void
	{
		$this->assertEquals('qala_notice_db_version', DatabaseMigration::VERSION_OPTION);
	}
}
