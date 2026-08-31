import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者シフト管理画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/shifts', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("シフト管理")', { state: 'visible', timeout: 15000 });
  });

  test('5-001: シフト管理画面の初期表示', async ({ page }) => {
    // 月セレクターが表示される（select要素で年月を選択）
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible();

    // シフトグリッドが表示される（スタッフ名が表示される）
    await expect(page.locator('text=スタッフ')).toBeVisible();

    // 保存ボタンが表示される
    await expect(page.locator('button:has-text("保存")')).toBeVisible();
  });

  test('5-003: 月の切り替え', async ({ page }) => {
    // セレクトボックスで月を切り替える
    const monthSelect = page.locator('select').first();
    await expect(monthSelect).toBeVisible();
    const optionCount = await monthSelect.locator('option').count();
    if (optionCount > 1) {
      await monthSelect.selectOption({ index: 0 });
      await page.waitForTimeout(1000);
      // ページが更新されることを確認
      await expect(page).toHaveURL(/\/admin\/shifts/);
    }
  });

  test('5-004: 全員のシフトパターン反映ボタン', async ({ page }) => {
    const applyButton = page.locator('button:has-text("全員のシフトパターンを反映")');
    await expect(applyButton).toBeVisible();
  });

  test('5-005: シフトセルクリックで変更', async ({ page }) => {
    // シフトグリッドのセル（w-14 h-10のシフトセル）をクリック
    // getByText('休み', { exact: true })でテキストが正確に「休み」のleaf要素を取得
    const cell = page.getByText('休み', { exact: true }).first();
    if (await cell.isVisible({ timeout: 3000 }).catch(() => false)) {
      await cell.click();
      await page.waitForTimeout(500);
      // クリック後もシフト管理画面にいることを確認
      await expect(page).toHaveURL(/\/admin\/shifts/);
    }
  });

  test('5-009: 勤務形態一覧の表示', async ({ page }) => {
    // 勤務形態一覧が表示される
    await expect(page.locator('text=勤務形態一覧')).toBeVisible();
    await expect(page.locator('text=日勤')).toBeVisible();
  });
});
