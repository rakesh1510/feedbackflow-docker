const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

test.use({
  storageState: 'tests/.auth/user.json'
});

test('Create 2 projects and submit feedback', async ({ page }) => {
  const projects = [
    'Auto Test Project 1',
    'Auto Test Project 2'
  ];

  for (const projectName of projects) {
    await page.goto(`${BASE_URL}/admin/projects.php`);

    await expect(page.locator('body')).toContainText(/projects/i);

    const createButton = page.locator('text=/new project|create project|add project/i').first();

    if (await createButton.count()) {
      await createButton.click();
    }

    await page.fill('input[name="name"], input[name="project_name"], input[placeholder*="Project"]', projectName);

    const submitButton = page.locator('button[type="submit"], input[type="submit"]').first();
    await submitButton.click();

    await page.waitForTimeout(1500);

    await expect(page.locator('body')).toContainText(projectName);
  }
});

test('Submit feedback through public board/form', async ({ page }) => {
  await page.goto(`${BASE_URL}/admin/projects.php`);

  await page.locator('text=/Open Dashboard/i').first().click();
  await page.waitForTimeout(1000);

  await page.goto(`${BASE_URL}/admin/channels.php`);

  await expect(page.locator('body')).toContainText(/channels|qr|feedback/i);

  const links = await page.locator('a[href*="feedback-link"], a[href*="public"]').evaluateAll(a =>
    a.map(link => link.href)
  );

  if (links.length === 0) {
    throw new Error('No public feedback link found on channels page');
  }

  const feedbackUrl = links[0];

  await page.goto(feedbackUrl);

  await expect(page.locator('body')).not.toBeEmpty();

  const textInput = page.locator('textarea, input[name="title"], input[name="message"], input[name="feedback"]').first();

  if (await textInput.count()) {
    await textInput.fill('This is an automated Playwright feedback test.');
  }

  const allInputs = page.locator('input');

  for (let i = 0; i < await allInputs.count(); i++) {
    const input = allInputs.nth(i);
    const type = await input.getAttribute('type');
    const name = await input.getAttribute('name');

    if (type === 'email' || name === 'email') {
      await input.fill('playwright-test@example.com');
    } else if (type !== 'hidden' && type !== 'submit') {
      const value = await input.inputValue().catch(() => '');
      if (!value) {
        await input.fill('Playwright Test');
      }
    }
  }

  await page.locator('button[type="submit"], input[type="submit"]').first().click();

  await page.waitForTimeout(2000);

  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);
});
