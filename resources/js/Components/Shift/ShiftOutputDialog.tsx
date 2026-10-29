import { useEffect, useState } from 'react';
import { Printer, FileSpreadsheet } from 'lucide-react';

interface ShiftOutputDialogProps {
  isOpen: boolean;
  periodLabel: string;
  departments: { id: number; name: string }[];
  /** 保存していないシフトの変更があるか（印刷・Excelには保存済みの内容が出る） */
  hasUnsavedChanges: boolean;
  onPrint: (departmentId: number | null, pageBreak: boolean) => void;
  onExcel: (departmentId: number | null, pageBreak: boolean) => void;
  onClose: () => void;
}

/**
 * シフト表の印刷・Excel出力の対象（全部署／選択した部署）を選ぶダイアログ
 */
export default function ShiftOutputDialog({
  isOpen,
  periodLabel,
  departments,
  hasUnsavedChanges,
  onPrint,
  onExcel,
  onClose,
}: ShiftOutputDialogProps) {
  const [departmentId, setDepartmentId] = useState<string>('');
  // 全部署のとき、部署ごとにページを分けるか（印刷でも、Excelの印刷でも）
  const [pageBreak, setPageBreak] = useState(true);

  // 開くたびに「全部署」に戻す
  useEffect(() => {
    if (isOpen) {
      setDepartmentId('');
      setPageBreak(true);
    }
  }, [isOpen]);

  if (!isOpen) {
    return null;
  }

  const selected = departmentId === '' ? null : Number(departmentId);

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h3 className="text-lg font-semibold text-gray-900 mb-1">シフト表の印刷・Excel出力</h3>
        <p className="text-sm text-gray-600 mb-4">{periodLabel}</p>

        <div className="mb-4">
          <label htmlFor="shift-output-department" className="block text-sm font-medium text-gray-700 mb-2">
            出力する部署
          </label>
          <select
            id="shift-output-department"
            value={departmentId}
            onChange={(e) => setDepartmentId(e.target.value)}
            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500"
          >
            <option value="">全部署</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
        </div>

        {selected === null && (
          <label className="mb-4 flex items-start space-x-3 cursor-pointer">
            <input
              type="checkbox"
              checked={pageBreak}
              onChange={(e) => setPageBreak(e.target.checked)}
              className="mt-0.5 w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
            />
            <span className="text-sm text-gray-700">
              部署ごとに改ページする
              <span className="block text-xs text-gray-500">全部署を出力するとき、部署ごとにページを分けて印刷します</span>
            </span>
          </label>
        )}

        {hasUnsavedChanges && (
          <p className="mb-4 text-xs text-yellow-800 bg-yellow-50 border border-yellow-200 rounded-md px-3 py-2">
            保存していないシフトの変更があります。印刷・Excelには保存済みの内容が出ます。変更を出力するには、先に「保存」を押してください。
          </p>
        )}

        <div className="grid grid-cols-2 gap-3 mb-3">
          <button
            type="button"
            onClick={() => onPrint(selected, pageBreak)}
            className="flex items-center justify-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md font-medium text-sm"
          >
            <Printer className="h-4 w-4" />
            <span>印刷</span>
          </button>
          <button
            type="button"
            onClick={() => onExcel(selected, pageBreak)}
            className="flex items-center justify-center space-x-2 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md font-medium text-sm"
          >
            <FileSpreadsheet className="h-4 w-4" />
            <span>Excel出力</span>
          </button>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-md font-medium text-sm"
        >
          閉じる
        </button>
      </div>
    </div>
  );
}
