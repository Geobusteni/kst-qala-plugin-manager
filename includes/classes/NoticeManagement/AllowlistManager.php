<?php
/**
 * AllowlistManager Class
 *
 * Manages patterns for notices that should be allowed (shown) despite notice hiding.
 * Provides CRUD operations for allowlist patterns and pattern matching functionality.
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

/**
 * Class AllowlistManager
 *
 * Manages the allowlist database table and provides pattern matching capabilities.
 * Supports exact matches, wildcard patterns, and regular expressions.
 *
 * Database table: wp_qala_notice_allowlist
 * Fields: id, pattern_value, pattern_type (exact/wildcard/regex), is_active, created_by, created_at, updated_at
 *
 * Pattern Types:
 * - exact: 'rocket_bad_deactivations' matches exactly
 * - wildcard: 'rocket_*' converts to regex /^rocket_.*$/
 * - regex: '/^rocket_.*$/' used as-is
 *
 * @since 1.0.0
 */
class AllowlistManager {

	/**
	 * Database table name (without prefix)
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'qala_notice_allowlist';

	/**
	 * Cache key prefix for transients
	 *
	 * @var string
	 */
	private const CACHE_KEY_PREFIX = 'qala_allowlist_patterns_';

	/**
	 * Cache expiration time in seconds (1 hour)
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Get the full table name with WordPress prefix
	 *
	 * @return string Full table name
	 */
	private function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Get the cache key for current site
	 *
	 * @return string Cache key
	 */
	private function get_cache_key(): string {
		return self::CACHE_KEY_PREFIX . get_current_blog_id();
	}

	/**
	 * Clear the allowlist patterns cache
	 *
	 * @return void
	 */
	private function clear_cache(): void {
		delete_transient( $this->get_cache_key() );
	}

	/**
	 * Add a pattern to the allowlist
	 *
	 * Adds a new pattern to the database that will be used to determine
	 * which notices should be shown despite global hiding.
	 *
	 * @param string $pattern The pattern value (function name, wildcard, or regex).
	 * @param string $type Pattern type: 'exact', 'wildcard', or 'regex'. Defaults to 'exact'.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function add_pattern( string $pattern, string $type = 'exact' ): bool {
		global $wpdb;

		// Validate regex patterns before adding.
		if ( $type === 'regex' ) {
			if ( ! $this->is_valid_regex( $pattern ) ) {
				return false;
			}
		}

		$result = $wpdb->insert(
			$this->get_table_name(),
			[
				'pattern_value' => $pattern,
				'pattern_type'  => $type,
				'is_active'     => 1,
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		// Clear cache after modification.
		$this->clear_cache();

		return (bool) $result;
	}

	/**
	 * Remove a pattern from the allowlist by ID
	 *
	 * @param int $id Pattern ID to remove.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_pattern( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		// Clear cache after modification.
		$this->clear_cache();

		// Returns number of rows affected, convert to boolean.
		return $result > 0;
	}

	/**
	 * Remove a pattern from the allowlist by pattern value
	 *
	 * @param string $pattern_value Pattern value to remove.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_pattern_by_value( string $pattern_value ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_table_name(),
			[ 'pattern_value' => $pattern_value ],
			[ '%s' ]
		);

		// Clear cache after modification.
		$this->clear_cache();

		// Returns number of rows affected, convert to boolean.
		return $result > 0;
	}

	/**
	 * Get all active patterns from the allowlist
	 *
	 * Returns all patterns that are currently active. Results are cached
	 * for performance using WordPress transients.
	 *
	 * @return array Array of pattern records, each containing:
	 *               - id: Pattern ID
	 *               - pattern_value: The pattern string
	 *               - pattern_type: Type (exact/wildcard/regex)
	 *               - is_active: Active status (always 1 for results)
	 */
	public function get_all_patterns(): array {
		// Check cache first.
		$cached = get_transient( $this->get_cache_key() );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT id, pattern_value, pattern_type, is_active
			FROM {$table_name}
			WHERE is_active = 1
			ORDER BY created_at DESC",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $results === null ) {
			$results = [];
		}

		// Cache the results.
		set_transient( $this->get_cache_key(), $results, self::CACHE_EXPIRATION );

		return $results;
	}

	/**
	 * Check if a callback name matches any allowlist pattern
	 *
	 * Iterates through all active patterns and checks if the given
	 * callback name matches any of them. Supports exact matches,
	 * wildcard patterns, and regular expressions.
	 *
	 * @param string $callback_name The callback name to check.
	 *
	 * @return bool True if callback matches any allowlist pattern, false otherwise.
	 */
	public function matches_allowlist( string $callback_name ): bool {
		$patterns = $this->get_all_patterns();

		foreach ( $patterns as $pattern ) {
			if ( $this->matches_pattern( $callback_name, $pattern['pattern_value'], $pattern['pattern_type'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a callback matches a specific pattern
	 *
	 * @param string $callback_name The callback name to check.
	 * @param string $pattern The pattern to match against.
	 * @param string $pattern_type The type of pattern (exact/wildcard/regex).
	 *
	 * @return bool True if callback matches the pattern, false otherwise.
	 */
	private function matches_pattern( string $callback_name, string $pattern, string $pattern_type ): bool {
		switch ( $pattern_type ) {
			case 'exact':
				return $callback_name === $pattern;

			case 'wildcard':
				return $this->matches_wildcard( $callback_name, $pattern );

			case 'regex':
				return $this->matches_regex( $callback_name, $pattern );

			default:
				return false;
		}
	}

	/**
	 * Check if callback matches a wildcard pattern
	 *
	 * Converts wildcard pattern to regex and performs match.
	 * Wildcard syntax: * matches any characters (0 or more)
	 *
	 * Examples:
	 * - 'rocket_*' matches 'rocket_notice', 'rocket_warning'
	 * - '*_notice' matches 'my_notice', 'show_notice'
	 *
	 * @param string $callback_name The callback name to check.
	 * @param string $wildcard_pattern The wildcard pattern.
	 *
	 * @return bool True if matches, false otherwise.
	 */
	private function matches_wildcard( string $callback_name, string $wildcard_pattern ): bool {
		// Escape special regex characters except asterisk.
		$escaped = preg_quote( $wildcard_pattern, '/' );

		// Convert asterisk to regex .* (match any characters).
		$regex = '/^' . str_replace( '\*', '.*', $escaped ) . '$/';

		return (bool) preg_match( $regex, $callback_name );
	}

	/**
	 * Check if callback matches a regex pattern
	 *
	 * @param string $callback_name The callback name to check.
	 * @param string $regex_pattern The regex pattern.
	 *
	 * @return bool True if matches, false otherwise.
	 */
	private function matches_regex( string $callback_name, string $regex_pattern ): bool {
		// Suppress warnings for invalid regex.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppressing errors is intentional for regex validation.
		return (bool) @preg_match( $regex_pattern, $callback_name );
	}

	/**
	 * Validate if a string is a valid regex pattern
	 *
	 * @param string $pattern The pattern to validate.
	 *
	 * @return bool True if valid regex, false otherwise.
	 */
	private function is_valid_regex( string $pattern ): bool {
		// Attempt to use the regex, check for errors.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppressing errors for regex validation is intentional.
		return @preg_match( $pattern, null ) !== false;
	}

	/**
	 * Activate a pattern by ID
	 *
	 * Sets the is_active flag to 1 for the specified pattern.
	 *
	 * @param int $id Pattern ID to activate.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function activate_pattern( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			[
				'is_active'  => 1,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		// Clear cache after modification.
		$this->clear_cache();

		// Returns number of rows affected, convert to boolean.
		return $result > 0;
	}

	/**
	 * Deactivate a pattern by ID
	 *
	 * Sets the is_active flag to 0 for the specified pattern.
	 * Deactivated patterns are not deleted but won't be used for matching.
	 *
	 * @param int $id Pattern ID to deactivate.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function deactivate_pattern( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			[
				'is_active'  => 0,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		// Clear cache after modification.
		$this->clear_cache();

		// Returns number of rows affected, convert to boolean.
		return $result > 0;
	}
}
