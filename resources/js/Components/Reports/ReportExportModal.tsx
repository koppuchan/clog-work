export type ExportScope = 'single' | 'all';

interface ReportExportModalProps {
  isOpen: boolean;
  exportFormats: { csv: boolean; excel: boolean };
  exportScope: ExportScope;
  selectedUserName: string;
  onFormatChange: (format: 'csv' | 'excel', checked: boolean) => void;
  onScopeChange: (scope: ExportScope) => void;
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
  selectedUserName,
  onFormatChange,
  onScopeChange,
  onExport,
  onClose,
}: ReportExportModalProps) {
  if (!isOpen) {
    return null;
  }

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
              <span className="text-sm text-gray-700">全従業員</span>
            </label>
          </div>
        </div>

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
