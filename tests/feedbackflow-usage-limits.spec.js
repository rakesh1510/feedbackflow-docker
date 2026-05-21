const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

test.setTimeout(120000);

test('Project limit redirects to billing page', async ({ page }) => {
  await page.goto(`${BASE_URL}/index.php`);

  await page.fill('input[name="email"]', 'playwright_1779095233467_256148@example.com');
  await page.fill('input[name="password"]', 'Test@123456!');
  await page.click('button[type="submit"]');

  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(3000);

  console.log('LOGIN URL:', page.url());

  for (let i = 1; i <= 7; i++) {
    console.log(`Trying to create project ${i}`);

    await page.goto(`${BASE_URL}/admin/projects.php?action=new`, {
      waitUntil: 'domcontentloaded'
    });

    await page.waitForTimeout(1000);

    const bodyText = await page.locator('body').innerText();

    if (bodyText.includes('Need more capacity') || page.url().includes('billing.php')) {
      console.log('Project limit reached. Redirected to billing.');
      break;
    }

    await page.fill('input[name="name"]', `Limit Test Project ${Date.now()} ${i}`);
    await page.fill('textarea[name="description"]', 'Created by Playwright billing limit test');
    await page.fill('input[name="website"]', 'https://trustnovatech.de');

    await page.getByRole('button', { name: /Create Project/i }).click();

    await page.waitForTimeout(2500);

    console.log(`After project ${i}, URL:`, page.url());

    if (page.url().includes('billing.php')) {
      console.log('Redirected to billing after limit reached.');
      break;
    }
  }

  await page.goto(`${BASE_URL}/admin/billing.php`, {
    waitUntil: 'domcontentloaded'
  });

  await page.waitForTimeout(3000);

  const billingText = await page.locator('body').innerText();
  console.log('BILLING PAGE TEXT:', billingText);

  await page.screenshot({
    path: 'test-results/project-limit-billing-page.png',
    fullPage: true
  });

  await expect(page.locator('body')).toContainText('Usage This Period');
  await expect(page.locator('body')).toContainText('Projects');
  await expect(page.locator('body')).toContainText('/ 5');
  await expect(page.locator('body')).toContainText('Need more capacity');

  console.log('✅ Project billing limit test passed');
});