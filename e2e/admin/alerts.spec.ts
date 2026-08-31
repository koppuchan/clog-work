import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者労務アラート画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/alerts', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("労務アラート")', { state: 'visible', timeout: 15000 });
  });

  test('10-001: 労務アラート画面の初期表示', async ({ page }) => {
    // ページが正しく表示される
    await expect(page).toHaveURL(/\/admin\/alerts/);

    // 統計カードが表示される
    await expect(page.locator('text=総アラート数')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=警告').first()).toBeVisible();
    await expect(page.locator('text=注意').first()).toBeVisible();
  });

  test('10-002: 月の切り替え', async ({ page }) => {
    // セレクトボックスで月を切り替える
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible();
    const optionCount = await monthSelect.locator('option').count();
    if (optionCount > 1) {
      await monthSelect.selectOption({ index: 1 });
      await page.waitForTimeout(1000);
      await expect(page).toHaveURL(/\/admin\/alerts/);
    }
  });

  test('10-003: 警告レベルフィルター', async ({ page }) => {
    // フィルターボタンが表示される
    await expect(page.locator('button:has-text("すべて")')).toBeVisible();
    await expect(page.locator('button:has-text("警告")')).toBeVisible();
    await expect(page.locator('button:has-text("注意")')).toBeVisible();

    // 警告フィルターをクリック
    await page.click('button:has-text("警告")');
    await page.waitForTimeout(1000);
    await expect(page).toHaveURL(/\/admin\/alerts/);
  });

  test('10-005: アラート一覧の表示', async ({ page }) => {
    // アラート一覧セクションが表示される
    await expect(page.locator('text=アラート一覧')).toBeVisible();
  });
});
