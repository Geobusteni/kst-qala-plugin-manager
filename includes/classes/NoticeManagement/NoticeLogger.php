<?php
/**
 * NoticeLogger Class
 *
 * Logs notice removals to database for analytics and allowlist management.
 * Provides query methods for admin page and implements deduplication.
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

/**
 * Class NoticeLogger
 *
 * Responsibilities:
 * - Insert records into qala_hidden_notices_log table
 * - Deduplicate log entries (one per notice per day)
 * - Provide query methods for admin page
 * - Clean old log entries (30-day retention by default)
 *
 * Database Table: wp_qala_hidden_notices_log
 * Fields:
 * - id: Auto-increment primary key
 * - callback_name: Full callback name (function or Class::method)
 * - hook_name: Hook name (admin_notices, network_admin_notices, etc)
 * - hook_priority: Hook priority level
 * - action_taken: Action (removed, kept_allowlisted, etc)
 * - reason: Reason (no_qala_full_access, matches_allowlist_pattern, etc)
 * - user_id: User ID who triggered removal
 * - site_id: Site ID (multisite)
 * - created_at: Timestamp
 *
 * @since 1.0.0
 */
class NoticeLogger {

	/**
	 * Database table name (without prefix)
	 *
	 * @var string
	 */
	private const TABLE_NAME = 'qala_hidden_notices_log';

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
	 * Log a notice removal or keepalive action
	 *
	 * Logs the action to database with deduplication to prevent
	 * multiple log entries for the same notice on the same day.
	 *
	 * Deduplication Strategy:
	 * - Check if entry exists for same callback_name, hook_name, and today's date
	 * - If exists, skip insert (already logged today)
	 * - If not, insert new entry
	 *
	 * @param string $callback_name The callback name (function or Class::method).
	 * @param string $hook_name The hook name where callback was registered.
	 * @param int    $priority The priority level.
	 * @param string $action The action taken (removed, kept_allowlisted, etc).
	 * @param string $reason The reason for action (no_qala_full_access, matches_allowlist_pattern, etc).
	 *
	 * @return void
	 */
	public function log_removal(
		string $callback_name,
		string $hook_name,
		int $priority,
		string $action,
		string $reason
	): void {
		global $wpdb;

		// Check if already logged today (deduplication)
		$today_start = gmdate( 'Y-m-d 00:00:00' );
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->get_table_name()}
				 WHERE callback_name = %s
				 AND hook_name = %s
				 AND created_at >= %s
				 LIMIT 1",
				$callback_name,
				$hook_name,
				$today_start
			)
		);

		if ( $exists ) {
			return; // Already logged today, skip
		}

		// Insert new log entry
		$wpdb->insert(
			$this->get_table_name(),
			[
				'callback_name' => $callback_name,
				'hook_name' => $hook_name,
				'hook_priority' => $priority,
				'action_taken' => $action,
				'reason' => $reason,
				'user_id' => get_current_user_id(),
				'site_id' => get_current_blog_id(),
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' ]
		);
	}

	/**
	 * Get recent notice log entries
	 *
	 * Returns the most recent log entries, ordered by creation date descending.
	 *
	 * @param int $limit Maximum number of entries to return (default: 100).
	 *
	 * @return array Array of log entry records.
	 */
	public function get_recent_notices( int $limit = 100 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()}
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Get unique notices (deduplicated by callback name)
	 *
	 * Returns one entry per unique callback_name, showing the most recent
	 * occurrence of each notice.
	 *
	 * @return array Array of unique notice records.
	 */
	public function get_unique_notices(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT DISTINCT callback_name, hook_name, MAX(created_at) as last_seen
			 FROM {$this->get_table_name()}
			 GROUP BY callback_name, hook_name
			 ORDER BY last_seen DESC",
			ARRAY_A
		);

		return $results ?: [];
	}

	/**
	 * Clean old log entries
	 *
	 * Deletes log entries older than specified number of days.
	 * Default retention is 30 days.
	 *
	 * @param int $days Number of days to retain (default: 30).
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_logs( int $days = 30 ): int {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->get_table_name()}
				 WHERE created_at < %s",
				$cutoff_date
			)
		);

		return (int) $deleted;
	}

	/**
	 * Get log statistics
	 *
	 * Returns statistics about logged notices:
	 * - Total notices logged
	 * - Unique callbacks
	 * - Most common callbacks
	 * - Actions breakdown (removed vs kept)
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics(): array {
		global $wpdb;
		$table = $this->get_table_name();

		// Total entries
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// Unique callbacks
		$unique = $wpdb->get_var( "SELECT COUNT(DISTINCT callback_name) FROM {$table}" );

		// Most common callbacks
		$most_common = $wpdb->get_results(
			"SELECT callback_name, COUNT(*) as count
			 FROM {$table}
			 GROUP BY callback_name
			 ORDER BY count DESC
			 LIMIT 10",
			ARRAY_A
		);

		// Actions breakdown
		$actions = $wpdb->get_results(
			"SELECT action_taken, COUNT(*) as count
			 FROM {$table}
			 GROUP BY action_taken",
			ARRAY_A
		);

		return [
			'total' => (int) $total,
			'unique_callbacks' => (int) $unique,
			'most_common' => $most_common ?: [],
			'actions' => $actions ?: [],
		];
	}
}
