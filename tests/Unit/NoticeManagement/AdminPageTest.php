<?php
/**
 * AdminPage Test
 *
 * Comprehensive tests for the AdminPage class - settings page UI for notice management.
 * Tests menu registration, settings API, AJAX handlers, nonce verification, and capability checking.
 *
 * @package QalaPluginManager\Tests\Unit\NoticeManagement
 */

namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;
use QalaPluginManager\NoticeManagement\AdminPage;
use QalaPluginManager\NoticeManagement\AllowlistManager;
use QalaPluginManager\NoticeManagement\NoticeLogger;
use QalaPluginManager\Tests\Unit\TestCase;

/**
 * Test case for AdminPage class
 *
 * Covers:
 * - Menu registration under Settings menu
 * - Settings API registration
 * - Asset enqueueing (CSS/JS)
 * - AJAX handler registration
 * - Add pattern AJAX handler with nonce verification
 * - Remove pattern AJAX handler with nonce verification
 * - Global toggle AJAX handler
 * - Form sanitization
 * - Capability checking (qala_full_access)
 * - Page rendering
 * - Error handling
 *
 * @group notice-management
 * @group admin-page
 * @group unit
 */
class AdminPageTest extends TestCase {

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
	 * AdminPage instance for testing
	 *
	 * @var AdminPage
	 */
	private $admin_page;

	/**
	 * Set up test environment before each test
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mocks for dependencies
		$this->allowlist_mock = Mockery::mock( AllowlistManager::class );
		$this->logger_mock = Mockery::mock( NoticeLogger::class );

		// Create AdminPage instance with mocked dependencies
		$this->admin_page = new AdminPage(
			$this->allowlist_mock,
			$this->logger_mock
		);

		// Mock common WordPress functions
		$this->mockGetCurrentUserId( 1 );
		$this->mockGetCurrentBlogId( 1 );
	}

	/**
	 * Test: AdminPage implements WithHooksInterface
	 *
	 * @return void
	 */
	public function test_implements_with_hooks_interface(): void {
		$this->assertInstanceOf(
			\QalaPluginManager\Interfaces\WithHooksInterface::class,
			$this->admin_page,
			'AdminPage should implement WithHooksInterface'
		);
	}

	/**
	 * Test: init() registers admin_menu hook
	 *
	 * @return void
	 */
	public function test_init_registers_admin_menu_hook(): void {
		Actions\expectAdded( 'admin_menu' )
			->once()
			->with( [ $this->admin_page, 'register_menu' ] );

		$this->admin_page->init();
	}

	/**
	 * Test: init() registers admin_init hook for settings
	 *
	 * @return void
	 */
	public function test_init_registers_admin_init_hook(): void {
		Actions\expectAdded( 'admin_init' )
			->once()
			->with( [ $this->admin_page, 'register_settings' ] );

		$this->admin_page->init();
	}

	/**
	 * Test: init() registers admin_enqueue_scripts hook
	 *
	 * @return void
	 */
	public function test_init_registers_enqueue_scripts_hook(): void {
		Actions\expectAdded( 'admin_enqueue_scripts' )
			->once()
			->with( [ $this->admin_page, 'enqueue_assets' ] );

		$this->admin_page->init();
	}

	/**
	 * Test: init() registers AJAX handlers for add pattern
	 *
	 * @return void
	 */
	public function test_init_registers_ajax_add_pattern_handler(): void {
		Actions\expectAdded( 'wp_ajax_qala_add_allowlist_pattern' )
			->once()
			->with( [ $this->admin_page, 'handle_add_pattern_ajax' ] );

		$this->admin_page->init();
	}

	/**
	 * Test: init() registers AJAX handlers for remove pattern
	 *
	 * @return void
	 */
	public function test_init_registers_ajax_remove_pattern_handler(): void {
		Actions\expectAdded( 'wp_ajax_qala_remove_allowlist_pattern' )
			->once()
			->with( [ $this->admin_page, 'handle_remove_pattern_ajax' ] );

		$this->admin_page->init();
	}

	/**
	 * Test: init() registers AJAX handlers for global toggle
	 *
	 * @return void
	 */
	public function test_init_registers_ajax_toggle_handler(): void {
		Actions\expectAdded( 'wp_ajax_qala_toggle_notices' )
			->once()
			->with( [ $this->admin_page, 'handle_toggle_ajax' ] );

		$this->admin_page->init();
	}

	/**
	 * Test: register_menu() adds settings page under Settings menu
	 *
	 * @return void
	 */
	public function test_register_menu_adds_settings_page(): void {
		Functions\expect( 'add_options_page' )
			->once()
			->with(
				'Hide Notices Settings',
				'Hide Notices',
				'qala_full_access',
				'qala-hide-notices',
				[ $this->admin_page, 'render_page' ]
			)
			->andReturn( 'settings_page_qala-hide-notices' );

		$result = $this->admin_page->register_menu();

		$this->assertEquals( 'settings_page_qala-hide-notices', $result );
	}

	/**
	 * Test: register_menu() uses correct capability
	 *
	 * @return void
	 */
	public function test_register_menu_requires_qala_full_access(): void {
		Functions\expect( 'add_options_page' )
			->once()
			->withArgs( function ( $page_title, $menu_title, $capability ) {
				return $capability === 'qala_full_access';
			} )
			->andReturn( 'settings_page_qala-hide-notices' );

		$this->admin_page->register_menu();
	}

	/**
	 * Test: register_settings() registers qala_notices_enabled setting
	 *
	 * @return void
	 */
	public function test_register_settings_registers_enabled_option(): void {
		Functions\expect( 'register_setting' )
			->once()
			->with(
				'qala_notices',
				'qala_notices_enabled',
				Mockery::on( function ( $args ) {
					return $args['type'] === 'string'
						&& $args['default'] === 'yes'
						&& is_callable( $args['sanitize_callback'] );
				} )
			);

		Functions\expect( 'add_settings_section' )->once();
		Functions\expect( 'add_settings_field' )->once();

		$this->admin_page->register_settings();
	}

	/**
	 * Test: register_settings() registers settings section
	 *
	 * @return void
	 */
	public function test_register_settings_adds_settings_section(): void {
		Functions\expect( 'register_setting' )->once();

		Functions\expect( 'add_settings_section' )
			->once()
			->with(
				'qala_notices_main',
				'Notice Management Settings',
				Mockery::any(),
				'qala-hide-notices'
			);

		Functions\expect( 'add_settings_field' )->once();

		$this->admin_page->register_settings();
	}

	/**
	 * Test: register_settings() registers global toggle field
	 *
	 * @return void
	 */
	public function test_register_settings_adds_global_toggle_field(): void {
		Functions\expect( 'register_setting' )->once();
		Functions\expect( 'add_settings_section' )->once();

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'qala_notices_enabled',
				'Enable Notice Hiding',
				Mockery::any(),
				'qala-hide-notices',
				'qala_notices_main'
			);

		$this->admin_page->register_settings();
	}

	/**
	 * Test: sanitize_yes_no() returns 'yes' for truthy values
	 *
	 * @return void
	 */
	public function test_sanitize_yes_no_returns_yes_for_truthy_values(): void {
		$this->assertEquals( 'yes', $this->admin_page->sanitize_yes_no( '1' ) );
		$this->assertEquals( 'yes', $this->admin_page->sanitize_yes_no( 'yes' ) );
		$this->assertEquals( 'yes', $this->admin_page->sanitize_yes_no( 'on' ) );
		$this->assertEquals( 'yes', $this->admin_page->sanitize_yes_no( true ) );
	}

	/**
	 * Test: sanitize_yes_no() returns 'no' for falsy values
	 *
	 * @return void
	 */
	public function test_sanitize_yes_no_returns_no_for_falsy_values(): void {
		$this->assertEquals( 'no', $this->admin_page->sanitize_yes_no( '0' ) );
		$this->assertEquals( 'no', $this->admin_page->sanitize_yes_no( 'no' ) );
		$this->assertEquals( 'no', $this->admin_page->sanitize_yes_no( 'off' ) );
		$this->assertEquals( 'no', $this->admin_page->sanitize_yes_no( false ) );
		$this->assertEquals( 'no', $this->admin_page->sanitize_yes_no( '' ) );
	}

	/**
	 * Test: render_page() checks capability before rendering
	 *
	 * @return void
	 */
	public function test_render_page_checks_capability(): void {
		// Mock user WITHOUT qala_full_access
		Functions\when( 'current_user_can' )
			->justReturn( false );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'wp_die called: You do not have permission to access this page.' );

		$this->admin_page->render_page();
	}

	/**
	 * Test: render_page() renders page for users with capability
	 *
	 * @return void
	 */
	public function test_render_page_renders_for_authorized_users(): void {
		// Mock user WITH qala_full_access
		Functions\when( 'current_user_can' )
			->justReturn( true );

		// Mock WordPress admin functions
		Functions\expect( 'get_option' )
			->with( 'qala_notices_enabled', 'yes' )
			->andReturn( 'yes' );

		Functions\when( 'esc_html_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'esc_attr_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'checked' )->returnArg();

		Functions\expect( 'settings_fields' )->once();
		Functions\expect( 'do_settings_sections' )->once();
		Functions\expect( 'submit_button' )->once();
		Functions\expect( 'wp_nonce_field' )->once();

		// Mock logger to return empty data
		$this->logger_mock->shouldReceive( 'get_unique_notices' )
			->andReturn( [] );

		$this->allowlist_mock->shouldReceive( 'get_all_patterns' )
			->andReturn( [] );

		// Capture output
		ob_start();
		$this->admin_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Hide Notices Settings', $output );
	}

	/**
	 * Test: enqueue_assets() only enqueues on settings page
	 *
	 * @return void
	 */
	public function test_enqueue_assets_only_on_settings_page(): void {
		// Mock NOT on settings page
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();

		$this->admin_page->enqueue_assets( 'index.php' );
	}

	/**
	 * Test: enqueue_assets() enqueues CSS on settings page
	 *
	 * @return void
	 */
	public function test_enqueue_assets_enqueues_css_on_settings_page(): void {
		Functions\expect( 'plugin_dir_url' )
			->andReturn( 'http://example.com/wp-content/mu-plugins/qala-plugin-manager/' );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'qala-admin-page',
				Mockery::type( 'string' ),
				[],
				Mockery::any()
			);

		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );

		$this->admin_page->enqueue_assets( 'settings_page_qala-hide-notices' );
	}

	/**
	 * Test: enqueue_assets() enqueues JS on settings page
	 *
	 * @return void
	 */
	public function test_enqueue_assets_enqueues_js_on_settings_page(): void {
		Functions\expect( 'plugin_dir_url' )
			->andReturn( 'http://example.com/wp-content/mu-plugins/qala-plugin-manager/' );

		Functions\when( 'wp_enqueue_style' )->justReturn( true );

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'qala-admin-page',
				Mockery::type( 'string' ),
				[ 'jquery' ],
				Mockery::any(),
				true
			);

		Functions\expect( 'wp_localize_script' )
			->once()
			->with(
				'qala-admin-page',
				'qalaAdminPage',
				Mockery::on( function ( $data ) {
					return isset( $data['ajaxUrl'] )
						&& isset( $data['nonces'] )
						&& isset( $data['nonces']['addPattern'] )
						&& isset( $data['nonces']['removePattern'] )
						&& isset( $data['nonces']['toggle'] );
				} )
			);

		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );

		$this->admin_page->enqueue_assets( 'settings_page_qala-hide-notices' );
	}

	/**
	 * Test: handle_add_pattern_ajax() verifies nonce
	 *
	 * @return void
	 */
	public function test_handle_add_pattern_ajax_verifies_nonce(): void {
		$_POST['nonce'] = 'invalid-nonce';
		$_POST['pattern'] = 'test_pattern';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'qala_add_pattern', 'nonce', false )
			->andReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Invalid nonce' ] );

		$this->admin_page->handle_add_pattern_ajax();
	}

	/**
	 * Test: handle_add_pattern_ajax() checks capability
	 *
	 * @return void
	 */
	public function test_handle_add_pattern_ajax_checks_capability(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = 'test_pattern';

		Functions\expect( 'check_ajax_referer' )
			->andReturn( true );

		Functions\when( 'current_user_can' )
			->justReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Permission denied' ] );

		$this->admin_page->handle_add_pattern_ajax();
	}

	/**
	 * Test: handle_add_pattern_ajax() sanitizes pattern input
	 *
	 * @return void
	 */
	public function test_handle_add_pattern_ajax_sanitizes_pattern(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = '<script>alert("xss")</script>rocket_*';
		$_POST['pattern_type'] = 'wildcard';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		// sanitize_text_field and wp_unslash are already mocked in TestCase
		// They return the value unchanged for testing

		$this->allowlist_mock->shouldReceive( 'add_pattern' )
			->once()
			->with( '<script>alert("xss")</script>rocket_*', 'wildcard' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andThrow( new \Exception( 'wp_send_json_success called' ) );

		try {
			$this->admin_page->handle_add_pattern_ajax();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_send_json_success called', $e->getMessage() );
		}
	}

	/**
	 * Test: handle_add_pattern_ajax() validates empty pattern
	 *
	 * @return void
	 */
	public function test_handle_add_pattern_ajax_validates_empty_pattern(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = '';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Pattern cannot be empty' ] );

		$this->admin_page->handle_add_pattern_ajax();
	}

	/**
	 * Test: handle_add_pattern_ajax() adds pattern successfully
	 *
	 * @return void
	 */
	public function test_handle_add_pattern_ajax_adds_pattern_successfully(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = 'rocket_*';
		$_POST['pattern_type'] = 'wildcard';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->allowlist_mock->shouldReceive( 'add_pattern' )
			->once()
			->with( 'rocket_*', 'wildcard' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andThrow( new \Exception( 'wp_send_json_success called' ) );

		try {
			$this->admin_page->handle_add_pattern_ajax();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_send_json_success called', $e->getMessage() );
		}
	}

	/**
	 * Test: handle_add_pattern_ajax() handles database error
	 *
	 * @return void
	 */
	public function test_handle_add_pattern_ajax_handles_database_error(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = 'rocket_*';
		$_POST['pattern_type'] = 'wildcard';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->allowlist_mock->shouldReceive( 'add_pattern' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Failed to add pattern to allowlist' ] );

		$this->admin_page->handle_add_pattern_ajax();
	}

	/**
	 * Test: handle_remove_pattern_ajax() verifies nonce
	 *
	 * @return void
	 */
	public function test_handle_remove_pattern_ajax_verifies_nonce(): void {
		$_POST['nonce'] = 'invalid-nonce';
		$_POST['pattern'] = 'test_pattern';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'qala_remove_pattern', 'nonce', false )
			->andReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Invalid nonce' ] );

		$this->admin_page->handle_remove_pattern_ajax();
	}

	/**
	 * Test: handle_remove_pattern_ajax() removes pattern successfully
	 *
	 * @return void
	 */
	public function test_handle_remove_pattern_ajax_removes_pattern_successfully(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = 'rocket_*';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->allowlist_mock->shouldReceive( 'remove_pattern_by_value' )
			->once()
			->with( 'rocket_*' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andThrow( new \Exception( 'wp_send_json_success called' ) );

		try {
			$this->admin_page->handle_remove_pattern_ajax();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_send_json_success called', $e->getMessage() );
		}
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
			->with( 'qala_toggle_notices', 'nonce', false )
			->andReturn( false );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( [ 'message' => 'Invalid nonce' ] );

		$this->admin_page->handle_toggle_ajax();
	}

	/**
	 * Test: handle_toggle_ajax() toggles option successfully
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_toggles_option_successfully(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['enabled'] = 'yes';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( 'qala_notices_enabled', 'yes' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( [ 'message' => 'Notice hiding enabled', 'enabled' => 'yes' ] );

		$this->admin_page->handle_toggle_ajax();
	}

	/**
	 * Test: handle_toggle_ajax() handles invalid value
	 *
	 * @return void
	 */
	public function test_handle_toggle_ajax_handles_invalid_value(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['enabled'] = 'invalid';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( 'qala_notices_enabled', 'no' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once();

		$this->admin_page->handle_toggle_ajax();
	}

	/**
	 * Test: validate_pattern_type() accepts valid types
	 *
	 * @return void
	 */
	public function test_validate_pattern_type_accepts_valid_types(): void {
		$this->assertEquals( 'exact', $this->admin_page->validate_pattern_type( 'exact' ) );
		$this->assertEquals( 'wildcard', $this->admin_page->validate_pattern_type( 'wildcard' ) );
		$this->assertEquals( 'regex', $this->admin_page->validate_pattern_type( 'regex' ) );
	}

	/**
	 * Test: validate_pattern_type() defaults to exact for invalid types
	 *
	 * @return void
	 */
	public function test_validate_pattern_type_defaults_to_exact(): void {
		$this->assertEquals( 'exact', $this->admin_page->validate_pattern_type( 'invalid' ) );
		$this->assertEquals( 'exact', $this->admin_page->validate_pattern_type( '' ) );
		$this->assertEquals( 'exact', $this->admin_page->validate_pattern_type( null ) );
	}

	/**
	 * Test: render_page() displays notice log table
	 *
	 * @return void
	 */
	public function test_render_page_displays_notice_log_table(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 'yes' );
		Functions\when( 'settings_fields' )->justReturn( null );
		Functions\when( 'do_settings_sections' )->justReturn( null );
		Functions\when( 'submit_button' )->justReturn( null );
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'esc_html_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'esc_attr_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'checked' )->returnArg();

		$this->logger_mock->shouldReceive( 'get_unique_notices' )
			->once()
			->andReturn( [
				[
					'callback_name' => 'test_function',
					'hook_name' => 'admin_notices',
					'last_seen' => '2025-10-25 10:00:00',
				],
			] );

		$this->allowlist_mock->shouldReceive( 'get_all_patterns' )
			->andReturn( [] );

		ob_start();
		$this->admin_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'test_function', $output );
		$this->assertStringContainsString( 'admin_notices', $output );
	}

	/**
	 * Test: render_page() displays allowlist patterns
	 *
	 * @return void
	 */
	public function test_render_page_displays_allowlist_patterns(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 'yes' );
		Functions\when( 'settings_fields' )->justReturn( null );
		Functions\when( 'do_settings_sections' )->justReturn( null );
		Functions\when( 'submit_button' )->justReturn( null );
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'esc_html_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'esc_attr_e' )->alias( function ( $text ) {
			echo $text;
		} );
		Functions\when( 'checked' )->returnArg();

		$this->logger_mock->shouldReceive( 'get_unique_notices' )
			->andReturn( [] );

		$this->allowlist_mock->shouldReceive( 'get_all_patterns' )
			->once()
			->andReturn( [
				[
					'pattern_value' => 'rocket_*',
					'pattern_type' => 'wildcard',
				],
			] );

		ob_start();
		$this->admin_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'rocket_*', $output );
		$this->assertStringContainsString( 'wildcard', $output );
	}

	/**
	 * Test: init() registers all hooks correctly
	 *
	 * @return void
	 */
	public function test_init_registers_all_hooks(): void {
		Actions\expectAdded( 'admin_menu' )->once();
		Actions\expectAdded( 'admin_init' )->once();
		Actions\expectAdded( 'admin_enqueue_scripts' )->once();
		Actions\expectAdded( 'wp_ajax_qala_add_allowlist_pattern' )->once();
		Actions\expectAdded( 'wp_ajax_qala_remove_allowlist_pattern' )->once();
		Actions\expectAdded( 'wp_ajax_qala_toggle_notices' )->once();

		$this->admin_page->init();
	}

	/**
	 * Test: pattern sanitization strips dangerous characters
	 *
	 * @return void
	 */
	public function test_pattern_sanitization_strips_dangerous_characters(): void {
		$_POST['nonce'] = 'valid-nonce';
		$_POST['pattern'] = 'test<script>alert("xss")</script>pattern';
		$_POST['pattern_type'] = 'exact';

		Functions\expect( 'check_ajax_referer' )->andReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->allowlist_mock->shouldReceive( 'add_pattern' )
			->once()
			->with( 'test<script>alert("xss")</script>pattern', 'exact' )
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andThrow( new \Exception( 'wp_send_json_success called' ) );

		try {
			$this->admin_page->handle_add_pattern_ajax();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_send_json_success called', $e->getMessage() );
		}
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
