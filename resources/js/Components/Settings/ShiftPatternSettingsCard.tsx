import { Clock, Plus, Edit, Trash2 } from 'lucide-react';
import SettingsCard from './SettingsCard';
import { SettingsShiftPattern } from '@/types/settings';
import { formatShiftTimeRange, formatBreakTime } from '@/utils/settingsHelpers';

interface ShiftPatternSettingsCardProps {
  shiftPatterns: SettingsShiftPattern[];
  defaultShiftPatternId: number | null;
  onDefaultChange: (id: number | null) => void;
  onAdd: () => void;
  onEdit: (id: number) => void;
  onDelete: (id: number) => void;
}

export default function ShiftPatternSettingsCard({
  shiftPatterns,
  defaultShiftPatternId,
  onDefaultChange,
  onAdd,
  onEdit,
  onDelete,
}: ShiftPatternSettingsCardProps) {
  return (
    <SettingsCard
      title="勤務時間（シフトパターン）設定"
      icon={Clock}
      headerAction={
        <button
          type="button"
          onClick={onAdd}
          className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg flex items-center space-x-1 text-sm"
        >
          <Plus className="h-3 w-3" />
          <span>追加</span>
        </button>
      }
    >
      {shiftPatterns.length > 0 && (
        <div className="mb-4 p-4 bg-gray-50 rounded-lg">
          <label className="block text-sm font-medium text-gray-700 mb-1">基本シフト</label>
          <select
            value={defaultShiftPatternId ?? ''}
            onChange={(e) => onDefaultChange(e.target.value ? parseInt(e.target.value) : null)}
            className="w-full max-w-xs border border-gray-300 rounded-md p-2 text-sm"
          >
            <option value="">未設定</option>
            {shiftPatterns.map((pattern) => (
              <option key={pattern.id} value={pattern.id}>
                {pattern.name}
              </option>
            ))}
          </select>
          <p className="text-xs text-gray-500 mt-1">
            スタッフ登録時の勤務日に自動で設定されるシフトパターンを選択します。
          </p>
        </div>
      )}

      <div className="space-y-3">
        {shiftPatterns.map((pattern) => (
          <div key={pattern.id} className="border rounded-lg p-4 flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <span className={`px-3 py-1 rounded font-medium text-sm ${pattern.backgroundColor ?? 'bg-teal-100'} ${pattern.textColor ?? 'text-teal-800'}`}>
                {pattern.name}
              </span>
              <span className="text-sm text-gray-600">
                {formatShiftTimeRange(pattern)}
              </span>
              {formatBreakTime(pattern) && (
                <span className="text-xs text-gray-500">({formatBreakTime(pattern)})</span>
              )}
              {pattern.isInUse && (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                  使用中
                </span>
              )}
            </div>
            <div className="flex space-x-2">
              <button
                type="button"
                onClick={() => onEdit(pattern.id)}
                className="text-blue-600 hover:text-blue-900 p-1"
              >
                <Edit className="h-4 w-4" />
              </button>
              <button
                type="button"
                onClick={() => onDelete(pattern.id)}
                disabled={pattern.isInUse}
                className={pattern.isInUse
                  ? 'text-gray-300 cursor-not-allowed p-1'
                  : 'text-red-600 hover:text-red-900 p-1'
                }
                title={pattern.isInUse ? 'シフトまたはスタッフで使用中のため削除できません' : ''}
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          </div>
        ))}
      </div>
    </SettingsCard>
  );
}
