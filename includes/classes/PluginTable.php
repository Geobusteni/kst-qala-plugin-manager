<?php
/**
 * Class that handles the plugin tables.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

/**
 * Class PluginTable
 *
 * @package QalaPluginManager
 */
class PluginTable {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'manage_plugins_columns', [ $this, 'add_sites_column' ] );
		add_action( 'manage_plugins_custom_column', [ $this, 'populate_sites_column' ], 20, 3 );
		add_action( 'manage_plugins_custom_column', [ $this, 'populate_slug_column' ], 20, 3 );
		add_action( 'activated_plugin', [ $this, 'invalidate_plugin_cache' ] );
		add_action( 'deactivated_plugin', [ $this, 'invalidate_plugin_cache' ] );
	}

	/**
	 * Add a custom column to the plugins table in the WP admin dashboard.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_sites_column( $columns ): array {
		if ( is_multisite() && is_super_admin() ) {
			$columns['sites_activated'] = __( 'Activated on sites', 'qala-plugin-manager' );
		}

		if ( current_user_can( 'qala_full_access' ) ) {
			$columns['plugin_slug'] = __( 'Slug', 'qala-plugin-manager' );
		}

		return $columns;
	}

	/**
	 * Populate the custom column with data.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param string $plugin_file The path to the plugin file.
	 * @param array  $plugin_data An array of plugin data.
	 *
	 * @return void|string
	 */
	public function populate_sites_column( $column_name, $plugin_file, $plugin_data ) {
		if ( $column_name !== 'sites_activated' ) {
			return;
		}

		$cache_key       = 'plugin_sites_activated_' . md5( $plugin_file );
		$activated_sites = get_site_transient( $cache_key );

		if ( $activated_sites === false ) {
			$sites           = get_sites();
			$activated_sites = [];

			foreach ( $sites as $site ) {
				$site_id = $site->blog_id;
				switch_to_blog( absint( $site_id ) );

				if ( is_plugin_active( $plugin_file ) ) {
					$activated_sites[] = sprintf( '<li><a href="%2$s" style="text-wrap:nowrap;" target="_blank">%1$s</a></li>', get_bloginfo( 'name' ), admin_url( 'plugins.php' ) );
				}

				restore_current_blog();
			}

			set_site_transient( $cache_key, $activated_sites, 12 * HOUR_IN_SECONDS );
		}

		if ( ! empty( $activated_sites ) ) {
			printf( '<ul style="margin:0;">%1$s</ul> ', implode( '', $activated_sites ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo esc_html__( 'None', 'qala-plugin-manager' );
		}
	}

	/**
	 * Populate the custom column with data.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param string $plugin_file The path to the plugin file.
	 * @param array  $plugin_data An array of plugin data.
	 *
	 * @return void|string
	 */
	public function populate_slug_column( $column_name, $plugin_file, $plugin_data ) {
		if ( $column_name !== 'plugin_slug' ) {
			return;
		}

		$slug = $plugin_data['slug'] ?? 'N/A';
		printf( '<pre><code>%1$s</code></pre>', esc_html( $slug ) );

		$textdomain = $plugin_data['TextDomain'] ?? false;
		if ( $slug === 'N/A' && $textdomain ) {
			printf( '<pre>Textdomain:<br><code>%1$s</code></pre>', esc_html( $textdomain ) );
		}
	}

	/**
	 * Invalidate the cache when a plugin is activated or deactivated.
	 *
	 * @param string $plugin The path to the plugin file.
	 *
	 * @return void
	 */
	public function invalidate_plugin_cache( $plugin ): void {
		if ( ! is_multisite() ) {
			return;
		}

		$cache_key = 'plugin_sites_activated_' . md5( $plugin );
		delete_site_transient( $cache_key );
	}
}
