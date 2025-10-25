<?php
/**
 * Capability Checker Trait
 *
 * Provides reusable methods for checking user capabilities.
 * Ensures consistent capability checking across the plugin.
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement\Traits
 */

namespace QalaPluginManager\NoticeManagement\Traits;

/**
 * Trait CapabilityChecker
 *
 * Provides helper methods for capability checking.
 * Always checks capabilities at runtime (not during class construction).
 */
trait CapabilityChecker {

	/**
	 * Check if current user has a specific capability
	 *
	 * This method should ALWAYS be called at hook runtime,
	 * never directly during class instantiation.
	 *
	 * Performs three-layer validation:
	 * 1. Must be in admin context (is_admin())
	 * 2. User must be logged in (is_user_logged_in())
	 * 3. User must have the specified capability (current_user_can())
	 *
	 * @param string $capability Capability to check (default: 'qala_full_access').
	 *
	 * @return bool True if user has capability, false otherwise.
	 */
	protected function user_has_capability( string $capability = 'qala_full_access' ): bool {
		// Must be in admin context
		if ( ! is_admin() ) {
			return false;
		}

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check capability
		return current_user_can( $capability );
	}

	/**
	 * Check if current user can manage Qala notices
	 *
	 * Wrapper for user_has_capability() with default 'qala_full_access'.
	 * Provides semantic method name for notice management permission checks.
	 *
	 * @return bool True if user can manage notices, false otherwise.
	 */
	protected function can_manage_notices(): bool {
		return $this->user_has_capability( 'qala_full_access' );
	}

	/**
	 * Check if current user should see admin notices
	 *
	 * Checks both capability and user meta preference.
	 * Implements two-tier logic:
	 * 1. Privileged users (with qala_full_access) always see notices
	 * 2. Non-privileged users see notices only if their preference is 'yes'
	 *
	 * @return bool True if user should see notices, false otherwise.
	 */
	protected function should_see_notices(): bool {
		// Users with qala_full_access always see notices
		if ( $this->user_has_capability( 'qala_full_access' ) ) {
			return true;
		}

		// Check user meta for per-user override
		$user_id = get_current_user_id();

		// User ID must be valid (not 0)
		if ( $user_id === 0 || empty( $user_id ) ) {
			return false;
		}

		$user_pref = get_user_meta( $user_id, 'qala_show_notices', true );

		// Only return true if preference is exactly 'yes'
		return ( $user_pref === 'yes' );
	}

	/**
	 * Die with error message if user lacks capability
	 *
	 * Use this in admin page callbacks to enforce capability checks.
	 * Calls wp_die() with appropriate error message if user lacks the required capability.
	 *
	 * @param string $capability Capability to check (default: 'qala_full_access').
	 * @param string $message Custom error message (optional, defaults to translated message).
	 *
	 * @return void Dies if user lacks capability, returns normally if user has capability.
	 *
	 * @throws void Calls wp_die() which terminates execution.
	 */
	protected function require_capability( string $capability = 'qala_full_access', string $message = '' ): void {
		if ( ! $this->user_has_capability( $capability ) ) {
			if ( empty( $message ) ) {
				$message = __( 'You do not have permission to access this page.', 'qala-plugin-manager' );
			}
			wp_die( $message );
		}
	}
}
