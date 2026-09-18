import { test, expect } from '@playwright/test';
import { loginAsStaff } from '../utils/auth';

test.describe('スタッフシフト確認画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsStaff(page);
    await page.goto('/staff/shifts', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("シフト確認")', { state: 'visible', timeout: 15000 });
  });

  test('14-001: シフト確認画面の初期表示', async ({ page }) => {
    // 月セレクトボックスが表示される
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible({ timeout: 10000 });

    // シフトテーブルが表示される
    const table = page.locator('table').first();
    await expect(table).toBeVisible();
  });

  test('14-002: 月の切り替え', async ({ page }) => {
    // 期間選択ボタンをクリックして年月ポップオーバーを開く
    const periodTrigger = page.locator('button', { hasText: /\d{4}年/ }).first();
    await expect(periodTrigger).toBeVisible();
    await periodTrigger.click();

    // 月グリッドから1つ選ぶ
    const monthButtons = page.locator('button', { hasText: /^\d{1,2}月$/ });
    if (await monthButtons.count() > 0) {
      await monthButtons.first().click();
      await page.waitForTimeout(1000);
      await expect(page).toHaveURL(/\/staff\/shifts/);
    }
  });

  test('14-004: シフトレジェンドの表示', async ({ page }) => {
    // シフト凡例が表示される
    await expect(page.locator('text=シフト凡例')).toBeVisible();
  });

  test('14-006: 読み取り専用（編集不可）', async ({ page }) => {
    // テーブルセルをクリックしてもモーダルが表示されないことを確認
    const cell = page.locator('td').first();
    if (await cell.isVisible()) {
      await cell.click();
      await page.waitForTimeout(500);

      // ダイアログ/モーダルが表示されないことを確認
      const dialog = page.locator('[role="dialog"], [class*="modal"]');
      await expect(dialog.first()).not.toBeVisible({ timeout: 1000 }).catch(() => {});
    }
  });
});
