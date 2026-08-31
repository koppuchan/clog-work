import { X } from 'lucide-react';
import Modal from './Modal';

interface ConfirmDialogProps {
    show: boolean;
    title: string;
    message: string;
    description?: string;
    icon?: React.ReactNode;
    iconBgClass?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmButtonClass?: string;
    processing?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export default function ConfirmDialog({
    show,
    title,
    message,
    description,
    icon,
    iconBgClass = 'bg-gray-100',
    confirmLabel = '確認',
    cancelLabel = 'キャンセル',
    confirmButtonClass = 'bg-blue-600 hover:bg-blue-700',
    processing = false,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    return (
        <Modal show={show} maxWidth="md" onClose={onCancel}>
            <div className="flex items-center justify-between p-4 border-b">
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                <button
                    onClick={onCancel}
                    className="text-gray-400 hover:text-gray-600"
                >
                    <X className="h-5 w-5" />
                </button>
            </div>
            <div className="p-6">
                {icon && (
                    <div className="flex items-center justify-center mb-4">
                        <div className={`p-3 rounded-full ${iconBgClass}`}>
                            {icon}
                        </div>
                    </div>
                )}
                <p className="text-center text-gray-700">{message}</p>
                {description && (
                    <p className="text-center text-sm text-gray-500 mt-2">
                        {description}
                    </p>
                )}
            </div>
            <div className="flex justify-end space-x-2 p-4 border-t">
                <button
                    onClick={onCancel}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg"
                >
                    {cancelLabel}
                </button>
                <button
                    onClick={onConfirm}
                    disabled={processing}
                    className={`px-4 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed ${confirmButtonClass}`}
                >
                    {processing ? '処理中...' : confirmLabel}
                </button>
            </div>
        </Modal>
    );
}
