
const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://187.77.184.173/feedbackflow';

const adminPages = [
  '/admin/index.php',
  '/admin/feedback.php',
  '/admin/tasks.php',
  '/admin/notifications.php',
  '/admin/projects.php',
  '/admin/channels.php',
  '/admin/widget.php',
  '/admin/email-campaigns.php',
  '/admin/review-booster.php',
  '/admin/suppression.php',
  '/admin/automations.php',
  '/admin/roadmap.php',
  '/admin/changelog.php',
  '/admin/status.php',
  '/admin/analytics.php',
  '/admin/reports.php',
  '/admin/settings.php',
  '/admin/profile.php',
  '/admin/integrations.php',
  '/admin/api-keys.php',
  '/admin/ai-copilot.php',
  '/admin/ai-insights.php'
];

for (const path of adminPages) {
  test(`Admin page loads safely: ${path}`, async ({ page }) => {
    const response = await page.goto(`${BASE_URL}${path}`, {
      waitUntil: 'domcontentloaded'
    });

    expect(response.status()).toBeLessThan(500);
    await expect(page.locator('body')).not.toBeEmpty();
    await expect(page.locator('body')).not.toContainText(/fatal error|sql syntax|undefined variable|parse error/i);
  });
}
