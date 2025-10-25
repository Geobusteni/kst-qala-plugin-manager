<?php
/**
 * Interface that handles the hooks of the plugin.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager\Interfaces;

/**
 * If you implement this interface, the init() method will be
 * invoked when correctly registering classes via the
 * Service_Provider class.
 *
 * Use it like so:
 *
 * namespace QalaPluginManager;
 *
 * use QalaPluginManager\Interfaces\WithHooksInterface;
 *
 * class MyClass implements WithHooksInterface {
 *     public function init() {
 *         add_action( 'init', [ $this, 'my_action' ] );
 *     }
 *
 *     public function my_action() {
 *         // sweet.
 *     }
 * }
 */
interface WithHooksInterface {
	/**
	 * Initialize the hooks when the class is registered.
	 *
	 * @return void
	 */
	public function init() : void;
}
