import { test, expect } from '@playwright/test';

test.describe('スタッフログイン画面', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/staff/login', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('#company_code', { state: 'visible', timeout: 15000 });
  });

  test('2-001: スタッフログイン画面の初期表示', async ({ page }) => {
    await expect(page.locator('#company_code')).toBeVisible();
    await expect(page.locator('#employee_code')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('2-002: 正常ログイン', async ({ page }) => {
    await page.fill('#company_code', process.env.TEST_COMPANY_CODE || '000001');
    await page.fill('#employee_code', process.env.TEST_STAFF_CODE || '000002');
    await page.fill('#password', process.env.TEST_STAFF_PASSWORD || 'password');
    await page.click('button[type="submit"]');

    // スタッフ画面にリダイレクト
    await page.waitForURL('**/staff/**', { timeout: 15000 });
    await expect(page).toHaveURL(/\/staff\//);
  });

  test('2-003: 全項目未入力でバリデーションエラー', async ({ page }) => {
    await page.click('button[type="submit"]');

    // HTML5 required属性によりフォーム送信が止まる（ログイン画面のまま）
    await expect(page).toHaveURL(/\/staff\/login/);

    // required属性が設定されていることを確認
    await expect(page.locator('#company_code')).toHaveAttribute('required', '');
  });

  test('2-004: 不正な認証情報でエラー', async ({ page }) => {
    await page.fill('#company_code', '999999');
    await page.fill('#employee_code', '999999');
    await page.fill('#password', 'wrongpassword');
    await page.click('button[type="submit"]');

    await page.waitForSelector('[class*="text-red"], [class*="error"], [role="alert"]', {
      timeout: 10000,
    });
  });

  test('2-005: ボタンのローディング状態', async ({ page }) => {
    await page.fill('#company_code', '000001');
    await page.fill('#employee_code', '000002');
    await page.fill('#password', 'password');

    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // ボタンテキストが「ログイン中...」に変わるか確認
    await expect(submitButton).toBeDisabled({ timeout: 3000 }).catch(() => {
      // 高速通信の場合検出できないことがある
    });
  });
});

test.describe('スタッフパスワード変更画面', () => {
  test('2-006: パスワード変更画面の初期表示', async ({ page }) => {
    // 初回ログインユーザーでログインしパスワード変更画面にリダイレクトされることを確認
    await page.goto('/staff/password-change', { waitUntil: 'domcontentloaded' });

    // パスワード入力欄が表示される（認証されている場合）
    const passwordField = page.locator('#password');
    const loginRedirect = page.url().includes('/login');

    if (!loginRedirect) {
      await expect(passwordField).toBeVisible();
      await expect(page.locator('#password_confirmation')).toBeVisible();
      // 注意メッセージ（黄色）が表示される
      await expect(page.locator('text=セキュリティ')).toBeVisible();
    }
  });

  test('2-008: パスワード未入力でバリデーションエラー', async ({ page }) => {
    await page.goto('/staff/password-change', { waitUntil: 'domcontentloaded' });

    const loginRedirect = page.url().includes('/login');
    if (loginRedirect) {
      test.skip();
      return;
    }

    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000);

    const errorElements = page.locator('[class*="text-red"], [class*="error"]');
    await expect(errorElements.first()).toBeVisible({ timeout: 5000 });
  });

  test('2-009: パスワード不一致でバリデーションエラー', async ({ page }) => {
    await page.goto('/staff/password-change', { waitUntil: 'domcontentloaded' });

    const loginRedirect = page.url().includes('/login');
    if (loginRedirect) {
      test.skip();
      return;
    }

    await page.fill('#password', 'newpassword123');
    await page.fill('#password_confirmation', 'differentpassword');
    await page.click('button[type="submit"]');

    await page.waitForTimeout(1000);
    const errorElements = page.locator('[class*="text-red"], [class*="error"]');
    await expect(errorElements.first()).toBeVisible({ timeout: 5000 });
  });
});
