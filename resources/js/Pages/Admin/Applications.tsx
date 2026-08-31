import React, { useCallback } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    ApplicationStats,
    ApplicationFilter,
    ApplicationList,
    Pagination,
    RequestData,
    StatusOption,
    RequestStats,
    PaginationData,
    ApplicationTypeOption,
    Applicant,
} from '@/Components/Application';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useApplicationFilters } from '@/hooks/application/useApplicationFilters';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';
import { Check, X, RotateCcw, Ban } from 'lucide-react';

interface ApplicationsPageProps {
    requests: RequestData[];
    pagination: PaginationData;
    stats: RequestStats;
    statusOptions: StatusOption[];
    approvers: Applicant[];
    applicationTypes: ApplicationTypeOption[];
    currentStatus: number | null;
    currentName: string | null;
    currentTargetMonth: string | null;
    currentApprover: number | null;
    currentType: string | null;
}

function ApplicationsPage({
    requests,
    pagination,
    stats,
    statusOptions,
    approvers,
    applicationTypes,
    currentStatus,
    currentName,
    currentTargetMonth,
    currentApprover,
    currentType,
}: ApplicationsPageProps) {
    const {
        handleFilterChange,
        handleNameChange,
        handleTargetMonthChange,
        handleApproverChange,
        handleTypeChange,
        handlePageChange,
        handleApproval,
        handleRevert,
        handleCancel,
    } = useApplicationFilters({ currentStatus, currentName, currentTargetMonth, currentApprover, currentType });

    const { dialogProps, openDialog } = useConfirmDialog();

    const confirmApprove = useCallback(
        (requestId: number, requestType: 'normal' | 'correction') => {
            openDialog({
                title: '申請の承認',
                message: 'この申請を承認しますか？',
                icon: <Check className="h-6 w-6 text-green-600" />,
                iconBgClass: 'bg-green-100',
                confirmLabel: '承認する',
                confirmButtonClass: 'bg-green-600 hover:bg-green-700',
                onConfirm: () => handleApproval(requestId, true, requestType),
            });
        },
        [openDialog, handleApproval],
    );

    const confirmReject = useCallback(
        (requestId: number, requestType: 'normal' | 'correction') => {
            openDialog({
                title: '申請の却下',
                message: 'この申請を却下しますか？',
                icon: <X className="h-6 w-6 text-red-600" />,
                iconBgClass: 'bg-red-100',
                confirmLabel: '却下する',
                confirmButtonClass: 'bg-red-600 hover:bg-red-700',
                onConfirm: () => handleApproval(requestId, false, requestType),
            });
        },
        [openDialog, handleApproval],
    );

    const confirmRevert = useCallback(
        (requestId: number, requestType: 'normal' | 'correction') => {
            openDialog({
                title: '申請の修正',
                message: 'この申請の承認を取り消して申請中に戻しますか？',
                icon: <RotateCcw className="h-6 w-6 text-yellow-600" />,
                iconBgClass: 'bg-yellow-100',
                confirmLabel: '申請中に戻す',
                confirmButtonClass: 'bg-yellow-500 hover:bg-yellow-600',
                onConfirm: () => handleRevert(requestId, requestType),
            });
        },
        [openDialog, handleRevert],
    );

    const confirmCancel = useCallback(
        (requestId: number, requestType: 'normal' | 'correction') => {
            openDialog({
                title: '申請の取消',
                message: 'この申請を取り消しますか？',
                icon: <Ban className="h-6 w-6 text-gray-600" />,
                iconBgClass: 'bg-gray-100',
                confirmLabel: '取り消す',
                confirmButtonClass: 'bg-gray-500 hover:bg-gray-600',
                onConfirm: () => handleCancel(requestId, requestType),
            });
        },
        [openDialog, handleCancel],
    );

    return (
        <div className="space-y-6">

            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900">申請管理</h1>
            </div>

            <ApplicationStats stats={stats} />

            <ApplicationFilter
                statusOptions={statusOptions}
                approvers={approvers}
                applicationTypes={applicationTypes}
                currentStatus={currentStatus}
                currentName={currentName}
                currentTargetMonth={currentTargetMonth}
                currentApprover={currentApprover}
                currentType={currentType}
                onFilterChange={handleFilterChange}
                onNameChange={handleNameChange}
                onTargetMonthChange={handleTargetMonthChange}
                onApproverChange={handleApproverChange}
                onTypeChange={handleTypeChange}
            />

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <ApplicationList requests={requests} onApproval={confirmApprove} onReject={confirmReject} onRevert={confirmRevert} onCancel={confirmCancel} />
                <Pagination pagination={pagination} onPageChange={handlePageChange} />
            </div>

            <ConfirmDialog {...dialogProps} />
        </div>
    );
}

ApplicationsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default ApplicationsPage;
