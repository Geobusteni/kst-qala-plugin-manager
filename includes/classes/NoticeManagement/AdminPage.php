<?php
/**
 * AdminPage Class
 *
 * Settings page UI for notice management.
 * Provides interface for:
 * - Viewing notice log
 * - Managing allowlist patterns
 * - Global enable/disable toggle
 * - AJAX operations
 *
 * @package QalaPluginManager
 * @subpackage NoticeManagement
 */

namespace QalaPluginManager\NoticeManagement;

use QalaPluginManager\Interfaces\WithHooksInterface;
use QalaPluginManager\NoticeManagement\Traits\CapabilityChecker;

/**
 * Class AdminPage
 *
 * Responsibilities:
 * - Register settings page under Settings menu
 * - Display notice log table with pagination
 * - Provide allowlist pattern management UI
 * - Handle AJAX requests for pattern operations
 * - Enqueue assets (JS/CSS)
 * - Use WordPress Settings API
 *
 * @since 1.0.0
 */
class AdminPage implements WithHooksInterface {

	use CapabilityChecker;

	/**
	 * AllowlistManager instance
	 *
	 * @var AllowlistManager
	 */
	private $allowlist;

	/**
	 * NoticeLogger instance
	 *
	 * @var NoticeLogger
	 */
	private $logger;

	/**
	 * Settings page hook suffix
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor
	 *
	 * @param AllowlistManager $allowlist Allowlist manager instance.
	 * @param NoticeLogger     $logger Notice logger instance.
	 */
	public function __construct( AllowlistManager $allowlist, NoticeLogger $logger ) {
		$this->allowlist = $allowlist;
		$this->logger = $logger;
	}

	/**
	 * Initialize hooks
	 *
	 * Registers all WordPress hooks for the admin page.
	 * Called by ServiceProvider when class is registered.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register settings page under Settings menu
		add_action( 'admin_menu', [ $this, 'register_menu' ] );

		// Register settings using WordPress Settings API
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Localize script for admin page (CSS/JS already loaded globally)
		add_action( 'admin_enqueue_scripts', [ $this, 'localize_script' ] );

		// AJAX handlers
		add_action( 'wp_ajax_qala_add_allowlist_pattern', [ $this, 'handle_add_pattern_ajax' ] );
		add_action( 'wp_ajax_qala_remove_allowlist_pattern', [ $this, 'handle_remove_pattern_ajax' ] );
		add_action( 'wp_ajax_qala_toggle_notices', [ $this, 'handle_toggle_ajax' ] );
	}

	/**
	 * Register settings submenu page
	 *
	 * Adds the settings page under Settings > Hide Notices
	 * Requires qala_full_access capability.
	 *
	 * @return string Page hook suffix
	 */
	public function register_menu(): string {
		$this->page_hook = add_options_page(
			__( 'Hide Notices Settings', 'qala-plugin-manager' ),
			__( 'Hide Notices', 'qala-plugin-manager' ),
			'qala_full_access',
			'qala-hide-notices',
			[ $this, 'render_page' ]
		);

		return $this->page_hook;
	}

	/**
	 * Register settings using WordPress Settings API
	 *
	 * Registers:
	 * - qala_notices_enabled option (global toggle)
	 * - Settings sections
	 * - Settings fields
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Register the main setting
		register_setting(
			'qala_notices',
			'qala_notices_enabled',
			[
				'type' => 'string',
				'default' => 'yes',
				'sanitize_callback' => [ $this, 'sanitize_yes_no' ],
			]
		);

		// Add settings section
		add_settings_section(
			'qala_notices_main',
			__( 'Notice Management Settings', 'qala-plugin-manager' ),
			[ $this, 'render_section_description' ],
			'qala-hide-notices'
		);

		// Add global toggle field
		add_settings_field(
			'qala_notices_enabled',
			__( 'Enable Notice Hiding', 'qala-plugin-manager' ),
			[ $this, 'render_enabled_field' ],
			'qala-hide-notices',
			'qala_notices_main'
		);
	}

	/**
	 * Render section description
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p>';
		esc_html_e(
			'Control which admin notices are hidden for users without qala_full_access capability.',
			'qala-plugin-manager'
		);
		echo '</p>';
	}

	/**
	 * Render enabled field
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$enabled = get_option( 'qala_notices_enabled', 'yes' );
		?>
		<label>
			<input
				type="checkbox"
				name="qala_notices_enabled"
				value="yes"
				<?php checked( $enabled, 'yes' ); ?>
			/>
			<?php esc_html_e( 'Hide admin notices for non-privileged users', 'qala-plugin-manager' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, notices will be hidden for users without qala_full_access capability.', 'qala-plugin-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize yes/no value
	 *
	 * Converts various truthy/falsy values to 'yes' or 'no'.
	 *
	 * @param mixed $value Input value.
	 * @return string Either 'yes' or 'no'
	 */
	public function sanitize_yes_no( $value ): string {
		// Convert to boolean, then to yes/no
		$truthy = [ '1', 'yes', 'on', true, 1 ];
		return in_array( $value, $truthy, true ) ? 'yes' : 'no';
	}

	/**
	 * Render settings page
	 *
	 * Main page output including:
	 * - Settings form
	 * - Notice log table
	 * - Allowlist management UI
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Check capability
		$this->require_capability( 'qala_full_access' );

		$enabled = get_option( 'qala_notices_enabled', 'yes' );
		$unique_notices = $this->logger->get_unique_notices();
		$allowlist_patterns = $this->allowlist->get_all_patterns();
		?>
		<div class="wrap qala-admin-page">
			<h1><?php esc_html_e( 'Hide Notices Settings', 'qala-plugin-manager' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'qala_notices' );
				do_settings_sections( 'qala-hide-notices' );
				submit_button();
				?>
			</form>

			<hr>

			<div class="qala-content-grid">
				<!-- Notice Log Table -->
				<div class="qala-section qala-notice-log">
					<h2><?php esc_html_e( 'Hidden Notices Log', 'qala-plugin-manager' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Recently hidden admin notices. Click "Add to Allowlist" to allow a specific notice.', 'qala-plugin-manager' ); ?>
					</p>

					<?php if ( empty( $unique_notices ) ) : ?>
						<p><em><?php esc_html_e( 'No notices have been hidden yet.', 'qala-plugin-manager' ); ?></em></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Callback Name', 'qala-plugin-manager' ); ?></th>
									<th><?php esc_html_e( 'Hook', 'qala-plugin-manager' ); ?></th>
									<th><?php esc_html_e( 'Last Seen', 'qala-plugin-manager' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'qala-plugin-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $unique_notices as $notice ) : ?>
									<tr>
										<td><code><?php echo esc_html( $notice['callback_name'] ); ?></code></td>
										<td><?php echo esc_html( $notice['hook_name'] ); ?></td>
										<td><?php echo esc_html( $notice['last_seen'] ); ?></td>
										<td>
											<button
												type="button"
												class="button qala-add-to-allowlist"
												data-pattern="<?php echo esc_attr( $notice['callback_name'] ); ?>"
												data-pattern-type="exact"
											>
												<?php esc_html_e( 'Add to Allowlist', 'qala-plugin-manager' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<!-- Allowlist Management -->
				<div class="qala-section qala-allowlist">
					<h2><?php esc_html_e( 'Allowlist Patterns', 'qala-plugin-manager' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Patterns in this list will NOT be hidden.', 'qala-plugin-manager' ); ?>
					</p>

					<!-- Add Pattern Form -->
					<div class="qala-add-pattern-form">
						<h3><?php esc_html_e( 'Add New Pattern', 'qala-plugin-manager' ); ?></h3>
						<?php wp_nonce_field( 'qala_add_pattern', 'qala_add_pattern_nonce' ); ?>
						<div class="qala-form-row">
							<input
								type="text"
								id="qala-new-pattern"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g., rocket_* or MyClass::method', 'qala-plugin-manager' ); ?>"
							/>
							<select id="qala-pattern-type">
								<option value="exact"><?php esc_html_e( 'Exact Match', 'qala-plugin-manager' ); ?></option>
								<option value="wildcard"><?php esc_html_e( 'Wildcard (*)', 'qala-plugin-manager' ); ?></option>
								<option value="regex"><?php esc_html_e( 'Regex', 'qala-plugin-manager' ); ?></option>
							</select>
							<button type="button" id="qala-add-pattern-btn" class="button button-primary">
								<?php esc_html_e( 'Add Pattern', 'qala-plugin-manager' ); ?>
							</button>
						</div>
						<div id="qala-add-pattern-message" class="qala-message" style="display: none;"></div>
					</div>

					<!-- Existing Patterns List -->
					<?php if ( empty( $allowlist_patterns ) ) : ?>
						<p><em><?php esc_html_e( 'No allowlist patterns configured.', 'qala-plugin-manager' ); ?></em></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped qala-patterns-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Pattern', 'qala-plugin-manager' ); ?></th>
									<th><?php esc_html_e( 'Type', 'qala-plugin-manager' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'qala-plugin-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $allowlist_patterns as $pattern ) : ?>
									<tr>
										<td><code><?php echo esc_html( $pattern['pattern_value'] ); ?></code></td>
										<td>
											<span class="qala-pattern-type qala-type-<?php echo esc_attr( $pattern['pattern_type'] ); ?>">
												<?php echo esc_html( ucfirst( $pattern['pattern_type'] ) ); ?>
											</span>
										</td>
										<td>
											<button
												type="button"
												class="button qala-remove-from-allowlist"
												data-pattern="<?php echo esc_attr( $pattern['pattern_value'] ); ?>"
											>
												<?php esc_html_e( 'Remove', 'qala-plugin-manager' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Localize script for admin page
	 *
	 * Provides AJAX data and translations for the admin page JavaScript.
	 * Note: The combined CSS/JS files are already loaded globally by other classes.
	 *
	 * Only localizes on the settings page to avoid unnecessary data on other pages.
	 *
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function localize_script( string $hook ): void {
		// Only localize on our settings page
		if ( $hook !== 'settings_page_qala-hide-notices' ) {
			return;
		}

		// Localize script with AJAX data for admin page functionality
		wp_localize_script(
			'qala-plugin-manager',
			'qalaAdminPage',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces' => [
					'addPattern' => wp_create_nonce( 'qala_add_pattern' ),
					'removePattern' => wp_create_nonce( 'qala_remove_pattern' ),
					'toggle' => wp_create_nonce( 'qala_toggle_notices' ),
				],
				'strings' => [
					'addSuccess' => __( 'Pattern added to allowlist successfully', 'qala-plugin-manager' ),
					'addError' => __( 'Failed to add pattern to allowlist', 'qala-plugin-manager' ),
					'removeSuccess' => __( 'Pattern removed from allowlist successfully', 'qala-plugin-manager' ),
					'removeError' => __( 'Failed to remove pattern from allowlist', 'qala-plugin-manager' ),
					'confirmRemove' => __( 'Are you sure you want to remove this pattern?', 'qala-plugin-manager' ),
					'emptyPattern' => __( 'Please enter a pattern', 'qala-plugin-manager' ),
				],
			]
		);
	}

	/**
	 * Handle AJAX request to add pattern to allowlist
	 *
	 * Validates:
	 * - Nonce
	 * - User capability
	 * - Pattern input
	 *
	 * Sanitizes all input before processing.
	 *
	 * @return void
	 */
	public function handle_add_pattern_ajax(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'qala_add_pattern', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'qala-plugin-manager' ) ] );
			return;
		}

		// Check capability
		if ( ! $this->user_has_capability( 'qala_full_access' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'qala-plugin-manager' ) ] );
			return;
		}

		// Get and sanitize input
		$pattern = isset( $_POST['pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern'] ) ) : '';
		$pattern_type = isset( $_POST['pattern_type'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern_type'] ) ) : 'exact';

		// Validate pattern
		if ( empty( $pattern ) ) {
			wp_send_json_error( [ 'message' => __( 'Pattern cannot be empty', 'qala-plugin-manager' ) ] );
			return;
		}

		// Validate pattern type
		$pattern_type = $this->validate_pattern_type( $pattern_type );

		// Add pattern to allowlist
		$result = $this->allowlist->add_pattern( $pattern, $pattern_type );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Pattern added to allowlist successfully', 'qala-plugin-manager' ),
				'pattern' => $pattern,
				'pattern_type' => $pattern_type,
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to add pattern to allowlist', 'qala-plugin-manager' ),
			] );
		}
	}

	/**
	 * Handle AJAX request to remove pattern from allowlist
	 *
	 * Validates:
	 * - Nonce
	 * - User capability
	 * - Pattern input
	 *
	 * @return void
	 */
	public function handle_remove_pattern_ajax(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'qala_remove_pattern', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'qala-plugin-manager' ) ] );
			return;
		}

		// Check capability
		if ( ! $this->user_has_capability( 'qala_full_access' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'qala-plugin-manager' ) ] );
			return;
		}

		// Get and sanitize input
		$pattern = isset( $_POST['pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern'] ) ) : '';

		// Validate pattern
		if ( empty( $pattern ) ) {
			wp_send_json_error( [ 'message' => __( 'Pattern cannot be empty', 'qala-plugin-manager' ) ] );
			return;
		}

		// Remove pattern from allowlist
		$result = $this->allowlist->remove_pattern_by_value( $pattern );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Pattern removed from allowlist successfully', 'qala-plugin-manager' ),
				'pattern' => $pattern,
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to remove pattern from allowlist', 'qala-plugin-manager' ),
			] );
		}
	}

	/**
	 * Handle AJAX request to toggle global notice hiding
	 *
	 * Updates the qala_notices_enabled option.
	 *
	 * @return void
	 */
	public function handle_toggle_ajax(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'qala_toggle_notices', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'qala-plugin-manager' ) ] );
			return;
		}

		// Check capability
		if ( ! $this->user_has_capability( 'qala_full_access' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'qala-plugin-manager' ) ] );
			return;
		}

		// Get and sanitize input
		$enabled = isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : 'no';
		$enabled = $this->sanitize_yes_no( $enabled );

		// Update option
		$result = update_option( 'qala_notices_enabled', $enabled );

		$message = ( $enabled === 'yes' )
			? __( 'Notice hiding enabled', 'qala-plugin-manager' )
			: __( 'Notice hiding disabled', 'qala-plugin-manager' );

		wp_send_json_success( [
			'message' => $message,
			'enabled' => $enabled,
		] );
	}

	/**
	 * Validate pattern type
	 *
	 * Ensures pattern type is one of: exact, wildcard, regex.
	 * Defaults to 'exact' if invalid.
	 *
	 * @param mixed $type Pattern type input.
	 * @return string Valid pattern type
	 */
	public function validate_pattern_type( $type ): string {
		$valid_types = [ 'exact', 'wildcard', 'regex' ];
		return in_array( $type, $valid_types, true ) ? $type : 'exact';
	}
}
