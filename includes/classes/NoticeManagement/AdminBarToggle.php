<?php
/**
 * AdminBarToggle Class
 *
 * Quick toggle in WordPress admin bar for per-user notice visibility control.
 * Allows users with qala_full_access capability to quickly toggle notice visibility
 * without navigating to the settings page.
 *
 * Features:
 * - Admin bar menu item with current state indicator
 * - AJAX-powered toggle for smooth UX
 * - Per-user preference storage in user meta
 * - Visual feedback (state indicators, loading states)
 * - Nonce verification for security
 * - Capability checking (qala_full_access only)
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

use QalaPluginManager\Interfaces\WithHooksInterface;
use QalaPluginManager\NoticeManagement\Traits\CapabilityChecker;
use WP_Admin_Bar;

/**
 * Class AdminBarToggle
 *
 * Responsibilities:
 * - Add admin bar menu item showing current notice visibility state
 * - Handle AJAX toggle requests
 * - Store and retrieve per-user notice visibility preferences
 * - Enqueue JavaScript and CSS assets
 * - Provide security through nonce verification and capability checking
 *
 * User Preference Storage:
 * - Meta key: qala_show_notices
 * - Values: 'yes' (notices visible) or 'no' (notices hidden)
 * - Default: 'no' (notices hidden for non-privileged users)
 *
 * @since 1.0.0
 */
class AdminBarToggle implements WithHooksInterface {

	use CapabilityChecker;

	/**
	 * User meta key for notice visibility preference
	 *
	 * @var string
	 */
	const META_KEY = 'qala_show_notices';

	/**
	 * AJAX action name for toggle
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'qala_toggle_notice_visibility';

	/**
	 * Nonce action name
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'qala_notice_toggle';

	/**
	 * Initialize hooks
	 *
	 * Registers all WordPress hooks for the admin bar toggle.
	 * Called by ServiceProvider when class is registered.
	 *
	 * Hooks registered:
	 * - admin_bar_menu: Add menu item to admin bar
	 * - admin_enqueue_scripts: Enqueue JS/CSS assets
	 * - wp_ajax_*: Handle AJAX toggle request
	 *
	 * @return void
	 */
	public function init(): void {
		// Add menu item to admin bar (priority 999 = very late)
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu' ], 999 );

		// Enqueue assets for AJAX functionality
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Register AJAX handler for toggle
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_toggle_ajax' ] );
	}

	/**
	 * Add admin bar menu item
	 *
	 * Adds a menu item to the WordPress admin bar showing the current
	 * notice visibility state. Only visible to users with qala_full_access capability.
	 *
	 * Menu states:
	 * - "Notices: On" (green icon) - Notices are currently visible
	 * - "Notices: Off" (red icon) - Notices are currently hidden
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WordPress admin bar instance.
	 *
	 * @return void
	 */
	public function add_admin_bar_menu( $wp_admin_bar ): void {
		// Only show to users with qala_full_access capability
		if ( ! $this->user_has_capability( 'qala_full_access' ) ) {
			return;
		}

		// Get current user preference
		$user_preference = $this->get_user_preference();
		$is_showing = ( $user_preference === 'yes' );

		// Build menu title with state indicator
		$state_text = $is_showing ? __( 'On', 'qala-plugin-manager' ) : __( 'Off', 'qala-plugin-manager' );
		$state_class = $is_showing ? 'qala-state-on' : 'qala-state-off';

		$title = sprintf(
			'<span class="qala-notice-toggle-wrapper">
				<span class="dashicons dashicons-visibility qala-toggle-icon"></span>
				<span class="qala-toggle-label">%s: </span>
				<span class="qala-toggle-state %s">%s</span>
			</span>',
			esc_html__( 'Notices', 'qala-plugin-manager' ),
			esc_attr( $state_class ),
			esc_html( $state_text )
		);

		// Add menu node to admin bar
		$wp_admin_bar->add_node(
			[
				'id' => 'qala-notice-toggle',
				'title' => $title,
				'href' => '#',
				'meta' => [
					'class' => 'qala-notice-toggle-item',
					'title' => __( 'Toggle admin notices visibility', 'qala-plugin-manager' ),
				],
			]
		);
	}

	/**
	 * Enqueue JavaScript and CSS assets
	 *
	 * Enqueues the JavaScript file for AJAX toggle functionality and
	 * the CSS file for styling the admin bar menu item.
	 *
	 * Also localizes the script with:
	 * - AJAX URL
	 * - Nonce for security
	 * - Action name
	 * - Translated strings
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Get plugin URL and directory for asset paths
		$plugin_url = plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) );
		$plugin_dir = dirname( dirname( dirname( __FILE__ ) ) );

		// Get file modification time for cache busting
		$css_file = $plugin_dir . '/assets/dist/css/admin-bar-toggle.css';
		$js_file = $plugin_dir . '/assets/dist/js/admin-bar-toggle.js';
		$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0';
		$js_version = file_exists( $js_file ) ? filemtime( $js_file ) : '1.0.0';

		// Enqueue JavaScript for AJAX toggle
		wp_enqueue_script(
			'qala-admin-bar-toggle',
			$plugin_url . 'assets/dist/js/admin-bar-toggle.js',
			[ 'jquery' ],
			$js_version,
			true
		);

		// Enqueue CSS for styling
		wp_enqueue_style(
			'qala-admin-bar-toggle',
			$plugin_url . 'assets/dist/css/admin-bar-toggle.css',
			[],
			$css_version
		);

		// Localize script with AJAX data and translations
		wp_localize_script(
			'qala-admin-bar-toggle',
			'qalaAdminBarToggle',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE_ACTION ),
				'action' => self::AJAX_ACTION,
				'strings' => [
					'noticesOn' => __( 'Notices: On', 'qala-plugin-manager' ),
					'noticesOff' => __( 'Notices: Off', 'qala-plugin-manager' ),
					'toggledOn' => __( 'Notices are now visible', 'qala-plugin-manager' ),
					'toggledOff' => __( 'Notices are now hidden', 'qala-plugin-manager' ),
					'error' => __( 'Failed to toggle notices. Please try again.', 'qala-plugin-manager' ),
					'loading' => __( 'Toggling...', 'qala-plugin-manager' ),
				],
			]
		);
	}

	/**
	 * Handle AJAX toggle request
	 *
	 * Toggles the user's notice visibility preference between 'yes' and 'no'.
	 * Performs security checks (nonce, capability) before updating user meta.
	 *
	 * Security:
	 * - Verifies nonce to prevent CSRF attacks
	 * - Checks qala_full_access capability
	 *
	 * Response on success:
	 * - showing: boolean (true if notices now visible)
	 * - message: string (user-friendly message)
	 * - new_title: string (new HTML title for admin bar item)
	 *
	 * Response on error:
	 * - message: string (error description)
	 *
	 * @return void Outputs JSON and exits
	 */
	public function handle_toggle_ajax(): void {
		// Verify nonce
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid nonce', 'qala-plugin-manager' ),
				]
			);
			return;
		}

		// Check capability
		if ( ! $this->user_has_capability( 'qala_full_access' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Permission denied', 'qala-plugin-manager' ),
				]
			);
			return;
		}

		// Get current preference
		$current_preference = $this->get_user_preference();

		// Toggle: 'yes' becomes 'no', anything else becomes 'yes'
		$new_preference = ( $current_preference === 'yes' ) ? 'no' : 'yes';

		// Update user meta
		$updated = $this->set_user_preference( $new_preference );

		if ( ! $updated ) {
			wp_send_json_error(
				[
					'message' => __( 'Failed to update preference', 'qala-plugin-manager' ),
				]
			);
			return;
		}

		// Build response data
		$is_showing = ( $new_preference === 'yes' );
		$state_text = $is_showing ? __( 'On', 'qala-plugin-manager' ) : __( 'Off', 'qala-plugin-manager' );
		$state_class = $is_showing ? 'qala-state-on' : 'qala-state-off';

		$new_title = sprintf(
			'<span class="qala-notice-toggle-wrapper">
				<span class="dashicons dashicons-visibility qala-toggle-icon"></span>
				<span class="qala-toggle-label">%s: </span>
				<span class="qala-toggle-state %s">%s</span>
			</span>',
			esc_html__( 'Notices', 'qala-plugin-manager' ),
			esc_attr( $state_class ),
			esc_html( $state_text )
		);

		$message = $is_showing
			? __( 'Notices are now visible', 'qala-plugin-manager' )
			: __( 'Notices are now hidden', 'qala-plugin-manager' );

		// Send success response
		wp_send_json_success(
			[
				'showing' => $is_showing,
				'message' => $message,
				'new_title' => $new_title,
			]
		);
	}

	/**
	 * Get user preference for notice visibility
	 *
	 * Retrieves the per-user preference from user meta.
	 * Returns 'yes' if notices should be visible, 'no' if hidden.
	 *
	 * @param int|null $user_id User ID (null = current user).
	 *
	 * @return string Either 'yes' or 'no'
	 */
	public function get_user_preference( ?int $user_id = null ): string {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		$preference = get_user_meta( $user_id, self::META_KEY, true );

		// Return 'yes' only if explicitly set to 'yes', otherwise 'no'
		return ( $preference === 'yes' ) ? 'yes' : 'no';
	}

	/**
	 * Set user preference for notice visibility
	 *
	 * Updates the per-user preference in user meta.
	 * Value is sanitized to ensure it's either 'yes' or 'no'.
	 *
	 * @param string   $value User preference ('yes' or 'no').
	 * @param int|null $user_id User ID (null = current user).
	 *
	 * @return bool True on success, false on failure
	 */
	public function set_user_preference( string $value, ?int $user_id = null ): bool {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		// Sanitize value to 'yes' or 'no'
		$sanitized_value = ( $value === 'yes' ) ? 'yes' : 'no';

		// Update user meta
		$result = update_user_meta( $user_id, self::META_KEY, $sanitized_value );

		// update_user_meta returns meta_id on success, false on failure
		return ( $result !== false );
	}
}
