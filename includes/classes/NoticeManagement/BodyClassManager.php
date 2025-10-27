<?php
/**
 * BodyClassManager Class
 *
 * Adds CSS classes to the admin body tag based on user capabilities and preferences.
 * This allows CSS/JS to control notice hiding without conditional loading.
 *
 * Features:
 * - Adds body classes to ALL admin pages
 * - CSS uses classes to hide/show notices
 * - JS uses classes to determine behavior
 * - Assets always load (no conditional logic)
 *
 * Body Classes:
 * - qala-notices-hidden: When notices should be hidden
 * - qala-notices-visible: When notices should be visible
 * - qala-has-full-access: When user has qala_full_access capability
 * - qala-no-full-access: When user doesn't have capability
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

use QalaPluginManager\NoticeManagement\Traits\CapabilityChecker;
use QalaPluginManager\Interfaces\WithHooksInterface;

/**
 * Class BodyClassManager
 *
 * Responsibilities:
 * - Hook into admin_body_class filter
 * - Add CSS classes based on user state
 * - Provide clean separation between logic and presentation
 * - Enable CSS-based notice hiding without conditional asset loading
 *
 * @since 1.0.0
 */
class BodyClassManager implements WithHooksInterface {

	use CapabilityChecker;

	/**
	 * AllowlistManager instance for pattern access
	 *
	 * @var AllowlistManager
	 */
	private $allowlist;

	/**
	 * Constructor
	 *
	 * @param AllowlistManager $allowlist Allowlist manager instance.
	 */
	public function __construct( AllowlistManager $allowlist ) {
		$this->allowlist = $allowlist;
	}

	/**
	 * Initialize hooks
	 *
	 * Registers the admin_body_class filter to add CSS classes.
	 * Also localizes allowlist patterns for JavaScript content matching.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'localize_allowlist_patterns' ], 15 );
	}

	/**
	 * Add body classes based on user state
	 *
	 * This method adds CSS classes to the admin body tag that indicate:
	 * 1. Whether the user has qala_full_access capability
	 * 2. Whether notices should be visible or hidden
	 *
	 * The CSS classes allow the notice hiding CSS rules to be applied
	 * conditionally without any JavaScript or PHP conditional logic.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string Modified body classes.
	 */
	public function add_body_classes( string $classes ): string {
		$class_array = [];

		// Check if user has qala_full_access.
		$has_access    = $this->user_has_capability( 'qala_full_access' );
		$class_array[] = $has_access ? 'qala-has-full-access' : 'qala-no-full-access';

		// Check if notices should be visible.
		$should_show   = $this->should_show_notices();
		$class_array[] = $should_show ? 'qala-notices-visible' : 'qala-notices-hidden';

		// Add all classes.
		$classes .= ' ' . implode( ' ', $class_array );

		return $classes;
	}

	/**
	 * Determine if notices should be shown
	 *
	 * Decision Logic:
	 * 1. Users WITH qala_full_access:
	 *    - Default: SHOW notices
	 *    - Check user preference: If they toggled OFF, HIDE notices
	 *
	 * 2. Users WITHOUT qala_full_access:
	 *    - Default: HIDE notices
	 *    - Check global toggle: If enabled, SHOW notices
	 *
	 * @return bool True if notices should be visible, false if hidden.
	 */
	private function should_show_notices(): bool {
		// If user has capability, they can see notices by default.
		if ( $this->user_has_capability( 'qala_full_access' ) ) {
			// But check their personal preference.
			$user_pref = get_user_meta( get_current_user_id(), 'qala_show_notices', true );
			if ( $user_pref === 'no' ) {
				return false; // User toggled them off.
			}
			return true; // Show by default.
		}

		// User without capability - check global toggle.
		$global_toggle = get_option( 'qala_notices_enabled', 'no' );
		if ( $global_toggle === 'yes' ) {
			return true; // Global setting says show.
		}

		return false; // Hide by default for non-privileged users.
	}

	/**
	 * Localize allowlist patterns for JavaScript
	 *
	 * Provides allowlist patterns to JavaScript for client-side notice content matching.
	 * This allows JavaScript to check notice text content against patterns and show
	 * matching notices even when they're hidden by CSS.
	 *
	 * Priority 15 ensures this runs after script is enqueued (priority 5) and
	 * after other localizations (priority 10).
	 *
	 * @return void
	 */
	public function localize_allowlist_patterns(): void {
		// Only localize if notices are hidden.
		if ( ! $this->should_hide_notices() ) {
			return;
		}

		// Get all allowlist patterns.
		$patterns = $this->allowlist->get_all_patterns();

		// Prepare patterns for JavaScript.
		$js_patterns = [];
		foreach ( $patterns as $pattern ) {
			$js_patterns[] = [
				'value' => $pattern['pattern_value'],
				'type'  => $pattern['pattern_type'],
			];
		}

		// Localize for JavaScript.
		wp_localize_script(
			'qala-plugin-manager',
			'qalaAllowlistPatterns',
			[
				'patterns' => $js_patterns,
				'enabled'  => true,
			]
		);
	}

	/**
	 * Determine if notices should be hidden
	 *
	 * Inverse of should_show_notices() for clarity in some contexts.
	 *
	 * @return bool True if notices should be hidden, false if shown.
	 */
	private function should_hide_notices(): bool {
		return ! $this->should_show_notices();
	}
}
