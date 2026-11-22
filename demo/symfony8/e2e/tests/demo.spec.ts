import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy path of the Google Translate demo: the page renders inside the demo platform shell and the form
 * posts. The Google endpoint is external: the outcome is either the result card or an error flash, never a blank page.
 */
test.describe('Google Translate demo', () => {
  test('the page renders inside the demo platform shell', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'GoogleTranslatePhpBundle', level: 1 })).toBeVisible();
    await expect(page.getByTestId('demo-profile')).toContainText('timeout=');
  });

  test('submitting the form answers with a result or an error flash', async ({ page }) => {
    await page.goto('/');
    await page.getByLabel('Text to translate').fill('Hello world');
    await page.getByRole('button', { name: 'Translate' }).click();
    await expect(page.getByTestId('demo-result').or(page.locator('.nowo-ui-flashes'))).toBeVisible({ timeout: 30000 });
  });
});
