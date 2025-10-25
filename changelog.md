# Changelog
## 1.0.1
* Ensure the plugin does not run in WP CLI mode since that can break functionality with WP CLI if running with `--skip-plugins`.

## 1.0.0
* Some tweaks to the logic of showing plugin table information.
* Added plugin specific logics
  * Added logic to deactivate the "connect store" notice from WooCommerce with the `woothemes_updater_notice` filter.

## 0.2.1
* Bugfix: filter out plugins that don't exist before we try (de)activating them.

## 0.2.0
* Adds new column "Slug" to the plugin table when the user is created through our AD plugin which shows the plugins slug or textdomain. This helps us easier determine plugin slug for composer and WP CLI.
* Adds new column "Activated on sites" to the plugin table which shows which sites in the network the plugin is activated on. Only appears on network setups.

## 0.1.0
* Ensures that the [Code snippets](https://wordpress.org/plugins/code-snippets/) plugin is never active live. Because this is too dangerous to keep active live. It can however be used on staging for development and testing and then moving that code over to the appropriate location in the codebase before it goes live.
