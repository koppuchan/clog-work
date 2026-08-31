import { Loader2 } from 'lucide-react';
import { ShiftColor, NewShiftPatternForm } from '@/types/settings';

interface ShiftPatternFormModalProps {
  isOpen: boolean;
  isEditing: boolean;
  shiftPattern: NewShiftPatternForm;
  breakStart: string;
  breakEnd: string;
  selectedColorId: number | null;
  availableColors: ShiftColor[];
  onShiftPatternChange: (shift: NewShiftPatternForm) => void;
  onBreakStartChange: (start: string) => void;
  onBreakEndChange: (end: string) => void;
  onColorChange: (id: number | null) => void;
  onSubmit: (e: React.FormEvent) => void;
  onClose: () => void;
  isSubmitting?: boolean;
  error?: string | null;
}

export default function ShiftPatternFormModal({
  isOpen,
  isEditing,
  shiftPattern,
  breakStart,
  breakEnd,
  selectedColorId,
  availableColors,
  onShiftPatternChange,
  onBreakStartChange,
  onBreakEndChange,
  onColorChange,
  onSubmit,
  onClose,
  isSubmitting = false,
  error = null,
}: ShiftPatternFormModalProps) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          {isEditing ? 'シフトパターン編集' : 'シフトパターン追加'}
        </h3>
        <form onSubmit={onSubmit} className="space-y-4">
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm">
              {error}
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              シフト名
            </label>
            <input
              type="text"
              value={shiftPattern.name}
              onChange={(e) => onShiftPatternChange({ ...shiftPattern, name: e.target.value })}
              className="w-full border border-gray-300 rounded-md p-2"
              placeholder="例: 早番"
              required
            />
          </div>
          {availableColors.length > 0 && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                表示色
              </label>
              <div className="flex flex-wrap gap-2">
                {availableColors.map((color) => (
                  <button
                    key={color.id}
                    type="button"
                    onClick={() => onColorChange(color.id)}
                    className={`px-3 py-1 rounded font-medium text-sm ${color.backgroundColor} ${color.textColor} ${
                      selectedColorId === color.id
                        ? 'ring-2 ring-offset-1 ring-blue-500'
                        : 'opacity-70 hover:opacity-100'
                    }`}
                  >
                    Aa
                  </button>
                ))}
              </div>
            </div>
          )}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                始業時刻
              </label>
              <input
                type="time"
                id="shiftStartTime"
                className="w-full border border-gray-300 rounded-md p-2"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                終業時刻
              </label>
              <input
                type="time"
                id="shiftEndTime"
                className="w-full border border-gray-300 rounded-md p-2"
                required
              />
            </div>
          </div>

          {/* 休憩時間入力 */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                休憩入
              </label>
              <input
                type="time"
                value={breakStart}
                onChange={(e) => onBreakStartChange(e.target.value)}
                className="w-full border border-gray-300 rounded-md p-2"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                休憩終
              </label>
              <input
                type="time"
                value={breakEnd}
                onChange={(e) => onBreakEndChange(e.target.value)}
                className="w-full border border-gray-300 rounded-md p-2"
              />
            </div>
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
