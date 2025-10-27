# Test Fixtures

This directory contains test data, mock responses, and fixture files used across all test suites.

## Purpose

Fixtures provide consistent, reusable test data that can be shared across unit, integration, and E2E tests, ensuring test reliability and maintainability.

## Structure

```
Fixtures/
├── notices/
│   ├── admin-notices.json
│   ├── plugin-notices.json
│   └── woocommerce-notices.json
├── users/
│   ├── admin-user.json
│   └── editor-user.json
├── settings/
│   └── plugin-settings.json
└── database/
    └── test-data.sql
```

## Types of Fixtures

### JSON Fixtures
Static data structures representing notices, users, settings, etc.

**Example: notices/admin-notices.json**
```json
{
  "update-notice": {
    "id": "update-notice",
    "message": "WordPress 6.4 is available! Please update now.",
    "type": "warning",
    "dismissible": true
  },
  "error-notice": {
    "id": "error-notice",
    "message": "Failed to connect to database",
    "type": "error",
    "dismissible": false
  }
}
```

### SQL Fixtures
Database dumps for integration tests requiring specific database states.

### Factory Functions
PHP classes that generate test data programmatically.

## Using Fixtures

### In Unit Tests
```php
$notices = json_decode(
    file_get_contents(__DIR__ . '/../Fixtures/notices/admin-notices.json'),
    true
);
```

### In Integration Tests
```php
$factory = new NoticeFactory();
$notice = $factory->create([
    'type' => 'warning',
    'message' => 'Test notice'
]);
```

### In E2E Tests
```typescript
import testNotices from '../fixtures/notices/admin-notices.json';

test('test with fixture', async ({ page }) => {
    await page.route('**/wp-admin/admin-ajax.php', route => {
        route.fulfill({ json: testNotices });
    });
});
```

## Best Practices

- Keep fixtures simple and focused
- Use descriptive file names
- Version control all fixtures
- Document the purpose of each fixture
- Avoid duplicating data across fixtures
- Use factory functions for dynamic data
- Keep fixtures small and readable
