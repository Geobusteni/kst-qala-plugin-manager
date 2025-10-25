<?php
/**
 * Class that handles WooCommerce specific functionality.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager\Plugins;

/**
 * Class WooCommerce
 *
 * @package QalaPluginManager
 */
class WooCommerce {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'remove_updater_notice' ], 15 );
	}

	/**
	 * Remove the WooCommerce Updater notice.
	 *
	 * @return void
	 */
	public function remove_updater_notice(): void {
		remove_action( 'admin_notices', 'woothemes_updater_notice' );
	}
}
