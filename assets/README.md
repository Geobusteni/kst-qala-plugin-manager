# Qala Plugin Manager - Assets

This directory contains the CSS and JavaScript assets for the Qala Plugin Manager.

## Directory Structure

```
assets/
├── css/                    # Source CSS files
│   ├── admin-page.css
│   └── admin-bar-toggle.css
├── js/                     # Source JS files
│   ├── admin-page.js
│   └── admin-bar-toggle.js
└── dist/                   # Production-ready files (minified, versioned)
    ├── css/
    │   ├── admin-page.css
    │   └── admin-bar-toggle.css
    └── js/
        ├── admin-page.js
        └── admin-bar-toggle.js
```

## Building Assets

To build production assets, run the build script from the plugin root directory:

```bash
cd /root/gits/kst-qala-plugin-manager/sources/qala-manager/qala-plugin-manager
./build-assets.sh
```

### What the Build Script Does

1. **Creates dist directories** - `assets/dist/css/` and `assets/dist/js/`
2. **Minifies CSS files** - Removes comments and excess whitespace
3. **Minifies JS files** - Removes comments and excess whitespace
4. **Adds version headers** - Each file includes version and build timestamp
5. **Reports file sizes** - Shows before/after comparison

### Build Output

The build process generates production-ready files in the `dist/` directory:

- **Size reduction**: ~12% smaller than source files (22.8KB → 20.1KB)
- **Cache busting**: Files use modification time for versioning
- **Version headers**: Each file includes build metadata

## Asset Loading

Assets are loaded by PHP classes using WordPress enqueue functions:

- **AdminPage.php** - Loads `assets/dist/css/admin-page.css` and `assets/dist/js/admin-page.js`
- **AdminBarToggle.php** - Loads `assets/dist/css/admin-bar-toggle.css` and `assets/dist/js/admin-bar-toggle.js`

Both classes use `filemtime()` for automatic cache busting when files are modified.

## Development Workflow

1. **Edit source files** in `assets/css/` or `assets/js/`
2. **Run build script** to generate dist files
3. **Test in WordPress** - PHP classes automatically load from dist/
4. **Commit both source and dist files**

## File Descriptions

### CSS Files

- **admin-page.css** - Styles for the Hide Notices settings page
  - Grid layout for sections
  - Table styling for notice log and allowlist
  - Form controls and buttons
  - Responsive design
  - Loading states and animations

- **admin-bar-toggle.css** - Styles for the admin bar toggle
  - Toggle button styling
  - State indicators (on/off colors)
  - Loading animation
  - Hover effects
  - Responsive design

### JavaScript Files

- **admin-page.js** - AJAX interactions for settings page
  - Add pattern to allowlist
  - Remove pattern from allowlist
  - Add from notice log
  - Error handling and success messages

- **admin-bar-toggle.js** - AJAX toggle for admin bar
  - Click handler for toggle
  - Loading state management
  - Success/error feedback
  - Page reload after toggle

## Cache Busting

Assets use file modification time (`filemtime()`) for versioning:

```php
$css_version = file_exists($css_file) ? filemtime($css_file) : '1.0.0';
wp_enqueue_style('handle', $url, [], $css_version);
```

This ensures browsers always load the latest version after rebuilding.

## Production Deployment

For production deployment:

1. **Run build script** before deploying
2. **Include dist/ directory** in your deployment
3. **Verify files exist** in `assets/dist/css/` and `assets/dist/js/`
4. **Check file permissions** - Files should be readable by web server

The plugin is configured to load from `dist/` by default, ensuring production-optimized assets are always used.
