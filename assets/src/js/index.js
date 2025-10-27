/**
 * Qala Plugin Manager - Main JavaScript Entry Point
 *
 * This file bundles all JavaScript functionality for the plugin.
 * Built with @wordpress/scripts and webpack.
 *
 * @package QalaPluginManager
 */

// Import all CSS files (webpack will bundle and extract them)
import '../css/qala-plugin-manager.css';
import '../css/admin-page.css';
import '../css/admin-bar-toggle.css';
import '../css/notice-hider.css';

// Import the main JavaScript functionality
// The qala-plugin-manager.js file contains all the combined logic
require('../../js/qala-plugin-manager.js');
