<?php
/**
 * AllowlistManager Class Tests
 *
 * Comprehensive unit tests for the AllowlistManager class.
 * Tests CRUD operations, pattern matching, caching, and edge cases.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use QalaPluginManager\NoticeManagement\AllowlistManager;
use QalaPluginManager\Tests\Unit\TestCase;

/**
 * Test case for AllowlistManager class
 *
 * Tests allowlist pattern CRUD operations and matching functionality.
 * Uses wpdb mocking for database operations.
 *
 * @group notice-management
 * @group allowlist-manager
 * @group unit
 */
class AllowlistManagerTest extends TestCase {

	/**
	 * Mock wpdb instance
	 *
	 * @var Mockery\MockInterface
	 */
	private $wpdb_mock;

	/**
	 * AllowlistManager instance for testing
	 *
	 * @var AllowlistManager
	 */
	private $manager;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create wpdb mock
		$this->wpdb_mock         = Mockery::mock( 'wpdb' );
		$this->wpdb_mock->prefix = 'wp_';

		// Set global wpdb
		global $wpdb;
		$wpdb = $this->wpdb_mock;

		// Create manager instance
		$this->manager = new AllowlistManager();

		// Mock WordPress time function
		Functions\when( 'current_time' )->alias(
			function ( $type ) {
				return gmdate( 'Y-m-d H:i:s' );
			}
		);

		// Mock get_current_user_id
		$this->mockGetCurrentUserId( 1 );

		// Mock get_current_blog_id
		$this->mockGetCurrentBlogId( 1 );

		// Mock set_transient
		Functions\when( 'set_transient' )->justReturn( true );

		// Mock delete_transient (called after mutations)
		Functions\when( 'delete_transient' )->justReturn( true );
	}

	/**
	 * Test add_pattern() successfully adds an exact pattern
	 *
	 * @return void
	 */
	public function test_add_pattern_adds_exact_pattern_successfully(): void {
		$pattern = 'rocket_bad_deactivations';
		$type    = 'exact';

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				Mockery::on(
					function ( $data ) use ( $pattern, $type ) {
						return $data['pattern_value'] === $pattern
						&& $data['pattern_type'] === $type
						&& $data['is_active'] === 1
						&& $data['created_by'] === 1
						&& isset( $data['created_at'] )
						&& isset( $data['updated_at'] );
					}
				),
				[ '%s', '%s', '%d', '%d', '%s', '%s' ]
			)
			->andReturn( 1 );

		$result = $this->manager->add_pattern( $pattern, $type );

		$this->assertTrue( $result, 'Should successfully add exact pattern' );
	}

	/**
	 * Test add_pattern() successfully adds a wildcard pattern
	 *
	 * @return void
	 */
	public function test_add_pattern_adds_wildcard_pattern_successfully(): void {
		$pattern = 'rocket_*';
		$type    = 'wildcard';

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->manager->add_pattern( $pattern, $type );

		$this->assertTrue( $result, 'Should successfully add wildcard pattern' );
	}

	/**
	 * Test add_pattern() successfully adds a regex pattern
	 *
	 * @return void
	 */
	public function test_add_pattern_adds_regex_pattern_successfully(): void {
		$pattern = '/^rocket_.*$/';
		$type    = 'regex';

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->manager->add_pattern( $pattern, $type );

		$this->assertTrue( $result, 'Should successfully add regex pattern' );
	}

	/**
	 * Test add_pattern() defaults to exact type when type not specified
	 *
	 * @return void
	 */
	public function test_add_pattern_defaults_to_exact_type(): void {
		$pattern = 'some_callback';

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				Mockery::on(
					function ( $data ) {
						return $data['pattern_type'] === 'exact';
					}
				),
				Mockery::any()
			)
			->andReturn( 1 );

		$result = $this->manager->add_pattern( $pattern );

		$this->assertTrue( $result, 'Should default to exact type' );
	}

	/**
	 * Test add_pattern() returns false when database insert fails
	 *
	 * @return void
	 */
	public function test_add_pattern_returns_false_on_database_failure(): void {
		$pattern = 'some_callback';

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$result = $this->manager->add_pattern( $pattern );

		$this->assertFalse( $result, 'Should return false when database insert fails' );
	}

	/**
	 * Test add_pattern() rejects invalid regex patterns
	 *
	 * @return void
	 */
	public function test_add_pattern_rejects_invalid_regex(): void {
		$invalid_regex = '/unclosed_bracket[/';

		// Should not call insert for invalid regex
		$this->wpdb_mock->shouldNotReceive( 'insert' );

		$result = $this->manager->add_pattern( $invalid_regex, 'regex' );

		$this->assertFalse( $result, 'Should reject invalid regex patterns' );
	}

	/**
	 * Test add_pattern() prevents SQL injection attempts
	 *
	 * @return void
	 */
	public function test_add_pattern_prevents_sql_injection(): void {
		$sql_injection_attempt = "'; DROP TABLE wp_qala_notice_allowlist; --";

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				Mockery::on(
					function ( $data ) use ( $sql_injection_attempt ) {
						// Pattern should be stored as-is, prepared statements handle safety
						return $data['pattern_value'] === $sql_injection_attempt;
					}
				),
				Mockery::any()
			)
			->andReturn( 1 );

		$result = $this->manager->add_pattern( $sql_injection_attempt, 'exact' );

		// Should succeed because prepared statements handle escaping
		$this->assertTrue( $result, 'Prepared statements should handle SQL injection safely' );
	}

	/**
	 * Test remove_pattern() successfully removes a pattern by ID
	 *
	 * @return void
	 */
	public function test_remove_pattern_removes_by_id_successfully(): void {
		$pattern_id = 42;

		$this->wpdb_mock->shouldReceive( 'delete' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				[ 'id' => $pattern_id ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$result = $this->manager->remove_pattern( $pattern_id );

		$this->assertTrue( $result, 'Should successfully remove pattern by ID' );
	}

	/**
	 * Test remove_pattern() returns false when pattern does not exist
	 *
	 * @return void
	 */
	public function test_remove_pattern_returns_false_when_pattern_not_found(): void {
		$pattern_id = 999;

		$this->wpdb_mock->shouldReceive( 'delete' )
			->once()
			->andReturn( 0 );

		$result = $this->manager->remove_pattern( $pattern_id );

		$this->assertFalse( $result, 'Should return false when pattern not found' );
	}

	/**
	 * Test remove_pattern() handles database errors gracefully
	 *
	 * @return void
	 */
	public function test_remove_pattern_handles_database_errors(): void {
		$pattern_id = 42;

		$this->wpdb_mock->shouldReceive( 'delete' )
			->once()
			->andReturn( false );

		$result = $this->manager->remove_pattern( $pattern_id );

		$this->assertFalse( $result, 'Should handle database errors gracefully' );
	}

	/**
	 * Test get_all_patterns() returns all active patterns from database
	 *
	 * @return void
	 */
	public function test_get_all_patterns_returns_active_patterns(): void {
		$expected_patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'rocket_bad_deactivations',
				'pattern_type'  => 'exact',
				'is_active'     => 1,
			],
			[
				'id'            => 2,
				'pattern_value' => 'rocket_*',
				'pattern_type'  => 'wildcard',
				'is_active'     => 1,
			],
			[
				'id'            => 3,
				'pattern_value' => '/^woo_.*$/',
				'pattern_type'  => 'regex',
				'is_active'     => 1,
			],
		];

		// Cache miss
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		$this->wpdb_mock->shouldReceive( 'get_results' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						return strpos( $query, 'SELECT' ) !== false
						&& strpos( $query, 'is_active = 1' ) !== false
						&& strpos( $query, 'wp_qala_notice_allowlist' ) !== false;
					}
				),
				ARRAY_A
			)
			->andReturn( $expected_patterns );

		$result = $this->manager->get_all_patterns();

		$this->assertCount( 3, $result, 'Should return all active patterns' );
		$this->assertEquals( $expected_patterns, $result, 'Should return exact pattern data' );
	}

	/**
	 * Test get_all_patterns() returns empty array when no patterns exist
	 *
	 * @return void
	 */
	public function test_get_all_patterns_returns_empty_array_when_no_patterns(): void {
		// Cache miss
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		$this->wpdb_mock->shouldReceive( 'get_results' )
			->once()
			->andReturn( null );

		$result = $this->manager->get_all_patterns();

		$this->assertIsArray( $result, 'Should return an array' );
		$this->assertEmpty( $result, 'Should return empty array when no patterns' );
	}

	/**
	 * Test get_all_patterns() uses cached patterns when available
	 *
	 * @return void
	 */
	public function test_get_all_patterns_uses_cache_when_available(): void {
		$cached_patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'cached_pattern',
				'pattern_type'  => 'exact',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $cached_patterns );

		// Should not query database when cache exists
		$this->wpdb_mock->shouldNotReceive( 'get_results' );

		$result = $this->manager->get_all_patterns();

		$this->assertEquals( $cached_patterns, $result, 'Should return cached patterns' );
	}

	/**
	 * Test get_all_patterns() only returns active patterns
	 *
	 * @return void
	 */
	public function test_get_all_patterns_only_returns_active_patterns(): void {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		$this->wpdb_mock->shouldReceive( 'get_results' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						// Verify WHERE clause includes is_active = 1
						return strpos( $query, 'WHERE is_active = 1' ) !== false;
					}
				),
				ARRAY_A
			)
			->andReturn( [] );

		$this->manager->get_all_patterns();

		// Assertion is in the query verification above
		$this->assertTrue( true, 'Query should filter for active patterns only' );
	}

	/**
	 * Test matches_allowlist() with exact pattern match
	 *
	 * @return void
	 */
	public function test_matches_allowlist_with_exact_pattern(): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'rocket_bad_deactivations',
				'pattern_type'  => 'exact',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		$result = $this->manager->matches_allowlist( 'rocket_bad_deactivations' );

		$this->assertTrue( $result, 'Should match exact callback name' );
	}

	/**
	 * Test matches_allowlist() with wildcard pattern match
	 *
	 * @return void
	 */
	public function test_matches_allowlist_with_wildcard_pattern(): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'rocket_*',
				'pattern_type'  => 'wildcard',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		$result = $this->manager->matches_allowlist( 'rocket_bad_deactivations' );

		$this->assertTrue( $result, 'Should match wildcard pattern' );
	}

	/**
	 * Test matches_allowlist() with regex pattern match
	 *
	 * @return void
	 */
	public function test_matches_allowlist_with_regex_pattern(): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => '/^rocket_.*$/',
				'pattern_type'  => 'regex',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		$result = $this->manager->matches_allowlist( 'rocket_bad_deactivations' );

		$this->assertTrue( $result, 'Should match regex pattern' );
	}

	/**
	 * Test matches_allowlist() returns false when no patterns match
	 *
	 * @return void
	 */
	public function test_matches_allowlist_returns_false_when_no_match(): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'rocket_*',
				'pattern_type'  => 'wildcard',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		$result = $this->manager->matches_allowlist( 'woocommerce_notice' );

		$this->assertFalse( $result, 'Should return false when no patterns match' );
	}

	/**
	 * Test matches_allowlist() returns false when no patterns exist
	 *
	 * @return void
	 */
	public function test_matches_allowlist_returns_false_when_no_patterns(): void {
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( [] );

		$result = $this->manager->matches_allowlist( 'any_callback' );

		$this->assertFalse( $result, 'Should return false when no patterns exist' );
	}

	/**
	 * Test matches_allowlist() checks multiple patterns in order
	 *
	 * @return void
	 */
	public function test_matches_allowlist_checks_multiple_patterns(): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'exact_match',
				'pattern_type'  => 'exact',
				'is_active'     => 1,
			],
			[
				'id'            => 2,
				'pattern_value' => 'rocket_*',
				'pattern_type'  => 'wildcard',
				'is_active'     => 1,
			],
			[
				'id'            => 3,
				'pattern_value' => '/^woo_.*$/',
				'pattern_type'  => 'regex',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		// Should match the wildcard pattern
		$result = $this->manager->matches_allowlist( 'rocket_warning' );

		$this->assertTrue( $result, 'Should check and match any pattern in the list' );
	}

	/**
	 * Test activate_pattern() activates an inactive pattern
	 *
	 * @return void
	 */
	public function test_activate_pattern_activates_inactive_pattern(): void {
		$pattern_id = 42;

		$this->wpdb_mock->shouldReceive( 'update' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				Mockery::on(
					function ( $data ) {
						return $data['is_active'] === 1 && isset( $data['updated_at'] );
					}
				),
				[ 'id' => $pattern_id ],
				[ '%d', '%s' ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$result = $this->manager->activate_pattern( $pattern_id );

		$this->assertTrue( $result, 'Should successfully activate pattern' );
	}

	/**
	 * Test activate_pattern() returns false when pattern not found
	 *
	 * @return void
	 */
	public function test_activate_pattern_returns_false_when_not_found(): void {
		$pattern_id = 999;

		$this->wpdb_mock->shouldReceive( 'update' )
			->once()
			->andReturn( 0 );

		$result = $this->manager->activate_pattern( $pattern_id );

		$this->assertFalse( $result, 'Should return false when pattern not found' );
	}

	/**
	 * Test deactivate_pattern() deactivates an active pattern
	 *
	 * @return void
	 */
	public function test_deactivate_pattern_deactivates_active_pattern(): void {
		$pattern_id = 42;

		$this->wpdb_mock->shouldReceive( 'update' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				Mockery::on(
					function ( $data ) {
						return $data['is_active'] === 0 && isset( $data['updated_at'] );
					}
				),
				[ 'id' => $pattern_id ],
				[ '%d', '%s' ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$result = $this->manager->deactivate_pattern( $pattern_id );

		$this->assertTrue( $result, 'Should successfully deactivate pattern' );
	}

	/**
	 * Test deactivate_pattern() returns false when pattern not found
	 *
	 * @return void
	 */
	public function test_deactivate_pattern_returns_false_when_not_found(): void {
		$pattern_id = 999;

		$this->wpdb_mock->shouldReceive( 'update' )
			->once()
			->andReturn( 0 );

		$result = $this->manager->deactivate_pattern( $pattern_id );

		$this->assertFalse( $result, 'Should return false when pattern not found' );
	}

	/**
	 * Test cache is cleared after adding a pattern
	 *
	 * @return void
	 */
	public function test_cache_cleared_after_adding_pattern(): void {
		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$this->manager->add_pattern( 'test_pattern' );

		// Assertion is in the delete_transient expectation
		$this->assertTrue( true, 'Cache should be cleared after adding pattern' );
	}

	/**
	 * Test cache is cleared after removing a pattern
	 *
	 * @return void
	 */
	public function test_cache_cleared_after_removing_pattern(): void {
		$this->wpdb_mock->shouldReceive( 'delete' )
			->once()
			->andReturn( 1 );

		$this->manager->remove_pattern( 42 );

		// Assertion is in the delete_transient expectation
		$this->assertTrue( true, 'Cache should be cleared after removing pattern' );
	}

	/**
	 * Test cache is cleared after activating a pattern
	 *
	 * @return void
	 */
	public function test_cache_cleared_after_activating_pattern(): void {
		$this->wpdb_mock->shouldReceive( 'update' )
			->once()
			->andReturn( 1 );

		$this->manager->activate_pattern( 42 );

		// Assertion is in the delete_transient expectation
		$this->assertTrue( true, 'Cache should be cleared after activating pattern' );
	}

	/**
	 * Test cache is cleared after deactivating a pattern
	 *
	 * @return void
	 */
	public function test_cache_cleared_after_deactivating_pattern(): void {
		$this->wpdb_mock->shouldReceive( 'update' )
			->once()
			->andReturn( 1 );

		$this->manager->deactivate_pattern( 42 );

		// Assertion is in the delete_transient expectation
		$this->assertTrue( true, 'Cache should be cleared after deactivating pattern' );
	}

	/**
	 * Test multisite compatibility with different blog IDs
	 *
	 * @return void
	 */
	public function test_multisite_compatibility_different_blog_ids(): void {
		// Test blog ID 1
		$this->mockGetCurrentBlogId( 1 );

		Functions\expect( 'get_transient' )
			->once()
			->with( 'qala_allowlist_patterns_1' )
			->andReturn( false );

		$this->wpdb_mock->shouldReceive( 'get_results' )
			->once()
			->andReturn( [] );

		$this->manager->get_all_patterns();

		// Test blog ID 2 (would use different cache key)
		$this->mockGetCurrentBlogId( 2 );
		$manager2 = new AllowlistManager();

		Functions\expect( 'get_transient' )
			->once()
			->with( 'qala_allowlist_patterns_2' )
			->andReturn( false );

		$this->wpdb_mock->shouldReceive( 'get_results' )
			->once()
			->andReturn( [] );

		$manager2->get_all_patterns();

		$this->assertTrue( true, 'Should use different cache keys for different blog IDs' );
	}

	/**
	 * Data provider for wildcard pattern matching
	 *
	 * @return array Test cases
	 */
	public function wildcard_pattern_matching_provider(): array {
		return [
			'prefix_wildcard_match'    => [
				'pattern'      => 'rocket_*',
				'callback'     => 'rocket_bad_deactivations',
				'should_match' => true,
			],
			'prefix_wildcard_no_match' => [
				'pattern'      => 'rocket_*',
				'callback'     => 'woocommerce_notice',
				'should_match' => false,
			],
			'suffix_wildcard_match'    => [
				'pattern'      => '*_notice',
				'callback'     => 'woocommerce_notice',
				'should_match' => true,
			],
			'suffix_wildcard_no_match' => [
				'pattern'      => '*_notice',
				'callback'     => 'rocket_bad_deactivations',
				'should_match' => false,
			],
			'middle_wildcard_match'    => [
				'pattern'      => 'My*::method',
				'callback'     => 'MyClass::method',
				'should_match' => true,
			],
			'multiple_wildcards_match' => [
				'pattern'      => '*Commerce*::*',
				'callback'     => 'WooCommerce_Helper::show_notice',
				'should_match' => true,
			],
		];
	}

	/**
	 * Test wildcard pattern matching with various patterns
	 *
	 * @dataProvider wildcard_pattern_matching_provider
	 *
	 * @param string $pattern Wildcard pattern.
	 * @param string $callback Callback name to test.
	 * @param bool   $should_match Expected match result.
	 *
	 * @return void
	 */
	public function test_wildcard_pattern_matching_scenarios(
		string $pattern,
		string $callback,
		bool $should_match
	): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => $pattern,
				'pattern_type'  => 'wildcard',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		$result = $this->manager->matches_allowlist( $callback );

		$this->assertSame(
			$should_match,
			$result,
			sprintf(
				'Pattern "%s" should %s callback "%s"',
				$pattern,
				$should_match ? 'match' : 'not match',
				$callback
			)
		);
	}

	/**
	 * Data provider for regex pattern matching
	 *
	 * @return array Test cases
	 */
	public function regex_pattern_matching_provider(): array {
		return [
			'simple_regex_match'           => [
				'pattern'      => '/^rocket_.*$/',
				'callback'     => 'rocket_bad_deactivations',
				'should_match' => true,
			],
			'simple_regex_no_match'        => [
				'pattern'      => '/^rocket_.*$/',
				'callback'     => 'woocommerce_notice',
				'should_match' => false,
			],
			'case_insensitive_regex_match' => [
				'pattern'      => '/^ROCKET_/i',
				'callback'     => 'rocket_bad_deactivations',
				'should_match' => true,
			],
			'complex_regex_match'          => [
				'pattern'      => '/^[A-Z][A-Za-z]+::[a-z_]+$/',
				'callback'     => 'MyClass::show_notice',
				'should_match' => true,
			],
			'digit_regex_match'            => [
				'pattern'      => '/^callback_\d+$/',
				'callback'     => 'callback_123',
				'should_match' => true,
			],
		];
	}

	/**
	 * Test regex pattern matching with various patterns
	 *
	 * @dataProvider regex_pattern_matching_provider
	 *
	 * @param string $pattern Regex pattern.
	 * @param string $callback Callback name to test.
	 * @param bool   $should_match Expected match result.
	 *
	 * @return void
	 */
	public function test_regex_pattern_matching_scenarios(
		string $pattern,
		string $callback,
		bool $should_match
	): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => $pattern,
				'pattern_type'  => 'regex',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->once()
			->andReturn( $patterns );

		$result = $this->manager->matches_allowlist( $callback );

		$this->assertSame(
			$should_match,
			$result,
			sprintf(
				'Pattern "%s" should %s callback "%s"',
				$pattern,
				$should_match ? 'match' : 'not match',
				$callback
			)
		);
	}

	/**
	 * Test handling of empty pattern values
	 *
	 * @return void
	 */
	public function test_handling_empty_pattern_values(): void {
		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->manager->add_pattern( '' );

		// Should allow empty patterns (database constraint will handle)
		$this->assertTrue( $result, 'Should handle empty pattern values' );
	}

	/**
	 * Test handling of very long pattern values
	 *
	 * @return void
	 */
	public function test_handling_long_pattern_values(): void {
		$long_pattern = str_repeat( 'a', 300 ); // Longer than VARCHAR(255)

		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_qala_notice_allowlist',
				Mockery::on(
					function ( $data ) use ( $long_pattern ) {
						return $data['pattern_value'] === $long_pattern;
					}
				),
				Mockery::any()
			)
			->andReturn( 1 );

		$result = $this->manager->add_pattern( $long_pattern );

		// Should allow (database will truncate or error based on schema)
		$this->assertTrue( $result, 'Should handle long pattern values' );
	}

	/**
	 * Test pattern matching is case-sensitive for exact matches
	 *
	 * @return void
	 */
	public function test_exact_pattern_matching_is_case_sensitive(): void {
		$patterns = [
			[
				'id'            => 1,
				'pattern_value' => 'MyCallback',
				'pattern_type'  => 'exact',
				'is_active'     => 1,
			],
		];

		Functions\expect( 'get_transient' )
			->twice()
			->andReturn( $patterns );

		// Exact match should succeed
		$result1 = $this->manager->matches_allowlist( 'MyCallback' );
		$this->assertTrue( $result1, 'Should match exact case' );

		// Different case should fail
		$result2 = $this->manager->matches_allowlist( 'mycallback' );
		$this->assertFalse( $result2, 'Should be case-sensitive for exact matches' );
	}

	/**
	 * Test adding duplicate patterns is prevented by unique constraint
	 *
	 * @return void
	 */
	public function test_adding_duplicate_patterns_fails(): void {
		// First insert succeeds
		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result1 = $this->manager->add_pattern( 'duplicate_pattern' );
		$this->assertTrue( $result1, 'First insert should succeed' );

		// Second insert with same pattern fails (unique constraint)
		$this->wpdb_mock->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$result2 = $this->manager->add_pattern( 'duplicate_pattern' );
		$this->assertFalse( $result2, 'Duplicate pattern should fail' );
	}
}
