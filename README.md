# Qala Plugin Manager

A powerful WordPress must-use (MU) plugin for plugin management and admin notice control.

## Features

### Core Plugin Management
- Plugin activation/deactivation management
- Enhanced plugin table with slug visibility for authorized users
- WooCommerce-specific notice management
- Multisite support with per-site activation tracking

### Hide Notices Feature (New in 1.0.0)
A comprehensive admin notice management system that gives you full control over WordPress admin notices.

#### Nuclear Approach
- **Hides ALL admin notices by default** - Clean admin interface
- **Allowlist system** - Show only the notices you want to see
- **Pattern matching** - Exact, wildcard, and regex pattern support

#### Key Features
1. **Notice Logging**
   - All hidden notices are logged to the database
   - View what's being hidden in a sortable, searchable table
   - One-click add to allowlist from logs

2. **Allowlist Management**
   - Add patterns to show specific notices
   - Three pattern types:
     - **Exact**: `'WooCommerce::show_notice'` - matches exactly
     - **Wildcard**: `'WooCommerce*'` - matches any WooCommerce notice
     - **Regex**: `'/^WooCommerce.*$/'` - full regex support
   - Enable/disable patterns without deleting them
   - Per-pattern management with visual indicators

3. **Admin Settings Page**
   - Located at **Settings > Hide Notices**
   - Notice log table with:
     - Callback names, hooks, priorities
     - Last seen timestamps
     - Quick actions (add to allowlist)
   - Allowlist pattern management
   - Global enable/disable toggle
   - WordPress-native, responsive design

4. **Admin Bar Quick Toggle**
   - Quick access from admin bar
   - Shows current state (Notices: On/Off)
   - Per-user preference
   - AJAX-powered smooth toggle
   - Visual state indicators (green=on, red=off)

5. **Site Health Hiding**
   - Hides Site Health page from non-privileged users
   - Removes Site Health dashboard widget
   - Redirects direct access attempts
   - Only users with `qala_full_access` can access

6. **Capability-Based Access**
   - Only users with `qala_full_access` capability can:
     - Access the settings page
     - See the admin bar toggle
     - View Site Health page
     - Manage allowlist patterns
   - All other users see a clean, notice-free admin

7. **Multisite Support**
   - Settings synchronized across network
   - Network admin and site admin support
   - Per-site notice filtering

8. **Notice Type Coverage**
   - Legacy WordPress notices (admin_notices, network_admin_notices, etc.)
   - Modern WordPress 6.4+ notices (wp_admin_notice function)
   - AJAX notices (category creation, etc.)
   - Plugin-specific implementations (WooCommerce, Yoast, WP Rocket)

## Requirements

- **PHP:** 7.3 or higher
- **WordPress:** 6.4 or higher
- **Server:** Must-use plugins support

## Installation

### As Must-Use Plugin (Recommended)

1. Download the plugin files
2. Upload to `/wp-content/mu-plugins/qala-plugin-manager/`
3. Create a loader file: `/wp-content/mu-plugins/qala-plugin-manager.php`

```php
<?php
/**
 * Plugin Name: Qala Plugin Manager
 * Description: Plugin management and admin notice control for Qala sites
 * Version: 1.0.0
 * Author: Your Name
 */

require_once WPMU_PLUGIN_DIR . '/qala-plugin-manager/index.php';
```

4. The plugin activates automatically on next page load

### Database Tables

The plugin creates two database tables automatically:

- `{prefix}qala_hidden_notices_log` - Stores hidden notice logs
- `{prefix}qala_notice_allowlist` - Stores allowlist patterns

## Usage

### Initial Setup

1. Navigate to **Settings > Hide Notices**
2. By default, ALL notices are hidden
3. Check the notice log to see what's being hidden
4. Add patterns to allowlist to show specific notices

### Adding Allowlist Patterns

**From the Settings Page:**

1. Go to **Settings > Hide Notices**
2. Find the "Add New Pattern" section
3. Enter your pattern
4. Select pattern type (Exact, Wildcard, or Regex)
5. Click "Add Pattern"

**From the Notice Log:**

1. Go to **Settings > Hide Notices**
2. Find a hidden notice in the log table
3. Click "Add to Allowlist"
4. Pattern is automatically created

### Pattern Examples

**Exact Match:**
```
WooCommerce::show_update_notice
```
Shows only this specific WooCommerce notice.

**Wildcard Match:**
```
WooCommerce*
```
Shows all WooCommerce notices.

```
*update*
```
Shows any notice with "update" in the name.

**Regex Match:**
```
/^WooCommerce::(update|payment).*$/
```
Shows WooCommerce update and payment notices.

```
/.*_admin_notice$/
```
Shows all notices ending with "_admin_notice".

### Quick Toggle

Use the admin bar toggle for quick on/off:

1. Look for **"Notices: On"** or **"Notices: Off"** in the admin bar
2. Click to toggle notice visibility
3. Page reloads with new setting
4. Setting is per-user (persists across sessions)

### Global Toggle

Disable notice hiding entirely:

1. Go to **Settings > Hide Notices**
2. Uncheck "Hide Admin Notices Globally"
3. Click "Save Changes"
4. All notices will be visible (allowlist is ignored)

## User Capabilities

### qala_full_access

Users with this capability have full access:

- ✅ See all notices (can toggle per-user)
- ✅ Access Settings > Hide Notices page
- ✅ Manage allowlist patterns
- ✅ View notice logs
- ✅ Use admin bar toggle
- ✅ Access Site Health page
- ✅ See Site Health dashboard widget

### Without qala_full_access

Users without this capability:

- ❌ Cannot see any admin notices
- ❌ Cannot access settings page
- ❌ Cannot manage allowlist
- ❌ Cannot toggle notice visibility
- ❌ Cannot access Site Health page
- ❌ Cannot see Site Health widget
- ❌ Redirected to Dashboard if trying direct access

## Technical Details

### Architecture

**Component-Based Design:**
- `NoticeFilter` - Core component that removes notice hooks
- `NoticeIdentifier` - Generates unique hashes for notices
- `AllowlistManager` - Manages pattern exceptions
- `NoticeLogger` - Logs hidden notices to database
- `AdminPage` - Settings page and AJAX handlers
- `AdminBarToggle` - Quick toggle functionality
- `SiteHealthHider` - Site Health access control
- `DatabaseMigration` - Database schema management

### Hook Execution

The plugin hooks into `in_admin_header` at priority 100000 (latest possible timing) to remove unwanted notice callbacks before they execute.

**Notice Hooks Handled:**
- `admin_notices`
- `network_admin_notices`
- `user_admin_notices`
- `all_admin_notices`

### Performance

- **Hash Generation:** ~0.22ms per notice
- **Pattern Matching:** <1ms per check
- **Caching:** WordPress transients for allowlist patterns
- **Database:** Optimized indexes for fast queries
- **Memory:** Minimal footprint (<2KB per instance)

### Security

- ✅ Nonce verification on all forms/AJAX
- ✅ Capability checking (qala_full_access)
- ✅ Input sanitization
- ✅ Output escaping
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS prevention
- ✅ CSRF protection

## Troubleshooting

### Notices Still Showing

1. Check if global toggle is enabled (Settings > Hide Notices)
2. Check if pattern is in allowlist
3. Check if user has `qala_full_access` capability
4. Check notice log to see if notice is being captured
5. Try different pattern type (wildcard vs regex)

### Allowlist Not Working

1. Verify pattern syntax (test with exact match first)
2. Check pattern is active (green checkmark)
3. Check callback name in notice log (must match exactly)
4. For regex, verify pattern is valid (`preg_match()` compatible)

### Cannot Access Settings Page

1. Verify user has `qala_full_access` capability
2. Check if plugin is activated (MU plugins auto-activate)
3. Check for JavaScript errors in browser console
4. Verify database tables exist

### Site Health Redirect Loop

1. Ensure user has `qala_full_access` capability to access
2. Check for conflicting plugins
3. Verify site health is not accessed via AJAX

### Database Tables Not Created

1. Check database user has CREATE TABLE permission
2. Look for errors in debug.log
3. Manually run migration: Access settings page (triggers migration)
4. Check WordPress version (requires 6.4+)

## Changelog

### 1.0.0 (2025-10-25)

**New Features:**
- Nuclear approach: Hide all notices by default
- Allowlist pattern system (exact, wildcard, regex)
- Notice logging with deduplication
- Admin settings page with notice management
- Admin bar quick toggle
- Site Health hiding for non-privileged users
- Multisite support
- Database-backed logging and allowlist
- WordPress 6.4+ notice system support
- AJAX notice handling

**Technical:**
- Test-Driven Development (250+ unit tests)
- WordPress Extra + VIP coding standards
- PHPStan level 8 compliance
- Comprehensive security measures
- GitHub workflow automation
- Performance optimization

## Development

### Running Tests

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run PHPStan
composer test:phpstan

# Run PHPCS
composer style:check

# Fix code style
composer style:fix
```

### Building Assets

```bash
# Install npm dependencies
npm install

# Build for production
npm run build

# Build for development (with source maps)
npm run dev

# Watch for changes
npm run watch
```

### Code Standards

- WordPress Extra coding standards
- WordPress VIP Go standards
- No Yoda conditions
- Short array syntax `[]`
- PSR-4 autoloading
- Comprehensive PHPDoc blocks
- Type hints (PHP 7.3+)

## Support

For issues, questions, or contributions:

1. Check the troubleshooting section above
2. Review the documentation in `/CLAUDE.md`
3. Check the technical architecture in `/FINAL_TECHNICAL_ARCHITECTURE.md`
4. Submit issues on GitHub (repository link)

## License

Proprietary - All rights reserved

## Credits

**Development:**
- Built with Test-Driven Development (TDD)
- Follows WordPress and VIP coding standards
- Uses Brain Monkey for testing
- Comprehensive test coverage (250+ tests)

**Research:**
- WordPress core notice system analysis
- Reference implementations studied:
  - unhook-admin-notices plugin
  - hide-wp-admin-notifications plugin
  - disable-admin-notices plugin

---

**Version:** 1.0.0
**Last Updated:** 2025-10-25
**WordPress Tested:** 6.4 - 6.8+
**PHP Tested:** 7.3 - 8.3
