import { format } from 'date-fns';
import { ShiftPatternDialogProps } from '@/types/shift';

/**
 * シフトパターン適用ダイアログコンポーネント
 *
 * 個別ユーザーのシフトパターンを月次カレンダーに適用する確認ダイアログ。
 *
 * 機能：
 * - ユーザーの曜日別シフトパターンを表示
 * - 適用対象の年月を表示
 * - 既存シフト上書きの警告表示
 * - 適用確認とキャンセル機能
 *
 * 表示内容：
 * - ユーザー名と適用対象月
 * - 日〜土の7曜日分のシフトパターン
 * - 各シフトパターンは色付きバッジで表示
 *
 * 操作：
 * - 「適用する」: シフトパターンを月次カレンダーに反映
 * - 「キャンセル」: ダイアログを閉じる
 */
export default function ShiftPatternDialog({
  showDialog,
  selectedDate,
  users,
  getShiftTypeInfo,
  onApply,
  onCancel,
}: ShiftPatternDialogProps) {
  if (!showDialog) return null;

  const user = users.find(u => u.id === showDialog.userId);

  return (
    <div className="fixed top-0 left-0 right-0 bottom-0 bg-gray-500 bg-opacity-10 backdrop-blur-sm flex items-center justify-center z-50 overflow-y-auto">
      <div className="bg-white rounded-lg shadow-2xl border border-gray-200 p-6 max-w-md w-full my-auto">
        <h3 className="text-lg font-bold text-gray-900 mb-4">シフトパターンの適用</h3>
        <p className="text-sm text-gray-700 mb-4">
          <strong>{showDialog.userName}</strong>のシフトパターンを<strong>{format(selectedDate, 'yyyy年MM月')}</strong>に適用しますか？
        </p>

        {user && user.shiftPatterns && (
          <div className="bg-gray-50 rounded-lg p-4 mb-4">
            <h4 className="text-xs font-semibold text-gray-700 mb-2">設定されているシフトパターン:</h4>
            <div className="grid grid-cols-7 gap-1">
              {(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const).map((dayKey, index) => {
                const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
                const shiftType = user.shiftPatterns?.[dayKey];
                const shiftInfo = shiftType ? getShiftTypeInfo(shiftType) : null;

                return (
                  <div key={dayKey} className="text-center">
                    <div className="text-xs text-gray-600 mb-1">{dayNames[index]}</div>
                    <div className={`text-xs font-medium py-1 px-1 rounded ${
                      shiftInfo ? `${shiftInfo.color} ${shiftInfo.textColor}` : 'bg-gray-200 text-gray-600'
                    }`}>
                      {shiftInfo ? shiftInfo.name : '休み'}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
          <p className="text-xs text-yellow-800">
            既存のシフトがある場合は上書きされます
          </p>
        </div>

        <div className="flex space-x-3">
          <button
            onClick={() => onApply(showDialog.userId)}
            className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium"
          >
            適用する
          </button>
          <button
            onClick={onCancel}
            className="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors font-medium"
          >
            キャンセル
          </button>
        </div>
      </div>
    </div>
  );
}
