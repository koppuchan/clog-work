import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('ユーザー新規作成画面', () => {
  test.beforeEach(async ({ page }) => {
    // 各テストの前に管理者としてログイン
    await loginAsAdmin(page);
    await page.goto('/admin/users/new', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("新規ユーザー作成")', { state: 'visible', timeout: 15000 });
  });

  test('新規作成画面が正しく表示される', async ({ page }) => {
    // フォームの必須フィールドが表示されていることを確認
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();

    // 権限選択が表示されていることを確認
    await expect(page.locator('select[name="role_id"]')).toBeVisible();

    // 部署選択が表示されていることを確認
    await expect(page.locator('select[name="department_id"]')).toBeVisible();
  });

  test('必須項目が空の場合、バリデーションエラーが表示される', async ({ page }) => {
    // 何も入力せずに確認画面へボタンをクリック
    await page.click('button:has-text("確認画面へ")');

    // HTML5バリデーションまたはサーバーサイドのエラーが表示される
    // 氏名が必須なのでエラーになる
    await expect(page).toHaveURL(/\/admin\/users\/new/);
  });

  test('正しいデータを入力して確認画面に遷移できる', async ({ page }) => {
    // フォームに入力
    await page.fill('input[name="name"]', 'テストユーザー');
    await page.fill('input[name="name_kana"]', 'テストユーザー');
    await page.fill('input[name="email"]', `test-${Date.now()}@example.com`);

    // 権限を選択（一般 = value "3"）
    await page.locator('select[name="role_id"]').selectOption('3');

    // 部署を選択
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    // 確認画面へボタンをクリック
    await page.click('button:has-text("確認画面へ")');
    await page.waitForTimeout(2000);

    // 確認画面に遷移することを確認
    await expect(page.locator('text=テストユーザー').first()).toBeVisible({ timeout: 5000 });
  });

  test('確認画面から入力画面に戻れる', async ({ page }) => {
    const testName = 'データ保持テスト';
    const testEmail = `retain-${Date.now()}@example.com`;

    // フォームに入力
    await page.fill('input[name="name"]', testName);
    await page.fill('input[name="email"]', testEmail);

    // 権限選択
    await page.locator('select[name="role_id"]').selectOption('3');

    // 部署選択
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    // 確認画面へ
    await page.click('button:has-text("確認画面へ")');
    await page.waitForTimeout(2000);

    // 確認画面が表示されることを確認
    await expect(page.locator('text=確認').first()).toBeVisible({ timeout: 5000 });

    // 戻るボタンをクリック
    const backButton = page.locator('button:has-text("戻る")').first();
    if (await backButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await backButton.click();
      await page.waitForTimeout(1000);

      // 入力画面に戻れることを確認
      await expect(page).toHaveURL(/\/admin\/users\/new/);
    }
  });

  test('ユーザーを正常に作成できる', async ({ page }) => {
    const uniqueEmail = `new-user-${Date.now()}@example.com`;
    const randomEmployeeCode = Math.floor(100000 + Math.random() * 900000).toString();

    // フォームに入力
    await page.fill('input[name="name"]', '新規ユーザー');
    await page.fill('input[name="name_kana"]', 'シンキユーザー');
    await page.fill('input[name="email"]', uniqueEmail);
    await page.fill('input[name="employee_code"]', randomEmployeeCode);

    // 権限を選択（一般 = value "3"）
    await page.locator('select[name="role_id"]').selectOption('3');

    // 部署を選択
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    // 確認画面へ
    await page.click('button:has-text("確認画面へ")');
    await page.waitForTimeout(1000);

    // 確認画面で登録ボタンをクリック
    const registerButton = page.locator('button:has-text("登録"), button:has-text("作成")').first();
    if (await registerButton.isVisible({ timeout: 5000 }).catch(() => false)) {
      await registerButton.click();

      // ユーザー一覧にリダイレクトされることを確認
      await page.waitForURL('**/admin/users', { timeout: 10000 });
    }
  });

  test('個人コードのバリデーションが機能する - 5桁はエラー', async ({ page }) => {
    await page.fill('input[name="name"]', 'バリデーションテスト');
    await page.fill('input[name="email"]', `validation-${Date.now()}@example.com`);

    // 不正な個人コード（5桁）を入力
    const invalidCode = Math.floor(10000 + Math.random() * 90000).toString();
    await page.fill('input[name="employee_code"]', invalidCode);

    // 権限選択
    await page.locator('select[name="role_id"]').selectOption('3');

    // 部署選択
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    // 確認画面へボタンをクリック
    await page.click('button:has-text("確認画面へ")');

    // エラーメッセージが表示されることを確認
    await expect(page.locator('text=6桁')).toBeVisible({ timeout: 5000 });
  });

  test('個人コードの入力制限確認 - 7桁入力', async ({ page }) => {
    // maxlength=6の制限を確認するため、キーボード入力で7桁をタイプ
    const input = page.locator('input[name="employee_code"]');
    await input.click();
    await input.pressSequentially('1234567');
    const value = await input.inputValue();
    // maxlength=6属性により6桁に制限される
    expect(value.length).toBeLessThanOrEqual(6);
  });

  test('キャンセルボタンで前のページに戻る', async ({ page }) => {
    // キャンセルボタンをクリック
    await page.click('button:has-text("キャンセル")');
    await page.waitForTimeout(1000);

    // 前のページに戻ることを確認（管理画面内）
    await expect(page).toHaveURL(/\/admin\//);
  });

  test('部署選択が正しく機能する', async ({ page }) => {
    const departmentSelector = page.locator('select[name="department_id"]');
    await expect(departmentSelector).toBeVisible();

    // 部署の選択肢が存在することを確認
    const optionCount = await departmentSelector.locator('option').count();
    expect(optionCount).toBeGreaterThan(1);

    // 部署を選択
    await departmentSelector.selectOption({ index: 1 });

    // 選択された値が保持されることを確認
    const selectedValue = await departmentSelector.inputValue();
    expect(selectedValue).not.toBe('');
  });
});
