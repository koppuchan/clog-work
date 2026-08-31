import { X } from 'lucide-react';
import type { TimeRecordCorrectionItem } from '@/types/reports';

interface CorrectionDetailModalProps {
  date: string | null;
  corrections: TimeRecordCorrectionItem[];
  onClose: () => void;
}

/**
 * 打刻修正詳細モーダル
 */
export default function CorrectionDetailModal({
  date,
  corrections,
  onClose,
}: CorrectionDetailModalProps) {
  if (!date) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4">
        <div className="fixed inset-0 bg-black bg-opacity-40" onClick={onClose} />
        <div className="relative bg-white rounded-lg shadow-xl w-full max-w-lg">
          <div className="flex items-center justify-between p-4 border-b">
            <h3 className="text-lg font-bold text-gray-900">打刻修正履歴</h3>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="p-4">
            <p className="text-sm text-gray-600 mb-3">対象日: <span className="font-semibold text-gray-900">{date}</span></p>

            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="py-2 text-left font-medium text-gray-500">種別</th>
                  <th className="py-2 text-left font-medium text-gray-500">修正前</th>
                  <th className="py-2 text-center font-medium text-gray-500"></th>
                  <th className="py-2 text-left font-medium text-gray-500">修正後</th>
                  <th className="py-2 text-left font-medium text-gray-500">修正元</th>
                  <th className="py-2 text-left font-medium text-gray-500">修正日時</th>
                </tr>
              </thead>
              <tbody>
                {corrections.map((c, i) => (
                  <tr key={i} className="border-b last:border-b-0">
                    <td className="py-2 text-gray-700 text-xs">{c.record_type?.label ?? '-'}</td>
                    <td className="py-2 text-gray-900">{c.before_time}</td>
                    <td className="py-2 text-center text-gray-400">&rarr;</td>
                    <td className="py-2 text-gray-900 font-medium">{c.after_time}</td>
                    <td className="py-2">
                      {c.correction_source === 'request' ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                          申請
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                          管理者
                        </span>
                      )}
                    </td>
                    <td className="py-2 text-gray-500 text-xs">{c.corrected_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex justify-end p-4 border-t">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
              閉じる
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
