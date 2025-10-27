<?php
/**
 * NoticeIdentifier Test
 *
 * Comprehensive tests for the NoticeIdentifier class.
 * Tests hash generation, callback name extraction, pattern matching, and sanitization.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use QalaPluginManager\Tests\Unit\TestCase;
use QalaPluginManager\NoticeManagement\NoticeIdentifier;
use Brain\Monkey;

/**
 * Test NoticeIdentifier class
 *
 * Covers:
 * - Hash generation for various callback types
 * - Callback name extraction
 * - Closure detection
 * - Pattern sanitization
 * - Pattern matching (exact, wildcard, regex)
 * - Edge cases and error handling
 */
class NoticeIdentifierTest extends TestCase {

	/**
	 * Instance of NoticeIdentifier to test
	 *
	 * @var NoticeIdentifier
	 */
	private $identifier;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->identifier = new NoticeIdentifier();

		// Mock WordPress salt function
		Monkey\Functions\when( 'wp_salt' )->justReturn( 'test_salt_value_12345' );
		$this->mockGetCurrentBlogId( 1 );
	}

	/**
	 * Test: generate_hash returns a valid MD5 hash
	 *
	 * @return void
	 */
	public function test_generate_hash_returns_valid_md5() {
		$hash = $this->identifier->generate_hash( 'my_function', 'admin_notices' );

		$this->assertIsString( $hash );
		$this->assertEquals( 32, strlen( $hash ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $hash );
	}

	/**
	 * Test: generate_hash for function name callback
	 *
	 * @return void
	 */
	public function test_generate_hash_for_function_callback() {
		$hash = $this->identifier->generate_hash( 'my_notice_function', 'admin_notices' );

		$this->assertIsString( $hash );
		$this->assertEquals( 32, strlen( $hash ) );
	}

	/**
	 * Test: generate_hash for class method array callback
	 *
	 * @return void
	 */
	public function test_generate_hash_for_class_method_callback() {
		$mock_object = new \stdClass();
		$callback    = [ $mock_object, 'show_notice' ];

		$hash = $this->identifier->generate_hash( $callback, 'admin_notices' );

		$this->assertIsString( $hash );
		$this->assertEquals( 32, strlen( $hash ) );
	}

	/**
	 * Test: generate_hash for static method callback
	 *
	 * @return void
	 */
	public function test_generate_hash_for_static_method_callback() {
		$callback = [ 'MyClass', 'show_notice' ];

		$hash = $this->identifier->generate_hash( $callback, 'admin_notices' );

		$this->assertIsString( $hash );
		$this->assertEquals( 32, strlen( $hash ) );
	}

	/**
	 * Test: generate_hash for closure callback
	 *
	 * @return void
	 */
	public function test_generate_hash_for_closure_callback() {
		 $callback = function () {
			return 'test';
		 };

		$hash = $this->identifier->generate_hash( $callback, 'admin_notices' );

		$this->assertIsString( $hash );
		$this->assertEquals( 32, strlen( $hash ) );
	}

	/**
	 * Test: generate_hash uses site salt for uniqueness
	 *
	 * Different blog IDs should produce different hashes for the same callback
	 *
	 * @return void
	 */
	public function test_generate_hash_uses_site_salt() {
		// Generate hash for blog ID 1
		$this->mockGetCurrentBlogId( 1 );
		$hash1 = $this->identifier->generate_hash( 'my_function', 'admin_notices' );

		// Generate hash for blog ID 2
		$this->mockGetCurrentBlogId( 2 );
		$hash2 = $this->identifier->generate_hash( 'my_function', 'admin_notices' );

		$this->assertNotEquals( $hash1, $hash2, 'Different blog IDs should produce different hashes' );
	}

	/**
	 * Test: generate_hash uses hook name in hash
	 *
	 * Different hook names should produce different hashes
	 *
	 * @return void
	 */
	public function test_generate_hash_includes_hook_name() {
		$hash1 = $this->identifier->generate_hash( 'my_function', 'admin_notices' );
		$hash2 = $this->identifier->generate_hash( 'my_function', 'network_admin_notices' );

		$this->assertNotEquals( $hash1, $hash2, 'Different hook names should produce different hashes' );
	}

	/**
	 * Test: generate_hash is deterministic (same input = same output)
	 *
	 * @return void
	 */
	public function test_generate_hash_is_deterministic() {
		 $hash1 = $this->identifier->generate_hash( 'my_function', 'admin_notices' );
		$hash2  = $this->identifier->generate_hash( 'my_function', 'admin_notices' );

		$this->assertEquals( $hash1, $hash2, 'Same input should always produce same hash' );
	}

	/**
	 * Test: get_callback_name extracts function name from string
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_function() {
		 $name = $this->identifier->get_callback_name( 'my_notice_function' );

		$this->assertEquals( 'my_notice_function', $name );
	}

	/**
	 * Test: get_callback_name extracts class::method from object array
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_object_method() {
		$mock_object = new \stdClass();
		$callback    = [ $mock_object, 'show_notice' ];

		$name = $this->identifier->get_callback_name( $callback );

		$this->assertEquals( 'stdClass::show_notice', $name );
	}

	/**
	 * Test: get_callback_name extracts class::method from static array
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_static_method() {
		$callback = [ 'MyClass', 'show_notice' ];

		$name = $this->identifier->get_callback_name( $callback );

		$this->assertEquals( 'MyClass::show_notice', $name );
	}

	/**
	 * Test: get_callback_name returns 'Closure' for anonymous functions
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_closure() {
		$callback = function () {
			return 'test';
		};

		$name = $this->identifier->get_callback_name( $callback );

		$this->assertEquals( 'Closure', $name );
	}

	/**
	 * Test: get_callback_name handles invokable objects
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_invokable_object() {
		 $invokable = new class() {
			public function __invoke() {
				return 'test';
			}
		 };

		$name = $this->identifier->get_callback_name( $invokable );

		$this->assertStringContainsString( 'class@anonymous', $name );
	}

	/**
	 * Test: get_callback_name returns 'Unknown' for invalid callbacks
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_invalid_callback() {
		 $name = $this->identifier->get_callback_name( 12345 );

		$this->assertEquals( 'Unknown', $name );
	}

	/**
	 * Test: get_callback_name handles null
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_null() {
		 $name = $this->identifier->get_callback_name( null );

		$this->assertEquals( 'Unknown', $name );
	}

	/**
	 * Test: is_closure returns true for closures
	 *
	 * @return void
	 */
	public function test_is_closure_detects_closures() {
		$closure = function () {
			return 'test';
		};

		$this->assertTrue( $this->identifier->is_closure( $closure ) );
	}

	/**
	 * Test: is_closure returns false for functions
	 *
	 * @return void
	 */
	public function test_is_closure_returns_false_for_functions() {
		 $this->assertFalse( $this->identifier->is_closure( 'my_function' ) );
	}

	/**
	 * Test: is_closure returns false for class methods
	 *
	 * @return void
	 */
	public function test_is_closure_returns_false_for_class_methods() {
		 $callback = [ 'MyClass', 'show_notice' ];

		$this->assertFalse( $this->identifier->is_closure( $callback ) );
	}

	/**
	 * Test: is_closure returns false for null
	 *
	 * @return void
	 */
	public function test_is_closure_returns_false_for_null() {
		$this->assertFalse( $this->identifier->is_closure( null ) );
	}

	/**
	 * Test: sanitize_pattern removes dangerous characters
	 *
	 * @return void
	 */
	public function test_sanitize_pattern_removes_dangerous_characters() {
		// Mock sanitize_text_field to actually sanitize
		Monkey\Functions\when( 'sanitize_text_field' )->alias(
			function ( $text ) {
				return trim( strip_tags( $text ) );
			}
		);

		$pattern   = '<script>alert("xss")</script>rocket_*';
		$sanitized = $this->identifier->sanitize_pattern( $pattern );

		$this->assertStringNotContainsString( '<script>', $sanitized );
		$this->assertStringNotContainsString( '</script>', $sanitized );
	}

	/**
	 * Test: sanitize_pattern preserves valid pattern characters
	 *
	 * @return void
	 */
	public function test_sanitize_pattern_preserves_valid_characters() {
		$pattern   = 'rocket_*';
		$sanitized = $this->identifier->sanitize_pattern( $pattern );

		$this->assertEquals( 'rocket_*', $sanitized );
	}

	/**
	 * Test: sanitize_pattern handles regex patterns
	 *
	 * @return void
	 */
	public function test_sanitize_pattern_preserves_regex() {
		$pattern   = '/^rocket_.*$/';
		$sanitized = $this->identifier->sanitize_pattern( $pattern );

		// Should preserve the pattern structure
		$this->assertStringContainsString( 'rocket', $sanitized );
	}

	/**
	 * Test: sanitize_pattern handles empty string
	 *
	 * @return void
	 */
	public function test_sanitize_pattern_handles_empty_string() {
		$sanitized = $this->identifier->sanitize_pattern( '' );

		$this->assertEquals( '', $sanitized );
	}

	/**
	 * Test: sanitize_pattern trims whitespace
	 *
	 * @return void
	 */
	public function test_sanitize_pattern_trims_whitespace() {
		Monkey\Functions\when( 'sanitize_text_field' )->alias(
			function ( $text ) {
				return trim( strip_tags( $text ) );
			}
		);

		$pattern   = '  rocket_*  ';
		$sanitized = $this->identifier->sanitize_pattern( $pattern );

		$this->assertEquals( 'rocket_*', $sanitized );
	}

	/**
	 * Data provider for exact match tests
	 *
	 * @return array
	 */
	public function provide_exact_match_scenarios() {
		return [
			'function name matches'        => [ 'my_function', 'my_function', true ],
			'function name does not match' => [ 'my_function', 'other_function', false ],
			'class method matches'         => [ [ 'MyClass', 'show' ], 'MyClass::show', true ],
			'class method does not match'  => [ [ 'MyClass', 'show' ], 'MyClass::hide', false ],
		];
	}

	/**
	 * Test: matches_pattern exact matching
	 *
	 * @dataProvider provide_exact_match_scenarios
	 *
	 * @param mixed  $callback The callback to test.
	 * @param string $pattern The pattern to match against.
	 * @param bool   $expected Expected result.
	 *
	 * @return void
	 */
	public function test_matches_pattern_exact_match( $callback, $pattern, $expected ) {
		$result = $this->identifier->matches_pattern( $callback, $pattern );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for wildcard match tests
	 *
	 * @return array
	 */
	public function provide_wildcard_match_scenarios() {
		return [
			'prefix wildcard matches'        => [ 'rocket_notice', 'rocket_*', true ],
			'prefix wildcard does not match' => [ 'my_rocket_notice', 'rocket_*', false ],
			'suffix wildcard matches'        => [ 'show_notice', '*_notice', true ],
			'suffix wildcard does not match' => [ 'notice_show', '*_notice', false ],
			'middle wildcard matches'        => [ 'rocket_bad_notice', 'rocket_*_notice', true ],
			'middle wildcard does not match' => [ 'rocket_notice', 'rocket_*_warning', false ],
			'class wildcard matches'         => [ [ 'YoastSEO', 'show' ], 'Yoast*::*', true ],
			'multiple wildcards match'       => [ 'any_prefix_any_suffix', '*_prefix_*', true ],
		];
	}

	/**
	 * Test: matches_pattern wildcard matching
	 *
	 * @dataProvider provide_wildcard_match_scenarios
	 *
	 * @param mixed  $callback The callback to test.
	 * @param string $pattern The pattern to match against.
	 * @param bool   $expected Expected result.
	 *
	 * @return void
	 */
	public function test_matches_pattern_wildcard_match( $callback, $pattern, $expected ) {
		$result = $this->identifier->matches_pattern( $callback, $pattern );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for regex match tests
	 *
	 * @return array
	 */
	public function provide_regex_match_scenarios() {
		return [
			'regex matches start'        => [ 'rocket_notice', '/^rocket_.*$/', true ],
			'regex does not match start' => [ 'my_rocket_notice', '/^rocket_.*$/', false ],
			'regex matches end'          => [ 'show_notice', '/.*_notice$/', true ],
			'regex case sensitive'       => [ 'Rocket_Notice', '/^rocket_.*$/', false ],
			'regex character class'      => [ 'rocket_123', '/^rocket_[0-9]+$/', true ],
			'regex optional group'       => [ 'rocket_notice_test', '/^rocket_(notice|warning)_test$/', true ],
		];
	}

	/**
	 * Test: matches_pattern regex matching
	 *
	 * @dataProvider provide_regex_match_scenarios
	 *
	 * @param mixed  $callback The callback to test.
	 * @param string $pattern The pattern to match against.
	 * @param bool   $expected Expected result.
	 *
	 * @return void
	 */
	public function test_matches_pattern_regex_match( $callback, $pattern, $expected ) {
		$result = $this->identifier->matches_pattern( $callback, $pattern );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test: matches_pattern handles invalid regex gracefully
	 *
	 * @return void
	 */
	public function test_matches_pattern_handles_invalid_regex() {
		$result = $this->identifier->matches_pattern( 'my_function', '/[invalid(regex/' );

		// Should not throw error, should return false
		$this->assertFalse( $result );
	}

	/**
	 * Test: matches_pattern handles closure callbacks
	 *
	 * @return void
	 */
	public function test_matches_pattern_for_closures() {
		$closure = function () {
			return 'test';
		};

		// Closures should match 'Closure' pattern
		$this->assertTrue( $this->identifier->matches_pattern( $closure, 'Closure' ) );
		$this->assertFalse( $this->identifier->matches_pattern( $closure, 'my_function' ) );
	}

	/**
	 * Test: matches_pattern handles empty pattern
	 *
	 * @return void
	 */
	public function test_matches_pattern_handles_empty_pattern() {
		$result = $this->identifier->matches_pattern( 'my_function', '' );

		$this->assertFalse( $result );
	}

	/**
	 * Test: all closures generate same hash
	 *
	 * This is by design - closures cannot be uniquely identified
	 *
	 * @return void
	 */
	public function test_all_closures_generate_same_hash() {
		$closure1 = function () {
			return 'test1';
		};
		$closure2 = function () {
			return 'test2';
		};

		$hash1 = $this->identifier->generate_hash( $closure1, 'admin_notices' );
		$hash2 = $this->identifier->generate_hash( $closure2, 'admin_notices' );

		$this->assertEquals( $hash1, $hash2, 'All closures should generate the same hash' );
	}

	/**
	 * Test: different functions generate different hashes
	 *
	 * @return void
	 */
	public function test_different_functions_generate_different_hashes() {
		$hash1 = $this->identifier->generate_hash( 'function_one', 'admin_notices' );
		$hash2 = $this->identifier->generate_hash( 'function_two', 'admin_notices' );

		$this->assertNotEquals( $hash1, $hash2 );
	}

	/**
	 * Test: different class methods generate different hashes
	 *
	 * @return void
	 */
	public function test_different_class_methods_generate_different_hashes() {
		$callback1 = [ 'MyClass', 'method_one' ];
		$callback2 = [ 'MyClass', 'method_two' ];

		$hash1 = $this->identifier->generate_hash( $callback1, 'admin_notices' );
		$hash2 = $this->identifier->generate_hash( $callback2, 'admin_notices' );

		$this->assertNotEquals( $hash1, $hash2 );
	}

	/**
	 * Test: get_callback_name handles complex namespaced classes
	 *
	 * @return void
	 */
	public function test_get_callback_name_for_namespaced_class() {
		 $callback = [ 'My\\Namespace\\MyClass', 'show_notice' ];

		$name = $this->identifier->get_callback_name( $callback );

		$this->assertEquals( 'My\\Namespace\\MyClass::show_notice', $name );
	}

	/**
	 * Test: matches_pattern with namespaced class names
	 *
	 * @return void
	 */
	public function test_matches_pattern_with_namespaced_classes() {
		$callback = [ 'WooCommerce\\Admin\\Notice', 'show' ];

		$this->assertTrue( $this->identifier->matches_pattern( $callback, 'WooCommerce\\Admin\\Notice::show' ) );
		$this->assertTrue( $this->identifier->matches_pattern( $callback, 'WooCommerce*::*' ) );
		$this->assertFalse( $this->identifier->matches_pattern( $callback, 'Yoast*::*' ) );
	}

	/**
	 * Test: hash generation performance (should be fast)
	 *
	 * @return void
	 */
	public function test_hash_generation_performance() {
		$start = microtime( true );

		// Generate 1000 hashes
		for ( $i = 0; $i < 1000; $i++ ) {
			$this->identifier->generate_hash( "function_$i", 'admin_notices' );
		}

		$elapsed = microtime( true ) - $start;

		// Should complete in less than 100ms (very generous threshold)
		$this->assertLessThan( 0.1, $elapsed, 'Hash generation should be fast' );
	}

	/**
	 * Test: pattern matching works with real-world plugin callbacks
	 *
	 * @return void
	 */
	public function test_real_world_plugin_patterns() {
		 // WooCommerce patterns
		$wc_callback = [ 'WC_Admin_Notices', 'add_notice' ];
		$this->assertTrue( $this->identifier->matches_pattern( $wc_callback, 'WC_*' ) );
		$this->assertTrue( $this->identifier->matches_pattern( $wc_callback, '/^WC_.*$/' ) );

		// Yoast SEO patterns
		$yoast_callback = [ 'WPSEO_Admin_Notifications', 'show' ];
		$this->assertTrue( $this->identifier->matches_pattern( $yoast_callback, 'WPSEO_*' ) );
		$this->assertTrue( $this->identifier->matches_pattern( $yoast_callback, '/^WPSEO_.*$/' ) );

		// WP Rocket patterns
		$rocket_callback = 'rocket_bad_deactivations';
		$this->assertTrue( $this->identifier->matches_pattern( $rocket_callback, 'rocket_*' ) );
		$this->assertTrue( $this->identifier->matches_pattern( $rocket_callback, '/^rocket_.*$/' ) );
	}
}
