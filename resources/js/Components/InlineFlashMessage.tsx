import { CheckCircle, XCircle } from 'lucide-react';

interface InlineFlashMessageProps {
  type: 'success' | 'error';
  message: string | null;
}

/**
 * インラインフラッシュメッセージコンポーネント
 *
 * ページ内に表示するローカルな成功/エラーメッセージ用
 * Inertiaのflashメッセージではなく、フロントエンドで管理するメッセージ表示に使用
 */
export default function InlineFlashMessage({ type, message }: InlineFlashMessageProps) {
  if (!message) {
    return null;
  }

  if (type === 'success') {
    return (
      <div className="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow animate-fade-in">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            <CheckCircle className="h-5 w-5 text-green-600" />
          </div>
          <div className="ml-3">
            <p className="text-sm font-medium text-green-800">{message}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow animate-fade-in">
      <div className="flex items-start">
        <div className="flex-shrink-0 mt-0.5">
          <XCircle className="h-5 w-5 text-red-600" />
        </div>
        <div className="ml-3">
          {message.includes('\n') ? (
            <ul className="text-sm font-medium text-red-800 list-disc list-inside space-y-1">
              {message.split('\n').map((line, i) => (
                <li key={i}>{line}</li>
              ))}
            </ul>
          ) : (
            <p className="text-sm font-medium text-red-800">{message}</p>
          )}
        </div>
      </div>
    </div>
  );
}
