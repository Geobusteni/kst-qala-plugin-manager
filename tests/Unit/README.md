# Unit Tests

This directory contains unit tests for all classes in the Qala Plugin Manager.

## Purpose

Unit tests verify individual components in isolation, mocking all external dependencies including WordPress functions, database calls, and third-party services.

## Structure

Tests mirror the structure of the `includes/classes/` directory:

```
Unit/
├── NoticeManagement/
│   ├── NoticeDetectorTest.php
│   ├── NoticeStorageTest.php
│   ├── NoticeSuppressorTest.php
│   └── NoticeManagerTest.php
├── Traits/
│   └── CapabilityCheckerTest.php
└── ...other test files
```

## Running Unit Tests

```bash
# Run all unit tests
composer test:unit

# Run specific test file
composer test:unit -- tests/Unit/NoticeManagement/NoticeDetectorTest.php

# Run with coverage
composer test:unit:coverage
```

## Writing Unit Tests

- Use PHPUnit 9.x syntax and assertions
- Mock all WordPress functions using Brain Monkey or similar
- Aim for 100% code coverage on business logic
- Follow AAA pattern: Arrange, Act, Assert
- Use descriptive test method names (test_it_should_...)

## Example

```php
namespace QalaPluginManager\Tests\Unit\NoticeManagement;

use PHPUnit\Framework\TestCase;
use QalaPluginManager\NoticeManagement\NoticeDetector;

class NoticeDetectorTest extends TestCase {
    public function test_it_should_detect_admin_notice() {
        // Arrange
        $detector = new NoticeDetector();

        // Act
        $result = $detector->detect();

        // Assert
        $this->assertIsArray($result);
    }
}
```
