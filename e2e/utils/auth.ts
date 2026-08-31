import { Page } from '@playwright/test';

/**
 * 管理者としてログイン
 */
export async function loginAsAdmin(page: Page) {
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });

  // ログインフォームが表示されるまで待機
  await page.waitForSelector('#company_code', { state: 'visible' });

  await page.fill('#company_code', process.env.TEST_COMPANY_CODE || '100001');
  await page.fill('#employee_code', process.env.TEST_ADMIN_CODE || '000001');
  await page.fill('#password', process.env.TEST_ADMIN_PASSWORD || 'password');

  await page.click('button[type="submit"]');

  // ダッシュボードにリダイレクトされるまで待機
  await page.waitForURL('**/admin/shifts', { timeout: 15000 });
}

/**
 * スタッフとしてログイン
 */
export async function loginAsStaff(page: Page) {
  await page.goto('/staff/login', { waitUntil: 'domcontentloaded' });

  await page.waitForSelector('#company_code', { state: 'visible' });

  await page.fill('#company_code', process.env.TEST_COMPANY_CODE || '100001');
  await page.fill('#employee_code', process.env.TEST_STAFF_CODE || '000002');
  await page.fill('#password', process.env.TEST_STAFF_PASSWORD || 'password');

  await page.click('button[type="submit"]');

  // スタッフ画面にリダイレクトされるまで待機
  await page.waitForURL('**/staff/stamp', { timeout: 15000 });
}

/**
 * マネージャーとしてログイン
 */
export async function loginAsManager(page: Page) {
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });

  await page.waitForSelector('#company_code', { state: 'visible' });

  await page.fill('#company_code', process.env.TEST_COMPANY_CODE || '100001');
  await page.fill('#employee_code', process.env.TEST_MANAGER_CODE || '000003');
  await page.fill('#password', process.env.TEST_MANAGER_PASSWORD || 'password');

  await page.click('button[type="submit"]');

  await page.waitForURL(/\/(admin|staff)\//, { timeout: 15000 });
}

/**
 * ログアウト
 */
export async function logout(page: Page) {
  await page.click('button:has-text("ログアウト"), a:has-text("ログアウト")');
  await page.waitForURL('**/login', { timeout: 10000 });
}
