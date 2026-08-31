import { usePage } from '@inertiajs/react';
import { CheckCircle, XCircle, Info, AlertTriangle } from 'lucide-react';

interface FlashMessages {
  success?: string;
  error?: string;
  info?: string;
  warning?: string;
}

/**
 * ページ内フラッシュメッセージコンポーネント
 *
 * Inertiaのflashメッセージをページ内にインラインで表示する
 * シフト管理などのページで使用される統一された表示形式
 */
export default function PageFlashMessage() {
  const { flash } = usePage<{ flash: FlashMessages }>().props;

  if (!flash.success && !flash.error && !flash.info && !flash.warning) {
    return null;
  }

  const renderMessage = (type: 'success' | 'error' | 'info' | 'warning', message: string) => {
    const styles = {
      success: {
        bg: 'bg-green-50',
        border: 'border-green-500',
        text: 'text-green-800',
        icon: <CheckCircle className="h-5 w-5 text-green-600" />,
      },
      error: {
        bg: 'bg-red-50',
        border: 'border-red-500',
        text: 'text-red-800',
        icon: <XCircle className="h-5 w-5 text-red-600" />,
      },
      info: {
        bg: 'bg-blue-50',
        border: 'border-blue-500',
        text: 'text-blue-800',
        icon: <Info className="h-5 w-5 text-blue-600" />,
      },
      warning: {
        bg: 'bg-yellow-50',
        border: 'border-yellow-500',
        text: 'text-yellow-800',
        icon: <AlertTriangle className="h-5 w-5 text-yellow-600" />,
      },
    };

    const style = styles[type];

    return (
      <div className={`${style.bg} border-l-4 ${style.border} p-4 rounded-lg shadow`}>
        <div className="flex items-start">
          <div className="flex-shrink-0 mt-0.5">
            {style.icon}
          </div>
          <div className="ml-3">
            {message.includes('\n') ? (
              <div className={`text-sm font-medium ${style.text} whitespace-pre-line`}>
                {message}
              </div>
            ) : (
              <p className={`text-sm font-medium ${style.text}`}>{message}</p>
            )}
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-4 mb-6">
      {flash.success && renderMessage('success', flash.success)}
      {flash.error && renderMessage('error', flash.error)}
      {flash.info && renderMessage('info', flash.info)}
      {flash.warning && renderMessage('warning', flash.warning)}
    </div>
  );
}
