<?php
/**
 * NoticeFilter Class
 *
 * CORE COMPONENT: Removes unwanted notice hooks before they execute.
 * This is the primary notice management component that integrates with
 * AllowlistManager and NoticeLogger to provide comprehensive notice filtering.
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

use QalaPluginManager\Interfaces\WithHooksInterface;
use QalaPluginManager\NoticeManagement\Traits\CapabilityChecker;

/**
 * Class NoticeFilter
 *
 * Responsibilities:
 * - Hook into in_admin_header at priority 100000 (latest possible timing)
 * - Remove callbacks from notice hooks BEFORE they execute
 * - Check AllowlistManager to preserve exceptions
 * - Log all removals via NoticeLogger
 * - Handle all 4 notice hook types (admin_notices, network_admin_notices, user_admin_notices, all_admin_notices)
 * - Access global $wp_filter to manipulate hooks directly
 *
 * Hook Timing:
 * Priority 100000 on in_admin_header ensures we run AFTER all plugins have
 * registered their notice callbacks but BEFORE the notice hooks fire.
 *
 * @since 1.0.0
 */
class NoticeFilter implements WithHooksInterface {

	use CapabilityChecker;

	/**
	 * AllowlistManager instance for checking exceptions
	 *
	 * @var AllowlistManager
	 */
	private $allowlist;

	/**
	 * NoticeLogger instance for logging removals
	 *
	 * @var NoticeLogger
	 */
	private $logger;

	/**
	 * NoticeIdentifier instance for callback identification
	 *
	 * @var NoticeIdentifier
	 */
	private $identifier;

	/**
	 * Constructor
	 *
	 * @param AllowlistManager  $allowlist Allowlist manager instance.
	 * @param NoticeLogger      $logger Logger instance.
	 * @param NoticeIdentifier  $identifier Identifier instance.
	 */
	public function __construct(
		AllowlistManager $allowlist,
		NoticeLogger $logger,
		NoticeIdentifier $identifier
	) {
		$this->allowlist = $allowlist;
		$this->logger = $logger;
		$this->identifier = $identifier;
	}

	/**
	 * Initialize hooks
	 *
	 * Implements WithHooksInterface to register hooks when class is registered
	 * via ServiceProvider.
	 *
	 * Hook Registration:
	 * - in_admin_header @ priority 100000: Remove notice hooks before they fire
	 *
	 * @return void
	 */
	public function init(): void {
		// Main hook: Filter notices at latest possible moment before they render
		add_action( 'in_admin_header', [ $this, 'filter_notices' ], 100000 );
	}

	/**
	 * Get all notice hook names
	 *
	 * Returns array of all WordPress notice hooks that should be filtered.
	 *
	 * Notice Hooks:
	 * - admin_notices: Standard admin notices (single site or site admin in multisite)
	 * - network_admin_notices: Network admin notices (multisite only)
	 * - user_admin_notices: User dashboard notices (multisite only)
	 * - all_admin_notices: Universal notices (fires after context-specific hooks)
	 *
	 * @return array Array of hook names.
	 */
	public function get_notice_hooks(): array {
		return [
			'admin_notices',
			'network_admin_notices',
			'user_admin_notices',
			'all_admin_notices',
		];
	}

	/**
	 * Filter notices before they render
	 *
	 * Main callback executed on in_admin_header hook.
	 * Processes all notice hooks and removes unwanted callbacks.
	 *
	 * Execution Flow:
	 * 1. Check if user has qala_full_access capability (skip filtering)
	 * 2. Check global toggle option (skip if disabled)
	 * 3. Check per-user preference (skip if user wants to see notices)
	 * 4. Iterate through all notice hooks
	 * 5. For each hook, iterate through all callbacks at all priorities
	 * 6. Check allowlist for each callback
	 * 7. Remove non-allowlisted callbacks
	 * 8. Log all actions (removed or kept)
	 *
	 * @return void
	 */
	public function filter_notices(): void {
		// Skip if user has qala_full_access capability
		if ( $this->user_has_capability( 'qala_full_access' ) ) {
			return;
		}

		// Skip if global toggle is disabled
		if ( get_option( 'qala_notices_enabled', 'yes' ) === 'no' ) {
			return;
		}

		// Check for per-user override
		$user_toggle = get_user_meta( get_current_user_id(), 'qala_show_notices', true );
		if ( $user_toggle === 'yes' ) {
			return;
		}

		// Access global $wp_filter to manipulate hooks
		global $wp_filter;

		// Process each notice hook
		foreach ( $this->get_notice_hooks() as $hook_name ) {
			$this->remove_notice_callbacks( $hook_name );
		}
	}

	/**
	 * Remove callbacks from a specific notice hook
	 *
	 * Iterates through all callbacks at all priorities for the given hook,
	 * checks allowlist, and removes non-allowlisted callbacks.
	 *
	 * @param string $hook_name The hook name to process.
	 *
	 * @return void
	 */
	public function remove_notice_callbacks( string $hook_name ): void {
		global $wp_filter;

		// Check if hook exists
		if ( ! isset( $wp_filter[ $hook_name ] ) ) {
			return;
		}

		// Check if hook has callbacks
		if ( empty( $wp_filter[ $hook_name ]->callbacks ) ) {
			return;
		}

		// Iterate through all priorities
		foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
			// Skip if no callbacks at this priority
			if ( empty( $callbacks ) ) {
				continue;
			}

			// Iterate through all callbacks at this priority
			foreach ( $callbacks as $id => $callback_data ) {
				$callback = $callback_data['function'];

				// Check if callback should be kept
				if ( $this->should_keep_callback( $callback, $hook_name ) ) {
					// Log that callback was kept (allowlisted)
					$this->log_callback_action(
						$callback,
						$hook_name,
						$priority,
						'kept_allowlisted'
					);
					continue;
				}

				// Remove callback from hook
				unset( $wp_filter[ $hook_name ]->callbacks[ $priority ][ $id ] );

				// Log removal
				$this->log_callback_action(
					$callback,
					$hook_name,
					$priority,
					'removed'
				);
			}
		}
	}

	/**
	 * Check if callback should be kept (allowlisted)
	 *
	 * @param mixed  $callback The callback to check.
	 * @param string $hook_name The hook name (for context).
	 *
	 * @return bool True if callback should be kept, false if should be removed.
	 */
	public function should_keep_callback( $callback, string $hook_name ): bool {
		// Get callback name for allowlist checking
		$callback_name = $this->identifier->get_callback_name( $callback );

		// Check if callback is allowlisted
		return $this->allowlist->matches_allowlist( $callback_name );
	}

	/**
	 * Log callback action (removed or kept)
	 *
	 * Helper method to log all callback actions via NoticeLogger.
	 *
	 * @param mixed  $callback The callback.
	 * @param string $hook_name The hook name.
	 * @param int    $priority The priority level.
	 * @param string $action The action taken ('removed' or 'kept_allowlisted').
	 *
	 * @return void
	 */
	private function log_callback_action( $callback, string $hook_name, int $priority, string $action ): void {
		// Get callback name for logging
		$callback_name = $this->identifier->get_callback_name( $callback );

		// Determine reason based on action
		$reason = ( $action === 'kept_allowlisted' )
			? 'matches_allowlist_pattern'
			: 'no_qala_full_access';

		// Log to database
		$this->logger->log_removal(
			$callback_name,
			$hook_name,
			$priority,
			$action,
			$reason
		);
	}
}
