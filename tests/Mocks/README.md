# Test Mocks

This directory contains mock classes and helpers for simulating WordPress core, plugins, and external dependencies in tests.

## Purpose

Mocks allow testing components in isolation by replacing real dependencies with controlled, predictable implementations.

## Structure

```
Mocks/
├── WordPress/
│   ├── MockWpdb.php
│   ├── MockWpHooks.php
│   └── MockWpOptions.php
├── Plugins/
│   ├── MockWooCommerce.php
│   └── MockAcf.php
└── External/
    └── MockHttpClient.php
```

## Types of Mocks

### WordPress Core Mocks

Mock implementations of WordPress core classes and functions.

**Example: MockWpdb.php**
```php
namespace QalaPluginManager\Tests\Mocks\WordPress;

class MockWpdb {
    public $last_query;
    public $queries = [];

    public function prepare($query, ...$args) {
        return vsprintf($query, $args);
    }

    public function get_results($query) {
        $this->last_query = $query;
        $this->queries[] = $query;
        return [];
    }
}
```

### Plugin Mocks

Mock implementations of third-party plugins like WooCommerce, ACF, etc.

### HTTP Mocks

Mock HTTP responses for testing API integrations.

## Using Mocks

### In Unit Tests with PHPUnit

```php
use QalaPluginManager\Tests\Mocks\WordPress\MockWpdb;

class MyServiceTest extends TestCase {
    public function test_it_queries_database() {
        // Arrange
        $mockDb = new MockWpdb();
        $service = new MyService($mockDb);

        // Act
        $service->getData();

        // Assert
        $this->assertStringContainsString('SELECT', $mockDb->last_query);
    }
}
```

### With Brain Monkey

```php
use Brain\Monkey\Functions;

Functions\when('get_option')->justReturn(['key' => 'value']);
Functions\expect('update_option')->once();
```

### With Mockery

```php
$mock = Mockery::mock('overload:WooCommerce');
$mock->shouldReceive('get_version')->andReturn('8.0.0');
```

## Best Practices

- Create mocks that implement the same interface as real objects
- Keep mocks simple and focused on test needs
- Use spy patterns to verify method calls
- Avoid complex logic in mocks
- Document mock behavior and limitations
- Prefer test doubles over real implementations
- Use type hints for better IDE support

## Mock vs. Stub vs. Spy

- **Mock**: Verifies behavior (method calls, arguments)
- **Stub**: Returns predefined responses
- **Spy**: Records interactions for later verification

## Example Mock Class

```php
namespace QalaPluginManager\Tests\Mocks\WordPress;

/**
 * Mock WordPress options API for testing.
 */
class MockWpOptions {
    private array $options = [];

    public function get_option(string $name, $default = false) {
        return $this->options[$name] ?? $default;
    }

    public function update_option(string $name, $value): bool {
        $this->options[$name] = $value;
        return true;
    }

    public function delete_option(string $name): bool {
        unset($this->options[$name]);
        return true;
    }

    public function reset(): void {
        $this->options = [];
    }
}
```
