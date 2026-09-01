import React, { useCallback, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Download, X, RotateCcw, Ban, Trash2 } from 'lucide-react';
import { format, parseISO, startOfMonth, endOfMonth } from 'date-fns';
import { router, usePage } from '@inertiajs/react';
import ReportExportModal from '@/Components/Reports/ReportExportModal';
import ApprovedRequestDetailModal from '@/Components/Reports/ApprovedRequestDetailModal';
import CorrectionDetailModal from '@/Components/Reports/CorrectionDetailModal';
import AttendanceIssueBanner, { ISSUE_LABELS, type AttendanceIssueMap } from '@/Components/Reports/AttendanceIssueBanner';
import WorkReportTable from '@/Components/Reports/WorkReportTable';
import MonthSelector from '@/Components/MonthSelector';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';
import type { ReportUser, ReportsPageProps, WorkSummary, ApprovedRequest, TimeRecordCorrectionItem } from '@/types/reports';
import type { ShiftDisplayPeriodType } from '@/types/shift';
import { useReports } from '@/hooks/reports/useReports';
import { getRequestBadgeStyle } from '@/utils/requestStyles';

function ReportsPage() {
  const { users, workSummaries, timeRecords, approvedRequests, timeRecordCorrections, monthlySummary, canExport, shifts, filters, attendanceIssues } = usePage<{
    props: ReportsPageProps;
  }>().props as unknown as ReportsPageProps;

  const shiftDisplayPeriod = filters.shiftDisplayPeriod as ShiftDisplayPeriodType;
  const payrollClosingDay = filters.payrollClosingDay;

  const {
    showExportModal,
    exportFormats,
    exportScope,
    selectedUserId,
    selectedUserName,
    getApprovedRequestsForDate,
    handleUserChange,
    handleFormatChange,
    handleScopeChange,
    openExportModal,
    closeExportModal,
    handleExport,
    showEditModal,
    editingSummary,
    editingDate,
    editForm,
    editErrors,
    isSaving,
    isDeleting,
    openEditModal,
    closeEditModal,
    saveEdit,
    deleteEdit,
    setEditForm,
  } = useReports({ users, workSummaries, timeRecords, approvedRequests, filters });

  // シフト管理と同じ期間計算ロジック
  const getDateRangeForPeriod = (baseDate: Date): { start: Date; end: Date } => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const closingDay = payrollClosingDay;
      const daysInPrevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 0).getDate();

      let start: Date;
      if (closingDay >= daysInPrevMonth) {
        start = new Date(baseDate.getFullYear(), baseDate.getMonth(), 1);
      } else {
        start = new Date(baseDate.getFullYear(), baseDate.getMonth() - 1, closingDay + 1);
      }

      const daysInMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() + 1, 0).getDate();
      const endDay = Math.min(closingDay, daysInMonth);
      const end = new Date(baseDate.getFullYear(), baseDate.getMonth(), endDay);

      return { start, end };
    } else {
      return {
        start: startOfMonth(baseDate),
        end: endOfMonth(baseDate),
      };
    }
  };

  const getPeriodLabel = (baseDate: Date): string => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const closingDay = payrollClosingDay;
      const prevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() - 1, 1);
      const daysInPrevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 0).getDate();
      const startDay = closingDay + 1;
      if (closingDay >= daysInPrevMonth) {
        return `${format(baseDate, 'yyyy年M月')}1日〜${format(baseDate, 'M月')}${closingDay}日`;
      }
      return `${format(prevMonth, 'yyyy年M月')}${startDay}日〜${format(baseDate, 'M月')}${closingDay}日`;
    } else {
      return format(baseDate, 'yyyy年MM月');
    }
  };

  const getCurrentPeriodMonth = (): Date => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const endDate = parseISO(filters.end_date);
      return new Date(endDate.getFullYear(), endDate.getMonth(), 1);
    } else {
      return new Date(parseISO(filters.start_date).getFullYear(), parseISO(filters.start_date).getMonth(), 1);
    }
  };

  const handlePeriodSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const [year, month] = e.target.value.split('-');
    const baseDate = new Date(parseInt(year), parseInt(month) - 1, 1);
    const { start, end } = getDateRangeForPeriod(baseDate);
    router.visit(
      `/admin/reports?start_date=${format(start, 'yyyy-MM-dd')}&end_date=${format(end, 'yyyy-MM-dd')}&user_id=${selectedUserId ?? ''}`,
      { preserveState: false }
    );
  };

  const [selectedRequest, setSelectedRequest] = useState<ApprovedRequest | null>(null);
  const [selectedCorrection, setSelectedCorrection] = useState<{ date: string; items: TimeRecordCorrectionItem[] } | null>(null);
  const { dialogProps, openDialog } = useConfirmDialog();

  const handleRevert = useCallback((requestId: number) => {
    setSelectedRequest(null);
    openDialog({
      title: '申請の修正',
      message: 'この申請の承認を取り消して申請中に戻しますか？',
      icon: <RotateCcw className="h-6 w-6 text-yellow-600" />,
      iconBgClass: 'bg-yellow-100',
      confirmLabel: '申請中に戻す',
      confirmButtonClass: 'bg-yellow-500 hover:bg-yellow-600',
      onConfirm: () => {
        router.post(`/admin/applications/${requestId}/revert`, { request_type: 'normal' }, { preserveScroll: true });
      },
    });
  }, [openDialog]);

  const handleDeleteSummary = useCallback(() => {
    openDialog({
      title: '打刻の削除',
      message: 'この日の打刻データを削除しますか？',
      description: '出勤・退勤・休憩の打刻と勤務実績がすべて削除されます。この操作は取り消せません。',
      icon: <Trash2 className="h-6 w-6 text-red-600" />,
      iconBgClass: 'bg-red-100',
      confirmLabel: '削除する',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: () => {
        deleteEdit();
      },
    });
  }, [openDialog, deleteEdit]);

  const handleCancel = useCallback((requestId: number) => {
    setSelectedRequest(null);
    openDialog({
      title: '申請の取消',
      message: 'この申請を取り消しますか？',
      description: '休暇申請の場合、勤務実績の休暇データもクリアされます。',
      icon: <Ban className="h-6 w-6 text-gray-600" />,
      iconBgClass: 'bg-gray-100',
      confirmLabel: '取り消す',
      confirmButtonClass: 'bg-gray-500 hover:bg-gray-600',
      onConfirm: () => {
        router.post(`/admin/applications/${requestId}/cancel`, { request_type: 'normal' }, { preserveScroll: true });
      },
    });
  }, [openDialog]);

  const renderLastColumn = useCallback(
    (date: Date, summary: WorkSummary | undefined) => {
      const dateRequests = getApprovedRequestsForDate(date);
      const dateStr = format(date, 'yyyy-MM-dd');
      const corrections = timeRecordCorrections?.[dateStr] || [];

      return (
        <div className="flex flex-col gap-1">
          <div className="flex flex-wrap gap-1">
            {dateRequests.map((req: ApprovedRequest) => (
              <button
                key={req.id}
                type="button"
                onClick={() => setSelectedRequest(req)}
                className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${getRequestBadgeStyle(req.type.code)} cursor-pointer transition-colors`}
              >
                {req.type.label}
              </button>
            ))}
            {(attendanceIssues?.[dateStr] ?? []).map((kind) => (
              <span
                key={kind}
                className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800"
              >
                {ISSUE_LABELS[kind] ?? kind}
              </span>
            ))}
            {corrections.length > 0 && (
              <button
                type="button"
                onClick={() => setSelectedCorrection({ date: dateStr, items: corrections })}
                className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 hover:bg-orange-200 cursor-pointer transition-colors"
              >
                打刻修正
              </button>
            )}
          </div>
          {summary?.note && <span className="text-gray-600">{summary.note}</span>}
          {dateRequests.length === 0 && corrections.length === 0 && !summary?.note && <span className="text-gray-400">-</span>}
        </div>
      );
    },
    [getApprovedRequestsForDate, timeRecordCorrections, attendanceIssues]
  );

  const editColumn = {
    header: (
      <th className="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">操作</th>
    ),
    render: (date: Date, summary: WorkSummary | undefined) => (
      <td className="px-3 py-3 whitespace-nowrap text-sm text-center">
        {summary && summary.id > 0 ? (
          <button
            onClick={() => openEditModal(summary)}
            className="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded border border-blue-200"
          >
            編集
          </button>
        ) : (
          <button
            onClick={() => openEditModal(format(date, 'yyyy-MM-dd'))}
            className="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded border border-green-200"
          >
            登録
          </button>
        )}
      </td>
    ),
  };

  return (
    <div className="space-y-6">
      <AttendanceIssueBanner issues={attendanceIssues ?? {}} />

      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">勤務実績</h1>
        {canExport && (
          <button
            onClick={openExportModal}
            className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2"
          >
            <Download className="h-4 w-4" />
            <span>出力</span>
          </button>
        )}
      </div>

      {/* フィルターパネル */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">対象期間</label>
            <MonthSelector
              value={format(getCurrentPeriodMonth(), 'yyyy-MM')}
              onChange={handlePeriodSelect}
              formatLabel={getPeriodLabel}
              className="w-full border border-gray-300 rounded-md p-2 pr-8 text-sm appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2024%2024%22%20fill%3d%22none%22%20stroke%3d%22%23666%22%20stroke-width%3d%222%22%20stroke-linecap%3d%22round%22%20stroke-linejoin%3d%22round%22%3e%3cpolyline%20points%3d%226%209%2012%2015%2018%209%22%3e%3c%2fpolyline%3e%3c%2fsvg%3e')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">従業員</label>
            <select
              value={selectedUserId || ''}
              onChange={(e) => handleUserChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2"
            >
              {users.map((user: ReportUser) => (
                <option key={user.id} value={user.id}>
                  {user.name}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      <WorkReportTable
        corrections={timeRecordCorrections}
        onCorrectionClick={(date) =>
          setSelectedCorrection({ date, items: timeRecordCorrections?.[date] ?? [] })
        }
        workSummaries={workSummaries}
        monthlySummary={monthlySummary}
        startDate={filters.start_date}
        endDate={filters.end_date}
        shifts={shifts}
        timeRecords={timeRecords}
        summaryLabel={`${getPeriodLabel(getCurrentPeriodMonth())} - ${selectedUserName}`}
        lastColumnHeader="備考"
        renderLastColumn={renderLastColumn}
        extraColumns={[editColumn]}
      />

      {/* 勤務実績編集モーダル */}
      {showEditModal && editingDate && (
        <div className="fixed inset-0 z-50 overflow-y-auto">
          <div className="flex items-center justify-center min-h-screen px-4">
            <div className="fixed inset-0 bg-black bg-opacity-40" onClick={closeEditModal} />
            <div className="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-bold text-gray-900">
                  {editingSummary ? '勤務実績の編集' : '勤務実績の登録'}
                </h3>
                <button onClick={closeEditModal} className="text-gray-400 hover:text-gray-600">
                  <X className="h-5 w-5" />
                </button>
              </div>

              <div className="mb-4 text-sm text-gray-600">
                対象日: <span className="font-semibold text-gray-900">{editingDate}</span>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">勤務開始</label>
                  <input
                    type="time"
                    value={editForm.work_start}
                    onChange={(e) => setEditForm((prev) => ({ ...prev, work_start: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">勤務終了</label>
                  <input
                    type="time"
                    value={editForm.work_end}
                    onChange={(e) => setEditForm((prev) => ({ ...prev, work_end: e.target.value }))}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">休憩時間</label>
                  <div className="space-y-2">
                    {editForm.break_periods.map((bp, idx) => (
                      <div key={idx} className="flex items-center gap-2">
                        <input
                          type="time"
                          value={bp.start}
                          onChange={(e) => setEditForm((prev) => {
                            const periods = [...prev.break_periods];
                            periods[idx] = { ...periods[idx], start: e.target.value };
                            return { ...prev, break_periods: periods };
                          })}
                          className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm"
                        />
                        <span className="text-gray-400">~</span>
                        <input
                          type="time"
                          value={bp.end}
                          onChange={(e) => setEditForm((prev) => {
                            const periods = [...prev.break_periods];
                            periods[idx] = { ...periods[idx], end: e.target.value };
                            return { ...prev, break_periods: periods };
                          })}
                          className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm"
                        />
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {editErrors.length > 0 && (
                <div className="mt-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm">
                  {editErrors.map((err, i) => (
                    <div key={i}>{err}</div>
                  ))}
                </div>
              )}

              <div className="flex justify-between items-center gap-3 mt-6">
                <div>
                  {editingSummary && editingSummary.id > 0 && (
                    <button
                      onClick={handleDeleteSummary}
                      disabled={isSaving || isDeleting}
                      className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-md hover:bg-red-50 disabled:opacity-50"
                    >
                      <Trash2 className="h-4 w-4" />
                      {isDeleting ? '削除中...' : '削除'}
                    </button>
                  )}
                </div>
                <div className="flex gap-3">
                  <button
                    onClick={closeEditModal}
                    disabled={isSaving || isDeleting}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                  >
                    キャンセル
                  </button>
                  <button
                    onClick={saveEdit}
                    disabled={isSaving || isDeleting}
                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
                  >
                    {isSaving ? '保存中...' : '保存'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      <ApprovedRequestDetailModal
        request={selectedRequest}
        onClose={() => setSelectedRequest(null)}
        onRevert={handleRevert}
        onCancel={handleCancel}
      />

      <CorrectionDetailModal
        date={selectedCorrection?.date ?? null}
        corrections={selectedCorrection?.items ?? []}
        onClose={() => setSelectedCorrection(null)}
      />

      <ConfirmDialog {...dialogProps} />

      <ReportExportModal
        isOpen={showExportModal}
        exportFormats={exportFormats}
        exportScope={exportScope}
        selectedUserName={selectedUserName}
        onFormatChange={handleFormatChange}
        onScopeChange={handleScopeChange}
        onExport={handleExport}
        onClose={closeExportModal}
      />
    </div>
  );
}

ReportsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default ReportsPage;
