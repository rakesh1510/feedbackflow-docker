

const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

test.setTimeout(0);

test('Billing and usage stress test', async ({ page }) => {

  // LOGIN
  await page.goto(`${BASE_URL}/index.php`);

  await page.fill('input[name="email"]', 'rp1510sasasa90@gmail.com');
  await page.fill('input[name="password"]', 'rakeshrakesh');

  await page.click('button[type="submit"]');

  await page.waitForTimeout(4000);

  console.log('LOGIN SUCCESS');

  // ---------------------------------------------------
  // CREATE PROJECTS
  // ---------------------------------------------------

  for (let i = 1; i <= 5; i++) {

    console.log(`Creating project ${i}`);

    await page.goto(`${BASE_URL}/admin/projects.php`);

    await page.waitForTimeout(2000);

    // open create page
    await page.click('a[href*="project"]');

    await page.waitForTimeout(2000);

    await page.fill(
      'input[name="name"]',
      `Stress Project ${Date.now()} ${i}`
    );

    const desc = page.locator('textarea[name="description"]');

    if (await desc.count()) {
      await desc.fill('Created by Playwright stress test');
    }

    await page.click('button[type="submit"], input[type="submit"]');

    await page.waitForTimeout(3000);
  }

  console.log('PROJECT CREATION COMPLETE');

  // ---------------------------------------------------
  // GENERATE FEEDBACK
  // ---------------------------------------------------

  for (let i = 1; i <= 2000; i++) {

    await page.goto(
      `${BASE_URL}/public/feedback-link.php?project_id=8`
    );

    await page.waitForTimeout(500);

    // message
    const messageField = page.locator(
      'textarea[name="message"]'
    );

    if (await messageField.count()) {
      await messageField.fill(
        `Automated feedback ${i}`
      );
    }

    // name
    const nameField = page.locator(
      'input[name="name"]'
    );

    if (await nameField.count()) {
      await nameField.fill(`User ${i}`);
    }

    // email
    const emailField = page.locator(
      'input[name="email"]'
    );

    if (await emailField.count()) {
      await emailField.fill(`user${i}@test.com`);
    }

    // submit
    await page.click(
      'button[type="submit"], input[type="submit"]'
    );

    if (i % 100 === 0) {
      console.log(`Submitted ${i} feedback entries`);
    }
  }

  console.log('2000 FEEDBACK SUBMITTED');

  // ---------------------------------------------------
  // BILLING PAGE VALIDATION
  // ---------------------------------------------------

  await page.goto(`${BASE_URL}/admin/billing.php`);

  await page.waitForTimeout(5000);

  await page.screenshot({
    path: 'test-results/final-billing-stress.png',
    fullPage: true
  });

  // usage checks
  await expect(page.locator('body')).toContainText('/ 5');
  await expect(page.locator('body')).toContainText('/ 2,000');

  console.log('BILLING VALIDATION COMPLETE');

});
