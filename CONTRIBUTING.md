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
- **Node.js**: 14.0 or higher
- **npm**: Latest version (comes with Node.js)
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

3. **Install npm Dependencies**

```bash
npm install
```

This installs build tools:
- @wordpress/scripts (webpack, babel, postcss)
- Asset bundling and minification
- Development server with hot reload

4. **Verify Installation**

```bash
# Check PHP version
php -v

# Check Node version
node -v

# Check composer dependencies
composer show

# Build assets
npm run build

# Run tests to verify setup
composer test
```

### Database Setup

Database tables are created automatically when the plugin loads in WordPress. No manual setup needed.

## Building the Plugin

### Understanding the Build Process

The plugin uses **@wordpress/scripts** for modern asset bundling:

- **Source Files** (edit these):
  - `assets/src/js/index.js` - JavaScript entry point
  - `assets/src/css/*.css` - CSS source files
  - `assets/js/qala-plugin-manager.js` - Main JavaScript (imported by index.js)

- **Build Output** (generated, don't edit):
  - `assets/dist/qala-plugin-manager.css` - Bundled CSS
  - `assets/dist/qala-plugin-manager-rtl.css` - RTL stylesheet (auto-generated)
  - `assets/dist/js/qala-plugin-manager.js` - Bundled & minified JavaScript
  - `assets/dist/js/qala-plugin-manager.asset.php` - Dependency file

### Building Assets

**Production Build** (minified, optimized):

```bash
npm run build
```

**Development Build** (with hot reload):

```bash
npm start
```

The build process:
1. Bundles all CSS into one file
2. Generates RTL stylesheet automatically
3. Bundles and minifies JavaScript
4. Creates dependency file for WordPress
5. Optimizes assets for production

**Note**: Always run `npm run build` before committing asset changes!

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
   npm run build
   ```
   Or use development mode with hot reload:
   ```bash
   npm start
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
npm run build
```

Verify output shows all files bundled successfully.

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

Use the package script:

```bash
./package-plugin.sh
```

This script:
- Verifies assets are built
- Creates zip with proper exclusions
- Excludes source files (assets/src/*, node_modules, etc.)
- Includes only production-ready files
- Names file: `qala-plugin-manager-vX.X.X.zip`

Output location: `../qala-plugin-manager-vX.X.X.zip`

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
├── package.json               # NPM dependencies & build scripts
├── webpack.config.js          # Webpack configuration
├── postcss.config.js          # PostCSS configuration
├── package-plugin.sh          # Release packaging script
├── CHANGELOG.md              # Version history
├── README.md                 # User documentation
├── CONTRIBUTING.md           # This file
├── CHANGES.md                # Detailed comparison from v1.x
├── assets/
│   ├── src/                  # Source files for webpack
│   │   ├── js/              # Source JavaScript
│   │   └── css/             # Source CSS
│   ├── js/                   # Legacy JavaScript (imported by webpack)
│   ├── css/                  # Legacy CSS (imported by webpack)
│   └── dist/                 # Built assets (generated by webpack)
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
├── tests/                    # PHPUnit tests
└── node_modules/             # NPM packages (generated)

```

## Troubleshooting

### Build Fails

**Problem**: `npm run build` errors or doesn't work

**Solutions**:
1. Ensure npm dependencies are installed: `npm install`
2. Run from plugin root directory
3. Check Node.js version: `node -v` (requires Node 14+)
4. Clear npm cache: `npm cache clean --force`
5. Remove node_modules and reinstall: `rm -rf node_modules && npm install`

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
