<?php
/**
 * AdminBarToggle Test
 *
 * Comprehensive tests for the AdminBarToggle class - admin bar quick toggle for notice visibility.
 * Tests hook registration, admin bar menu, AJAX handlers, user preference storage, and capability checking.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;
use QalaPluginManager\NoticeManagement\AdminBarToggle;
use QalaPluginManager\Tests\Unit\TestCase;

/**
 * Test case for AdminBarToggle class
 *
 * Covers:
 * - Hook registration (admin_bar_menu, admin_enqueue_scripts, wp_ajax)
 * - Admin bar menu item creation
 * - Current state display (Notices: On/Off)
 * - AJAX toggle handler
 * - User preference storage (get/set user meta)
 * - Nonce verification
 * - Capability checking (qala_full_access)
 * - Asset enqueueing (JS/CSS)
 * - Error handling
 *
 * @group notice-management
 * @group admin-bar-toggle
 * @group unit
 */
class AdminBarToggleTest extends TestCase {

	/**
	 * AdminBarToggle instance for testing
	 *
	 * @var AdminBarToggle
	 */
	private $admin_bar_toggle;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create AdminBarToggle instance
		$this->admin_bar_toggle = new AdminBarToggle();

		// Mock common WordPress functions
		$this->mockGetCurrentUserId( 1 );
		$this->mockGetCurrentBlogId( 1 );
	}

	/**
	 * Test: AdminBarToggle implements WithHooksInterface
	 *
	 * @return void
	 */
	public function test_implements_with_hooks_interface(): void {
		$this->assertInstanceOf(
			\QalaPluginManager\Interfaces\WithHooksInterface::class,
			$this->admin_bar_toggle,
			'AdminBarToggle should implement WithHooksInterface'
		);
	}

	/**
	 * Test: init() registers admin_bar_menu hook
	 *
	 * @return void
	 */
	public function test_init_registers_admin_bar_menu_hook(): void {
		Actions\expectAdded( 'admin_bar_menu' )
			->once()
			->with( [ $this->admin_bar_toggle, 'add_admin_bar_menu' ], 999 );

		$this->admin_bar_toggle->init();
	}

	/**
	 * Test: init() registers admin_enqueue_scripts hook
	 *
	 * @return void
	 */
	public function test_init_registers_enqueue_scripts_hook(): void {
		Actions\expectAdded( 'admin_enqueue_scripts' )
			->once()
			->with( [ $this->admin_bar_toggle, 'enqueue_assets' ] );

		$this->admin_bar_toggle->init();
	}

	/**
	 * Test: init() registers AJAX handler for toggle
	 *
	 * @return void
	 */
	public function test_init_registers_ajax_toggle_handler(): void {
		Actions\expectAdded( 'wp_ajax_qala_toggle_notice_visibility' )
			->once()
			->with( [ $this->admin_bar_toggle, 'handle_toggle_ajax' ] );

		$this->admin_bar_toggle->init();
	}

	/**
	 * Test: init() registers all hooks correctly
	 *
	 * @return void
	 */
	public function test_init_registers_all_hooks(): void {
		Actions\expectAdded( 'admin_bar_menu' )->once();
		Actions\expectAdded( 'admin_enqueue_scripts' )->once();
		Actions\expectAdded( 'wp_ajax_qala_toggle_notice_visibility' )->once();

		$this->admin_bar_toggle->init();
	}

	/**
	 * Test: add_admin_bar_menu() does nothing for users without qala_full_access
	 *
	 * @return void
	 */
	public function test_add_admin_bar_menu_skips_for_non_privileged_users(): void {
		// Mock user WITHOUT qala_full_access
		Functions\when( 'current_user_can' )
			->justReturn( false );

		// Create mock WP_Admin_Bar
		$wp_admin_bar = Mockery::mock( 'WP_Admin_Bar' );

		// Should NOT add any nodes
		$wp_admin_bar->shouldNotReceive( 'add_node' );

		$this->admin_bar_toggle->add_admin_bar_menu( $wp_admin_bar );
	}

	/**
	 * Test: add_admin_bar_menu() adds menu item for privileged users
	 *
	 * @return void
	 */
	public function test_add_admin_bar_menu_adds_item_for_privileged_users(): void {
		// Mock user WITH qala_full_access
		Functions\when( 'current_user_can' )
			->justReturn( true );

		// Mock user preference (notices hidden)
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( '' );

		// Create mock WP_Admin_Bar
		$wp_admin_bar = Mockery::mock( 'WP_Admin_Bar' );

		// Should add menu node
		$wp_admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return $args['id'] === 'qala-notice-toggle'
					&& isset( $args['title'] )
					&& isset( $args['href'] )
					&& isset( $args['meta'] );
			} ) );

		$this->admin_bar_toggle->add_admin_bar_menu( $wp_admin_bar );
	}

	/**
	 * Test: add_admin_bar_menu() shows "Notices: Off" when notices are hidden
	 *
	 * @return void
	 */
	public function test_add_admin_bar_menu_shows_off_state_when_hidden(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		// Mock user preference - notices hidden (empty or 'no')
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( '' );

		$wp_admin_bar = Mockery::mock( 'WP_Admin_Bar' );

		$wp_admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( function ( $args ) {
				// Title should indicate notices are OFF
				return strpos( $args['title'], 'Off' ) !== false
					|| strpos( $args['title'], 'OFF' ) !== false;
			} ) );

		$this->admin_bar_toggle->add_admin_bar_menu( $wp_admin_bar );
	}

	/**
	 * Test: add_admin_bar_menu() shows "Notices: On" when notices are visible
	 *
	 * @return void
	 */
	public function test_add_admin_bar_menu_shows_on_state_when_visible(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		// Mock user preference - notices visible
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( 'yes' );

		$wp_admin_bar = Mockery::mock( 'WP_Admin_Bar' );

		$wp_admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( function ( $args ) {
				// Title should indicate notices are ON
				return strpos( $args['title'], 'On' ) !== false
					|| strpos( $args['title'], 'ON' ) !== false;
			} ) );

		$this->admin_bar_toggle->add_admin_bar_menu( $wp_admin_bar );
	}

	/**
	 * Test: add_admin_bar_menu() sets correct menu ID
	 *
	 * @return void
	 */
	public function test_add_admin_bar_menu_sets_correct_id(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$wp_admin_bar = Mockery::mock( 'WP_Admin_Bar' );

		$wp_admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return $args['id'] === 'qala-notice-toggle';
			} ) );

		$this->admin_bar_toggle->add_admin_bar_menu( $wp_admin_bar );
	}

	/**
	 * Test: add_admin_bar_menu() sets href to # for JavaScript handling
	 *
	 * @return void
	 */
	public function test_add_admin_bar_menu_sets_href_to_hash(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$wp_admin_bar = Mockery::mock( 'WP_Admin_Bar' );

		$wp_admin_bar->shouldReceive( 'add_node' )
			->once()
			->with( Mockery::on( function ( $args ) {
				return $args['href'] === '#';
			} ) );

		$this->admin_bar_toggle->add_admin_bar_menu( $wp_admin_bar );
	}

	/**
	 * Test: handle_toggle_ajax() verifies nonce
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_verifies_nonce(): void {
		$_POST['nonce'] = 'invalid-nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'qala_notice_toggle', 'nonce', false )
			->andReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( Mockery::on( function ( $data ) {
				return isset( $data['message'] );
			} ) );

		$this->admin_bar_toggle->handle_toggle_ajax();
	}

	/**
	 * Test: handle_toggle_ajax() checks capability
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_checks_capability(): void {
		$_POST['nonce'] = 'valid-nonce';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );

		Functions\when( 'current_user_can' )
			->justReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( Mockery::on( function ( $data ) {
				return $data['message'] === 'Permission denied';
			} ) );

		$this->admin_bar_toggle->handle_toggle_ajax();
	}

	/**
	 * Test: handle_toggle_ajax() toggles from hidden to visible
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_toggles_from_hidden_to_visible(): void {
		$_POST['nonce'] = 'valid-nonce';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// Current state: notices hidden (empty or 'no')
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( '' );

		// Should update to 'yes'
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 1, 'qala_show_notices', 'yes' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( Mockery::on( function ( $data ) {
				return $data['showing'] === true
					&& isset( $data['message'] )
					&& isset( $data['new_title'] );
			} ) );

		$this->admin_bar_toggle->handle_toggle_ajax();
	}

	/**
	 * Test: handle_toggle_ajax() toggles from visible to hidden
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_toggles_from_visible_to_hidden(): void {
		$_POST['nonce'] = 'valid-nonce';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// Current state: notices visible
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( 'yes' );

		// Should update to 'no'
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 1, 'qala_show_notices', 'no' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( Mockery::on( function ( $data ) {
				return $data['showing'] === false
					&& isset( $data['message'] )
					&& isset( $data['new_title'] );
			} ) );

		$this->admin_bar_toggle->handle_toggle_ajax();
	}

	/**
	 * Test: get_user_preference() returns 'yes' when notices are visible
	 *
	 * @return void
	 */
	public function test_get_user_preference_returns_yes_when_visible(): void {
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( 'yes' );

		$result = $this->admin_bar_toggle->get_user_preference( 1 );

		$this->assertEquals( 'yes', $result );
	}

	/**
	 * Test: get_user_preference() returns 'no' when notices are hidden
	 *
	 * @return void
	 */
	public function test_get_user_preference_returns_no_when_hidden(): void {
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( '' );

		$result = $this->admin_bar_toggle->get_user_preference( 1 );

		$this->assertEquals( 'no', $result );
	}

	/**
	 * Test: get_user_preference() uses current user when no user_id provided
	 *
	 * @return void
	 */
	public function test_get_user_preference_uses_current_user_by_default(): void {
		// Current user ID is 1 (mocked in setUp)
		Functions\expect( 'get_user_meta' )
			->with( 1, 'qala_show_notices', true )
			->andReturn( 'yes' );

		$result = $this->admin_bar_toggle->get_user_preference();

		$this->assertEquals( 'yes', $result );
	}

	/**
	 * Test: get_user_preference() returns 'yes' for yes meta value
	 *
	 * @return void
	 */
	public function test_get_user_preference_returns_yes_for_yes_meta(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 1, 'qala_show_notices', true )
			->andReturn( 'yes' );

		$this->assertEquals( 'yes', $this->admin_bar_toggle->get_user_preference( 1 ) );
	}

	/**
	 * Test: get_user_preference() returns 'no' for no meta value
	 *
	 * @return void
	 */
	public function test_get_user_preference_returns_no_for_no_meta(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 2, 'qala_show_notices', true )
			->andReturn( 'no' );

		$this->assertEquals( 'no', $this->admin_bar_toggle->get_user_preference( 2 ) );
	}

	/**
	 * Test: get_user_preference() returns 'no' for empty meta value
	 *
	 * @return void
	 */
	public function test_get_user_preference_returns_no_for_empty_meta(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 3, 'qala_show_notices', true )
			->andReturn( '' );

		$this->assertEquals( 'no', $this->admin_bar_toggle->get_user_preference( 3 ) );
	}

	/**
	 * Test: set_user_preference() updates user meta to 'yes'
	 *
	 * @return void
	 */
	public function test_set_user_preference_updates_to_yes(): void {
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 1, 'qala_show_notices', 'yes' )
			->andReturn( true );

		$result = $this->admin_bar_toggle->set_user_preference( 'yes', 1 );

		$this->assertTrue( $result );
	}

	/**
	 * Test: set_user_preference() updates user meta to 'no'
	 *
	 * @return void
	 */
	public function test_set_user_preference_updates_to_no(): void {
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 1, 'qala_show_notices', 'no' )
			->andReturn( true );

		$result = $this->admin_bar_toggle->set_user_preference( 'no', 1 );

		$this->assertTrue( $result );
	}

	/**
	 * Test: set_user_preference() uses current user when no user_id provided
	 *
	 * @return void
	 */
	public function test_set_user_preference_uses_current_user_by_default(): void {
		// Current user ID is 1 (mocked in setUp)
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 1, 'qala_show_notices', 'yes' )
			->andReturn( true );

		$result = $this->admin_bar_toggle->set_user_preference( 'yes' );

		$this->assertTrue( $result );
	}

	/**
	 * Test: set_user_preference() sanitizes value to yes/no
	 *
	 * @return void
	 */
	public function test_set_user_preference_sanitizes_value(): void {
		// Any truthy value should become 'yes'
		Functions\expect( 'update_user_meta' )
			->with( 1, 'qala_show_notices', 'yes' )
			->andReturn( true );
		$this->assertTrue( $this->admin_bar_toggle->set_user_preference( 'yes', 1 ) );

		// Any non-'yes' value should become 'no'
		Functions\expect( 'update_user_meta' )
			->with( 1, 'qala_show_notices', 'no' )
			->andReturn( true );
		$this->assertTrue( $this->admin_bar_toggle->set_user_preference( 'no', 1 ) );
	}

	/**
	 * Test: enqueue_assets() enqueues JavaScript file
	 *
	 * @return void
	 */
	public function test_enqueue_assets_enqueues_javascript(): void {
		Functions\expect( 'plugin_dir_url' )
			->andReturn( 'http://example.com/wp-content/mu-plugins/qala-plugin-manager/' );

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'qala-admin-bar-toggle',
				Mockery::type( 'string' ),
				[ 'jquery' ],
				Mockery::any(),
				true
			);

		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );

		$this->admin_bar_toggle->enqueue_assets();
	}

	/**
	 * Test: enqueue_assets() enqueues CSS file
	 *
	 * @return void
	 */
	public function test_enqueue_assets_enqueues_css(): void {
		Functions\expect( 'plugin_dir_url' )
			->andReturn( 'http://example.com/wp-content/mu-plugins/qala-plugin-manager/' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'qala-admin-bar-toggle',
				Mockery::type( 'string' ),
				[],
				Mockery::any()
			);

		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );

		$this->admin_bar_toggle->enqueue_assets();
	}

	/**
	 * Test: enqueue_assets() localizes script with AJAX data
	 *
	 * @return void
	 */
	public function test_enqueue_assets_localizes_script_with_ajax_data(): void {
		Functions\when( 'plugin_dir_url' )
			->justReturn( 'http://example.com/wp-content/mu-plugins/qala-plugin-manager/' );

		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( true );

		Functions\expect( 'wp_create_nonce' )
			->once()
			->with( 'qala_notice_toggle' )
			->andReturn( 'test-nonce-12345' );

		Functions\expect( 'admin_url' )
			->once()
			->with( 'admin-ajax.php' )
			->andReturn( 'http://example.com/wp-admin/admin-ajax.php' );

		Functions\expect( 'wp_localize_script' )
			->once()
			->with(
				'qala-admin-bar-toggle',
				'qalaAdminBarToggle',
				Mockery::on( function ( $data ) {
					return isset( $data['ajaxUrl'] )
						&& isset( $data['nonce'] )
						&& isset( $data['action'] )
						&& $data['action'] === 'qala_toggle_notice_visibility';
				} )
			);

		$this->admin_bar_toggle->enqueue_assets();
	}

	/**
	 * Test: handle_toggle_ajax() returns correct new title when toggling to visible
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_returns_correct_title_when_toggling_to_visible(): void {
		$_POST['nonce'] = 'valid-nonce';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// Toggle from hidden to visible
		Functions\expect( 'get_user_meta' )
			->andReturn( '' );

		Functions\expect( 'update_user_meta' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( Mockery::on( function ( $data ) {
				// New title should show "On"
				return isset( $data['new_title'] )
					&& ( strpos( $data['new_title'], 'On' ) !== false
						|| strpos( $data['new_title'], 'ON' ) !== false );
			} ) );

		$this->admin_bar_toggle->handle_toggle_ajax();
	}

	/**
	 * Test: handle_toggle_ajax() returns correct new title when toggling to hidden
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_returns_correct_title_when_toggling_to_hidden(): void {
		$_POST['nonce'] = 'valid-nonce';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// Toggle from visible to hidden
		Functions\expect( 'get_user_meta' )
			->andReturn( 'yes' );

		Functions\expect( 'update_user_meta' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( Mockery::on( function ( $data ) {
				// New title should show "Off"
				return isset( $data['new_title'] )
					&& ( strpos( $data['new_title'], 'Off' ) !== false
						|| strpos( $data['new_title'], 'OFF' ) !== false );
			} ) );

		$this->admin_bar_toggle->handle_toggle_ajax();
	}

	/**
	 * Clean up after each test
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_POST );
		parent::tearDown();
	}
}
