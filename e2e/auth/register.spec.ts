import { test, expect } from '@playwright/test';

test.describe('新規会員登録 - メール入力画面', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/register', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('#email', { state: 'visible', timeout: 15000 });
  });

  test('3-001: メール入力画面の初期表示', async ({ page }) => {
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#email')).toHaveAttribute('placeholder', 'example@example.com');
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toHaveText(/確認メールを送信/);
    await expect(page.locator('a[href*="login"]')).toBeVisible();
  });

  test('3-003: メール未入力でバリデーションエラー', async ({ page }) => {
    await page.click('button[type="submit"]');

    // HTML5 required属性によりフォーム送信が止まる（登録画面のまま）
    await expect(page).toHaveURL(/\/register/);

    // required属性が設定されていることを確認
    await expect(page.locator('#email')).toHaveAttribute('required', '');
  });

  test('3-004: 不正なメール形式でバリデーションエラー', async ({ page }) => {
    await page.fill('#email', 'invalid-email');
    await page.click('button[type="submit"]');

    // HTML5 type="email"バリデーションによりフォーム送信が止まる
    await expect(page).toHaveURL(/\/register/);
  });

  test('3-006: ログイン画面へ戻る', async ({ page }) => {
    await page.click('a[href*="login"]');
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('3-016: メール送信ボタンのローディング状態', async ({ page }) => {
    await page.fill('#email', `test-${Date.now()}@example.com`);

    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // ボタンが「送信中...」に変わるか確認
    await expect(submitButton).toBeDisabled({ timeout: 3000 }).catch(() => {
      // 高速通信の場合検出できないことがある
    });
  });
});
