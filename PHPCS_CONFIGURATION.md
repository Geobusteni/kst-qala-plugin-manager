# PHPCS Configuration for Qala Plugin Manager

This document explains the PHP_CodeSniffer configuration for the Qala Plugin Manager, including the WordPress VIP standards integration.

## Table of Contents

- [Overview](#overview)
- [Included Rulesets](#included-rulesets)
- [VIP Rules Included](#vip-rules-included)
- [VIP Rules Excluded and Why](#vip-rules-excluded-and-why)
- [Alignment with Existing Code](#alignment-with-existing-code)
- [Common Violations to Watch For](#common-violations-to-watch-for)
- [Running Checks Locally](#running-checks-locally)
- [Integration with Development Workflow](#integration-with-development-workflow)

## Overview

The Qala Plugin Manager uses a comprehensive PHPCS configuration that combines:

- **WordPress-Extra**: Core WordPress coding standards
- **WordPress-Docs**: Documentation standards
- **WordPress-VIP-Go**: WordPress VIP platform best practices

This configuration is designed to maintain high code quality while supporting modern PHP practices like PSR-4 autoloading and contemporary syntax preferences.

## Included Rulesets

### 1. WordPress-Extra

The base WordPress coding standards that cover:
- Code formatting and indentation
- Naming conventions
- PHP best practices
- Security measures
- WordPress-specific patterns

### 2. WordPress-Docs

Documentation standards including:
- File headers
- Class documentation
- Function/method documentation
- Inline comments

### 3. WordPress-VIP-Go

Enterprise-level WordPress standards focusing on:
- Performance optimization
- Security hardening
- Scalability best practices
- Caching strategies
- Database query optimization
- Input validation and sanitization

## VIP Rules Included

The following WordPress VIP rules are **actively enforced**:

### Security Rules (HIGH Priority)

- **WordPress.Security.EscapeOutput**: Ensures all output is properly escaped
- **WordPress.Security.NonceVerification**: Requires nonce verification for form submissions
- **WordPress.Security.ValidatedSanitizedInput**: Validates and sanitizes user input (selectively enforced)
- **WordPress.Security.PluginMenuSlug**: Prevents menu slug vulnerabilities
- **WordPress.Security.SafeRedirect**: Ensures redirects are to safe URLs

### Performance Rules (HIGH Priority)

- **WordPress.VIP.CronInterval**: Prevents cron intervals shorter than 15 minutes
- **WordPress.VIP.SlowQuery**: Detects potentially slow database queries
- **WordPress.VIP.PostsPerPage**: Limits posts_per_page to prevent memory issues
- **WordPress.WP.PostsPerPage**: Enforces reasonable pagination limits

### Code Quality Rules (MEDIUM Priority)

- **WordPress.WP.I18n**: Ensures proper internationalization
- **WordPress.WP.EnqueuedResources**: Ensures scripts/styles are properly enqueued
- **WordPress.WP.GlobalVariablesOverride**: Prevents overriding WordPress globals
- **WordPress.WP.DiscouragedConstants**: Warns about deprecated constants
- **WordPress.WP.DiscouragedFunctions**: Warns about deprecated functions

### Best Practice Rules (MEDIUM Priority)

- **WordPress.VIP.OrderByRand**: Discourages RAND() in queries
- **WordPress.VIP.TimezoneChange**: Prevents timezone manipulation
- **WordPress.PHP.DevelopmentFunctions**: Detects debug code (error_log, var_dump, etc.)
- **WordPress.PHP.DiscouragedPHPFunctions**: Warns about risky PHP functions

## VIP Rules Excluded and Why

The following VIP rules are **explicitly excluded** because they conflict with our coding standards or are not applicable to our environment:

### File Naming (PSR-4 Compatibility)

**Excluded:**
- `WordPress.Files.FileName.InvalidClassFileName`
- `WordPress.Files.FileName.NotHyphenatedLowercase`

**Reason:** We use PSR-4 autoloading with namespaced classes. Our class files are named using PascalCase (e.g., `PluginFactory.php`, `ServiceProvider.php`) rather than WordPress's traditional hyphenated lowercase naming (e.g., `class-plugin-factory.php`). This is a modern PHP best practice that improves developer experience and IDE integration.

**Example:**
```php
// Our approach (PSR-4)
namespace QalaPluginManager;
class PluginFactory { }
// File: includes/classes/PluginFactory.php

// Traditional WordPress (excluded)
// File: includes/class-plugin-factory.php
```

### Code Style Preferences

**Excluded:**
- `WordPress.PHP.YodaConditions`
- `WordPress.PHP.YodaConditions.NotYoda`

**Reason:** We explicitly disallow Yoda conditions as they reduce code readability. We enforce normal comparison order through `Generic.ControlStructures.DisallowYodaConditions`.

**Example:**
```php
// Our approach (enforced)
if ( $foo === 'bar' ) { }
if ( $count > 10 ) { }

// Yoda conditions (excluded)
if ( 'bar' === $foo ) { }
if ( 10 < $count ) { }
```

**Excluded:**
- `WordPress.PHP.DisallowShortTernary`

**Reason:** Short ternary operators are readable and concise for simple fallback logic.

**Example:**
```php
// Our approach (allowed)
$slug = $plugin_data['slug'] ?? 'N/A';
$value = $input ?: 'default';

// VIP preference (we don't enforce this)
$slug = isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : 'N/A';
```

**Excluded:**
- `Generic.Arrays.DisallowShortArraySyntax`

**Enforced Instead:**
- `Generic.Arrays.DisallowLongArraySyntax`

**Reason:** We require modern short array syntax `[]` instead of the older `array()` notation.

**Example:**
```php
// Our approach (enforced)
$classes = [
    TranslationsLoader::class,
    PluginActivation::class,
];

// Old syntax (disallowed)
$classes = array(
    TranslationsLoader::class,
    PluginActivation::class,
);
```

### Platform-Specific Restrictions

**Excluded:**
- `WordPress.VIP.RestrictedFunctions.switch_to_blog_switch_to_blog`
- `WordPress.VIP.RestrictedFunctions.get_posts_get_posts`
- `WordPress.VIP.RestrictedFunctions.get_pages_get_pages`

**Reason:** These functions are restricted on WordPress VIP platform due to their performance characteristics at scale. However, we're not running on VIP infrastructure, and these functions are legitimate and necessary for our multisite functionality (see `PluginTable::populate_sites_column()`).

**Example from our code:**
```php
// Legitimate use in PluginTable.php
$sites = get_sites();
foreach ( $sites as $site ) {
    switch_to_blog( absint( $site_id ) );
    // ... check plugin activation
    restore_current_blog();
}
```

**Excluded:**
- `WordPress.VIP.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__`

**Reason:** VIP restricts `$_SERVER['REMOTE_ADDR']` usage due to caching concerns on their platform. We may need this for legitimate use cases like logging or security checks.

**Excluded:**
- `WordPress.VIP.SessionFunctionsUsage.session_session_start`

**Reason:** PHP sessions are not available on VIP platform, but we may use them in standard WordPress environments.

**Excluded:**
- `WordPress.VIP.FileSystemWritesDisallow`

**Reason:** VIP platform has read-only filesystem. Our plugin may need filesystem writes for caching or temporary files in standard WordPress environments.

**Excluded:**
- `WordPress.VIP.AdminBarRemoval.RemovalDetected`

**Reason:** VIP doesn't allow removing the admin bar. We may have legitimate use cases for this in custom implementations.

### Database Query Handling

**Excluded:**
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`
- `WordPress.DB.DirectDatabaseQuery.NoCaching`

**Reason:** While VIP encourages using WordPress query functions, we sometimes need direct database queries for complex operations. We handle caching appropriately in our code (see `PluginTable` transient usage).

**Example from our code:**
```php
// We use transient caching for expensive operations
$cache_key = 'plugin_sites_activated_' . md5( $plugin_file );
$activated_sites = get_site_transient( $cache_key );

if ( $activated_sites === false ) {
    // Expensive operation with caching
    // ...
    set_site_transient( $cache_key, $activated_sites, 12 * HOUR_IN_SECONDS );
}
```

### Strict Comparison

**Excluded:**
- `WordPress.PHP.StrictInArray.MissingTrueStrict`

**Reason:** While strict comparisons are generally good practice, we handle this on a case-by-case basis. Some legacy code or third-party integrations may require loose comparisons.

### Alternative Functions

**Excluded:**
- `WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents`

**Reason:** VIP prefers `wpcom_vip_file_get_contents()` or `wp_remote_get()`, but we're not on VIP platform. We'll use appropriate functions based on context (local files vs. remote URLs).

## Alignment with Existing Code

Our PHPCS configuration is designed to match the existing codebase patterns:

### Type Declarations

Our code uses PHP 7+ type declarations extensively:

```php
public function get_path() : string {
    return self::$plugin_path;
}

public function add_sites_column( $columns ): array {
    // ...
    return $columns;
}

protected function register_classes() : void {
    // ...
}
```

### Modern PHP Syntax

- **Short array syntax**: `[]` instead of `array()`
- **Null coalescing operator**: `??` for defaults
- **Class constants**: `ClassName::class` for class references
- **Strict comparisons**: `===` and `!==` where appropriate

### PSR-4 Autoloading

```php
namespace QalaPluginManager;

// Autoloaded from includes/classes/PluginFactory.php
class PluginFactory {
    // ...
}
```

### Documentation Standards

All classes and methods have proper DocBlocks:

```php
/**
 * Main plugin handler class.
 *
 * Also contains some useful getters for things like plugin path etc.
 *
 * @package QalaPluginManager
 */
class Plugin {
    /**
     * Plugin constructor.
     *
     * @param ServiceProvider $service_provider
     */
    public function __construct( ServiceProvider $service_provider ) {
        // ...
    }
}
```

### Security Practices

Output escaping and input sanitization are followed:

```php
// Output escaping
echo esc_html__( 'None', 'qala-plugin-manager' );
printf( '<pre><code>%1$s</code></pre>', esc_html( $slug ) );

// Input sanitization
switch_to_blog( absint( $site_id ) );
```

## Common Violations to Watch For

When writing new code, watch out for these common PHPCS violations:

### 1. Missing Output Escaping

**Bad:**
```php
echo $user_input;
echo get_option( 'my_option' );
```

**Good:**
```php
echo esc_html( $user_input );
echo esc_html( get_option( 'my_option' ) );
```

### 2. Missing Input Validation

**Bad:**
```php
$id = $_POST['id'];
update_option( 'my_id', $_POST['value'] );
```

**Good:**
```php
$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
update_option( 'my_id', $value );
```

### 3. Missing Nonce Verification

**Bad:**
```php
if ( isset( $_POST['submit'] ) ) {
    // Process form
}
```

**Good:**
```php
if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'my_action' ) ) {
    // Process form
}
```

### 4. Slow Database Queries

**Bad:**
```php
$query->set( 'posts_per_page', -1 ); // Gets ALL posts
$query->set( 'posts_per_page', 10000 ); // Too many
```

**Good:**
```php
$query->set( 'posts_per_page', 100 ); // Reasonable limit
// Or use pagination
```

### 5. Using Long Array Syntax

**Bad:**
```php
$options = array(
    'option1' => 'value1',
    'option2' => 'value2',
);
```

**Good:**
```php
$options = [
    'option1' => 'value1',
    'option2' => 'value2',
];
```

### 6. Using Yoda Conditions

**Bad:**
```php
if ( 'bar' === $foo ) { }
if ( 10 < $count ) { }
```

**Good:**
```php
if ( $foo === 'bar' ) { }
if ( $count > 10 ) { }
```

### 7. Direct Script/Style Output

**Bad:**
```php
echo '<script src="my-script.js"></script>';
```

**Good:**
```php
wp_enqueue_script( 'my-script', plugin_dir_url( __FILE__ ) . 'my-script.js' );
```

### 8. Missing Internationalization

**Bad:**
```php
echo '<p>Hello World</p>';
$message = 'Error occurred';
```

**Good:**
```php
echo '<p>' . esc_html__( 'Hello World', 'qala-plugin-manager' ) . '</p>';
$message = __( 'Error occurred', 'qala-plugin-manager' );
```

### 9. Development Functions in Code

**Bad:**
```php
var_dump( $data );
print_r( $array );
error_log( 'Debug: ' . $value ); // OK for production logging, but review usage
```

**Good:**
```php
// Use proper debugging tools in development
// Remove debug code before committing
```

### 10. Incorrect Hook Naming

**Bad:**
```php
do_action( 'myPlugin-customHook' );
```

**Good:**
```php
// We allow / as delimiter
do_action( 'qala/plugin/manager/custom_hook' );
// Or standard WordPress style
do_action( 'qala_plugin_manager_custom_hook' );
```

## Running Checks Locally

### Prerequisites

1. Install dependencies:
```bash
composer install
```

This will install:
- PHP_CodeSniffer
- WordPress Coding Standards (wpcs)
- WordPress VIP Coding Standards (vipwpcs)
- All other development dependencies

### Check Code Style

Run PHPCS to check for violations:

```bash
composer style:check
```

Or directly:
```bash
./dependencies/vendor/bin/phpcs -s --extensions=php .
```

The `-s` flag shows the name of each violated rule, which helps when you need to add inline ignores.

### Auto-Fix Code Style

Many violations can be automatically fixed:

```bash
composer style:fix
```

Or directly:
```bash
./dependencies/vendor/bin/phpcbf --extensions=php .
```

**Note:** Not all violations can be auto-fixed (e.g., missing escaping, nonce verification). You'll need to fix these manually.

### Check Specific Files or Directories

Check a specific file:
```bash
./dependencies/vendor/bin/phpcs -s includes/classes/Plugin.php
```

Check a specific directory:
```bash
./dependencies/vendor/bin/phpcs -s includes/classes/
```

### Inline Ignores

Sometimes you have a legitimate reason to ignore a specific rule. Use inline comments:

```php
// Ignore a single line
echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

// Ignore multiple rules on a line
echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

// Disable for a block
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo $html1;
echo $html2;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
```

**Important:** Always document why you're ignoring a rule. Code review should scrutinize all ignores.

## Integration with Development Workflow

### GrumPHP Integration

The project uses GrumPHP to run PHPCS automatically on git commits.

Initialize GrumPHP:
```bash
composer init:grumphp
```

Run GrumPHP manually:
```bash
composer test:grumphp
```

When you commit code, GrumPHP will automatically:
1. Run PHPCS on changed files
2. Prevent commit if violations are found
3. Show you what needs to be fixed

### Full Test Suite

Run all tests (PHPStan + GrumPHP):
```bash
composer test
```

### Pre-Commit Workflow

Recommended workflow before committing:

1. **Fix auto-fixable issues:**
   ```bash
   composer style:fix
   ```

2. **Check for remaining issues:**
   ```bash
   composer style:check
   ```

3. **Fix manual issues** (escaping, validation, etc.)

4. **Run full test suite:**
   ```bash
   composer test
   ```

5. **Commit your changes:**
   ```bash
   git add .
   git commit -m "Your commit message"
   ```

### CI/CD Integration

The configuration is ready for CI/CD pipelines. Add to your CI configuration:

```yaml
# Example GitHub Actions
- name: Check Code Style
  run: composer style:check

# Example GitLab CI
phpcs:
  script:
    - composer style:check
```

## Best Practices

1. **Run checks frequently:** Don't wait until commit time. Run `composer style:check` regularly while developing.

2. **Fix as you go:** Fix violations immediately rather than accumulating technical debt.

3. **Understand violations:** Read the error messages and understand why the rule exists before ignoring it.

4. **Document exceptions:** If you must ignore a rule, add a comment explaining why.

5. **Review configuration changes:** If you need to exclude additional rules, discuss with the team and document the reasoning.

6. **Keep standards updated:** Periodically update coding standards packages:
   ```bash
   composer update wp-coding-standards/wpcs automattic/vipwpcs --with-dependencies
   ```

7. **Security first:** Never ignore security-related rules (escaping, validation, nonces) without careful consideration and team review.

8. **Performance matters:** Pay special attention to VIP performance rules even though we're not on VIP platform. They represent best practices for scalable WordPress development.

## Additional Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [WordPress VIP Documentation](https://docs.wpvip.com/technical-references/code-quality/)
- [PHP_CodeSniffer Documentation](https://github.com/squizlabs/PHP_CodeSniffer/wiki)
- [WordPress VIP Coding Standards](https://github.com/Automattic/VIP-Coding-Standards)

## Questions or Issues?

If you encounter issues with the PHPCS configuration:

1. Check this documentation first
2. Review the `phpcs.xml` file for specific rule configurations
3. Search existing issues in the WordPress WPCS or VIP WPCS repositories
4. Discuss with the team before modifying the configuration

Remember: These standards exist to maintain code quality, security, and performance. When in doubt, follow the stricter standard.
