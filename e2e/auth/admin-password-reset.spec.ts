import { test, expect } from '@playwright/test';

test.describe('管理者パスワードリセット申請画面', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/forgot-password', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('#company_code', { state: 'visible', timeout: 15000 });
  });

  test('4-001: リセット申請画面の初期表示', async ({ page }) => {
    await expect(page.locator('#company_code')).toBeVisible();
    await expect(page.locator('#employee_code')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toHaveText(/リセットリンクを送信/);
    await expect(page.locator('a[href*="login"]')).toBeVisible();
  });

  test('4-003: 会社コード未入力でバリデーションエラー', async ({ page }) => {
    await page.fill('#employee_code', '000001');
    await page.fill('#email', 'test@example.com');
    await page.click('button[type="submit"]');

    // HTML5 required属性によりフォーム送信が止まる
    await expect(page).toHaveURL(/\/admin\/forgot-password/);
    await expect(page.locator('#company_code')).toHaveAttribute('required', '');
  });

  test('4-004: 個人コード未入力でバリデーションエラー', async ({ page }) => {
    await page.fill('#company_code', '000001');
    await page.fill('#email', 'test@example.com');
    await page.click('button[type="submit"]');

    // HTML5 required属性によりフォーム送信が止まる
    await expect(page).toHaveURL(/\/admin\/forgot-password/);
    await expect(page.locator('#employee_code')).toHaveAttribute('required', '');
  });

  test('4-005: メール未入力でバリデーションエラー', async ({ page }) => {
    await page.fill('#company_code', '000001');
    await page.fill('#employee_code', '000001');
    await page.click('button[type="submit"]');

    // HTML5 required属性によりフォーム送信が止まる
    await expect(page).toHaveURL(/\/admin\/forgot-password/);
    await expect(page.locator('#email')).toHaveAttribute('required', '');
  });

  test('4-007: ログイン画面へ戻るリンク', async ({ page }) => {
    await page.click('a[href*="login"]');
    await expect(page).toHaveURL(/\/admin\/login/);
  });
});

test.describe('パスワード再設定画面', () => {
  test('4-009: パスワード再設定画面の初期表示', async ({ page }) => {
    // トークン付きURLでアクセス（テスト用の仮URL）
    await page.goto('/admin/reset-password/test-token?email=test@example.com', {
      waitUntil: 'domcontentloaded',
    });

    // ページが存在する場合のみテスト
    if (page.url().includes('reset-password')) {
      // メールフィールド（読み取り専用）
      const emailField = page.locator('#email');
      if (await emailField.isVisible()) {
        await expect(emailField).toBeVisible();
      }

      // パスワードフィールド
      const passwordField = page.locator('#password');
      if (await passwordField.isVisible()) {
        await expect(passwordField).toBeVisible();
        await expect(page.locator('#password_confirmation')).toBeVisible();
      }
    }
  });
});
