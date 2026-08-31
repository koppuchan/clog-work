# フロントエンド コーディングガイド

## 共通フック

### `useCurrentTime`

1秒ごとに現在時刻を更新するカスタムフック。

```tsx
import { useCurrentTime } from '@/hooks/useCurrentTime';

const currentTime = useCurrentTime(); // Date
```

**使用箇所:** `Pages/Public/Stamp.tsx`, `Pages/Staff/Stamp.tsx`

---

## 共通ユーティリティ

### `formatMinutesToHM`

分数を `H:MM` 形式の文字列に変換する。0以下の場合は `-` を返す。

```tsx
import { formatMinutesToHM } from '@/utils/timeFormat';

formatMinutesToHM(90);  // "1:30"
formatMinutesToHM(0);   // "-"
```

**使用箇所:** `Components/Reports/WorkReportTable.tsx`, `hooks/reports/useReports.ts`, `hooks/staff/reports/useStaffReports.ts`

---

## 共通コンポーネント

### `MonthSelector`

24ヶ月分（前後12ヶ月）の月選択ドロップダウン。

```tsx
import MonthSelector from '@/Components/MonthSelector';

// 基本
<MonthSelector
  value="2026-02"
  onChange={(e) => handleChange(e.target.value)}
/>

// カスタムラベル
<MonthSelector
  value="2026-02"
  onChange={handleChange}
  formatLabel={(date) => customLabel(date)}
/>

// カスタムクラス
<MonthSelector
  value="2026-02"
  onChange={handleChange}
  className="w-full border ..."
/>
```

| Prop | 型 | 必須 | 説明 |
|------|-----|------|------|
| `value` | `string` | Yes | `yyyy-MM` 形式の選択値 |
| `onChange` | `(e: ChangeEvent) => void` | Yes | 変更ハンドラ |
| `className` | `string` | No | カスタムCSSクラス（デフォルトのスタイルあり） |
| `formatLabel` | `(date: Date) => string` | No | ラベルのフォーマット関数（デフォルト: `yyyy年M月`） |

**使用箇所:** `Pages/Staff/Reports.tsx`, `Pages/Admin/Reports.tsx`, `Pages/Admin/Shifts.tsx`, `Pages/Staff/Shifts.tsx`

---

### `ConfirmDialog`

確認ダイアログコンポーネント。削除、取消などの操作前にユーザーへ確認を求める際に使用する。
内部で `Modal.jsx`（HeadlessUI）を使用しており、統一されたアニメーション・オーバーレイを提供する。

> **ルール:** ブラウザネイティブの `confirm()` は使用禁止。必ず `ConfirmDialog` を使用すること。

```tsx
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';
import { Trash2 } from 'lucide-react';

function MyPage() {
  const { dialogProps, openDialog } = useConfirmDialog();

  const handleDelete = (id: number) => {
    openDialog({
      title: '削除の確認',
      message: 'このアイテムを削除しますか？',
      description: 'この操作は取り消せません。',        // 省略可
      icon: <Trash2 className="h-6 w-6 text-red-600" />, // 省略可
      iconBgClass: 'bg-red-100',                          // 省略可
      confirmLabel: '削除する',                           // デフォルト: "確認"
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',  // デフォルト: blue系
      onConfirm: () => executeDelete(id),
    });
  };

  return (
    <>
      <button onClick={() => handleDelete(1)}>削除</button>
      <ConfirmDialog {...dialogProps} />
    </>
  );
}
```

| Prop | 型 | 必須 | 説明 |
|------|-----|------|------|
| `show` | `boolean` | Yes | 表示状態 |
| `title` | `string` | Yes | ダイアログタイトル |
| `message` | `string` | Yes | メインメッセージ |
| `description` | `string` | No | 補足テキスト（小さいグレー文字） |
| `icon` | `ReactNode` | No | アイコン要素 |
| `iconBgClass` | `string` | No | アイコン背景色（デフォルト: `bg-gray-100`） |
| `confirmLabel` | `string` | No | 確認ボタンテキスト（デフォルト: `確認`） |
| `cancelLabel` | `string` | No | キャンセルボタンテキスト（デフォルト: `キャンセル`） |
| `confirmButtonClass` | `string` | No | 確認ボタンの色クラス（デフォルト: blue系） |
| `processing` | `boolean` | No | 処理中フラグ（`true` でボタン無効化） |
| `onConfirm` | `() => void` | Yes | 確認時コールバック |
| `onCancel` | `() => void` | Yes | キャンセル時コールバック |

**使用箇所:** `Pages/Admin/Applications.tsx`, `Pages/Admin/Users.tsx`, `Pages/Admin/Settings.tsx`

---

## 共通フック（続き）

### `useConfirmDialog`

`ConfirmDialog` の状態管理を簡単にするカスタムフック。

```tsx
import { useConfirmDialog } from '@/hooks/useConfirmDialog';

const { dialogProps, openDialog, closeDialog } = useConfirmDialog();

// dialogProps → <ConfirmDialog {...dialogProps} /> に spread で渡す
// openDialog({ title, message, onConfirm, ... }) → ダイアログを開く
// closeDialog() → ダイアログを閉じる（通常は自動で閉じるため不要）
```

**使用箇所:** `Pages/Admin/Applications.tsx`, `Pages/Admin/Users.tsx`, `Pages/Admin/Settings.tsx`

---

## バリデーションエラー表示パターン

バリデーションエラーの表示は、表示箇所に応じて以下のパターンを使い分ける。

### ページレベル

`ValidationErrors` コンポーネントを使用する。サーバー側エラー（Inertia `errors`）とフロントエンド側エラー（`localErrors`）の両方に対応。

```tsx
import ValidationErrors from '@/Components/ValidationErrors';

<ValidationErrors localErrors={localErrors} />
```

**使用箇所:** `Pages/Admin/UserNew.tsx`, `Pages/Admin/UserEdit.tsx`

### モーダル内

`bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm` のスタイルを使用する。

```tsx
{errors.length > 0 && (
  <div className="mt-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm">
    {errors.map((err, i) => (
      <div key={i}>{err}</div>
    ))}
  </div>
)}
```

**使用箇所:** `Pages/Admin/Reports.tsx`, `Components/Settings/DepartmentFormModal.tsx`, `Components/Settings/ShiftPatternFormModal.tsx`

### フィールドレベル（インライン）

各入力フィールドの直下に `text-sm text-red-600` で表示する。

```tsx
{errors.fieldName && <p className="mt-1 text-sm text-red-600">{errors.fieldName}</p>}
```

**使用箇所:** `Pages/Auth/Login.tsx`, `Components/User/BasicInfoFields.tsx`

### Inertia `onError` でのサーバーエラー受け取り

`router.post` / `router.put` のコールバックでサーバーバリデーションエラーを受け取り、フロントエンドの state に反映する。

```tsx
const onError = (errors: Record<string, string>) => {
  setEditErrors(Object.values(errors));
};

router.post('/path', payload, {
  preserveState: true,
  onSuccess,
  onError,
  onFinish,
});
```

---

## UIパターンの統一

同じ目的のUIは、ページをまたいで同じパターン・レイアウトで実装する。

### 検索バー

テキスト検索は以下のパターンで統一する。

```tsx
<div className="flex gap-2">
  <div className="relative flex-1">
    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
      <Search className="h-5 w-5 text-gray-400" />
    </div>
    <input
      type="text"
      value={input}
      onChange={(e) => setInput(e.target.value)}
      onKeyDown={(e) => { if (e.key === 'Enter') onSearch(input); }}
      className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg ..."
      placeholder="検索..."
    />
  </div>
  <button
    type="button"
    onClick={() => onSearch(input)}
    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
  >
    検索
  </button>
</div>
```

- 左側にアイコン、右端に「検索」ボタン
- Enter キーでも検索実行
- 検索バーは全幅で配置し、フィルター項目の上に配置する

**使用箇所:** `Pages/Admin/Users.tsx`, `Components/Application/ApplicationFilter.tsx`

### フィルター・検索エリアの構成順序

検索・フィルターを含むカードは、以下の順序で構成する。

1. **検索バー**（テキスト検索がある場合）
2. **ステータスフィルター**（ボタン群）
3. **詳細フィルター**（ドロップダウン・日付選択など、`grid` で横並び）

```tsx
<div className="bg-white shadow rounded-lg p-6">
  <div className="space-y-4">
    {/* 1. 検索バー */}
    <div className="flex gap-2">...</div>

    {/* 2. ステータスフィルター */}
    <div className="flex flex-wrap gap-2">...</div>

    {/* 3. 詳細フィルター */}
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">...</div>
  </div>
</div>
```

---

## ビルドに関する注意

- フロントエンドの変更確認時、`npm run build` を実行する必要はない
- 開発中は `npm run dev`（Vite dev server）を起動しておけばHMRで自動反映される
- 本番ビルドはデプロイ時のみ実行する
