<?php
/**
 * NoticeLogTable Class
 *
 * Extends WP_List_Table to display notice log entries in WordPress admin.
 * Provides table display with:
 * - Sorting
 * - Pagination
 * - Bulk actions
 * - Search/filter
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

// WordPress requires WP_List_Table to be loaded manually
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class NoticeLogTable
 *
 * Displays notice log entries in a WordPress-native table format.
 *
 * Features:
 * - Display callback names, hooks, timestamps
 * - Add to allowlist action
 * - Bulk delete logs
 * - Search by callback name
 * - Sort by various columns
 *
 * @since 1.0.0
 */
class NoticeLogTable extends \WP_List_Table {

	/**
	 * NoticeLogger instance
	 *
	 * @var NoticeLogger
	 */
	private $logger;

	/**
	 * AllowlistManager instance
	 *
	 * @var AllowlistManager
	 */
	private $allowlist;

	/**
	 * Constructor
	 *
	 * @param NoticeLogger     $logger Notice logger instance.
	 * @param AllowlistManager $allowlist Allowlist manager instance.
	 */
	public function __construct( NoticeLogger $logger, AllowlistManager $allowlist ) {
		$this->logger = $logger;
		$this->allowlist = $allowlist;

		parent::__construct( [
			'singular' => 'notice',
			'plural' => 'notices',
			'ajax' => false,
		] );
	}

	/**
	 * Get table columns
	 *
	 * @return array Column definitions
	 */
	public function get_columns(): array {
		return [
			'cb' => '<input type="checkbox" />',
			'callback_name' => __( 'Callback Name', 'qala-plugin-manager' ),
			'hook_name' => __( 'Hook', 'qala-plugin-manager' ),
			'hook_priority' => __( 'Priority', 'qala-plugin-manager' ),
			'action_taken' => __( 'Action', 'qala-plugin-manager' ),
			'created_at' => __( 'Last Seen', 'qala-plugin-manager' ),
		];
	}

	/**
	 * Get sortable columns
	 *
	 * @return array Sortable column definitions
	 */
	public function get_sortable_columns(): array {
		return [
			'callback_name' => [ 'callback_name', false ],
			'hook_name' => [ 'hook_name', false ],
			'hook_priority' => [ 'hook_priority', false ],
			'created_at' => [ 'created_at', true ], // Default sort
		];
	}

	/**
	 * Get bulk actions
	 *
	 * @return array Bulk action definitions
	 */
	public function get_bulk_actions(): array {
		return [
			'delete' => __( 'Delete', 'qala-plugin-manager' ),
			'add_to_allowlist' => __( 'Add to Allowlist', 'qala-plugin-manager' ),
		];
	}

	/**
	 * Render checkbox column
	 *
	 * @param array $item Row data.
	 * @return string Checkbox HTML
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="notice[]" value="%s" />',
			esc_attr( $item['id'] )
		);
	}

	/**
	 * Render callback name column
	 *
	 * @param array $item Row data.
	 * @return string Column content
	 */
	public function column_callback_name( $item ): string {
		$actions = [
			'add_allowlist' => sprintf(
				'<a href="#" class="qala-add-to-allowlist" data-pattern="%s" data-pattern-type="exact">%s</a>',
				esc_attr( $item['callback_name'] ),
				__( 'Add to Allowlist', 'qala-plugin-manager' )
			),
		];

		return sprintf(
			'<code>%s</code> %s',
			esc_html( $item['callback_name'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render hook name column
	 *
	 * @param array $item Row data.
	 * @return string Column content
	 */
	public function column_hook_name( $item ): string {
		return esc_html( $item['hook_name'] );
	}

	/**
	 * Render hook priority column
	 *
	 * @param array $item Row data.
	 * @return string Column content
	 */
	public function column_hook_priority( $item ): string {
		return esc_html( $item['hook_priority'] );
	}

	/**
	 * Render action taken column
	 *
	 * @param array $item Row data.
	 * @return string Column content
	 */
	public function column_action_taken( $item ): string {
		$action = $item['action_taken'];
		$class = 'qala-action-' . sanitize_html_class( $action );

		$labels = [
			'removed' => __( 'Removed', 'qala-plugin-manager' ),
			'kept_allowlisted' => __( 'Kept (Allowlisted)', 'qala-plugin-manager' ),
		];

		$label = isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( $action );

		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Render created_at column
	 *
	 * @param array $item Row data.
	 * @return string Column content
	 */
	public function column_created_at( $item ): string {
		$timestamp = strtotime( $item['created_at'] );
		$time_diff = human_time_diff( $timestamp, current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s ago</abbr>',
			esc_attr( $item['created_at'] ),
			esc_html( $time_diff )
		);
	}

	/**
	 * Default column renderer
	 *
	 * @param array  $item Row data.
	 * @param string $column_name Column name.
	 * @return string Column content
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Prepare items for display
	 *
	 * Fetches data from database, handles pagination, sorting, and filtering.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		// Register columns
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		// Handle bulk actions
		$this->process_bulk_action();

		// Get data
		$per_page = 20;
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		// Get orderby and order parameters
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		// Get search term
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Fetch data
		$data = $this->fetch_data( $per_page, $offset, $orderby, $order, $search );
		$total_items = $this->get_total_items( $search );

		// Set items
		$this->items = $data;

		// Set pagination
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
	}

	/**
	 * Fetch data from database
	 *
	 * @param int    $per_page Items per page.
	 * @param int    $offset Offset for pagination.
	 * @param string $orderby Column to order by.
	 * @param string $order Sort order (ASC or DESC).
	 * @param string $search Search term.
	 * @return array Data rows
	 */
	private function fetch_data( int $per_page, int $offset, string $orderby, string $order, string $search ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'qala_hidden_notices_log';

		// Validate orderby
		$valid_orderby = [ 'callback_name', 'hook_name', 'hook_priority', 'action_taken', 'created_at' ];
		if ( ! in_array( $orderby, $valid_orderby, true ) ) {
			$orderby = 'created_at';
		}

		// Validate order
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// Build query
		$where = '1=1';
		$args = [];

		if ( ! empty( $search ) ) {
			$where .= ' AND callback_name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$args[] = $per_page;
		$args[] = $offset;

		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $args ) ) {
			$query = $wpdb->prepare( $query, ...$args );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Get total items count
	 *
	 * @param string $search Search term.
	 * @return int Total items
	 */
	private function get_total_items( string $search ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'qala_hidden_notices_log';

		$where = '1=1';
		$args = [];

		if ( ! empty( $search ) ) {
			$where .= ' AND callback_name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		if ( ! empty( $args ) ) {
			$query = $wpdb->prepare( $query, ...$args );
		}

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Process bulk actions
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		// Check if action is requested
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-notices' ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'qala-plugin-manager' ) );
		}

		// Get selected items
		$notice_ids = isset( $_GET['notice'] ) ? array_map( 'intval', (array) $_GET['notice'] ) : [];

		if ( empty( $notice_ids ) ) {
			return;
		}

		// Process action
		switch ( $action ) {
			case 'delete':
				$this->bulk_delete( $notice_ids );
				break;

			case 'add_to_allowlist':
				$this->bulk_add_to_allowlist( $notice_ids );
				break;
		}
	}

	/**
	 * Bulk delete notices
	 *
	 * @param array $notice_ids Notice IDs to delete.
	 * @return void
	 */
	private function bulk_delete( array $notice_ids ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'qala_hidden_notices_log';

		$placeholders = implode( ',', array_fill( 0, count( $notice_ids ), '%d' ) );
		$query = "DELETE FROM {$table} WHERE id IN ({$placeholders})";
		$query = $wpdb->prepare( $query, ...$notice_ids );

		$wpdb->query( $query );

		add_settings_error(
			'qala_notices',
			'notices_deleted',
			sprintf(
				/* translators: %d: Number of notices deleted */
				__( 'Deleted %d notice log entries', 'qala-plugin-manager' ),
				count( $notice_ids )
			),
			'updated'
		);
	}

	/**
	 * Bulk add to allowlist
	 *
	 * @param array $notice_ids Notice IDs to add to allowlist.
	 * @return void
	 */
	private function bulk_add_to_allowlist( array $notice_ids ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'qala_hidden_notices_log';

		$placeholders = implode( ',', array_fill( 0, count( $notice_ids ), '%d' ) );
		$query = "SELECT DISTINCT callback_name FROM {$table} WHERE id IN ({$placeholders})";
		$query = $wpdb->prepare( $query, ...$notice_ids );

		$callbacks = $wpdb->get_col( $query );

		$added = 0;
		foreach ( $callbacks as $callback ) {
			if ( $this->allowlist->add_pattern( $callback, 'exact' ) ) {
				$added++;
			}
		}

		add_settings_error(
			'qala_notices',
			'patterns_added',
			sprintf(
				/* translators: %d: Number of patterns added */
				__( 'Added %d patterns to allowlist', 'qala-plugin-manager' ),
				$added
			),
			'updated'
		);
	}

	/**
	 * Display table navigation
	 *
	 * Adds search box before the table.
	 *
	 * @param string $which Top or bottom position.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( $which === 'top' ) {
			?>
			<div class="alignleft actions">
				<label class="screen-reader-text" for="notice-search-input">
					<?php esc_html_e( 'Search Notices', 'qala-plugin-manager' ); ?>
				</label>
				<input
					type="search"
					id="notice-search-input"
					name="s"
					value="<?php echo isset( $_GET['s'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'Search by callback name...', 'qala-plugin-manager' ); ?>"
				/>
				<?php submit_button( __( 'Search Notices', 'qala-plugin-manager' ), 'button', '', false ); ?>
			</div>
			<?php
		}
	}
}
