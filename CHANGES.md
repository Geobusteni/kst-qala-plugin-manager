# Changes from Original Plugin

This document provides a detailed comparison between the original Qala Plugin Manager and the current version with the Hide Notices feature and modern build system.

## Table of Contents

- [Overview](#overview)
- [File Structure Comparison](#file-structure-comparison)
- [New Features](#new-features)
- [Build System](#build-system)
- [Modified Files](#modified-files)
- [Testing](#testing)

## Overview

The current version extends the original plugin management functionality with a comprehensive admin notice control system and modernized build tooling.

### Original Plugin
- Basic plugin management
- Plugin table customization
- Simple activation/deactivation tracking

### Current Version
- All original features preserved
- **NEW**: Hide Notices feature with allowlist system
- **NEW**: Modern webpack-based build system
- **NEW**: Comprehensive documentation
- **NEW**: Enhanced testing infrastructure

## File Structure Comparison

### Original Structure
```
qala-plugin-manager/
├── index.php
├── composer.json
├── includes/
│   └── classes/
│       ├── Plugin.php
│       ├── PluginFactory.php
│       ├── PluginTable.php
│       ├── PluginConfigurations.php
│       └── Plugins/
│           └── WooCommerce.php
├── dependencies/
│   └── vendor/
└── languages/
```

### Current Structure
```
qala-plugin-manager/
├── index.php                          # Modified (version, description)
├── composer.json                      # Modified (dev dependencies)
├── package.json                       # NEW - npm configuration
├── webpack.config.js                  # NEW - Webpack config
├── postcss.config.js                  # NEW - PostCSS config
├── package-plugin.sh                  # NEW - Release packaging
├── README.md                          # NEW - Comprehensive docs
├── CONTRIBUTING.md                    # NEW - Developer guide
├── CHANGELOG.md                       # NEW - Version history
├── CHANGES.md                         # NEW - This file
├── includes/
│   └── classes/
│       ├── Plugin.php                 # Original (preserved)
│       ├── PluginFactory.php          # Original (preserved)
│       ├── PluginTable.php            # Original (preserved)
│       ├── PluginConfigurations.php   # Original (preserved)
│       ├── ServiceProvider.php        # Modified (adds notice management)
│       ├── Interfaces/                # NEW
│       │   └── WithHooksInterface.php
│       ├── NoticeManagement/          # NEW - Entire directory
│       │   ├── AdminBarToggle.php
│       │   ├── AdminPage.php
│       │   ├── AllowlistManager.php
│       │   ├── BodyClassManager.php
│       │   ├── DatabaseMigration.php
│       │   ├── NoticeFilter.php
│       │   ├── NoticeIdentifier.php
│       │   ├── NoticeLogger.php
│       │   ├── NoticeLogTable.php
│       │   ├── SiteHealthHider.php
│       │   └── Traits/
│       │       └── CapabilityChecker.php
│       └── Plugins/
│           └── WooCommerce.php        # Original (preserved)
├── assets/                            # NEW - Entire directory
│   ├── src/                          # Source files for webpack
│   │   ├── js/
│   │   │   └── index.js              # Entry point
│   │   └── css/
│   │       ├── qala-plugin-manager.css
│   │       ├── admin-page.css
│   │       ├── admin-bar-toggle.css
│   │       └── notice-hider.css
│   ├── js/                           # Legacy source
│   │   └── qala-plugin-manager.js
│   ├── css/                          # Legacy source
│   └── dist/                         # Webpack output
│       ├── qala-plugin-manager.css
│       ├── qala-plugin-manager-rtl.css
│       └── js/
│           ├── qala-plugin-manager.js
│           └── qala-plugin-manager.asset.php
├── tests/                             # NEW - PHPUnit tests
│   └── Unit/
│       ├── NoticeManagement/
│       └── Traits/
├── dependencies/
│   ├── vendor/                        # Modified (new dev packages)
│   └── grumphp/                       # NEW - Code quality
├── languages/
└── node_modules/                      # NEW - npm packages
```

## New Features

### 1. Hide Notices Feature

**Purpose**: Comprehensive admin notice management with "nuclear approach" - hide all notices by default, show only what you allow.

**Components**:
- `NoticeFilter`: Removes notice hooks before execution
- `AllowlistManager`: Manages patterns for allowed notices
- `NoticeLogger`: Logs hidden notices to database
- `AdminPage`: Settings page for management
- `AdminBarToggle`: Quick toggle in admin bar
- `SiteHealthHider`: Hides Site Health from non-privileged users
- `BodyClassManager`: Adds body classes for CSS/JS detection

**Capabilities**:
- `qala_full_access`: Custom capability for full feature access

**Database Tables**:
- `wp_qala_hidden_notices_log`: Notice logging
- `wp_qala_notice_allowlist`: Pattern management

**Pattern Types**:
- Exact: `callback_name`
- Wildcard: `plugin_*`
- Regex: `/^pattern$/i`

### 2. Modern Build System

**Purpose**: Industry-standard WordPress build tooling

**Technology**:
- **@wordpress/scripts**: Official WordPress build package
- **Webpack 5**: Module bundling
- **PostCSS**: CSS processing
- **Babel**: JavaScript transpilation

**Benefits**:
- Automatic minification
- RTL stylesheet generation
- Source maps for development
- Tree shaking for smaller bundles
- Hot module replacement (`npm start`)

### 3. Documentation

**Files**:
- `README.md`: User-focused documentation
- `CONTRIBUTING.md`: Developer guide
- `CHANGELOG.md`: Version history
- `CHANGES.md`: This comparison document

### 4. Testing Infrastructure

**Test Types**:
- Unit tests (PHPUnit + Brain Monkey)
- Static analysis (PHPStan level 8)
- Code style (PHPCS with WordPress + VIP standards)

**Commands**:
- `composer test`: Run all tests
- `composer test:phpstan`: Static analysis
- `composer style:check`: Code style

## Build System

### v1.x (Old System)
```bash
# Build assets
./build-assets.sh

# Output
assets/dist/css/admin-page.css
assets/dist/css/admin-bar-toggle.css
assets/dist/js/admin-page.js
assets/dist/js/admin-bar-toggle.js
```

**Method**: Bash script with sed for minification

### v2.0 (New System)
```bash
# Install dependencies
npm install

# Build for production
npm run build

# Development with hot reload
npm start

# Output
assets/dist/qala-plugin-manager.css (bundled)
assets/dist/qala-plugin-manager-rtl.css (auto-generated)
assets/dist/js/qala-plugin-manager.js (bundled & minified)
assets/dist/js/qala-plugin-manager.asset.php (dependencies)
```

**Method**: Webpack + PostCSS via @wordpress/scripts

## Modified Files

### index.php
**Changes**:
- Updated version number
- Updated description to mention notice control

**Original**:
```php
Version: 1.0.1
Description: A plugin that handles other plugins...
```

**Current**:
```php
Version: 2.0.0
Description: Plugin management and comprehensive admin notice control...
```

### ServiceProvider.php
**Changes**:
- Added NoticeManagement component initialization
- Registers all new notice-related classes

**New Registrations**:
- DatabaseMigration
- NoticeFilter
- NoticeLogger
- AllowlistManager
- AdminPage
- AdminBarToggle
- BodyClassManager
- SiteHealthHider

## Testing

### Test Location
All tests are in the `tests/` directory:

```
tests/
├── Unit/
│   ├── NoticeManagement/
│   │   ├── AdminBarToggleTest.php
│   │   ├── AdminPageTest.php
│   │   ├── AllowlistManagerTest.php
│   │   ├── BodyClassManagerTest.php
│   │   ├── DatabaseMigrationTest.php
│   │   ├── NoticeFilterTest.php
│   │   ├── NoticeIdentifierTest.php
│   │   ├── NoticeLoggerTest.php
│   │   └── SiteHealthHiderTest.php
│   └── Traits/
│       └── CapabilityCheckerTest.php
└── bootstrap.php
```

### Running Tests

**All Tests**:
```bash
composer test
```

**Unit Tests Only**:
```bash
composer test:unit
```

**Static Analysis**:
```bash
composer test:phpstan
```

**Code Style**:
```bash
composer style:check
composer style:fix  # Auto-fix
```

### Test Coverage
- PHPUnit tests for all notice management classes
- PHPStan level 8 compliance
- WordPress Extra + VIP coding standards
- All new code fully tested

## Migration Guide

### For End Users
No changes required - update the plugin and it works.

### For Developers

**Updating from v1.x**:

1. **Pull latest code**:
   ```bash
   git pull origin main
   ```

2. **Install npm dependencies**:
   ```bash
   npm install
   ```

3. **Build assets**:
   ```bash
   npm run build
   ```

4. **Development workflow**:
   ```bash
   # Edit source files in assets/src/
   npm start  # Auto-rebuild on save

   # Or manual build
   npm run build
   ```

5. **Create release**:
   ```bash
   npm run build
   ./package-plugin.sh
   ```

### Breaking Changes

- **Asset paths**: CSS/JS now bundled into single files
- **Build command**: Use `npm run build` instead of `./build-assets.sh`
- **Package command**: Use `./package-plugin.sh` instead of manual zip

### Non-Breaking

- All original plugin management features preserved
- Database structure unchanged for original features
- API compatibility maintained

## Summary

### Added (New)
- Complete Hide Notices feature
- Modern webpack build system
- Comprehensive documentation
- Test infrastructure
- Custom capability system
- Two new database tables

### Modified
- `index.php`: Version and description
- `ServiceProvider.php`: Adds notice management
- `composer.json`: Dev dependencies
- Asset loading: Now uses bundled files

### Preserved (Unchanged)
- Original plugin management features
- PluginTable customization
- PluginConfigurations
- WooCommerce integration
- Basic plugin structure

---

**Version**: 2.0.0
**Last Updated**: 2025-10-27
**Maintained By**: Angry Creative AB
