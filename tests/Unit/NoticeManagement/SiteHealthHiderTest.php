<?php
/**
 * SiteHealthHider Test
 *
 * Comprehensive tests for the SiteHealthHider class that hides Site Health
 * pages and widgets from users without qala_full_access capability.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use QalaPluginManager\NoticeManagement\SiteHealthHider;
use QalaPluginManager\Tests\Unit\TestCase;

/**
 * Test case for SiteHealthHider class
 *
 * @group notice-management
 * @group site-health
 * @group unit
 */
class SiteHealthHiderTest extends TestCase {

	/**
	 * SiteHealthHider instance for testing
	 *
	 * @var SiteHealthHider
	 */
	private $hider;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create SiteHealthHider instance
		$this->hider = new SiteHealthHider();

		// Mock get_current_user_id
		$this->mockGetCurrentUserId( 1 );

		// Mock get_option for Site Health settings
		// Default: hide Site Health for all users
		Functions\when( 'get_option' )->alias( function ( $option, $default = false ) {
			if ( $option === 'qala_hide_site_health_for_all' ) {
				return 'yes'; // Default: hide for all
			}
			if ( $option === 'qala_show_site_health_for_non_qala_users' ) {
				return 'no'; // Default: don't show for non-qala users
			}
			return $default;
		} );
	}

	/**
	 * Test: implements WithHooksInterface
	 *
	 * @return void
	 */
	public function test_implements_with_hooks_interface(): void {
		$this->assertInstanceOf(
			\QalaPluginManager\Interfaces\WithHooksInterface::class,
			$this->hider,
			'SiteHealthHider should implement WithHooksInterface'
		);
	}

	/**
	 * Test: init() registers all required hooks
	 *
	 * @return void
	 */
	public function test_init_registers_all_hooks(): void {
		// Expect admin_menu hook to be added
		Actions\expectAdded( 'admin_menu' )
			->once()
			->whenHappen(
				function ( $callback, $priority ) {
					$this->assertEquals( 999, $priority, 'admin_menu priority should be 999' );
				}
			);

		// Expect wp_dashboard_setup hook to be added
		Actions\expectAdded( 'wp_dashboard_setup' )
			->once()
			->whenHappen(
				function ( $callback, $priority ) {
					$this->assertEquals( 10, $priority, 'wp_dashboard_setup priority should be 10' );
				}
			);

		// Expect admin_init hook to be added
		Actions\expectAdded( 'admin_init' )
			->once()
			->whenHappen(
				function ( $callback, $priority ) {
					$this->assertEquals( 10, $priority, 'admin_init priority should be 10' );
				}
			);

		$this->hider->init();
	}

	/**
	 * Test: remove_menu_page() removes Site Health menu for non-privileged users
	 *
	 * @return void
	 */
	public function test_remove_menu_page_removes_for_non_privileged_users(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Expect remove_submenu_page to be called (Site Health is a submenu, not a menu)
		Functions\expect( 'remove_submenu_page' )
			->once()
			->with( 'tools.php', 'site-health.php' )
			->andReturn( true );

		$this->hider->remove_menu_page();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: remove_menu_page() does nothing for privileged users
	 *
	 * @return void
	 */
	public function test_remove_menu_page_preserves_for_privileged_users(): void {
		// Mock user with qala_full_access
		Functions\when( 'current_user_can' )->justReturn( true );

		// remove_submenu_page should NOT be called
		Functions\expect( 'remove_submenu_page' )->never();

		$this->hider->remove_menu_page();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: remove_menu_page() does nothing when not in admin
	 *
	 * @return void
	 */
	public function test_remove_menu_page_skips_when_not_admin(): void {
		// Mock not in admin context
		Functions\when( 'is_admin' )->justReturn( false );

		// remove_submenu_page should NOT be called
		Functions\expect( 'remove_submenu_page' )->never();

		$this->hider->remove_menu_page();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: remove_dashboard_widget() removes widget for non-privileged users
	 *
	 * @return void
	 */
	public function test_remove_dashboard_widget_removes_for_non_privileged_users(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Expect remove_meta_box to be called
		Functions\expect( 'remove_meta_box' )
			->once()
			->with( 'dashboard_site_health', 'dashboard', 'normal' )
			->andReturnNull();

		$this->hider->remove_dashboard_widget();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: remove_dashboard_widget() does nothing for privileged users
	 *
	 * @return void
	 */
	public function test_remove_dashboard_widget_preserves_for_privileged_users(): void {
		// Mock user with qala_full_access
		Functions\when( 'current_user_can' )->justReturn( true );

		// remove_meta_box should NOT be called
		Functions\expect( 'remove_meta_box' )->never();

		$this->hider->remove_dashboard_widget();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: redirect_site_health() redirects non-privileged users
	 *
	 * @return void
	 */
	public function test_redirect_site_health_redirects_non_privileged_users(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Mock we are on site-health.php page
		global $pagenow;
		$pagenow = 'site-health.php';

		// Not doing AJAX
		Functions\when( 'wp_doing_ajax' )->justReturn( false );

		// Expect wp_safe_redirect to be called to dashboard
		Functions\expect( 'admin_url' )
			->once()
			->with( '' )
			->andReturn( 'http://example.com/wp-admin/' );

		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'http://example.com/wp-admin/' )
			->andReturnNull();

		$this->hider->redirect_site_health();

		// If we get here, expectations were met
		$this->assertTrue( true );
	}

	/**
	 * Test: redirect_site_health() does nothing for privileged users
	 *
	 * @return void
	 */
	public function test_redirect_site_health_allows_privileged_users(): void {
		// Mock user with qala_full_access
		Functions\when( 'current_user_can' )->justReturn( true );

		// Mock we are on site-health.php page
		global $pagenow;
		$pagenow = 'site-health.php';

		// wp_safe_redirect should NOT be called
		Functions\expect( 'wp_safe_redirect' )->never();

		$this->hider->redirect_site_health();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: redirect_site_health() does nothing when not on site-health.php
	 *
	 * @return void
	 */
	public function test_redirect_site_health_skips_other_pages(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Mock we are on a different page
		global $pagenow;
		$pagenow = 'index.php';

		// Mock wp_doing_ajax to avoid "not defined" error
		Functions\when( 'wp_doing_ajax' )->justReturn( false );

		// wp_safe_redirect should NOT be called
		Functions\expect( 'wp_safe_redirect' )->never();

		$this->hider->redirect_site_health();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: is_site_health_page() correctly identifies Site Health pages
	 *
	 * @return void
	 */
	public function test_is_site_health_page_identifies_correctly(): void {
		// Test site-health.php
		global $pagenow;
		$pagenow = 'site-health.php';
		$this->assertTrue(
			$this->hider->is_site_health_page(),
			'Should identify site-health.php as Site Health page'
		);

		// Test health-check.php
		$pagenow = 'health-check.php';
		$this->assertTrue(
			$this->hider->is_site_health_page(),
			'Should identify health-check.php as Site Health page'
		);

		// Test other pages
		$pagenow = 'index.php';
		$this->assertFalse(
			$this->hider->is_site_health_page(),
			'Should NOT identify index.php as Site Health page'
		);

		$pagenow = 'plugins.php';
		$this->assertFalse(
			$this->hider->is_site_health_page(),
			'Should NOT identify plugins.php as Site Health page'
		);

		// Test when $pagenow is not set
		unset( $pagenow );
		$this->assertFalse(
			$this->hider->is_site_health_page(),
			'Should return false when $pagenow is not set'
		);
	}

	/**
	 * Test: redirect_site_health() handles AJAX requests properly
	 *
	 * @return void
	 */
	public function test_redirect_site_health_skips_ajax_requests(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Mock AJAX request
		Functions\expect( 'wp_doing_ajax' )
			->once()
			->andReturn( true );

		// Mock we are on site-health.php page
		global $pagenow;
		$pagenow = 'site-health.php';

		// wp_safe_redirect should NOT be called for AJAX
		Functions\expect( 'wp_safe_redirect' )->never();

		$this->hider->redirect_site_health();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: complete flow for non-privileged user
	 *
	 * @return void
	 */
	public function test_complete_flow_for_non_privileged_user(): void {
		// Mock user without qala_full_access
		Functions\when( 'current_user_can' )->justReturn( false );

		// Mock we are on site-health.php
		global $pagenow;
		$pagenow = 'site-health.php';

		// Not doing AJAX
		Functions\when( 'wp_doing_ajax' )->justReturn( false );

		// Expect submenu page removal
		Functions\expect( 'remove_submenu_page' )
			->once()
			->with( 'tools.php', 'site-health.php' );

		// Expect widget removal
		Functions\expect( 'remove_meta_box' )
			->once()
			->with( 'dashboard_site_health', 'dashboard', 'normal' );

		// Expect redirect
		Functions\expect( 'admin_url' )
			->once()
			->andReturn( 'http://example.com/wp-admin/' );

		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'http://example.com/wp-admin/' );

		// Execute all methods
		$this->hider->remove_menu_page();
		$this->hider->remove_dashboard_widget();
		$this->hider->redirect_site_health();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}

	/**
	 * Test: complete flow for privileged user
	 *
	 * @return void
	 */
	public function test_complete_flow_for_privileged_user(): void {
		// Mock user with qala_full_access
		Functions\when( 'current_user_can' )->justReturn( true );

		// Mock we are on site-health.php
		global $pagenow;
		$pagenow = 'site-health.php';

		// Nothing should be called
		Functions\expect( 'remove_submenu_page' )->never();
		Functions\expect( 'remove_meta_box' )->never();
		Functions\expect( 'wp_safe_redirect' )->never();

		// Execute all methods
		$this->hider->remove_menu_page();
		$this->hider->remove_dashboard_widget();
		$this->hider->redirect_site_health();

		// Expectations verified by Brain Monkey
		$this->assertTrue( true );
	}
}
