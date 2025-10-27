<?php
/**
 * CapabilityChecker Trait Tests
 *
 * Comprehensive unit tests for the CapabilityChecker trait.
 * Tests all four methods with multiple scenarios including edge cases.
 *
 * @package QalaPluginManager\Tests\Unit\Traits
 */

namespace QalaPluginManager\Tests\Unit\Traits;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QalaPluginManager\NoticeManagement\Traits\CapabilityChecker;

/**
 * Test case for CapabilityChecker trait
 *
 * Tests capability checking functionality using Brain Monkey to mock WordPress functions.
 * Creates a concrete test class since traits cannot be instantiated directly.
 *
 * @group traits
 * @group capability-checker
 * @group unit
 */
class CapabilityCheckerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Concrete class that uses the CapabilityChecker trait for testing
	 *
	 * @var object
	 */
	private $test_instance;

	/**
	 * Set up test environment before each test
	 *
	 * Initializes Brain Monkey and creates a concrete test class instance.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Create anonymous class that uses the trait for testing
		$this->test_instance = new class() {
			use CapabilityChecker;

			// Make protected methods public for testing
			public function public_user_has_capability( string $capability = 'qala_full_access' ): bool {
				return $this->user_has_capability( $capability );
			}

			public function public_can_manage_notices(): bool {
				return $this->can_manage_notices();
			}

			public function public_should_see_notices(): bool {
				return $this->should_see_notices();
			}

			public function public_require_capability( string $capability = 'qala_full_access', string $message = '' ): void {
				$this->require_capability( $capability, $message );
			}
		};
	}

	/**
	 * Tear down test environment after each test
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test user_has_capability() returns false when not in admin context
	 *
	 * @return void
	 */
	public function test_user_has_capability_returns_false_when_not_in_admin(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$result = $this->test_instance->public_user_has_capability( 'qala_full_access' );

		$this->assertFalse( $result, 'Should return false when not in admin context' );
	}

	/**
	 * Test user_has_capability() returns false when user is not logged in
	 *
	 * @return void
	 */
	public function test_user_has_capability_returns_false_when_not_logged_in(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$result = $this->test_instance->public_user_has_capability( 'qala_full_access' );

		$this->assertFalse( $result, 'Should return false when user is not logged in' );
	}

	/**
	 * Test user_has_capability() returns false when user lacks capability
	 *
	 * @return void
	 */
	public function test_user_has_capability_returns_false_when_user_lacks_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );

		$result = $this->test_instance->public_user_has_capability( 'qala_full_access' );

		$this->assertFalse( $result, 'Should return false when user lacks capability' );
	}

	/**
	 * Test user_has_capability() returns true when user has capability
	 *
	 * @return void
	 */
	public function test_user_has_capability_returns_true_when_user_has_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( true );

		$result = $this->test_instance->public_user_has_capability( 'qala_full_access' );

		$this->assertTrue( $result, 'Should return true when user has capability' );
	}

	/**
	 * Test user_has_capability() works with custom capability string
	 *
	 * @return void
	 */
	public function test_user_has_capability_works_with_custom_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( true );

		$result = $this->test_instance->public_user_has_capability( 'manage_options' );

		$this->assertTrue( $result, 'Should work with custom capability strings' );
	}

	/**
	 * Test user_has_capability() defaults to qala_full_access capability
	 *
	 * @return void
	 */
	public function test_user_has_capability_defaults_to_qala_full_access(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'qala_full_access' )
			->andReturn( true );

		$result = $this->test_instance->public_user_has_capability();

		$this->assertTrue( $result, 'Should default to qala_full_access capability' );
	}

	/**
	 * Test can_manage_notices() returns false when user lacks capability
	 *
	 * @return void
	 */
	public function test_can_manage_notices_returns_false_when_user_lacks_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );

		$result = $this->test_instance->public_can_manage_notices();

		$this->assertFalse( $result, 'Should return false when user cannot manage notices' );
	}

	/**
	 * Test can_manage_notices() returns true when user has capability
	 *
	 * @return void
	 */
	public function test_can_manage_notices_returns_true_when_user_has_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( true );

		$result = $this->test_instance->public_can_manage_notices();

		$this->assertTrue( $result, 'Should return true when user can manage notices' );
	}

	/**
	 * Test can_manage_notices() respects admin context requirement
	 *
	 * @return void
	 */
	public function test_can_manage_notices_requires_admin_context(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$result = $this->test_instance->public_can_manage_notices();

		$this->assertFalse( $result, 'Should require admin context' );
	}

	/**
	 * Test should_see_notices() returns true when user has qala_full_access
	 *
	 * @return void
	 */
	public function test_should_see_notices_returns_true_for_privileged_users(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( true );

		$result = $this->test_instance->public_should_see_notices();

		$this->assertTrue( $result, 'Privileged users should always see notices' );
	}

	/**
	 * Test should_see_notices() checks user preference when user lacks capability
	 *
	 * @return void
	 */
	public function test_should_see_notices_checks_user_preference(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\expect( 'get_user_meta' )
			->with( 42, 'qala_show_notices', true )
			->andReturn( 'yes' );

		$result = $this->test_instance->public_should_see_notices();

		$this->assertTrue( $result, 'Should return true when user preference is "yes"' );
	}

	/**
	 * Test should_see_notices() returns false when user preference is not "yes"
	 *
	 * @return void
	 */
	public function test_should_see_notices_returns_false_when_preference_not_yes(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\expect( 'get_user_meta' )
			->with( 42, 'qala_show_notices', true )
			->andReturn( 'no' );

		$result = $this->test_instance->public_should_see_notices();

		$this->assertFalse( $result, 'Should return false when user preference is not "yes"' );
	}

	/**
	 * Test should_see_notices() returns false when user meta is empty
	 *
	 * @return void
	 */
	public function test_should_see_notices_returns_false_when_user_meta_empty(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\expect( 'get_user_meta' )
			->with( 42, 'qala_show_notices', true )
			->andReturn( '' );

		$result = $this->test_instance->public_should_see_notices();

		$this->assertFalse( $result, 'Should return false when user meta is empty' );
	}

	/**
	 * Test should_see_notices() handles user ID of zero
	 *
	 * @return void
	 */
	public function test_should_see_notices_handles_zero_user_id(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\expect( 'get_user_meta' )
			->with( 0, 'qala_show_notices', true )
			->andReturn( 'yes' );

		$result = $this->test_instance->public_should_see_notices();

		$this->assertFalse( $result, 'Should return false for user ID of zero' );
	}

	/**
	 * Test require_capability() calls wp_die when user lacks capability
	 *
	 * @return void
	 */
	public function test_require_capability_calls_wp_die_when_user_lacks_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );
		Functions\expect( '__' )
			->with( 'You do not have permission to access this page.', 'qala-plugin-manager' )
			->andReturnFirstArg();

		Functions\expect( 'wp_die' )
			->once()
			->with( 'You do not have permission to access this page.' );

		$this->test_instance->public_require_capability( 'qala_full_access' );
	}

	/**
	 * Test require_capability() does not call wp_die when user has capability
	 *
	 * @return void
	 */
	public function test_require_capability_does_not_call_wp_die_when_user_has_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( true );

		Functions\expect( 'wp_die' )->never();

		// Should execute without calling wp_die
		$this->test_instance->public_require_capability( 'qala_full_access' );

		// If we get here without wp_die being called, test passes
		$this->assertTrue( true, 'Should not call wp_die when user has capability' );
	}

	/**
	 * Test require_capability() uses custom error message when provided
	 *
	 * @return void
	 */
	public function test_require_capability_uses_custom_error_message(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );

		$custom_message = 'Custom permission denied message';

		Functions\expect( 'wp_die' )
			->once()
			->with( $custom_message );

		$this->test_instance->public_require_capability( 'qala_full_access', $custom_message );
	}

	/**
	 * Test require_capability() uses default message when custom message is empty
	 *
	 * @return void
	 */
	public function test_require_capability_uses_default_message_when_custom_empty(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( false );
		Functions\expect( '__' )
			->with( 'You do not have permission to access this page.', 'qala-plugin-manager' )
			->andReturnFirstArg();

		Functions\expect( 'wp_die' )
			->once()
			->with( 'You do not have permission to access this page.' );

		$this->test_instance->public_require_capability( 'qala_full_access', '' );
	}

	/**
	 * Test require_capability() works with custom capabilities
	 *
	 * @return void
	 */
	public function test_require_capability_works_with_custom_capabilities(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( '__' )
			->with( 'You do not have permission to access this page.', 'qala-plugin-manager' )
			->andReturnFirstArg();

		Functions\expect( 'wp_die' )
			->once()
			->with( 'You do not have permission to access this page.' );

		$this->test_instance->public_require_capability( 'manage_options' );
	}

	/**
	 * Test require_capability() defaults to qala_full_access capability
	 *
	 * @return void
	 */
	public function test_require_capability_defaults_to_qala_full_access(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'qala_full_access' )
			->andReturn( true );

		Functions\expect( 'wp_die' )->never();

		$this->test_instance->public_require_capability();

		$this->assertTrue( true, 'Should default to qala_full_access' );
	}

	/**
	 * Test require_capability() in non-admin context
	 *
	 * @return void
	 */
	public function test_require_capability_in_non_admin_context(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( '__' )
			->with( 'You do not have permission to access this page.', 'qala-plugin-manager' )
			->andReturnFirstArg();

		Functions\expect( 'wp_die' )
			->once()
			->with( 'You do not have permission to access this page.' );

		$this->test_instance->public_require_capability( 'qala_full_access' );
	}

	/**
	 * Data provider for capability check edge cases
	 *
	 * @return array Test cases with different contexts
	 */
	public function capability_check_edge_cases_provider(): array {
		return [
			'not_admin_not_logged_in'     => [
				'is_admin'          => false,
				'is_user_logged_in' => false,
				'current_user_can'  => true,
				'expected'          => false,
				'description'       => 'Not in admin and not logged in',
			],
			'admin_not_logged_in'         => [
				'is_admin'          => true,
				'is_user_logged_in' => false,
				'current_user_can'  => true,
				'expected'          => false,
				'description'       => 'In admin but not logged in',
			],
			'not_admin_logged_in_has_cap' => [
				'is_admin'          => false,
				'is_user_logged_in' => true,
				'current_user_can'  => true,
				'expected'          => false,
				'description'       => 'Logged in with capability but not in admin',
			],
			'admin_logged_in_no_cap'      => [
				'is_admin'          => true,
				'is_user_logged_in' => true,
				'current_user_can'  => false,
				'expected'          => false,
				'description'       => 'In admin and logged in but lacks capability',
			],
			'all_conditions_met'          => [
				'is_admin'          => true,
				'is_user_logged_in' => true,
				'current_user_can'  => true,
				'expected'          => true,
				'description'       => 'All conditions satisfied',
			],
		];
	}

	/**
	 * Test user_has_capability() with various edge case combinations
	 *
	 * @dataProvider capability_check_edge_cases_provider
	 *
	 * @param bool   $is_admin Whether in admin context.
	 * @param bool   $is_user_logged_in Whether user is logged in.
	 * @param bool   $current_user_can Whether user has capability.
	 * @param bool   $expected Expected result.
	 * @param string $description Test case description.
	 *
	 * @return void
	 */
	public function test_user_has_capability_edge_cases(
		bool $is_admin,
		bool $is_user_logged_in,
		bool $current_user_can,
		bool $expected,
		string $description
	): void {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		Functions\when( 'is_user_logged_in' )->justReturn( $is_user_logged_in );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( $current_user_can );

		$result = $this->test_instance->public_user_has_capability( 'qala_full_access' );

		$this->assertSame( $expected, $result, $description );
	}

	/**
	 * Data provider for should_see_notices() scenarios
	 *
	 * @return array Test scenarios
	 */
	public function should_see_notices_scenarios_provider(): array {
		return [
			'privileged_user_always_sees'        => [
				'has_capability'  => true,
				'user_preference' => 'no',
				'expected'        => true,
				'description'     => 'Privileged users always see notices regardless of preference',
			],
			'unprivileged_with_yes_preference'   => [
				'has_capability'  => false,
				'user_preference' => 'yes',
				'expected'        => true,
				'description'     => 'Unprivileged user with "yes" preference sees notices',
			],
			'unprivileged_with_no_preference'    => [
				'has_capability'  => false,
				'user_preference' => 'no',
				'expected'        => false,
				'description'     => 'Unprivileged user with "no" preference does not see notices',
			],
			'unprivileged_with_empty_preference' => [
				'has_capability'  => false,
				'user_preference' => '',
				'expected'        => false,
				'description'     => 'Unprivileged user with empty preference does not see notices',
			],
			'unprivileged_with_null_preference'  => [
				'has_capability'  => false,
				'user_preference' => null,
				'expected'        => false,
				'description'     => 'Unprivileged user with null preference does not see notices',
			],
		];
	}

	/**
	 * Test should_see_notices() with various scenarios
	 *
	 * @dataProvider should_see_notices_scenarios_provider
	 *
	 * @param bool   $has_capability Whether user has qala_full_access.
	 * @param mixed  $user_preference User preference value.
	 * @param bool   $expected Expected result.
	 * @param string $description Test case description.
	 *
	 * @return void
	 */
	public function test_should_see_notices_scenarios(
		bool $has_capability,
		$user_preference,
		bool $expected,
		string $description
	): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\expect( 'current_user_can' )
			->with( 'qala_full_access' )
			->andReturn( $has_capability );
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\expect( 'get_user_meta' )
			->with( 42, 'qala_show_notices', true )
			->andReturn( $user_preference );

		$result = $this->test_instance->public_should_see_notices();

		$this->assertSame( $expected, $result, $description );
	}
}
