import { FileText, Check, X, Calendar, Clock, User, RotateCcw, Ban, ArrowRight } from 'lucide-react';
import {
    RequestData,
    STATUS_PENDING,
    STATUS_APPROVED,
    STATUS_REJECTED,
    STATUS_CANCELLED,
} from './types';

interface ApplicationListProps {
    requests: RequestData[];
    onApproval: (requestId: number, requestType: 'normal' | 'correction') => void;
    onReject: (requestId: number, requestType: 'normal' | 'correction') => void;
    onRevert?: (requestId: number, requestType: 'normal' | 'correction') => void;
    onCancel?: (requestId: number, requestType: 'normal' | 'correction') => void;
}

export default function ApplicationList({ requests, onApproval, onReject, onRevert, onCancel }: ApplicationListProps) {
    const currentMonth = new Date().toISOString().slice(0, 7);

    const shouldShowStatusBadge = (request: RequestData): boolean => {
        if (request.status === STATUS_PENDING || request.status === STATUS_CANCELLED) {
            return true;
        }
        // 当月以外の承認済み・却下はラベルを非表示
        return request.target_date.slice(0, 7) === currentMonth;
    };

    if (requests.length === 0) {
        return (
            <div className="p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">申請一覧</h3>
                <p className="text-gray-500 text-center py-8">申請がありません</p>
            </div>
        );
    }

    const getDecisionInfo = (request: RequestData) => {
        if (!request.decided_at) return null;

        if (request.status === STATUS_APPROVED) {
            return {
                icon: <Check className="h-4 w-4 flex-shrink-0 text-green-600" />,
                label: '承認日時',
                date: request.decided_at,
                approver: request.approver?.name,
            };
        }
        if (request.status === STATUS_REJECTED) {
            return {
                icon: <X className="h-4 w-4 flex-shrink-0 text-red-600" />,
                label: '却下日時',
                date: request.decided_at,
                approver: request.approver?.name,
            };
        }
        if (request.status === STATUS_CANCELLED) {
            return {
                icon: <X className="h-4 w-4 flex-shrink-0 text-gray-600" />,
                label: '取消日時',
                date: request.decided_at,
                approver: null,
            };
        }
        return null;
    };

    return (
        <div className="p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">申請一覧</h3>
            <div className="space-y-4">
                {requests.map(request => {
                    const decisionInfo = getDecisionInfo(request);

                    return (
                        <div key={`${request.request_type}-${request.id}`} className="border rounded-lg p-4">
                            <div className="flex items-start justify-between">
                                <div className="flex items-start space-x-4 flex-1 min-w-0">
                                    <div className="bg-blue-100 p-2 rounded-full flex-shrink-0">
                                        <FileText className="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center space-x-3 mb-3">
                                            <h4 className="font-semibold text-gray-900">
                                                {request.type_label}
                                            </h4>
                                            {shouldShowStatusBadge(request) && (
                                                <span
                                                    className={`px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap ${request.status_css_class}`}
                                                >
                                                    {request.status_label}
                                                </span>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-600">
                                            <div className="flex items-center space-x-2">
                                                <User className="h-4 w-4 flex-shrink-0" />
                                                <span className="truncate">
                                                    {request.requested_by.name}
                                                </span>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Calendar className="h-4 w-4 flex-shrink-0" />
                                                <span>対象日: {request.target_date}</span>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Clock className="h-4 w-4 flex-shrink-0" />
                                                <span className="truncate">
                                                    申請日時: {request.created_at}
                                                </span>
                                            </div>
                                            {(request.start_time || request.end_time) && (
                                                <div className="flex items-center space-x-2">
                                                    <Clock className="h-4 w-4 flex-shrink-0" />
                                                    <span>
                                                        {request.start_time && `開始: ${request.start_time.slice(0, 5)}`}
                                                        {request.start_time && request.end_time && ' / '}
                                                        {request.end_time && `終了: ${request.end_time.slice(0, 5)}`}
                                                    </span>
                                                </div>
                                            )}
                                            {request.correction_details && request.correction_details.length > 0 && (
                                                <div className="col-span-2 mt-1">
                                                    <div className="bg-gray-50 rounded-md p-3 space-y-1">
                                                        <p className="text-xs font-medium text-gray-500 mb-2">修正内容</p>
                                                        {request.correction_details.map((detail, idx) => (
                                                            <div key={idx} className="flex items-center text-sm text-gray-700 space-x-2">
                                                                <span className="font-medium min-w-[5em]">{detail.record_type_label}</span>
                                                                <span className="text-gray-400">{detail.original_record_time ?? '未打刻'}</span>
                                                                <ArrowRight className="h-3 w-3 text-gray-400 flex-shrink-0" />
                                                                <span className="font-medium text-blue-600">{detail.corrected_record_time ?? '-'}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                            {decisionInfo && (
                                                <div className="flex items-center space-x-2">
                                                    {decisionInfo.icon}
                                                    <span className="truncate">
                                                        {decisionInfo.label}: {decisionInfo.date}
                                                        {decisionInfo.approver &&
                                                            ` (${decisionInfo.approver})`}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        <div className="mt-3">
                                            <p className="text-sm text-gray-700">
                                                <span className="font-medium">理由: </span>
                                                {request.reason}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                {request.status === STATUS_PENDING && (
                                    <div className="flex space-x-2 ml-4 flex-shrink-0">
                                        <button
                                            onClick={() => onApproval(request.id, request.request_type)}
                                            className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm flex items-center space-x-1"
                                        >
                                            <Check className="h-3 w-3" />
                                            <span>承認</span>
                                        </button>
                                        <button
                                            onClick={() => onReject(request.id, request.request_type)}
                                            className="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm flex items-center space-x-1"
                                        >
                                            <X className="h-3 w-3" />
                                            <span>却下</span>
                                        </button>
                                    </div>
                                )}
                                {request.status === STATUS_APPROVED && (
                                    <div className="flex space-x-2 ml-4 flex-shrink-0">
                                        <button
                                            onClick={() => onRevert?.(request.id, request.request_type)}
                                            className="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm flex items-center space-x-1"
                                        >
                                            <RotateCcw className="h-3 w-3" />
                                            <span>修正</span>
                                        </button>
                                        <button
                                            onClick={() => onCancel?.(request.id, request.request_type)}
                                            className="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm flex items-center space-x-1"
                                        >
                                            <Ban className="h-3 w-3" />
                                            <span>取消</span>
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

