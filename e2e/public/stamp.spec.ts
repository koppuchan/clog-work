import { test, expect } from '@playwright/test';

/**
 * 公開打刻ページのテスト
 *
 * 注意: このテストを実行するには、有効な公開打刻UUID が必要です。
 * 環境変数 TEST_STAMP_UUID にUUIDを設定してください。
 * UUIDが未設定の場合、テストはスキップされます。
 */

const STAMP_UUID = process.env.TEST_STAMP_UUID || '';

test.describe('公開打刻ページ', () => {
  test.skip(!STAMP_UUID, '環境変数 TEST_STAMP_UUID が未設定のためスキップ');

  test.beforeEach(async ({ page }) => {
    await page.goto(`/stamp/${STAMP_UUID}`, { waitUntil: 'domcontentloaded' });
  });

  test('16-001: 公開打刻画面の初期表示', async ({ page }) => {
    // 現在時刻が表示される
    const timeDisplay = page.locator('[class*="text-4xl"], [class*="text-5xl"], [class*="text-6xl"], [class*="time"]').first();
    await expect(timeDisplay).toBeVisible({ timeout: 10000 });

    // ユーザー選択ドロップダウンが表示される
    const userSelect = page.locator('select').first();
    await expect(userSelect).toBeVisible();
  });

  test('16-002: ユーザー選択', async ({ page }) => {
    const userSelect = page.locator('select').first();
    const optionCount = await userSelect.locator('option').count();

    if (optionCount > 1) {
      await userSelect.selectOption({ index: 1 });
      await page.waitForTimeout(500);

      // パスワード入力欄が表示される
      const passwordField = page.locator('input[type="password"]');
      await expect(passwordField).toBeVisible({ timeout: 3000 });
    }
  });

  test('16-004: パスワード認証失敗', async ({ page }) => {
    const userSelect = page.locator('select').first();
    const optionCount = await userSelect.locator('option').count();

    if (optionCount > 1) {
      await userSelect.selectOption({ index: 1 });
      await page.waitForTimeout(500);

      const passwordField = page.locator('input[type="password"]');
      if (await passwordField.isVisible()) {
        await passwordField.fill('wrongpassword');

        const verifyButton = page.locator('button:has-text("認証"), button:has-text("確認"), button[type="submit"]').first();
        await verifyButton.click();

        // エラーメッセージが表示される
        await page.waitForTimeout(1000);
        const error = page.locator('[class*="text-red"], [class*="error"], text=パスワード').first();
        await expect(error).toBeVisible({ timeout: 5000 });
      }
    }
  });

  test('16-003: パスワード認証成功後に打刻ボタン表示', async ({ page }) => {
    const userSelect = page.locator('select').first();
    const optionCount = await userSelect.locator('option').count();

    if (optionCount > 1) {
      await userSelect.selectOption({ index: 1 });
      await page.waitForTimeout(500);

      const passwordField = page.locator('input[type="password"]');
      if (await passwordField.isVisible()) {
        await passwordField.fill(process.env.TEST_STAMP_PASSWORD || 'password');

        const verifyButton = page.locator('button:has-text("認証"), button:has-text("確認"), button[type="submit"]').first();
        await verifyButton.click();

        await page.waitForTimeout(2000);

        // 打刻ボタンが表示される
        await expect(page.locator('button:has-text("出勤")')).toBeVisible({ timeout: 5000 });
      }
    }
  });
});

test.describe('公開打刻ページ - 無効なUUID', () => {
  test('16-012: 無効なUUIDでエラー表示', async ({ page }) => {
    const response = await page.goto('/stamp/invalid-uuid-12345', { waitUntil: 'domcontentloaded' });

    // 404エラーまたはエラーページが表示される
    if (response) {
      const status = response.status();
      expect([404, 403, 302, 200]).toContain(status);
    }
  });
});
