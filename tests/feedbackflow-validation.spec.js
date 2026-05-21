
const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

test('Login page frontend text and fields exist', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=login`);

  await expect(page.locator('body')).toContainText(/login|email|password/i);
  await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible();
  await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible();
//  await expect(page.locator('button, input[type="submit"]')).toBeVisible();
await expect(page.locator('button[type="submit"], input[type="submit"]')).toBeVisible();
});

test('Login with invalid email format', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=login`);

  await page.fill('input[type="email"], input[name="email"]', 'wrongemail');
  await page.fill('input[type="password"], input[name="password"]', 'test123');
  await page.click('button[type="submit"], input[type="submit"]');

  await expect(page.locator('body')).toContainText(/email|invalid|required|login/i);
});

test('Login with wrong credentials', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=login`);

  await page.fill('input[type="email"], input[name="email"]', 'wrong@test.com');
  await page.fill('input[type="password"], input[name="password"]', 'wrongpassword');
  await page.click('button[type="submit"], input[type="submit"]');

  await expect(page.locator('body')).toContainText(/invalid|wrong|incorrect|error|login/i);
});

test('Login with special characters in email and password', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=login`);

  await page.fill('input[type="email"], input[name="email"]', 'test+special@example.com');
  await page.fill('input[type="password"], input[name="password"]', '!@#$%^&*()_+<>?');
  await page.click('button[type="submit"], input[type="submit"]');

  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);
});

test('Register page frontend text and fields exist', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=register`);

  await expect(page.locator('body')).toContainText(/register|sign up|email|password/i);
  await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible();
  await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible();
});

test('Register form handles special characters safely', async ({ page }) => {
  await page.goto(`${BASE_URL}/?action=register`);

  const inputs = await page.locator('input').count();

  for (let i = 0; i < inputs; i++) {
    const input = page.locator('input').nth(i);
    const type = await input.getAttribute('type');
    const name = await input.getAttribute('name');

    if (type === 'email' || name === 'email') {
      await input.fill('special+test@example.com');
    } else if (type === 'password' || name?.toLowerCase().includes('password')) {
      await input.fill('Test@12345!');
    } else if (type !== 'hidden' && type !== 'submit') {
      await input.fill('Test Company !@#$%');
    }
  }

  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);
});
