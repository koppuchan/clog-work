import { Loader2 } from 'lucide-react';
import { NewDepartmentForm } from '@/types/settings';

interface DepartmentFormModalProps {
  isOpen: boolean;
  isEditing: boolean;
  department: NewDepartmentForm;
  onDepartmentChange: (department: NewDepartmentForm) => void;
  onSubmit: (e: React.FormEvent) => void;
  onClose: () => void;
  isSubmitting?: boolean;
  error?: string | null;
}

export default function DepartmentFormModal({
  isOpen,
  isEditing,
  department,
  onDepartmentChange,
  onSubmit,
  onClose,
  isSubmitting = false,
  error = null,
}: DepartmentFormModalProps) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          {isEditing ? '部署編集' : '部署追加'}
        </h3>
        <form onSubmit={onSubmit} className="space-y-4">
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm">
              {error}
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              部署名
            </label>
            <input
              type="text"
              value={department.name}
              onChange={(e) => onDepartmentChange({ ...department, name: e.target.value })}
              className="w-full border border-gray-300 rounded-md p-2"
              placeholder="例: 営業部"
              required
              disabled={isSubmitting}
            />
          </div>
          <div>
            <label className="flex items-start cursor-pointer">
              <input
                type="checkbox"
                checked={department.isStampHidden}
                onChange={(e) => onDepartmentChange({ ...department, isStampHidden: e.target.checked })}
                className="mt-1 mr-3 h-4 w-4"
                disabled={isSubmitting}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">打刻画面を非表示にする</span>
                <p className="text-xs text-gray-500 mt-1">
                  この部署のスタッフの打刻画面を非表示にします。
                </p>
              </div>
            </label>
          </div>
          <div className="flex space-x-3 pt-4">
            <button
              type="submit"
              disabled={isSubmitting}
              className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white py-2 px-4 rounded-md flex items-center justify-center"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                  保存中...
                </>
              ) : (
                isEditing ? '更新' : '追加'
              )}
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 text-gray-700 py-2 px-4 rounded-md"
            >
              キャンセル
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
