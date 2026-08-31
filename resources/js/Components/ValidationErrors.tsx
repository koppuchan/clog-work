import { usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';

interface ValidationErrorsProps {
  /** ローカルで管理するエラー（フロントエンドバリデーション用） */
  localErrors?: Record<string, string>;
}

/**
 * バリデーションエラー表示コンポーネント
 *
 * サーバーサイドのバリデーションエラー（Inertia経由）と
 * フロントエンドのローカルエラーの両方を表示する
 */
export default function ValidationErrors({ localErrors = {} }: ValidationErrorsProps) {
  const { errors: pageErrors } = usePage<{ errors: Record<string, string> }>().props;

  const allErrors = { ...pageErrors, ...localErrors };
  const errorEntries = Object.entries(allErrors);

  if (errorEntries.length === 0) {
    return null;
  }

  return (
    <div className="bg-red-50 border border-red-200 rounded-lg p-4">
      <div className="flex items-center gap-3 mb-2">
        <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0" />
        <span className="text-red-800 font-semibold">入力エラーがあります</span>
      </div>
      <ul className="list-disc list-inside text-red-700 text-sm space-y-1 ml-8">
        {errorEntries.map(([key, message]) => (
          <li key={key}>{message}</li>
        ))}
      </ul>
    </div>
  );
}
