import { useEffect, useMemo, useRef, useState } from 'react';
import { Printer, FileSpreadsheet } from 'lucide-react';

interface ShiftOutputDialogProps {
  isOpen: boolean;
  periodLabel: string;
  departments: { id: number; name: string }[];
  /** 保存していないシフトの変更があるか（印刷・Excelには保存済みの内容が出る） */
  hasUnsavedChanges: boolean;
  /**
   * departmentIds が null のときは全部署（部署が決まっていないスタッフも含む）。
   * pageBreak は、複数の部署を出力するときに部署ごとにページを分けるか。
   */
  onPrint: (departmentIds: number[] | null, pageBreak: boolean) => void;
  onExcel: (departmentIds: number[] | null, pageBreak: boolean) => void;
  onClose: () => void;
}

/**
 * シフト表の印刷・Excel出力の対象（全部署／選択した部署。複数選べる）を選ぶダイアログ
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
  const allIds = useMemo(() => departments.map((department) => department.id), [departments]);
  const [selectedIds, setSelectedIds] = useState<number[]>(allIds);
  // 複数の部署を出力するとき、部署ごとにページを分けるか（印刷でも、Excelの印刷でも）
  const [pageBreak, setPageBreak] = useState(true);
  const allCheckboxRef = useRef<HTMLInputElement>(null);

  // 開くたびに「全部署」に戻す
  useEffect(() => {
    if (isOpen) {
      setSelectedIds(allIds);
      setPageBreak(true);
    }
  }, [isOpen, allIds]);

  const isAllSelected = selectedIds.length === allIds.length;
  const isPartlySelected = selectedIds.length > 0 && !isAllSelected;

  // 一部だけ選んでいるときは、「全部署」のチェックを中間の状態で表示する
  useEffect(() => {
    if (allCheckboxRef.current) {
      allCheckboxRef.current.indeterminate = isPartlySelected;
    }
  }, [isPartlySelected, isOpen]);

  if (!isOpen) {
    return null;
  }

  const toggleDepartment = (id: number, checked: boolean) => {
    setSelectedIds((prev) => (checked ? [...prev, id] : prev.filter((selectedId) => selectedId !== id)));
  };

  // 全部のときは部署を指定せず「全部署」として出す（部署が決まっていないスタッフも含まれる）
  const outputIds = isAllSelected ? null : selectedIds;
  const hasSelection = selectedIds.length > 0 || allIds.length === 0;
  const hasSeveralDepartments = isAllSelected ? allIds.length > 1 : selectedIds.length > 1;

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <h3 className="text-lg font-semibold text-gray-900 mb-1">シフト表の印刷・Excel出力</h3>
        <p className="text-sm text-gray-600 mb-4">{periodLabel}</p>

        <fieldset className="mb-4">
          <legend className="block text-sm font-medium text-gray-700 mb-2">出力する部署（複数選べます）</legend>
          <div className="border border-gray-300 rounded-md divide-y divide-gray-200">
            <label className="flex items-center space-x-3 px-3 py-2 cursor-pointer bg-gray-50">
              <input
                ref={allCheckboxRef}
                type="checkbox"
                checked={isAllSelected}
                onChange={(e) => setSelectedIds(e.target.checked ? allIds : [])}
                className="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
              />
              <span className="text-sm font-medium text-gray-800">全部署</span>
            </label>
            <div className="max-h-52 overflow-y-auto divide-y divide-gray-100">
              {departments.map((department) => (
                <label key={department.id} className="flex items-center space-x-3 px-3 py-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(department.id)}
                    onChange={(e) => toggleDepartment(department.id, e.target.checked)}
                    className="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                  />
                  <span className="text-sm text-gray-700">{department.name}</span>
                </label>
              ))}
            </div>
          </div>
          {!hasSelection && <p className="mt-2 text-xs text-red-600">部署を1つ以上選んでください。</p>}
        </fieldset>

        {hasSeveralDepartments && (
          <label className="mb-4 flex items-start space-x-3 cursor-pointer">
            <input
              type="checkbox"
              checked={pageBreak}
              onChange={(e) => setPageBreak(e.target.checked)}
              className="mt-0.5 w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
            />
            <span className="text-sm text-gray-700">
              部署ごとに改ページする
              <span className="block text-xs text-gray-500">複数の部署を出力するとき、部署ごとにページを分けて印刷します</span>
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
            disabled={!hasSelection}
            onClick={() => onPrint(outputIds, pageBreak)}
            className="flex items-center justify-center space-x-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-2 px-4 rounded-md font-medium text-sm"
          >
            <Printer className="h-4 w-4" />
            <span>印刷</span>
          </button>
          <button
            type="button"
            disabled={!hasSelection}
            onClick={() => onExcel(outputIds, pageBreak)}
            className="flex items-center justify-center space-x-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-2 px-4 rounded-md font-medium text-sm"
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
