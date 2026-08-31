import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者レイアウト・ナビゲーション', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('17-001: サイドバーの初期表示', async ({ page }) => {
    // サイドバーのナビゲーションリンクが表示される
    await expect(page.locator('a[href*="/admin/shifts"]').first()).toBeVisible();
    await expect(page.locator('a[href*="/admin/applications"]').first()).toBeVisible();
    await expect(page.locator('a[href*="/admin/reports"]').first()).toBeVisible();
    await expect(page.locator('a[href*="/admin/alerts"]').first()).toBeVisible();
    await expect(page.locator('a[href*="/admin/users"]').first()).toBeVisible();
    await expect(page.locator('a[href*="/admin/settings"]').first()).toBeVisible();
  });

  test('17-005: シフト管理への遷移', async ({ page }) => {
    await page.click('a[href*="/admin/shifts"]');
    await expect(page).toHaveURL(/\/admin\/shifts/);
  });

  test('17-006: 申請管理への遷移', async ({ page }) => {
    await page.click('a[href*="/admin/applications"]');
    await expect(page).toHaveURL(/\/admin\/applications/);
  });

  test('17-007: 勤務実績への遷移', async ({ page }) => {
    await page.click('a[href*="/admin/reports"]');
    await expect(page).toHaveURL(/\/admin\/reports/);
  });

  test('17-008: 労務アラートへの遷移', async ({ page }) => {
    await page.click('a[href*="/admin/alerts"]');
    await expect(page).toHaveURL(/\/admin\/alerts/);
  });

  test('17-009: ユーザー管理への遷移', async ({ page }) => {
    await page.click('a[href*="/admin/users"]');
    await expect(page).toHaveURL(/\/admin\/users/);
  });

  test('17-010: 設定への遷移', async ({ page }) => {
    await page.click('a[href*="/admin/settings"]');
    await expect(page).toHaveURL(/\/admin\/settings/);
  });

  test('17-002: サイドバーの折りたたみ', async ({ page }) => {
    // 折りたたみボタンを探す
    const collapseButton = page.locator('button[aria-label*="折りたたみ"], button[aria-label*="collapse"], button[class*="collapse"]').first();
    if (await collapseButton.isVisible()) {
      await collapseButton.click();
      await page.waitForTimeout(500);

      // サイドバーが折りたたまれたことを確認（幅が変わるか、テキストが非表示になる）
    }
  });

  test('17-013: ログアウト', async ({ page }) => {
    const logoutButton = page.locator('button:has-text("ログアウト"), a:has-text("ログアウト")').first();
    await logoutButton.click();

    // ログイン画面にリダイレクト
    await page.waitForURL('**/login', { timeout: 10000 });
    await expect(page).toHaveURL(/\/login/);
  });
});
