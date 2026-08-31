import { X, RotateCcw, Ban } from 'lucide-react';

interface RequestDetail {
  id: number;
  type: { label: string };
  target_date?: string;
  start_time: string | null;
  end_time: string | null;
  reason: string | null;
  decided_at?: string | null;
  approver?: { id: number; name: string } | null;
  status?: { label: string; css_class?: string };
  created_at?: string;
}

interface ApprovedRequestDetailModalProps {
  request: RequestDetail | null;
  onClose: () => void;
  onRevert?: (requestId: number) => void;
  onCancel?: (requestId: number) => void;
}

/**
 * 申請の詳細表示モーダル
 */
export default function ApprovedRequestDetailModal({
  request,
  onClose,
  onRevert,
  onCancel,
}: ApprovedRequestDetailModalProps) {
  if (!request) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4">
        <div className="fixed inset-0 bg-black bg-opacity-40" onClick={onClose} />
        <div className="relative bg-white rounded-lg shadow-xl w-full max-w-md">
          <div className="flex items-center justify-between p-4 border-b">
            <h3 className="text-lg font-bold text-gray-900">申請詳細</h3>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="p-6">
            <dl className="space-y-3">
              <div>
                <dt className="text-sm font-medium text-gray-500">種別</dt>
                <dd className="mt-1 text-sm text-gray-900">{request.type.label}</dd>
              </div>
              {request.target_date && (
                <div>
                  <dt className="text-sm font-medium text-gray-500">対象日</dt>
                  <dd className="mt-1 text-sm text-gray-900">{request.target_date}</dd>
                </div>
              )}
              {(request.start_time || request.end_time) && (
                <div>
                  <dt className="text-sm font-medium text-gray-500">時間</dt>
                  <dd className="mt-1 text-sm text-gray-900">
                    {request.start_time && request.end_time
                      ? `${request.start_time} 〜 ${request.end_time}`
                      : request.start_time || request.end_time}
                  </dd>
                </div>
              )}
              <div>
                <dt className="text-sm font-medium text-gray-500">理由</dt>
                <dd className="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                  {request.reason || '-'}
                </dd>
              </div>
              {request.status && (
                <div>
                  <dt className="text-sm font-medium text-gray-500">ステータス</dt>
                  <dd className="mt-1 text-sm text-gray-900">{request.status.label}</dd>
                </div>
              )}
              {request.approver && (
                <div>
                  <dt className="text-sm font-medium text-gray-500">承認者</dt>
                  <dd className="mt-1 text-sm text-gray-900">{request.approver.name}</dd>
                </div>
              )}
              {request.decided_at && (
                <div>
                  <dt className="text-sm font-medium text-gray-500">承認日時</dt>
                  <dd className="mt-1 text-sm text-gray-900">{request.decided_at}</dd>
                </div>
              )}
              {request.created_at && (
                <div>
                  <dt className="text-sm font-medium text-gray-500">申請日時</dt>
                  <dd className="mt-1 text-sm text-gray-900">{request.created_at}</dd>
                </div>
              )}
            </dl>
          </div>

          <div className="flex justify-between p-4 border-t">
            <div className="flex gap-2">
              {onRevert && (
                <button
                  onClick={() => onRevert(request.id)}
                  className="px-3 py-2 text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600 rounded-md flex items-center gap-1"
                >
                  <RotateCcw className="h-4 w-4" />
                  修正
                </button>
              )}
              {onCancel && (
                <button
                  onClick={() => onCancel(request.id)}
                  className="px-3 py-2 text-sm font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center gap-1"
                >
                  <Ban className="h-4 w-4" />
                  取消
                </button>
              )}
            </div>
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
