import { useEffect, useState, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { CheckCircle, XCircle, Info, AlertTriangle, X } from 'lucide-react';

interface FlashMessages {
  success?: string;
  error?: string;
  info?: string;
  warning?: string;
}

export default function FlashMessage() {
  const { flash } = usePage<{ flash: FlashMessages }>().props;
  const [visible, setVisible] = useState(false);
  const [message, setMessage] = useState<{ type: string; text: string } | null>(null);
  const prevFlashRef = useRef<string>('');

  useEffect(() => {
    const currentFlash = JSON.stringify(flash);

    // フラッシュメッセージがない、または前回と同じ場合はスキップ
    if (!flash.success && !flash.error && !flash.info && !flash.warning) {
      prevFlashRef.current = currentFlash;
      return;
    }

    if (currentFlash === prevFlashRef.current) {
      return;
    }

    prevFlashRef.current = currentFlash;

    if (flash.success) {
      setMessage({ type: 'success', text: flash.success });
      setVisible(true);
    } else if (flash.error) {
      setMessage({ type: 'error', text: flash.error });
      setVisible(true);
    } else if (flash.info) {
      setMessage({ type: 'info', text: flash.info });
      setVisible(true);
    } else if (flash.warning) {
      setMessage({ type: 'warning', text: flash.warning });
      setVisible(true);
    }
  });

  useEffect(() => {
    if (visible && message) {
      // パスワード表示を含むメッセージは自動で消さない
      if (message.text.includes('パスワード:')) {
        return;
      }
      // 5秒後に自動的に非表示にする
      const timer = setTimeout(() => {
        setVisible(false);
      }, 5000);

      return () => clearTimeout(timer);
    }
  }, [visible, message]);

  if (!visible || !message) {
    return null;
  }

  const getStyles = () => {
    switch (message.type) {
      case 'success':
        return {
          bg: 'bg-green-50',
          border: 'border-green-200',
          text: 'text-green-800',
          icon: <CheckCircle className="h-5 w-5 text-green-600" />,
        };
      case 'error':
        return {
          bg: 'bg-red-50',
          border: 'border-red-200',
          text: 'text-red-800',
          icon: <XCircle className="h-5 w-5 text-red-600" />,
        };
      case 'info':
        return {
          bg: 'bg-blue-50',
          border: 'border-blue-200',
          text: 'text-blue-800',
          icon: <Info className="h-5 w-5 text-blue-600" />,
        };
      case 'warning':
        return {
          bg: 'bg-yellow-50',
          border: 'border-yellow-200',
          text: 'text-yellow-800',
          icon: <AlertTriangle className="h-5 w-5 text-yellow-600" />,
        };
      default:
        return {
          bg: 'bg-gray-50',
          border: 'border-gray-200',
          text: 'text-gray-800',
          icon: <Info className="h-5 w-5 text-gray-600" />,
        };
    }
  };

  const styles = getStyles();

  return (
    <div
      className={`fixed top-4 right-4 z-50 max-w-md w-full ${styles.bg} ${styles.border} border rounded-lg shadow-lg p-4 transition-all duration-300 ease-in-out ${
        visible ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-2'
      }`}
    >
      <div className="flex items-start">
        <div className="flex-shrink-0">{styles.icon}</div>
        <div className={`ml-3 flex-1 ${styles.text}`}>
          {message.text.includes('\n') ? (
            <div className="text-sm font-medium whitespace-pre-line">{message.text}</div>
          ) : (
            <p className="text-sm font-medium">{message.text}</p>
          )}
        </div>
        <div className="ml-4 flex-shrink-0">
          <button
            onClick={() => setVisible(false)}
            className={`inline-flex rounded-md ${styles.text} hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 transition-opacity`}
          >
            <span className="sr-only">閉じる</span>
            <X className="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>
  );
}
