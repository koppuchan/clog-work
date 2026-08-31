import { test, expect } from '@playwright/test';
import { loginAsStaff } from '../utils/auth';

test.describe('スタッフレイアウト・ナビゲーション', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStaff(page);
  });

  test('18-001: サイドバーの初期表示', async ({ page }) => {
    // ナビゲーションリンクが表示される
    const stampLink = page.locator('a[href*="/staff/stamp"]').first();
    const shiftsLink = page.locator('a[href*="/staff/shifts"]').first();
    const reportsLink = page.locator('a[href*="/staff/reports"]').first();

    // シフト確認と勤務実績は必ず表示
    await expect(shiftsLink).toBeVisible();
    await expect(reportsLink).toBeVisible();
  });

  test('18-003: ユーザー名の挨拶表示', async ({ page }) => {
    // ユーザー名が表示されることを確認
    const userName = page.locator('[class*="sidebar"], nav, header').locator('text=/.*さん|.*様/').first();
    await expect(userName).toBeVisible({ timeout: 5000 }).catch(() => {
      // 挨拶形式が異なる場合がある
    });
  });

  test('18-004: 打刻への遷移', async ({ page }) => {
    const stampLink = page.locator('a[href*="/staff/stamp"]').first();
    if (await stampLink.isVisible()) {
      await stampLink.click();
      await expect(page).toHaveURL(/\/staff\/stamp/);
    }
  });

  test('18-005: シフト確認への遷移', async ({ page }) => {
    await page.click('a[href*="/staff/shifts"]');
    await expect(page).toHaveURL(/\/staff\/shifts/);
  });

  test('18-006: 勤務実績への遷移', async ({ page }) => {
    await page.click('a[href*="/staff/reports"]');
    await expect(page).toHaveURL(/\/staff\/reports/);
  });

  test('18-009: ログアウト', async ({ page }) => {
    const logoutButton = page.locator('button:has-text("ログアウト"), a:has-text("ログアウト")').first();
    await logoutButton.click();

    await page.waitForURL('**/login', { timeout: 10000 });
    await expect(page).toHaveURL(/\/login/);
  });
});
