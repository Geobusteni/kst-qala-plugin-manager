<?php
/**
 * This handles which classes we want to instantiate automatically on running the plugin.
 * Usually that means classes that have some form of logic in its constructor.
 *
 * We do not need to add classes that should just be _available_ here, they are available anyway through the autoloader.
 *
 * @package QalaPluginManager
 */

namespace QalaPluginManager;

use Inpsyde\MultilingualPress\Installation\PluginDeactivator;

/**
 * Class ServiceProvider
 *
 * @package QalaPluginManager
 */
class ServiceProvider {
	/**
	 * Add the classes you'd like to bootstrap here.
	 *
	 * Here we can, for example, register the classes we use
	 * to load our translations and to enqueue the plugin assets.

	 * If you feel unsure about the Class_Name::class syntax
	 * you can read a little bit about it here:
	 *
	 * @link https://www.php.net/manual/en/migration55.new-features.php#migration55.new-features.class-name
	 *
	 * If you need CSS and JS you can uncomment the
	 *
	 * Enqueue_Assets::class
	 *
	 * line. Don't forget to run `npm install` too!
	 *
	 * @var array
	 */
	protected $classes = [
		TranslationsLoader::class,
		PluginActivation::class,
		PluginDeactivation::class,
		PluginTable::class,
		Plugins\WooCommerce::class,
	];

	/**
	 * Notice Management component instances (for dependency injection)
	 *
	 * @var array
	 */
	private $notice_components = [];

	/**
	 * get_registered_classes
	 *
	 * @return array
	 */
	public function get_registered_classes() : array {
		return $this->classes;
	}

	/**
	 * Get Notice Management components with proper dependency injection
	 *
	 * This method creates all Notice Management components in the correct order,
	 * handling their dependencies. Called by Plugin::register_classes().
	 *
	 * Initialization Order:
	 * 1. DatabaseMigration - Run migrations first
	 * 2. NoticeIdentifier - No dependencies
	 * 3. NoticeLogger - No dependencies
	 * 4. AllowlistManager - No dependencies
	 * 5. BodyClassManager - No dependencies (adds CSS classes to body tag)
	 * 6. NoticeFilter - Depends on AllowlistManager, NoticeLogger, NoticeIdentifier
	 * 7. AdminPage - Depends on AllowlistManager, NoticeLogger
	 * 8. AdminBarToggle - No dependencies
	 * 9. SiteHealthHider - No dependencies
	 *
	 * @return array Array of instantiated Notice Management components
	 */
	public function get_notice_management_components() : array {
		if ( ! empty( $this->notice_components ) ) {
			return $this->notice_components;
		}

		// Step 1: Run database migrations FIRST
		$migration = new NoticeManagement\DatabaseMigration();
		$migration->run_migrations();

		// Step 2: Create components with no dependencies
		$identifier = new NoticeManagement\NoticeIdentifier();
		$logger = new NoticeManagement\NoticeLogger();
		$allowlist = new NoticeManagement\AllowlistManager();

		// Step 3: Create components with dependencies
		$body_class = new NoticeManagement\BodyClassManager( $allowlist );
		$filter = new NoticeManagement\NoticeFilter( $allowlist, $logger, $identifier );
		$admin_page = new NoticeManagement\AdminPage( $allowlist, $logger );
		$admin_bar = new NoticeManagement\AdminBarToggle();
		$site_health = new NoticeManagement\SiteHealthHider();

		// Store for reuse
		$this->notice_components = [
			$body_class,
			$filter,
			$admin_page,
			$admin_bar,
			$site_health,
		];

		return $this->notice_components;
	}
}
