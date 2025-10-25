<?php
/**
 * Database Migration for Notice Management Tables
 *
 * Handles creation, updates, and rollback of notice management database tables.
 * Uses WordPress dbDelta() for safe schema updates.
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

/**
 * Class DatabaseMigration
 *
 * Manages database schema for notice management feature.
 * Includes version tracking and rollback support.
 */
class DatabaseMigration {

	/**
	 * Current schema version
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * WordPress option name for schema version tracking
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'qala_notice_db_version';

	/**
	 * Run all migrations
	 *
	 * Creates or updates tables based on schema version.
	 * Safe to run multiple times (idempotent).
	 *
	 * @return void
	 */
	public function run_migrations(): void {
		$current_version = $this->get_schema_version();

		// Only run if version is outdated or not set
		if ( version_compare( $current_version, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		try {
			$this->create_notice_log_table();
			$this->create_allowlist_table();
			$this->update_schema_version( self::SCHEMA_VERSION );

			error_log( sprintf(
				'Qala Notice Management: Database migration completed (v%s)',
				self::SCHEMA_VERSION
			) );
		} catch ( \Exception $e ) {
			error_log( sprintf(
				'Qala Notice Management: Migration failed - %s',
				$e->getMessage()
			) );
		}
	}

	/**
	 * Create notice removal log table
	 *
	 * Table: {prefix}qala_hidden_notices_log
	 * Purpose: Log all notice removals for analytics and allowlist management
	 *
	 * @return void
	 */
	public function create_notice_log_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'qala_hidden_notices_log';
		$charset_collate = $wpdb->get_charset_collate();

		// SQL must follow dbDelta() requirements:
		// - Two spaces after PRIMARY KEY
		// - Key definitions on separate lines
		// - No newlines within field definitions
		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			notice_hash varchar(32) NOT NULL COMMENT 'MD5 hash of callback name',
			callback_name varchar(255) NOT NULL COMMENT 'Full callback name (function or Class::method)',
			hook_name varchar(100) NOT NULL COMMENT 'Hook name (admin_notices, network_admin_notices, etc)',
			priority int(11) NOT NULL DEFAULT 10 COMMENT 'Hook priority level',
			action varchar(20) NOT NULL DEFAULT 'removed' COMMENT 'Action: removed, restored',
			reason varchar(255) DEFAULT NULL COMMENT 'Reason: no_qala_full_access, user_preference, etc',
			user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID who triggered removal',
			site_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Site ID (multisite)',
			created_at datetime NOT NULL COMMENT 'When notice was removed',
			PRIMARY KEY  (id),
			KEY idx_notice_hash (notice_hash),
			KEY idx_hook_name (hook_name),
			KEY idx_created_at (created_at),
			KEY idx_site_id (site_id),
			UNIQUE KEY idx_dedup (notice_hash, hook_name, created_at)
		) $charset_collate;";

		$this->load_upgrade_functions();
		dbDelta( $sql );

		// Verify table was created
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			throw new \Exception( "Failed to create table: $table_name" );
		}
	}

	/**
	 * Create notice allowlist table
	 *
	 * Table: {prefix}qala_notice_allowlist
	 * Purpose: Store allowlist patterns for exceptions to notice hiding
	 *
	 * @return void
	 */
	public function create_allowlist_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'qala_notice_allowlist';
		$charset_collate = $wpdb->get_charset_collate();

		// SQL must follow dbDelta() requirements
		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			pattern_value varchar(255) NOT NULL COMMENT 'Pattern to match (function name, regex, wildcard)',
			pattern_type enum('exact','wildcard','regex') NOT NULL DEFAULT 'exact' COMMENT 'Pattern matching type',
			is_active tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is this pattern active',
			created_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID who created',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_pattern (pattern_value),
			KEY idx_active (is_active)
		) $charset_collate;";

		$this->load_upgrade_functions();
		dbDelta( $sql );

		// Verify table was created
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			throw new \Exception( "Failed to create table: $table_name" );
		}
	}

	/**
	 * Load WordPress upgrade functions
	 *
	 * Protected method to allow mocking in tests.
	 *
	 * @return void
	 */
	protected function load_upgrade_functions(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
	}

	/**
	 * Get current schema version from options
	 *
	 * @return string Version string (e.g., '1.0.0') or '0.0.0' if not set
	 */
	public function get_schema_version(): string {
		return get_option( self::VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Update schema version in options
	 *
	 * @param string $version Version string to store
	 * @return void
	 */
	public function update_schema_version( string $version ): void {
		update_option( self::VERSION_OPTION, $version, false );
	}

	/**
	 * Rollback migration (drop tables)
	 *
	 * WARNING: This will delete all data!
	 * Only use for testing or complete uninstall.
	 *
	 * @return void
	 */
	public function rollback(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'qala_hidden_notices_log',
			$wpdb->prefix . 'qala_notice_allowlist',
		];

		foreach ( $tables as $table_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		}

		delete_option( self::VERSION_OPTION );

		error_log( 'Qala Notice Management: Database tables dropped (rollback)' );
	}

	/**
	 * Check if migrations are needed
	 *
	 * @return bool True if migrations should run
	 */
	public function needs_migration(): bool {
		$current_version = $this->get_schema_version();
		return version_compare( $current_version, self::SCHEMA_VERSION, '<' );
	}

	/**
	 * Verify table exists
	 *
	 * @param string $table_name Table name without prefix
	 * @return bool True if table exists
	 */
	public function table_exists( string $table_name ): bool {
		global $wpdb;
		$full_table_name = $wpdb->prefix . $table_name;
		return $wpdb->get_var( "SHOW TABLES LIKE '$full_table_name'" ) === $full_table_name;
	}

	/**
	 * Get table row count
	 *
	 * @param string $table_name Table name without prefix
	 * @return int Number of rows
	 */
	public function get_table_row_count( string $table_name ): int {
		global $wpdb;
		$full_table_name = $wpdb->prefix . $table_name;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $full_table_name" );
	}

	/**
	 * Get table schema info
	 *
	 * @param string $table_name Table name without prefix
	 * @return array Table columns information
	 */
	public function get_table_schema( string $table_name ): array {
		global $wpdb;
		$full_table_name = $wpdb->prefix . $table_name;
		return $wpdb->get_results( "DESCRIBE $full_table_name", ARRAY_A );
	}
}
