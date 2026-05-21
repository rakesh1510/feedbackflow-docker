const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

test('Login and save session', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=login`);

  await page.fill('input[type="email"], input[name="email"]', process.env.FF_EMAIL);
  await page.fill('input[type="password"], input[name="password"]', process.env.FF_PASSWORD);

  await page.click('button[type="submit"], input[type="submit"]');

  await expect(page).not.toHaveURL(/action=login/);

  await page.context().storageState({ path: 'tests/.auth/user.json' });
});
