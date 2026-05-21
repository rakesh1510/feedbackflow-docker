
const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

const pages = [
  '/',
  '/?action=login',
  '/?action=register',
  '/pricing.php',
  '/onboarding/setup.php',
  '/admin',
  '/administrator'
];

for (const path of pages) {
  test(`Page loads: ${path}`, async ({ page }) => {
    const response = await page.goto(`${BASE_URL}${path}`, {
      waitUntil: 'domcontentloaded'
    });

    expect(response.status()).toBeLessThan(500);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test('Mobile homepage loads', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(BASE_URL);
  await expect(page.locator('body')).not.toBeEmpty();
});
