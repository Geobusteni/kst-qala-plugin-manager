<?php
/**
 * Site Health Hider
 *
 * Hides the Site Health admin page and dashboard widget from users
 * without qala_full_access capability. Redirects unauthorized users
 * who attempt to access Site Health directly.
 *
 * @package QalaPluginManager\NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

use QalaPluginManager\Interfaces\WithHooksInterface;
use QalaPluginManager\NoticeManagement\Traits\CapabilityChecker;

/**
 * Class SiteHealthHider
 *
 * Manages Site Health visibility based on user capabilities:
 * - Removes Site Health menu page for users without qala_full_access
 * - Removes Site Health dashboard widget for users without qala_full_access
 * - Redirects unauthorized users who try to access Site Health directly
 * - Preserves full access for users with qala_full_access capability
 */
class SiteHealthHider implements WithHooksInterface {

	use CapabilityChecker;

	/**
	 * Initialize hooks
	 *
	 * Registers all WordPress hooks needed for Site Health hiding functionality.
	 * Called automatically by ServiceProvider when implementing WithHooksInterface.
	 *
	 * @return void
	 */
	public function init(): void {
		// Remove Site Health menu page (priority 999 to run after menu registration)
		add_action( 'admin_menu', [ $this, 'remove_menu_page' ], 999 );

		// Remove Site Health dashboard widget
		add_action( 'wp_dashboard_setup', [ $this, 'remove_dashboard_widget' ] );

		// Redirect unauthorized access attempts
		add_action( 'admin_init', [ $this, 'redirect_site_health' ] );
	}

	/**
	 * Remove Site Health menu page based on settings
	 *
	 * Removes the "Site Health" submenu item from Tools menu.
	 * Visibility controlled by two settings:
	 * 1. qala_hide_site_health_for_all (default: yes) - Hide for everyone
	 * 2. qala_show_site_health_for_non_qala_users (default: no) - Show for non-qala users
	 *
	 * Hook: admin_menu (priority 999)
	 *
	 * @return void
	 */
	public function remove_menu_page(): void {
		// Skip if not in admin context
		if ( ! is_admin() ) {
			return;
		}

		// Check if we should hide Site Health
		if ( ! $this->should_hide_site_health() ) {
			return;
		}

		// Remove Site Health submenu from Tools menu
		// Site Health is a submenu item under Tools, not a top-level menu
		remove_submenu_page( 'tools.php', 'site-health.php' );
	}

	/**
	 * Remove Site Health dashboard widget based on settings
	 *
	 * Removes the "Site Health Status" meta box from the dashboard.
	 * Visibility controlled by two settings:
	 * 1. qala_hide_site_health_for_all (default: yes) - Hide for everyone
	 * 2. qala_show_site_health_for_non_qala_users (default: no) - Show for non-qala users
	 *
	 * Hook: wp_dashboard_setup
	 *
	 * @return void
	 */
	public function remove_dashboard_widget(): void {
		// Skip if not in admin context
		if ( ! is_admin() ) {
			return;
		}

		// Check if we should hide Site Health
		if ( ! $this->should_hide_site_health() ) {
			return;
		}

		// Remove Site Health dashboard widget
		// Widget ID: dashboard_site_health
		// Screen: dashboard
		// Context: normal (main column)
		remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	}

	/**
	 * Redirect users trying to access Site Health based on settings
	 *
	 * If Site Health should be hidden based on settings, redirect to dashboard.
	 * Visibility controlled by two settings:
	 * 1. qala_hide_site_health_for_all (default: yes) - Hide for everyone
	 * 2. qala_show_site_health_for_non_qala_users (default: no) - Show for non-qala users
	 *
	 * Handles both site-health.php (WP 5.2+) and health-check.php (older versions).
	 * Skips AJAX requests to avoid breaking Site Health API calls.
	 *
	 * Hook: admin_init
	 *
	 * @return void
	 */
	public function redirect_site_health(): void {
		// Skip if not in admin context
		if ( ! is_admin() ) {
			return;
		}

		// Skip AJAX requests (Site Health API endpoints)
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		// Check if we're on a Site Health page
		if ( ! $this->is_site_health_page() ) {
			return;
		}

		// Check if we should hide Site Health
		if ( ! $this->should_hide_site_health() ) {
			return;
		}

		// Redirect to dashboard
		$redirect_url = admin_url( '' );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Check if current page is a Site Health page
	 *
	 * Checks the global $pagenow variable to determine if we're on:
	 * - site-health.php (WordPress 5.2+)
	 * - health-check.php (older WordPress versions)
	 *
	 * @return bool True if on Site Health page, false otherwise
	 */
	public function is_site_health_page(): bool {
		global $pagenow;

		if ( ! isset( $pagenow ) ) {
			return false;
		}

		// Check if we're on Site Health pages
		$site_health_pages = [
			'site-health.php',   // Modern Site Health page (WP 5.2+)
			'health-check.php',  // Legacy Site Health page (older WP)
		];

		return in_array( $pagenow, $site_health_pages, true );
	}

	/**
	 * Determine if Site Health should be hidden for current user
	 *
	 * Logic:
	 * 1. If qala_hide_site_health_for_all is 'yes' (default), hide for everyone
	 * 2. If qala_hide_site_health_for_all is 'no':
	 *    a. If qala_show_site_health_for_non_qala_users is 'yes', show for everyone
	 *    b. If qala_show_site_health_for_non_qala_users is 'no' (default), show only for qala_full_access users
	 *
	 * @return bool True if Site Health should be hidden, false if it should be visible
	 */
	public function should_hide_site_health(): bool {
		// Get settings with defaults
		$hide_for_all = get_option( 'qala_hide_site_health_for_all', 'yes' );
		$show_for_non_qala = get_option( 'qala_show_site_health_for_non_qala_users', 'no' );

		// If hide for all is enabled, hide Site Health for everyone
		if ( $hide_for_all === 'yes' ) {
			return true;
		}

		// If hide for all is disabled, check the second setting
		// If show for non-qala users is enabled, show for everyone (don't hide)
		if ( $show_for_non_qala === 'yes' ) {
			return false;
		}

		// If show for non-qala users is disabled, show only for qala_full_access users
		// Hide for users WITHOUT qala_full_access
		return ! $this->user_has_capability( 'qala_full_access' );
	}
}
