const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

test.setTimeout(90000);

test('Register, login, create project, submit feedback and verify', async ({ page }) => {
  const password = 'Test@123456!';
  const baseTimestamp = Date.now();

  let finalEmail = '';
  let finalCompanyName = '';

  const projectName = `Playwright Project ${baseTimestamp}`;
  const feedbackText = `Automated feedback from Playwright ${baseTimestamp}`;

  // =========================================================
// 1. REGISTER
// =========================================================

await page.context().clearCookies();

//let finalEmail = '';
//let finalCompanyName = '';
let finalUserName = '';

async function registerUser() {

  const ts = Date.now();
  const rand = Math.floor(Math.random() * 1000000);

  finalEmail = `playwright_${ts}_${rand}@example.com`;
  finalCompanyName = `abdddd Company ${ts} ${rand}`;
  finalUserName = `PW User ${rand}`;

  await page.goto(`${BASE_URL}/?action=register`, {
    waitUntil: 'domcontentloaded',
  });

  await page.fill('input[name="name"]', finalUserName);
  await page.fill('input[name="email"]', finalEmail);
  await page.fill('input[name="company_name"]', finalCompanyName);
  await page.fill('input[name="password"]', password);

  console.log('EMAIL:', finalEmail);
  console.log('COMPANY:', finalCompanyName);
  console.log('USER:', finalUserName);

  await page.locator(
    'button[type="submit"], input[type="submit"]'
  ).first().click();

  await page.waitForTimeout(3000);
}

// first try
await registerUser();

// if still on register page => create completely new user/company
if (page.url().includes('action=register')) {

  console.log('Registration failed. Creating completely new user/company...');

  await page.context().clearCookies();

  await registerUser();
}

console.log('AFTER REGISTER URL:', page.url());
console.log('AFTER REGISTER TEXT:', await page.locator('body').innerText());

await expect(page.locator('body')).not.toContainText(
  /already exists|fatal error|sql syntax|warning|notice/i
);
  // 2. Login
  await page.goto(`${BASE_URL}/?action=login`);

  await page.fill('input[type="email"], input[name="email"]', finalEmail);
  await page.fill('input[type="password"], input[name="password"]', password);

  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForTimeout(3000);

  console.log('AFTER LOGIN URL:', page.url());
  console.log('AFTER LOGIN TEXT:', await page.locator('body').innerText());

  await expect(page.locator('body')).not.toContainText(/invalid email or password/i);
  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);

  // 3. Go to projects page
  await page.goto(`${BASE_URL}/admin/projects.php`);
  await expect(page.locator('body')).toContainText(/projects/i);

  // 4. Create project
  const createBtn = page.locator('text=/new project|create project|add project/i').first();

  if (await createBtn.count()) {
    await createBtn.click();
    await page.waitForTimeout(1000);
  }

  const projectInput = page.locator(
    'input[name="name"], input[name="project_name"], input[placeholder*="Project"], input[placeholder*="project"]'
  ).first();

  if (await projectInput.count()) {
    await projectInput.fill(projectName);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForTimeout(2000);
  }

  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);

  // 5. Open channels page / QR page
  await page.goto(`${BASE_URL}/admin/channels.php?tab=qr`);
  await expect(page.locator('body')).toContainText(/qr|feedback|channel/i);

  // 6. Find public feedback-link.php only
  const publicLinks = await page
    .locator('a[href*="public"], a[href*="feedback-link"]')
    .evaluateAll(links => links.map(a => a.href));

  console.log('PUBLIC LINKS:', publicLinks);

  const feedbackUrl = publicLinks.find(link =>
    link.includes('/public/feedback-link.php')
  );

  if (!feedbackUrl) {
    throw new Error('No feedback-link.php URL found');
  }

  console.log('SELECTED FEEDBACK URL:', feedbackUrl);

  // 7. Open public feedback page
  const feedbackResponse = await page.goto(feedbackUrl, {
    waitUntil: 'domcontentloaded',
  });

  console.log('FEEDBACK URL STATUS:', feedbackResponse.status());
  console.log('FEEDBACK PAGE URL:', page.url());
  console.log('FEEDBACK PAGE BODY:', await page.locator('body').innerText());

  expect(feedbackResponse.status()).toBeLessThan(500);

  await expect(page.locator('body')).not.toBeEmpty();
  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);

  // 8. Fill feedback form
  const titleInput = page.locator('input[name="title"]').first();
  if (await titleInput.count()) {
    await titleInput.fill(feedbackText);
  }

  const descriptionTextarea = page.locator('textarea[name="description"], textarea').first();
  if (await descriptionTextarea.count()) {
    await descriptionTextarea.fill(feedbackText);
  } else {
    throw new Error('No feedback textarea found.');
  }

  const ratingFiveLabel = page.locator('text="5 ★"').first();

if (await ratingFiveLabel.count()) {
  await ratingFiveLabel.click();
}

  const nameInput = page.locator('input[name="submitter_name"]').first();
  if (await nameInput.count()) {
    await nameInput.fill('Playwright Customer');
  }

  const emailInput = page.locator('input[name="submitter_email"], input[type="email"]').first();
  if (await emailInput.count()) {
    await emailInput.fill(finalEmail);
  }

  // 9. Submit feedback
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForTimeout(3000);

  await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|warning|notice/i);
  await expect(page.locator('body')).toContainText(/thank you|submitted successfully|success/i);

  // 10. Verify feedback in admin
  await page.goto(`${BASE_URL}/admin/feedback.php`);

  await expect(page.locator('body')).toContainText(/feedback/i);
  await expect(page.locator('body')).toContainText(feedbackText);
});