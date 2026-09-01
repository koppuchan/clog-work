export type ExportScope = 'single' | 'all';

interface ReportExportModalProps {
  isOpen: boolean;
  exportFormats: { csv: boolean; excel: boolean };
  exportScope: ExportScope;
  exportBatch: number | null;
  selectedUserName: string;
  userCount: number;
  batchSize: number;
  onFormatChange: (format: 'csv' | 'excel', checked: boolean) => void;
  onScopeChange: (scope: ExportScope) => void;
  onBatchChange: (batch: number | null) => void;
  onExport: () => void;
  onClose: () => void;
}

/**
 * 勤務実績出力のフォーマット・対象選択モーダル
 */
export default function ReportExportModal({
  isOpen,
  exportFormats,
  exportScope,
  exportBatch,
  selectedUserName,
  userCount,
  batchSize,
  onFormatChange,
  onScopeChange,
  onBatchChange,
  onExport,
  onClose,
}: ReportExportModalProps) {
  if (!isOpen) {
    return null;
  }

  // 1始まりのバッチ番号と、その区切りに入る人数の範囲
  const batches = Array.from({ length: Math.ceil(userCount / batchSize) }, (_, index) => ({
    number: index + 1,
    label: `${index * batchSize + 1}〜${Math.min((index + 1) * batchSize, userCount)}名`,
  }));

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">出力設定</h3>

        <div className="mb-5">
          <p className="text-sm font-medium text-gray-700 mb-2">出力対象</p>
          <div className="space-y-2">
            <label className="flex items-center space-x-3 cursor-pointer">
              <input
                type="radio"
                name="exportScope"
                value="single"
                checked={exportScope === 'single'}
                onChange={() => onScopeChange('single')}
                className="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500"
              />
              <span className="text-sm text-gray-700">
                選択中のスタッフ（{selectedUserName}）
              </span>
            </label>
            <label className="flex items-center space-x-3 cursor-pointer">
              <input
                type="radio"
                name="exportScope"
                value="all"
                checked={exportScope === 'all'}
                onChange={() => onScopeChange('all')}
                className="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500"
              />
              <span className="text-sm text-gray-700">全従業員（{userCount}名）</span>
            </label>
          </div>
        </div>

        {exportScope === 'all' && batches.length > 1 && (
          <div className="mb-5">
            <p className="text-sm font-medium text-gray-700 mb-2">出力範囲</p>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => onBatchChange(null)}
                className={`px-3 py-1 rounded text-sm border ${
                  exportBatch === null
                    ? 'bg-green-600 border-green-600 text-white'
                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                }`}
              >
                全員まとめて
              </button>
              {batches.map((batch) => (
                <button
                  key={batch.number}
                  type="button"
                  onClick={() => onBatchChange(batch.number)}
                  className={`px-3 py-1 rounded text-sm border ${
                    exportBatch === batch.number
                      ? 'bg-green-600 border-green-600 text-white'
                      : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  {batch.label}
                </button>
              ))}
            </div>
            <p className="mt-2 text-xs text-gray-500">
              全員まとめても内部では{batchSize}名ずつ処理しますが、人数が多く時間がかかる場合は範囲を分けて取得できます。
            </p>
          </div>
        )}

        <div className="mb-6">
          <p className="text-sm font-medium text-gray-700 mb-2">出力形式</p>
          <div className="space-y-2">
            <label className="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                checked={exportFormats.csv}
                onChange={(e) => onFormatChange('csv', e.target.checked)}
                className="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
              />
              <span className="text-sm text-gray-700">CSV形式</span>
            </label>
            <label className="flex items-center space-x-3 cursor-pointer">
              <input
                type="checkbox"
                checked={exportFormats.excel}
                onChange={(e) => onFormatChange('excel', e.target.checked)}
                className="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
              />
              <span className="text-sm text-gray-700">Excel形式</span>
            </label>
          </div>
        </div>

        <div className="flex space-x-3">
          <button
            type="button"
            onClick={onExport}
            className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md font-medium"
          >
            出力
          </button>
          <button
            type="button"
            onClick={onClose}
            className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md font-medium"
          >
            キャンセル
          </button>
        </div>
      </div>
    </div>
  );
}
