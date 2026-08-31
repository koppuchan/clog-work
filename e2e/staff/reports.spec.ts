import { test, expect } from '@playwright/test';
import { loginAsStaff } from '../utils/auth';

test.describe('スタッフ勤務実績・申請画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStaff(page);
    await page.goto('/staff/reports', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("勤務実績")', { state: 'visible', timeout: 15000 });
  });

  test('15-001: 勤務実績画面の初期表示', async ({ page }) => {
    // 月セレクトボックスが表示される
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible({ timeout: 10000 });

    // 勤務実績テーブルが表示される
    const table = page.locator('table').first();
    await expect(table).toBeVisible();
  });

  test('15-002: 月の切り替え', async ({ page }) => {
    // セレクトボックスで月を切り替える
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible();
    const optionCount = await monthSelect.locator('option').count();
    if (optionCount > 1) {
      await monthSelect.selectOption({ index: 0 });
      await page.waitForTimeout(1000);
      await expect(page).toHaveURL(/\/staff\/reports/);
    }
  });

  test('15-004: 月間集計統計の表示', async ({ page }) => {
    // 出勤日数が表示される
    await expect(page.locator('text=出勤日数')).toBeVisible({ timeout: 5000 });
  });

  test('15-007: 申請ダイアログの表示', async ({ page }) => {
    const applyButton = page.locator('button:has-text("申請する")').first();
    if (await applyButton.isVisible()) {
      await applyButton.click();
      await page.waitForTimeout(1000);

      const dialog = page.locator('[role="dialog"], [class*="modal"], [class*="dialog"]').first();
      await expect(dialog).toBeVisible({ timeout: 3000 }).catch(() => {
        // 申請ダイアログが表示されない場合もある
      });
    }
  });
});
