# End-to-End Tests

This directory contains end-to-end tests that simulate real user interactions with the plugin in a browser environment.

## Purpose

E2E tests validate complete user workflows from the browser perspective, ensuring all components work together correctly in production-like scenarios.

## Technology Stack

- **Playwright**: Modern browser automation framework
- **TypeScript**: Type-safe test authoring
- **WordPress Test Environment**: Dockerized WordPress instance

## Structure

```
E2E/
├── specs/
│   ├── notice-hiding.spec.ts
│   ├── notice-management.spec.ts
│   └── admin-ui.spec.ts
├── fixtures/
│   └── test-data.json
├── page-objects/
│   ├── PluginManagerPage.ts
│   └── NoticesPage.ts
└── helpers/
    └── wordpress-helpers.ts
```

## Running E2E Tests

```bash
# Run all E2E tests
npm run test:e2e

# Run in headed mode (see browser)
npm run test:e2e:headed

# Run specific test file
npm run test:e2e -- notice-hiding.spec.ts

# Debug mode with Playwright Inspector
npm run test:e2e:debug
```

## Setup

1. Start WordPress test environment:
```bash
docker-compose -f docker-compose.test.yml up -d
```

2. Install dependencies:
```bash
npm install
npx playwright install
```

3. Configure base URL in `playwright.config.js`

## Writing E2E Tests

- Use Page Object Model for maintainable tests
- Use data-testid attributes for reliable selectors
- Implement proper waits (avoid hard sleeps)
- Test critical user journeys end-to-end
- Include visual regression tests where appropriate

## Example

```typescript
import { test, expect } from '@playwright/test';
import { PluginManagerPage } from '../page-objects/PluginManagerPage';

test('should hide admin notice when user clicks hide button', async ({ page }) => {
  // Arrange
  const pluginManager = new PluginManagerPage(page);
  await pluginManager.goto();
  await pluginManager.waitForNotices();

  // Act
  await pluginManager.hideNotice('update-notice');

  // Assert
  await expect(pluginManager.getNotice('update-notice')).not.toBeVisible();

  // Verify persistence
  await page.reload();
  await expect(pluginManager.getNotice('update-notice')).not.toBeVisible();
});
```

## Best Practices

- Keep tests independent and idempotent
- Use fixtures for test data
- Clean up test data after each test
- Use meaningful test descriptions
- Group related tests in describe blocks
- Implement retry logic for flaky tests
- Take screenshots on failure for debugging
