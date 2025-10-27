/**
 * Webpack configuration for Qala Plugin Manager
 *
 * Extends @wordpress/scripts default webpack config
 * to bundle all JS and CSS into single files.
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'qala-plugin-manager': path.resolve(process.cwd(), 'assets/src/js', 'index.js'),
	},
	output: {
		path: path.resolve(process.cwd(), 'assets/dist'),
		filename: 'js/[name].js',
	},
};
