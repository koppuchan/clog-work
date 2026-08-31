import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者勤務実績画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/reports', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("勤務実績")', { state: 'visible', timeout: 15000 });
  });

  test('9-001: 勤務実績画面の初期表示', async ({ page }) => {
    // 対象期間セレクターが表示される
    await expect(page.locator('text=対象期間')).toBeVisible({ timeout: 10000 });

    // テーブルが表示される
    const table = page.locator('table').first();
    await expect(table).toBeVisible();
  });

  test('9-002: 月の切り替え', async ({ page }) => {
    // 対象期間のセレクトボックスで月を切り替える
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible();
    const optionCount = await monthSelect.locator('option').count();
    if (optionCount > 1) {
      await monthSelect.selectOption({ index: 0 });
      await page.waitForTimeout(1000);
      await expect(page).toHaveURL(/\/admin\/reports/);
    }
  });

  test('9-003: 従業員選択', async ({ page }) => {
    // 従業員セレクトボックスが表示される
    await expect(page.locator('text=従業員')).toBeVisible();
    const userSelect = page.locator('select').nth(1);
    if (await userSelect.isVisible()) {
      const optionCount = await userSelect.locator('option').count();
      if (optionCount > 1) {
        await userSelect.selectOption({ index: 1 });
        await page.waitForTimeout(1000);
        await expect(page.locator('table').first()).toBeVisible();
      }
    }
  });

  test('9-004: 出力ボタンの表示', async ({ page }) => {
    const exportButton = page.locator('button:has-text("出力")');
    await expect(exportButton).toBeVisible();
  });

  test('9-009: 勤務実績の登録ボタン', async ({ page }) => {
    // テーブル内の「登録」ボタンが表示される
    const registerButton = page.locator('button:has-text("登録")').first();
    await expect(registerButton).toBeVisible();
  });
});
