# E2Eテスト（Playwright）

このディレクトリには、Playwrightを使用したエンドツーエンド（E2E）テストが含まれています。

## セットアップ

### 1. Playwrightのインストール

```bash
./vendor/bin/sail npm install -D @playwright/test
./vendor/bin/sail npx playwright install
```

### 2. 環境設定

テストを実行する前に、アプリケーションが起動していることを確認してください：

```bash
./vendor/bin/sail up -d
```

デフォルトでは `http://localhost` をベースURLとして使用します。別のURLを使用する場合は、`playwright.config.ts` の `baseURL` を変更してください。

## テストの実行

### すべてのテストを実行

```bash
./vendor/bin/sail npm run test:e2e
```

### UIモードでテストを実行（ローカル環境のみ）

**注意**: Docker/Sail環境ではGUIが利用できないため、UIモードはローカル環境でのみ使用できます。

```bash
# ローカル環境（Sailを使わない場合）
npm run test:e2e:ui
```

### ブラウザを表示してテストを実行（ローカル環境のみ）

**注意**: Docker/Sail環境ではGUIが利用できないため、headedモードはローカル環境でのみ使用できます。

```bash
# ローカル環境（Sailを使わない場合）
npm run test:e2e:headed
```

### デバッグモードでテストを実行

```bash
./vendor/bin/sail npm run test:e2e:debug
```

### 特定のテストファイルを実行

```bash
./vendor/bin/sail npx playwright test e2e/admin/user-creation.spec.ts
```

### 特定のブラウザでテストを実行

```bash
./vendor/bin/sail npx playwright test --project=chromium
./vendor/bin/sail npx playwright test --project=firefox
./vendor/bin/sail npx playwright test --project=webkit
```

## テストレポート

テスト実行後、HTMLレポートを表示するには：

```bash
./vendor/bin/sail npm run test:e2e:report
```

## ディレクトリ構造

```
e2e/
├── admin/              # 管理者機能のテスト
│   └── user-creation.spec.ts
├── staff/              # スタッフ機能のテスト
├── utils/              # テストユーティリティ
│   └── auth.ts         # 認証ヘルパー
├── fixtures/           # テストフィクスチャ
└── README.md
```

## テストの書き方

### 基本的なテスト構造

```typescript
import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/auth';

test.describe('機能名', () => {
  test.beforeEach(async ({ page }) => {
    // 各テストの前に実行される処理
    await loginAsAdmin(page);
  });

  test('テストケース名', async ({ page }) => {
    // テストロジック
    await page.goto('/path');
    await expect(page.locator('selector')).toBeVisible();
  });
});
```

### 認証ヘルパーの使用

```typescript
import { loginAsAdmin, loginAsManager, loginAsEmployee } from '../utils/auth';

// 管理者としてログイン
await loginAsAdmin(page);

// マネージャーとしてログイン
await loginAsManager(page);

// 一般ユーザーとしてログイン
await loginAsEmployee(page);
```

## ベストプラクティス

1. **テストの独立性**: 各テストは他のテストに依存しないようにする
2. **クリーンアップ**: テストで作成したデータは可能な限りクリーンアップする
3. **待機処理**: 明示的な待機を使用し、固定時間の待機は避ける
4. **セレクタ**: できるだけデータ属性やロール属性を使用する
5. **アサーション**: 明確で読みやすいアサーションを書く

## トラブルシューティング

### テストが失敗する

1. アプリケーションが起動しているか確認
2. データベースがシードされているか確認
3. スクリーンショットとトレースを確認（`test-results/` ディレクトリ）

### ブラウザがインストールされていない

```bash
./vendor/bin/sail npx playwright install
```

### ポートの競合

`playwright.config.ts` の `baseURL` を適切なポートに変更してください。

### Docker環境でのGUI関連エラー

Docker/Sail環境ではGUIが利用できないため、以下のエラーが発生する場合があります：

```
Error: browserType.launch: Target page, context or browser has been closed
Browser logs:
╔═══════════════════════════════════════════════════════════════╗
║ Looks like you launched a headed browser without XServer...  ║
╚═══════════════════════════════════════════════════════════════╝
```

**解決方法**:

1. `playwright.config.ts` で `headless: true` に設定されていることを確認
2. `--headed` や `--ui` オプションを使用しない
3. 基本的なテスト実行コマンドを使用：

```bash
./vendor/bin/sail npm run test:e2e
```

### UIモードやHeadedモードを使いたい場合

ローカル環境（Docker/Sailを使わない）でテストを実行してください：

```bash
# ローカル環境でのセットアップ
npm install
npx playwright install

# UIモードで実行
npm run test:e2e:ui
```

## 参考リンク

- [Playwright公式ドキュメント](https://playwright.dev/)
- [ベストプラクティス](https://playwright.dev/docs/best-practices)
- [デバッグガイド](https://playwright.dev/docs/debug)
