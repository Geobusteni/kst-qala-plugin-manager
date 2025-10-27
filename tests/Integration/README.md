# Integration Tests

This directory contains integration tests that verify how components work together with real WordPress installations.

## Purpose

Integration tests validate interactions between multiple components, database operations, and WordPress core functionality in a real or simulated WordPress environment.

## Structure

```
Integration/
├── NoticeManagement/
│   ├── NoticeWorkflowTest.php
│   ├── NoticeStorageDatabaseTest.php
│   └── NoticeSuppressionTest.php
└── ...other test files
```

## Requirements

- WordPress Test Suite (WP_TESTS_DIR environment variable)
- MySQL/MariaDB test database
- WordPress core test library

## Running Integration Tests

```bash
# Run all integration tests
composer test:integration

# Run specific test suite
composer test:integration -- tests/Integration/NoticeManagement/

# Run with WordPress test environment
WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
```

## Setup

1. Install WordPress test suite:
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

2. Configure database in `phpunit.xml`

3. Ensure WordPress test bootstrap is loaded

## Writing Integration Tests

- Extend `WP_UnitTestCase` for WordPress integration tests
- Use real database interactions (tests run in transactions)
- Test actual WordPress hooks, filters, and actions
- Verify cross-component interactions
- Clean up test data in tearDown methods

## Example

```php
namespace QalaPluginManager\Tests\Integration\NoticeManagement;

use WP_UnitTestCase;
use QalaPluginManager\NoticeManagement\NoticeStorage;

class NoticeStorageDatabaseTest extends WP_UnitTestCase {
    public function test_it_should_save_notice_to_database() {
        // Arrange
        $storage = new NoticeStorage();
        $notice = ['id' => 'test', 'message' => 'Test notice'];

        // Act
        $result = $storage->save($notice);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals($notice, $storage->get('test'));
    }
}
```
