import { ShiftPattern } from '@/types';

/**
 * 勤務スケジュール設定フィールドのプロップス
 */
interface WorkScheduleFieldsProps {
  data: {
    shift_patterns: {
      sunday: number | undefined;
      monday: number | undefined;
      tuesday: number | undefined;
      wednesday: number | undefined;
      thursday: number | undefined;
      friday: number | undefined;
      saturday: number | undefined;
    };
  };
  shiftPatterns: ShiftPattern[];
  setData: (key: string, value: any) => void;
  processing: boolean;
}

/**
 * 勤務スケジュール設定フィールドコンポーネント
 *
 * スタッフの勤務スケジュールを設定：
 * - 各曜日のシフトパターン選択（シフトパターン設定あり＝勤務日、休日選択＝非勤務日）
 */
export default function WorkScheduleFields({
  data,
  shiftPatterns,
  setData,
  processing
}: WorkScheduleFieldsProps) {
  // 曜日の定義（日曜日から土曜日まで）
  const days = [
    { key: 'sunday', label: '日', color: 'text-red-600' },
    { key: 'monday', label: '月', color: 'text-gray-700' },
    { key: 'tuesday', label: '火', color: 'text-gray-700' },
    { key: 'wednesday', label: '水', color: 'text-gray-700' },
    { key: 'thursday', label: '木', color: 'text-gray-700' },
    { key: 'friday', label: '金', color: 'text-gray-700' },
    { key: 'saturday', label: '土', color: 'text-blue-600' },
  ] as const;

  return (
    <>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-3">
          勤務日とシフトパターン
        </label>
        <div className="grid grid-cols-7 gap-2">
          {days.map((day) => {
            // 選択されているシフトパターンを取得
            const selectedShiftId = data.shift_patterns[day.key as keyof typeof data.shift_patterns];
            const selectedShift = shiftPatterns.find(s => s.id === selectedShiftId);
            const isWorkDay = !!selectedShiftId;
            return (
              <div key={day.key} className="flex flex-col space-y-2">
                <div
                  className={`flex flex-col items-center p-3 border rounded-lg transition-colors ${
                    isWorkDay
                      ? 'bg-blue-50 border-blue-500'
                      : 'bg-white border-gray-300'
                  }`}
                >
                  <span className={`text-sm font-medium ${day.color}`}>
                    {day.label}
                  </span>
                </div>
                <select
                  value={data.shift_patterns[day.key as keyof typeof data.shift_patterns] || ''}
                  onChange={(e) => setData('shift_patterns', {
                    ...data.shift_patterns,
                    [day.key]: e.target.value ? parseInt(e.target.value) : undefined
                  })}
                  disabled={processing}
                  className={`w-full border border-gray-300 rounded-md p-3 text-base font-medium disabled:bg-gray-100 disabled:text-gray-400 ${selectedShift ? selectedShift.background_color + ' ' + selectedShift.text_color : ''}`}
                >
                  <option value="">休日</option>
                  {shiftPatterns.map((shift) => (
                    <option key={shift.id} value={shift.id}>
                      {shift.name}
                    </option>
                  ))}
                </select>
              </div>
            );
          })}
        </div>
        <p className="text-xs text-gray-500 mt-2">
          各曜日のシフトパターンを選択してください。休日の場合は「休日」を選択してください
        </p>
      </div>
    </>
  );
}
