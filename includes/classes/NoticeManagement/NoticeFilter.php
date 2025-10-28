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
	 * - admin_enqueue_scripts @ priority 1: Enqueue combined CSS (always loads)
	 *
	 * The CSS approach provides a bulletproof fallback for notices that bypass
	 * the hook system (e.g., WooCommerce HTTPS notice, AJAX notices).
	 *
	 * @return void
	 */
	public function init(): void {
		// Main hook: Filter notices at latest possible moment before they render
		add_action( 'in_admin_header', [ $this, 'filter_notices' ], 100000 );

		// Enqueue combined CSS at highest priority (always loads)
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 1 );
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
	 * 1. Check if user has qala_full_access capability (skip filtering - show notices)
	 * 2. Check global toggle option (skip if enabled - show notices)
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
		// DEBUG: Log that we're running
		error_log( '=== NoticeFilter::filter_notices() START ===' );

		$current_user_id = get_current_user_id();
		$has_capability = $this->user_has_capability( 'qala_full_access' );
		$global_toggle = get_option( 'qala_notices_enabled', 'yes' );
		$user_toggle = get_user_meta( $current_user_id, 'qala_show_notices', true );

		error_log( sprintf(
			'NoticeFilter: User ID=%d, Has qala_full_access=%s, Global toggle=%s, User toggle=%s',
			$current_user_id,
			$has_capability ? 'YES' : 'NO',
			$global_toggle,
			$user_toggle ?: '(empty)'
		) );

		// Skip filtering (show notices) if user has qala_full_access capability
		if ( $has_capability ) {
			error_log( 'NoticeFilter: User has qala_full_access - SHOWING all notices' );
			return;
		}

		// Skip filtering (show notices) if global toggle is enabled (yes)
		// FIXED BUG: Was checking for 'no', should check for 'yes'
		if ( $global_toggle === 'yes' ) {
			error_log( 'NoticeFilter: Global toggle is ENABLED - SHOWING all notices' );
			return;
		}

		// Skip filtering (show notices) if user wants to see them
		if ( $user_toggle === 'yes' ) {
			error_log( 'NoticeFilter: User toggle is YES - SHOWING all notices' );
			return;
		}

		error_log( 'NoticeFilter: Proceeding to HIDE notices (nuclear mode)' );

		// Access global $wp_filter to manipulate hooks
		global $wp_filter;

		// Process each notice hook
		$total_removed = 0;
		$total_kept = 0;
		foreach ( $this->get_notice_hooks() as $hook_name ) {
			$stats = $this->remove_notice_callbacks( $hook_name );
			$total_removed += $stats['removed'];
			$total_kept += $stats['kept'];
		}

		error_log( sprintf(
			'NoticeFilter: Processed hooks - Removed: %d, Kept: %d',
			$total_removed,
			$total_kept
		) );
		error_log( '=== NoticeFilter::filter_notices() END ===' );
	}

	/**
	 * Remove callbacks from a specific notice hook
	 *
	 * Iterates through all callbacks at all priorities for the given hook,
	 * checks allowlist, and removes non-allowlisted callbacks.
	 *
	 * @param string $hook_name The hook name to process.
	 *
	 * @return array Stats array with 'removed' and 'kept' counts.
	 */
	public function remove_notice_callbacks( string $hook_name ): array {
		global $wp_filter;

		$stats = [
			'removed' => 0,
			'kept' => 0,
		];

		// Check if hook exists
		if ( ! isset( $wp_filter[ $hook_name ] ) ) {
			error_log( sprintf( 'NoticeFilter: Hook "%s" does not exist in $wp_filter', $hook_name ) );
			return $stats;
		}

		// Check if hook has callbacks
		if ( empty( $wp_filter[ $hook_name ]->callbacks ) ) {
			error_log( sprintf( 'NoticeFilter: Hook "%s" has no callbacks', $hook_name ) );
			return $stats;
		}

		error_log( sprintf( 'NoticeFilter: Processing hook "%s"', $hook_name ) );

		// Iterate through all priorities
		foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
			// Skip if no callbacks at this priority
			if ( empty( $callbacks ) ) {
				continue;
			}

			error_log( sprintf( 'NoticeFilter: Hook "%s" has %d callbacks at priority %d', $hook_name, count( $callbacks ), $priority ) );

			// Iterate through all callbacks at this priority
			foreach ( $callbacks as $id => $callback_data ) {
				$callback = $callback_data['function'];
				$callback_name = $this->identifier->get_callback_name( $callback );

				// Check if callback should be kept
				if ( $this->should_keep_callback( $callback, $hook_name ) ) {
					// Log that callback was kept (allowlisted)
					error_log( sprintf( 'NoticeFilter: KEEPING callback "%s" (allowlisted)', $callback_name ) );
					$this->log_callback_action(
						$callback,
						$hook_name,
						$priority,
						'kept_allowlisted'
					);
					$stats['kept']++;
					continue;
				}

				// Remove callback from hook
				error_log( sprintf( 'NoticeFilter: REMOVING callback "%s"', $callback_name ) );
				unset( $wp_filter[ $hook_name ]->callbacks[ $priority ][ $id ] );

				// Log removal
				$this->log_callback_action(
					$callback,
					$hook_name,
					$priority,
					'removed'
				);
				$stats['removed']++;
			}
		}

		return $stats;
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

	/**
	 * Enqueue combined CSS assets
	 *
	 * Enqueues the combined qala-plugin-manager.css file which includes:
	 * - Notice hider styles (nuclear approach for hiding notices)
	 * - Admin bar toggle styles
	 * - Admin page styles
	 *
	 * IMPORTANT: CSS is only loaded when user preference is to HIDE notices.
	 * If user wants to show all notices (qala_show_notices = 'yes'), CSS is not loaded.
	 * This prevents the notice-hiding CSS from interfering with notice visibility.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Check user preference - 'yes' means show notices, 'no' means hide them
		$user_id = get_current_user_id();
		$user_preference = get_user_meta( $user_id, 'qala_show_notices', true );

		// Only load CSS if user wants to HIDE notices (preference is not 'yes')
		// Default to hiding if no preference set
		if ( $user_preference === 'yes' ) {
			return; // User wants to see notices, don't load hiding CSS
		}

		$css_path = \QalaPluginManager\Plugin::get_path() . '/assets/dist/qala-plugin-manager.css';
		$css_url = \QalaPluginManager\Plugin::get_url() . '/assets/dist/qala-plugin-manager.css';

		wp_enqueue_style(
			'qala-plugin-manager',
			$css_url,
			[],
			file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.3'
		);
	}

}
