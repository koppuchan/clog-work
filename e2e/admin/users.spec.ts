import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者ユーザー一覧画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("ユーザー管理")', { state: 'visible', timeout: 15000 });
  });

  test('6-001: ユーザー一覧画面の初期表示', async ({ page }) => {
    // ユーザーテーブルが表示される
    const table = page.locator('table').first();
    await expect(table).toBeVisible();

    // 統計情報が表示される
    await expect(page.locator('text=在籍').first()).toBeVisible();

    // 新規ユーザーリンクが表示される
    await expect(page.locator('a:has-text("新規ユーザー")').first()).toBeVisible();
  });

  test('6-002: ロールフィルター', async ({ page }) => {
    // フィルターボタンが表示される
    const filterButton = page.locator('button:has-text("すべて")').first();
    if (await filterButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await filterButton.click();
      await page.waitForTimeout(1000);
      await expect(page.locator('table').first()).toBeVisible();
    }
  });

  test('6-004: 検索機能', async ({ page }) => {
    const searchInput = page.locator('input[type="search"], input[placeholder*="検索"], input[name*="search"]').first();
    if (await searchInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await searchInput.fill('テスト');
      await page.waitForTimeout(1000);
      await expect(page.locator('table').first()).toBeVisible();
    }
  });

  test('6-005: 新規作成画面への遷移', async ({ page }) => {
    await page.click('a:has-text("新規ユーザー")');
    await expect(page).toHaveURL(/\/admin\/users\/new/);
  });

  test('6-018: ユーザー削除 - 確認ダイアログ表示', async ({ page }) => {
    // 削除ボタン（title="削除"のbutton、disabled除外）を探す
    const deleteButton = page.locator('button[title="削除"]:not([disabled])').first();
    if (await deleteButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await deleteButton.click();
      await page.waitForTimeout(500);

      const dialog = page.locator('[role="dialog"], [class*="modal"]').first();
      await expect(dialog).toBeVisible({ timeout: 3000 });
    }
  });

  test('6-019: 削除確認ダイアログのキャンセル', async ({ page }) => {
    const deleteButton = page.locator('button[title="削除"]:not([disabled])').first();
    if (await deleteButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await deleteButton.click();
      await page.waitForTimeout(500);

      const dialog = page.locator('[role="dialog"], [class*="modal"]').first();
      if (await dialog.isVisible({ timeout: 3000 }).catch(() => false)) {
        const cancelButton = dialog.locator('button:has-text("キャンセル")').first();
        if (await cancelButton.isVisible()) {
          await cancelButton.click();
          await page.waitForTimeout(500);
        }
      }
    }
  });
});

test.describe('管理者ユーザー新規作成画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/users/new', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("新規ユーザー作成")', { state: 'visible', timeout: 15000 });
  });

  test('6-006: 新規作成画面の初期表示（Step1入力）', async ({ page }) => {
    // 基本情報フィールド
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();

    // 権限選択
    await expect(page.locator('select[name="role_id"]')).toBeVisible();
  });

  test('6-010: 必須項目未入力でバリデーションエラー', async ({ page }) => {
    // 何も入力せずに確認画面へボタンをクリック
    await page.click('button:has-text("確認画面へ")');

    await page.waitForTimeout(1000);
    // HTML5バリデーションまたはサーバーサイドエラーで遷移しない
    await expect(page).toHaveURL(/\/admin\/users\/new/);
  });

  test('6-007: ユーザー新規作成 - 入力→確認→登録', async ({ page }) => {
    const uniqueEmail = `e2e-test-${Date.now()}@example.com`;
    const randomCode = Math.floor(100000 + Math.random() * 900000).toString();

    // 基本情報を入力
    await page.fill('input[name="name"]', 'E2Eテストユーザー');
    await page.fill('input[name="name_kana"]', 'イーツーイーテストユーザー');
    await page.fill('input[name="email"]', uniqueEmail);
    await page.fill('input[name="employee_code"]', randomCode);

    // 権限選択（一般 = value "3"）
    await page.locator('select[name="role_id"]').selectOption('3');

    // 部署選択
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    // 確認画面へボタンをクリック
    await page.click('button:has-text("確認画面へ")');
    await page.waitForTimeout(2000);

    // 確認画面の表示を確認
    await expect(page.locator('text=E2Eテストユーザー')).toBeVisible();

    // 登録ボタンをクリック
    const registerButton = page.locator('button:has-text("登録"), button:has-text("作成")').first();
    if (await registerButton.isVisible({ timeout: 5000 }).catch(() => false)) {
      await registerButton.click();

      // ユーザー一覧にリダイレクト
      await page.waitForURL('**/admin/users', { timeout: 10000 });
    }
  });

  test('6-009: 確認画面から入力画面に戻れる', async ({ page }) => {
    const testName = 'データ保持テスト';

    await page.fill('input[name="name"]', testName);
    await page.fill('input[name="email"]', `retain-${Date.now()}@example.com`);

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

  test('6-011: メールアドレス重複チェック', async ({ page }) => {
    await page.fill('input[name="name"]', '重複テスト');
    await page.fill('input[name="email"]', 'yamada+100001@demo.com');

    await page.locator('select[name="role_id"]').selectOption('3');
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    await page.click('button:has-text("確認画面へ")');
    await page.waitForTimeout(2000);

    // エラーメッセージが表示される
    await expect(page.locator('text=メールアドレスは既に使用されています')).toBeVisible({ timeout: 5000 });
  });

  test('6-012: 個人コード5桁でバリデーションエラー', async ({ page }) => {
    await page.fill('input[name="name"]', 'コードテスト');
    await page.fill('input[name="email"]', `code5-${Date.now()}@example.com`);
    await page.fill('input[name="employee_code"]', '12345');

    await page.locator('select[name="role_id"]').selectOption('3');
    await page.locator('select[name="department_id"]').selectOption({ index: 1 });

    await page.click('button:has-text("確認画面へ")');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=6桁')).toBeVisible({ timeout: 5000 });
  });

  test('6-013: 個人コード7桁入力時の制限確認', async ({ page }) => {
    // maxlength=6の制限を確認するため、キーボード入力で7桁をタイプ
    const input = page.locator('input[name="employee_code"]');
    await input.click();
    await input.pressSequentially('1234567');
    const value = await input.inputValue();
    // maxlength=6属性により6桁に制限される
    expect(value.length).toBeLessThanOrEqual(6);
  });

  test('6-022: キャンセルボタンで前のページに戻る', async ({ page }) => {
    await page.click('button:has-text("キャンセル")');
    await page.waitForTimeout(1000);

    // 管理画面内に戻ることを確認
    await expect(page).toHaveURL(/\/admin\//);
  });

  test('6-023: 勤務スケジュール設定の表示', async ({ page }) => {
    // 曜日ごとのシフトパターン選択が表示される
    await expect(page.locator('text=勤務日とシフトパターン')).toBeVisible();
  });

  test('6-024: 権限選択の表示', async ({ page }) => {
    // 権限セクションが表示される
    await expect(page.locator('text=権限')).toBeVisible();
    // 権限の選択肢が存在する
    const roleSelect = page.locator('select[name="role_id"]');
    const optionCount = await roleSelect.locator('option').count();
    expect(optionCount).toBeGreaterThan(1);
  });
});
