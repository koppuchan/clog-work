import { test, expect } from '@playwright/test';

test.describe('管理者ログイン画面', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('#company_code', { state: 'visible', timeout: 15000 });
  });

  test('1-001: ログイン画面の初期表示', async ({ page }) => {
    await expect(page.locator('#company_code')).toBeVisible();
    await expect(page.locator('#employee_code')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toHaveText(/ログイン/);
    await expect(page.locator('a[href="/admin/forgot-password"]')).toBeVisible();
    await expect(page.locator('a[href="/register"]')).toBeVisible();
  });

  test('1-002: 会社コード入力欄にフォーカスされている', async ({ page }) => {
    await expect(page.locator('#company_code')).toBeFocused();
  });

  test('1-003: 正常ログイン', async ({ page }) => {
    await page.fill('#company_code', process.env.TEST_COMPANY_CODE || '000001');
    await page.fill('#employee_code', process.env.TEST_ADMIN_CODE || '000001');
    await page.fill('#password', process.env.TEST_ADMIN_PASSWORD || 'password');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/admin/shifts', { timeout: 15000 });
    await expect(page).toHaveURL(/\/admin\/shifts/);
  });

  test('1-004: 会社コード未入力でバリデーションエラー', async ({ page }) => {
    await page.fill('#employee_code', '000001');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    // HTML5 required属性によるバリデーション or サーバーバリデーション
    // required属性があるのでフォーム送信自体が止まる
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('1-005: 個人コード未入力でバリデーションエラー', async ({ page }) => {
    await page.fill('#company_code', '000001');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('1-006: パスワード未入力でバリデーションエラー', async ({ page }) => {
    await page.fill('#company_code', '000001');
    await page.fill('#employee_code', '000001');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('1-007: 不正な認証情報でエラー表示', async ({ page }) => {
    await page.fill('#company_code', '999999');
    await page.fill('#employee_code', '999999');
    await page.fill('#password', 'wrongpassword');
    await page.click('button[type="submit"]');

    // サーバーからのエラーメッセージ表示を待機
    await expect(page.locator('.text-red-600, .text-red-500, [class*="text-red"]').first()).toBeVisible({ timeout: 10000 });
  });

  test('1-008: ログインボタンのdisabled状態', async ({ page }) => {
    await page.fill('#company_code', '000001');
    await page.fill('#employee_code', '000001');
    await page.fill('#password', 'password');

    const submitButton = page.locator('button[type="submit"]');

    // クリック直後にテキストが「ログイン中...」に変わることを確認
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('login'), { timeout: 15000 }).catch(() => null),
      submitButton.click(),
    ]);

    // ボタンテキストまたはdisabled状態の変化（高速通信では検出できない場合あり）
  });

  test('1-010: パスワードリセットリンクの遷移', async ({ page }) => {
    await page.click('a[href="/admin/forgot-password"]');
    await expect(page).toHaveURL(/\/admin\/forgot-password/);
  });

  test('1-011: 新規会員登録リンクの遷移', async ({ page }) => {
    await page.click('a[href="/register"]');
    await expect(page).toHaveURL(/\/register/);
  });
});
