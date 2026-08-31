import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright設定
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './e2e',

  /* テストの最大実行時間 */
  timeout: 60 * 1000,

  /* テストごとの期待タイムアウト */
  expect: {
    timeout: 10000,
  },

  /* 並列実行の設定（データベース競合を避けるため無効化） */
  fullyParallel: false,

  /* CIで失敗したテストのみ再試行 */
  forbidOnly: !!process.env.CI,

  /* 再試行回数（Inertia.jsのハイドレーション待ちのため） */
  retries: process.env.CI ? 2 : 1,

  /* 並列ワーカー数（1つずつ実行） */
  workers: 1,

  /* レポーター設定 */
  reporter: 'html',

  /* すべてのテストで共有する設定 */
  use: {
    /* ベースURL */
    baseURL: process.env.APP_URL || 'http://attendance-web.test',

    /* Headlessモード（Docker環境ではGUIが使えないため） */
    headless: true,

    /* ナビゲーションタイムアウト */
    navigationTimeout: 30000,

    /* アクションタイムアウト */
    actionTimeout: 10000,

    /* 失敗時のスクリーンショットを取得 */
    screenshot: 'only-on-failure',

    /* 失敗時のトレースを記録 */
    trace: 'on-first-retry',

    /* ビデオ録画 */
    video: 'retain-on-failure',
  },

  /* プロジェクト設定（chromiumのみで高速化） */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    // 他のブラウザは必要に応じてコメント解除
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },

    /* モバイルビューポート */
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
    // {
    //   name: 'Mobile Safari',
    //   use: { ...devices['iPhone 12'] },
    // },
  ],

  /* ローカル開発サーバーの起動 */
  // webServer: {
  //   command: './vendor/bin/sail up',
  //   url: 'http://localhost',
  //   reuseExistingServer: !process.env.CI,
  // },
});
