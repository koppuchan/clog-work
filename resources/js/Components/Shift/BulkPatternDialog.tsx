import { BulkPatternDialogProps } from '@/types/shift';

/**
 * 一括シフトパターン適用ダイアログコンポーネント
 *
 * アクティブな全スタッフのシフトパターンを一括で月次カレンダーに適用する確認ダイアログ。
 *
 * 機能：
 * - 適用対象のスタッフ一覧を表示
 * - 適用対象の年月を表示
 * - スタッフ数の表示
 * - 既存シフト上書きの警告表示
 * - 一括適用確認とキャンセル機能
 *
 * 表示内容：
 * - 対象スタッフ数と年月
 * - 各スタッフの名前、部署、アイコン
 * - スクロール可能なスタッフリスト
 *
 * 操作：
 * - 「全員に適用する」: 全スタッフのシフトパターンを月次カレンダーに反映
 * - 「キャンセル」: ダイアログを閉じる
 *
 * 注意：
 * - 各スタッフのシフトパターンは個別に設定されたものが適用される
 * - 既存のシフトは上書きされる
 */
export default function BulkPatternDialog({
  showDialog,
  periodLabel,
  activeUsers,
  onApply,
  onCancel,
}: BulkPatternDialogProps) {
  if (!showDialog) return null;

  return (
    <div className="fixed top-0 left-0 right-0 bottom-0 bg-gray-500 bg-opacity-10 backdrop-blur-sm flex items-center justify-center z-50 overflow-y-auto">
      <div className="bg-white rounded-lg shadow-2xl border border-gray-200 p-6 max-w-2xl w-full my-auto">
        <h3 className="text-lg font-bold text-gray-900 mb-4">全員のシフトパターンを一括適用</h3>
        <p className="text-sm text-gray-700 mb-4">
          全員のシフトパターンを<strong>{periodLabel}</strong>に適用しますか？
        </p>

        <div className="bg-gray-50 rounded-lg p-4 mb-4 max-h-64 overflow-y-auto">
          <h4 className="text-xs font-semibold text-gray-700 mb-3">適用対象: {activeUsers.length}名</h4>
          <div className="grid grid-cols-2 gap-2">
            {activeUsers.map(user => (
              <div key={user.id} className="flex items-center space-x-2 bg-white rounded px-3 py-2">
                <div className="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                  <span className="text-xs font-medium text-blue-600">{user.name.substring(0, 2)}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 truncate">{user.name}</p>
                  <p className="text-xs text-gray-500">{user.department}</p>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
          <p className="text-xs text-yellow-800">
            各スタッフに設定されているシフトパターンが反映されます。既存のシフトがある場合は上書きされます。
          </p>
        </div>

        <div className="flex space-x-3">
          <button
            onClick={onApply}
            className="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium"
          >
            全員に適用する
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
