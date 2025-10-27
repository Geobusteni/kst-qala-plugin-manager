# Contributing to Qala Plugin Manager

This guide provides detailed instructions for developers working on the Qala Plugin Manager plugin.

## Table of Contents

- [Development Setup](#development-setup)
- [Building the Plugin](#building-the-plugin)
- [Making Changes](#making-changes)
- [Testing](#testing)
- [Creating a Release](#creating-a-release)
- [Git Workflow](#git-workflow)
- [Code Standards](#code-standards)

## Development Setup

### Prerequisites

- **PHP**: 7.3 or higher
- **Composer**: Latest version
- **Git**: For version control
- **WordPress**: 6.4+ for testing

### Initial Setup

1. **Clone the Repository**

```bash
# GitHub
git clone https://github.com/YOUR-ORG/kst-qala-plugin-manager.git

# GitLab
git clone https://gitlab.com/YOUR-ORG/kst-qala-plugin-manager.git

cd kst-qala-plugin-manager/sources/qala-manager/qala-plugin-manager
```

2. **Install PHP Dependencies**

```bash
composer install
```

This installs development tools:
- PHPStan (static analysis)
- PHPCS/PHPCBF (code style)
- PHPUnit (unit testing)
- GrumPHP (pre-commit hooks)

3. **Verify Installation**

```bash
# Check PHP version
php -v

# Check composer dependencies
composer show

# Run tests to verify setup
composer test
```

### Database Setup

Database tables are created automatically when the plugin loads in WordPress. No manual setup needed.

## Building the Plugin

### Understanding the Build Process

The plugin uses a custom bash script to process assets:

- **Source Files** (edit these):
  - `assets/js/qala-plugin-manager.js` - Main JavaScript file
  - `assets/js/admin-page.js` - Admin page specific JS
  - `assets/js/admin-bar-toggle.js` - Admin bar toggle JS
  - `assets/css/*.css` - All CSS files

- **Dist Files** (generated, don't edit):
  - `assets/dist/js/*.js` - Processed JavaScript
  - `assets/dist/css/*.css` - Processed CSS

### Building Assets

Run the build script from the plugin root directory:

```bash
./build-assets.sh
```

This script:
1. Creates `assets/dist/` directories
2. Adds version headers to files
3. Minifies CSS and JavaScript
4. Outputs summary of generated files

**Note**: Always run this before committing asset changes!

## Making Changes

### Source File Locations

#### PHP Files
- **Classes**: `includes/classes/`
- **Main Plugin File**: `index.php`
- **Autoloading**: PSR-4 via Composer

#### JavaScript Files
**Edit source files only** (NOT dist files):
- `assets/js/qala-plugin-manager.js` - Combined main JavaScript
- `assets/js/admin-page.js` - Settings page functionality
- `assets/js/admin-bar-toggle.js` - Quick toggle functionality

#### CSS Files
**Edit source files only**:
- `assets/css/qala-plugin-manager.css` - Combined main CSS
- `assets/css/admin-page.css` - Settings page styles
- `assets/css/admin-bar-toggle.css` - Admin bar toggle styles
- `assets/css/notice-hider.css` - Notice hiding CSS (nuclear approach)

### Development Workflow

1. **Make Changes** to source files (PHP, JS, CSS)
2. **Build Assets** if you changed JS/CSS:
   ```bash
   ./build-assets.sh
   ```
3. **Test Locally** in WordPress installation
4. **Run Tests**:
   ```bash
   composer test
   ```
5. **Fix Code Style** if needed:
   ```bash
   composer style:fix
   ```
6. **Commit Changes** (see Git Workflow below)

## Testing

### Running Tests

```bash
# Run all tests (PHPStan + unit tests)
composer test

# Run only unit tests
composer test:unit

# Run only PHPStan static analysis
composer test:phpstan

# Check code style
composer style:check

# Auto-fix code style issues
composer style:fix
```

### Code Quality Standards

The project enforces:
- **PHPStan**: Level 8 static analysis
- **PHPCS**: WordPress Extra + VIP coding standards
- **PHPUnit**: Unit test coverage
- **GrumPHP**: Pre-commit hooks for quality checks

## Creating a Release

### Step-by-Step Release Process

#### 1. Update Version Number

Edit `index.php` and update the version:

```php
/**
 * Version: 1.0.X
 */
```

#### 2. Update CHANGELOG.md

Add new version section at the top:

```markdown
## [1.0.X] - YYYY-MM-DD

### Fixed
- Description of bug fixes

### Added
- Description of new features

### Changed
- Description of changes
```

#### 3. Build Assets

```bash
./build-assets.sh
```

Verify output shows all files processed successfully.

#### 4. Run Tests

```bash
composer test
```

Ensure all tests pass before releasing.

#### 5. Commit Changes

```bash
git add .
git commit -m "Release v1.0.X - Brief description

Detailed changes:
- Change 1
- Change 2
- Change 3"
```

#### 6. Create Git Tag

```bash
git tag -a v1.0.X -m "Version 1.0.X - Brief description

Release notes:
- Feature/fix 1
- Feature/fix 2"
```

#### 7. Push to Remote

```bash
# Push code
git push origin main

# Push tag
git push origin v1.0.X
```

#### 8. Create Release Package (ZIP)

From the `sources/qala-manager` directory:

```bash
zip -r qala-plugin-manager-v1.0.X.zip qala-plugin-manager \
  -x "*/node_modules/*" \
  -x "*/vendor/bin/*" \
  -x "*/tests/*" \
  -x "*/.git/*" \
  -x "*/.github/*" \
  -x "*/.gitlab/*" \
  -x "*/phpunit.xml" \
  -x "*/composer.lock" \
  -x "*/package-lock.json" \
  -x "*/.DS_Store"
```

This creates an installation-ready zip file.

#### 9. Create Platform Release

**For GitHub:**

```bash
gh release create v1.0.X \
  --title "v1.0.X - Release Title" \
  --notes "Release notes here" \
  qala-plugin-manager-v1.0.X.zip
```

Or create manually via GitHub web interface.

**For GitLab:**

Navigate to Repository → Tags → New release, or use GitLab CLI:

```bash
glab release create v1.0.X \
  --name "v1.0.X - Release Title" \
  --notes "Release notes here" \
  qala-plugin-manager-v1.0.X.zip
```

Or create manually via GitLab web interface.

## Git Workflow

### Branch Strategy

- **main**: Production-ready code
- **develop**: Development branch (if using)
- **feature/**: Feature branches
- **bugfix/**: Bug fix branches
- **hotfix/**: Urgent fixes

### Commit Message Format

```
Type: Brief description (50 chars or less)

Detailed explanation of changes (wrap at 72 chars):
- Change 1
- Change 2
- Change 3

Refs: #issue-number (if applicable)
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code refactoring
- `style`: Code style changes
- `docs`: Documentation updates
- `test`: Test updates
- `chore`: Build/tooling changes

### Tag Naming Convention

Use semantic versioning with `v` prefix:
- `v1.0.0` - Major release
- `v1.0.1` - Patch release
- `v1.1.0` - Minor release

## Code Standards

### PHP Standards

- **WordPress Coding Standards**: WordPress Extra
- **VIP Go Standards**: Automattic VIP rules
- **PSR-4 Autoloading**: Namespace matches directory structure
- **Type Hints**: Use PHP 7.3+ type hints where possible
- **PHPDoc Blocks**: All functions must have documentation

### JavaScript Standards

- **ES6+**: Use modern JavaScript
- **No jQuery**: Vanilla JS preferred (except where WordPress requires it)
- **Comments**: Document complex logic
- **Console Logging**: Use for debugging only

### CSS Standards

- **BEM-like naming**: Block__Element--Modifier pattern
- **Mobile-first**: Design for mobile, enhance for desktop
- **WordPress admin styles**: Follow WP admin design patterns

### Code Style Tools

```bash
# Check PHP code style
composer style:check

# Auto-fix PHP code style
composer style:fix

# Run static analysis
composer test:phpstan
```

## Project Structure

```
qala-plugin-manager/
├── index.php                  # Main plugin file
├── composer.json              # PHP dependencies
├── build-assets.sh           # Build script
├── CHANGELOG.md              # Version history
├── README.md                 # User documentation
├── CONTRIBUTING.md           # This file
├── assets/
│   ├── js/                   # Source JavaScript
│   ├── css/                  # Source CSS
│   └── dist/                 # Built assets (generated)
├── includes/
│   └── classes/              # PHP classes (PSR-4)
│       ├── NoticeManagement/ # Notice hiding features
│       ├── Interfaces/       # PHP interfaces
│       └── Plugins/          # Plugin-specific handlers
├── dependencies/
│   ├── vendor/               # Composer dependencies
│   ├── grumphp/             # GrumPHP config
│   └── scripts/             # Build scripts
├── languages/                # Translation files
└── tests/                    # PHPUnit tests

```

## Troubleshooting

### Build Script Fails

**Problem**: `build-assets.sh` errors or doesn't work

**Solutions**:
1. Make script executable: `chmod +x build-assets.sh`
2. Run from plugin root directory
3. Check bash is available: `which bash`

### Tests Fail

**Problem**: `composer test` fails

**Solutions**:
1. Run `composer install` to update dependencies
2. Check PHP version: `php -v` (must be 7.3+)
3. Check specific test: `composer test:phpstan` or `composer test:unit`

### Code Style Failures

**Problem**: PHPCS reports style violations

**Solution**:
```bash
# Auto-fix most issues
composer style:fix

# Check remaining issues
composer style:check
```

### Git Push Rejected

**Problem**: Push rejected by pre-commit hooks

**Solutions**:
1. Fix code style: `composer style:fix`
2. Fix PHPStan errors: Review output, fix issues
3. Bypass (not recommended): `git push --no-verify`

## Getting Help

- **Documentation**: Check `/CLAUDE.md` for project context
- **Architecture**: See `/FINAL_TECHNICAL_ARCHITECTURE.md`
- **Issues**: Report on GitHub/GitLab issue tracker
- **Questions**: Contact development team

## License

Proprietary - All rights reserved

---

**Last Updated**: 2025-10-26
**Plugin Version**: 1.0.11
