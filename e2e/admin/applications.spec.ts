import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('管理者申請管理画面', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/applications', { waitUntil: 'domcontentloaded' });
    // Inertia.jsのハイドレーション待ち
    await page.waitForSelector('h1:has-text("申請管理")', { state: 'visible', timeout: 15000 });
  });

  test('8-001: 申請管理画面の初期表示', async ({ page }) => {
    // 統計カードが表示される
    await expect(page.locator('text=総申請数').first()).toBeVisible({ timeout: 10000 });

    // 申請一覧セクションが表示される
    await expect(page.locator('text=申請一覧')).toBeVisible();
  });

  test('8-002: 申請統計カードの表示', async ({ page }) => {
    // 各統計が表示される
    await expect(page.locator('text=総申請数').first()).toBeVisible();
    await expect(page.locator('text=承認待ち').first()).toBeVisible();
    await expect(page.locator('text=承認済み').first()).toBeVisible();
    await expect(page.locator('text=却下').first()).toBeVisible();
  });

  test('8-003: ステータスフィルター', async ({ page }) => {
    // ステータスフィルターボタンが表示される
    await expect(page.locator('button:has-text("すべて")')).toBeVisible();
    await expect(page.locator('button:has-text("申請中")')).toBeVisible();
    await expect(page.locator('button:has-text("承認")')).toBeVisible();
    await expect(page.locator('button:has-text("却下")')).toBeVisible();

    // 「申請中」ボタンをクリック
    await page.click('button:has-text("申請中")');
    await page.waitForTimeout(1000);
    // ページがリロードされずに表示が更新される
    await expect(page).toHaveURL(/\/admin\/applications/);
  });

  test('8-008: 承認フィルターボタンのクリック', async ({ page }) => {
    // 承認フィルターボタンをクリック
    await page.click('button:has-text("承認")');
    await page.waitForTimeout(1000);
    // ページがリロードされずに表示が更新される
    await expect(page).toHaveURL(/\/admin\/applications/);
  });

  test('8-012: 却下フィルターボタンのクリック', async ({ page }) => {
    // 却下フィルターボタンをクリック
    await page.click('button:has-text("却下")');
    await page.waitForTimeout(1000);
    // ページがリロードされずに表示が更新される
    await expect(page).toHaveURL(/\/admin\/applications/);
  });

  test('8-013: 検索機能', async ({ page }) => {
    // 検索ボタンが表示される
    const searchButton = page.locator('button:has-text("検索")');
    await expect(searchButton).toBeVisible();

    // 申請者入力欄が表示される
    const searchInput = page.locator('input[placeholder*="検索"]');
    await expect(searchInput).toBeVisible();
  });
});
