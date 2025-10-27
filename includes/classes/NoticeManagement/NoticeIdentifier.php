<?php
/**
 * Notice Identifier
 *
 * Generates unique hashes for notice callbacks and provides pattern matching utilities.
 * Uses callback-based hashing (not content-based) to avoid executing callbacks.
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

/**
 * Class NoticeIdentifier
 *
 * Handles:
 * - Generating unique hashes for notice callbacks
 * - Extracting human-readable names from callbacks
 * - Pattern matching (exact, wildcard, regex)
 * - Closure detection
 * - Pattern sanitization
 *
 * Hash Strategy:
 * - Callback-based only (no content hashing to avoid callback execution)
 * - Uses MD5 with site salt for uniqueness
 * - Includes hook name in hash for context-specific identification
 */
class NoticeIdentifier {

	/**
	 * Generate unique hash for a callback
	 *
	 * Creates a stable, unique identifier for a callback by hashing:
	 * 1. Callback name (function or Class::method)
	 * 2. Hook name (for context-specific identification)
	 * 3. Site-specific salt (for multisite isolation)
	 *
	 * This is NOT content-based hashing because:
	 * - Executing callbacks has side effects
	 * - Content can change dynamically
	 * - Performance overhead is too high
	 *
	 * @param mixed  $callback The callback to hash (function name, array, or Closure).
	 * @param string $hook_name The hook name where this callback is registered.
	 *
	 * @return string MD5 hash (32 characters).
	 */
	public function generate_hash( $callback, string $hook_name ): string {
		// Extract callback name.
		$callback_name = $this->get_callback_name( $callback );

		// Build unique string with site-specific salt.
		$site_salt = get_current_blog_id() . '_' . wp_salt( 'nonce' );

		// Include hook name for context-specific identification.
		$hash_string = $callback_name . '::' . $hook_name . '::' . $site_salt;

		// Generate MD5 hash (fast, sufficient for identification).
		return md5( $hash_string );
	}

	/**
	 * Extract human-readable name from callback
	 *
	 * Handles different callback types:
	 * - String: Function name (e.g., 'my_function')
	 * - Array: Class method (e.g., ['MyClass', 'method'] or [$object, 'method'])
	 * - Closure: Anonymous function (returns 'Closure')
	 * - Invokable object: Object with __invoke() method
	 *
	 * @param mixed $callback The callback to extract name from.
	 *
	 * @return string Human-readable callback name.
	 */
	public function get_callback_name( $callback ): string {
		// Case 1: String function name.
		if ( is_string( $callback ) ) {
			return $callback;
		}

		// Case 2: Array [Class, method] or [Object, method].
		if ( is_array( $callback ) && count( $callback ) >= 2 ) {
			$class_or_object = $callback[0];
			$method          = $callback[1];

			// Get class name (handle both objects and class names).
			if ( is_object( $class_or_object ) ) {
				$class_name = get_class( $class_or_object );
			} elseif ( is_string( $class_or_object ) ) {
				$class_name = $class_or_object;
			} else {
				return 'Unknown';
			}

			return $class_name . '::' . $method;
		}

		// Case 3: Closure (anonymous function).
		if ( $callback instanceof \Closure ) {
			// Closures cannot be uniquely identified.
			return 'Closure';
		}

		// Case 4: Invokable object (has __invoke method).
		if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
			return get_class( $callback ) . '::__invoke';
		}

		// Fallback: Unknown callback type.
		return 'Unknown';
	}

	/**
	 * Check if callback is a closure
	 *
	 * Closures are anonymous functions that cannot be uniquely identified.
	 * All closures will generate the same hash and match the same patterns.
	 *
	 * @param mixed $callback The callback to check.
	 *
	 * @return bool True if callback is a Closure, false otherwise.
	 */
	public function is_closure( $callback ): bool {
		return $callback instanceof \Closure;
	}

	/**
	 * Sanitize pattern input from users
	 *
	 * Removes dangerous characters while preserving valid pattern syntax.
	 * Uses WordPress sanitization functions for consistency.
	 *
	 * Valid pattern characters:
	 * - Alphanumeric: a-z, A-Z, 0-9
	 * - Common separators: _ - : \ /
	 * - Wildcard: *
	 * - Regex delimiters and special chars: / ^ $ . * + ? [ ] ( ) { } |
	 *
	 * @param string $pattern The pattern to sanitize.
	 *
	 * @return string Sanitized pattern.
	 */
	public function sanitize_pattern( string $pattern ): string {
		// Use WordPress sanitization.
		$pattern = sanitize_text_field( $pattern );

		// Trim whitespace.
		$pattern = trim( $pattern );

		return $pattern;
	}

	/**
	 * Check if callback matches a pattern
	 *
	 * Supports three pattern types:
	 * 1. Exact match: Pattern exactly equals callback name
	 * 2. Wildcard match: Pattern contains * (e.g., 'rocket_*')
	 * 3. Regex match: Pattern is valid PCRE regex (e.g., '/^rocket_.*$/')
	 *
	 * Pattern matching is attempted in this order:
	 * 1. Exact match (fastest)
	 * 2. Regex match (if pattern is valid regex)
	 * 3. Wildcard match (if pattern contains *)
	 *
	 * @param mixed  $callback The callback to check.
	 * @param string $pattern The pattern to match against.
	 *
	 * @return bool True if callback matches pattern, false otherwise.
	 */
	public function matches_pattern( $callback, string $pattern ): bool {
		// Handle empty pattern.
		if ( empty( $pattern ) ) {
			return false;
		}

		// Get callback name for matching.
		$callback_name = $this->get_callback_name( $callback );

		// Try 1: Exact match (fastest).
		if ( $callback_name === $pattern ) {
			return true;
		}

		// Try 2: Regex pattern (check if valid regex).
		if ( $this->is_valid_regex( $pattern ) ) {
			return (bool) preg_match( $pattern, $callback_name );
		}

		// Try 3: Wildcard match (if pattern contains *).
		if ( strpos( $pattern, '*' ) !== false ) {
			return $this->matches_wildcard( $callback_name, $pattern );
		}

		// No match.
		return false;
	}

	/**
	 * Check if a pattern is a valid regular expression
	 *
	 * Uses @preg_match() with error suppression to test validity.
	 * Invalid regex will return false from preg_match().
	 *
	 * @param string $pattern The pattern to validate.
	 *
	 * @return bool True if pattern is valid regex, false otherwise.
	 */
	private function is_valid_regex( string $pattern ): bool {
		// Suppress errors and test if pattern is valid regex.
		// preg_match returns false for invalid patterns.
		return @preg_match( $pattern, '' ) !== false;
	}

	/**
	 * Match callback name against wildcard pattern
	 *
	 * Converts wildcard pattern to regex for matching.
	 * Wildcard syntax:
	 * - * matches any characters (0 or more)
	 *
	 * Examples:
	 * - 'rocket_*' matches 'rocket_notice', 'rocket_warning'
	 * - '*_notice' matches 'my_notice', 'show_notice'
	 * - 'rocket_*_test' matches 'rocket_foo_test', 'rocket_bar_test'
	 *
	 * @param string $callback_name The callback name to match.
	 * @param string $pattern The wildcard pattern.
	 *
	 * @return bool True if matches, false otherwise.
	 */
	private function matches_wildcard( string $callback_name, string $pattern ): bool {
		// Escape regex special characters except *.
		$escaped_pattern = preg_quote( $pattern, '/' );

		// Convert * to .* (match any characters).
		$regex_pattern = str_replace( '\*', '.*', $escaped_pattern );

		// Wrap in anchors for full string match.
		$regex = '/^' . $regex_pattern . '$/';

		// Match callback name against regex.
		return (bool) preg_match( $regex, $callback_name );
	}
}
