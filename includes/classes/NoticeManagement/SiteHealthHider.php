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
	 * Remove Site Health menu page for users without qala_full_access
	 *
	 * Removes the "Site Health" menu item from Tools menu.
	 * Only affects users WITHOUT qala_full_access capability.
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

		// Skip if user has qala_full_access capability
		if ( $this->user_has_capability( 'qala_full_access' ) ) {
			return;
		}

		// Remove Site Health menu page
		remove_menu_page( 'site-health.php' );
	}

	/**
	 * Remove Site Health dashboard widget for users without qala_full_access
	 *
	 * Removes the "Site Health Status" meta box from the dashboard.
	 * Only affects users WITHOUT qala_full_access capability.
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

		// Skip if user has qala_full_access capability
		if ( $this->user_has_capability( 'qala_full_access' ) ) {
			return;
		}

		// Remove Site Health dashboard widget
		// Widget ID: dashboard_site_health
		// Screen: dashboard
		// Context: normal (main column)
		remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	}

	/**
	 * Redirect unauthorized users trying to access Site Health
	 *
	 * If a user without qala_full_access tries to access Site Health directly
	 * via URL, redirect them to the dashboard.
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

		// Skip if user has qala_full_access capability
		if ( $this->user_has_capability( 'qala_full_access' ) ) {
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
}
