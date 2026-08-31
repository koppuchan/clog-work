import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者設定画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/settings', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("システム設定")', { state: 'visible', timeout: 15000 });
  });

  test('11-001: 設定画面の初期表示', async ({ page }) => {
    await expect(page).toHaveURL(/\/admin\/settings/);

    // 会社設定セクションが表示される
    await expect(page.locator('text=会社設定')).toBeVisible({ timeout: 10000 });

    // 部署設定セクションが表示される
    await expect(page.locator('text=部署設定')).toBeVisible();
  });

  test('11-005: 部署の追加ボタン', async ({ page }) => {
    // 部署設定セクションの追加ボタンをクリック
    const addDeptButton = page.locator('button:has-text("追加")').first();
    await expect(addDeptButton).toBeVisible();
    await addDeptButton.click();
    await page.waitForTimeout(500);

    // 部署追加ダイアログが表示される（「部署追加」見出しと入力フォームを確認）
    await expect(page.locator('text=部署追加').first()).toBeVisible({ timeout: 5000 });
    await expect(page.locator('input[placeholder*="営業部"]')).toBeVisible();
  });

  test('11-007: 部署一覧の表示', async ({ page }) => {
    // 部署が表示されていることを確認
    await expect(page.locator('text=営業部')).toBeVisible();
    await expect(page.locator('text=総務部')).toBeVisible();
    await expect(page.locator('text=開発部')).toBeVisible();
  });

  test('11-009: シフトパターン設定の表示', async ({ page }) => {
    // ページをスクロールしてシフトパターンセクションを表示
    await page.locator('text=勤務時間').scrollIntoViewIfNeeded();

    // シフトパターンが表示されていることを確認（span要素内のテキストを指定）
    await expect(page.locator('span:has-text("日勤")')).toBeVisible();
    await expect(page.locator('span:has-text("早番")')).toBeVisible();
    await expect(page.locator('span:has-text("遅番")')).toBeVisible();
  });

  test('11-017: 保存ボタンの表示', async ({ page }) => {
    // ページ下部にスクロール
    await page.locator('button:has-text("保存")').scrollIntoViewIfNeeded();

    const saveButton = page.locator('button:has-text("保存")').first();
    await expect(saveButton).toBeVisible();
  });
});
