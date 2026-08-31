import React, { useState, useCallback } from 'react';
import StaffLayout from '@/Layouts/StaffLayout';
import { format, parseISO, startOfMonth, endOfMonth } from 'date-fns';
import { router, usePage } from '@inertiajs/react';
import type { ShiftDisplayPeriodType } from '@/types/shift';
import { ApplicationDialog } from '@/Components/Staff/Reports';
import ApprovedRequestDetailModal from '@/Components/Reports/ApprovedRequestDetailModal';
import CorrectionDetailModal from '@/Components/Reports/CorrectionDetailModal';
import WorkReportTable from '@/Components/Reports/WorkReportTable';
import MonthSelector from '@/Components/MonthSelector';
import { Clock, CheckCircle, XCircle } from 'lucide-react';
import { getRequestBadgeStyle } from '@/utils/requestStyles';
import type { WorkSummary, MonthlySummary, TimeRecord, TimeRecordCorrectionItem, ShiftInfo } from '@/types/reports';

interface RequestData {
  id: number;
  type: { value: number; code: string; label: string };
  start_time: string | null;
  end_time: string | null;
  reason: string;
  status: { value: number; label: string; css_class: string };
  created_at: string;
}

interface LeaveSettings {
  half_day: boolean;
  hourly: boolean;
}

interface StaffReportsPageProps {
  workSummaries: WorkSummary[];
  monthlySummary: MonthlySummary | null;
  timeRecords: TimeRecord[];
  timeRecordCorrections: Record<string, TimeRecordCorrectionItem[]>;
  requests: Record<string, RequestData[]>;
  shifts: Record<string, ShiftInfo>;
  todayWorkStart: string | null;
  filters: {
    month: string;
    start_date: string;
    end_date: string;
    shiftDisplayPeriod: ShiftDisplayPeriodType;
    payrollClosingDay: number;
  };
  leaveSettings: LeaveSettings;
}

function StaffReportsPage() {
  const { workSummaries, monthlySummary, timeRecords, timeRecordCorrections, requests, shifts, todayWorkStart, filters, leaveSettings } = usePage<{
    props: StaffReportsPageProps;
  }>().props as unknown as StaffReportsPageProps;

  const [isDialogOpen, setDialogOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [selectedRequest, setSelectedRequest] = useState<RequestData | null>(null);
  const [selectedCorrection, setSelectedCorrection] = useState<{ date: string; items: TimeRecordCorrectionItem[] } | null>(null);

  const shiftDisplayPeriod = filters.shiftDisplayPeriod;
  const payrollClosingDay = filters.payrollClosingDay;

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

  const getRequestsForDate = useCallback(
    (date: Date): RequestData[] => {
      const dateStr = format(date, 'yyyy-MM-dd');
      return requests[dateStr] ?? [];
    },
    [requests]
  );

  const getExistingTypesForDate = useCallback(
    (date: Date): string[] => {
      return getRequestsForDate(date).map((r) => r.type.code);
    },
    [getRequestsForDate]
  );

  const handlePeriodSelect = useCallback((e: React.ChangeEvent<HTMLSelectElement>) => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const [year, month] = e.target.value.split('-');
      const baseDate = new Date(parseInt(year), parseInt(month) - 1, 1);
      const { start, end } = getDateRangeForPeriod(baseDate);
      router.get(
        '/staff/reports',
        { start_date: format(start, 'yyyy-MM-dd'), end_date: format(end, 'yyyy-MM-dd') },
        { preserveState: false }
      );
    } else {
      router.get('/staff/reports', { month: e.target.value }, { preserveState: true });
    }
  }, [shiftDisplayPeriod, getDateRangeForPeriod]);

  const openApplicationDialog = useCallback((date: Date) => {
    setSelectedDate(date);
    setDialogOpen(true);
  }, []);

  const closeApplicationDialog = useCallback(() => {
    setDialogOpen(false);
    setSelectedDate(null);
  }, []);

  const handleApplicationSubmit = useCallback(
    async (
      type: string,
      reason: string,
      clockErrorData?: { startTime?: string; endTime?: string; breakStartTime?: string; breakEndTime?: string }
    ): Promise<void> => {
      if (!selectedDate) return;

      return new Promise<void>((resolve, reject) => {
        router.post(
          '/staff/requests',
          {
            target_date: format(selectedDate, 'yyyy-MM-dd'),
            type,
            reason,
            start_time: clockErrorData?.startTime,
            end_time: clockErrorData?.endTime,
            break_start_time: clockErrorData?.breakStartTime,
            break_end_time: clockErrorData?.breakEndTime,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              closeApplicationDialog();
              resolve();
            },
            onError: () => {
              reject(new Error('申請の送信に失敗しました'));
            },
          }
        );
      });
    },
    [selectedDate, closeApplicationDialog]
  );

  const renderLastColumn = useCallback(
    (date: Date, summary: WorkSummary | undefined) => {
      const dateRequests = getRequestsForDate(date);
      const dateStr = format(date, 'yyyy-MM-dd');
      const corrections = timeRecordCorrections?.[dateStr] || [];

      return (
        <div className="flex flex-col gap-1">
          <div className="flex flex-wrap items-center gap-1.5">
            {dateRequests.map((req) => (
              <React.Fragment key={req.id}>
                <button
                  type="button"
                  onClick={() => setSelectedRequest(req)}
                  className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${getRequestBadgeStyle(req.type.code)} cursor-pointer transition-colors`}
                >
                  {req.type.label}
                </button>
                {req.status.value === 1 && (
                  <span title="申請中" className="cursor-default">
                    <Clock className="h-4 w-4 text-yellow-500" />
                  </span>
                )}
                {req.status.value === 2 && (
                  <span title="承認済み" className="cursor-default">
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  </span>
                )}
                {req.status.value === 3 && (
                  <span title="却下" className="cursor-default">
                    <XCircle className="h-4 w-4 text-red-500" />
                  </span>
                )}
                {req.status.value === 4 && (
                  <span title="取消" className="cursor-default">
                    <XCircle className="h-4 w-4 text-gray-400" />
                  </span>
                )}
              </React.Fragment>
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
          {dateRequests.length === 0 && corrections.length === 0 && !summary?.note && (
            <button
              onClick={() => openApplicationDialog(date)}
              className="text-xs text-blue-600 hover:text-blue-800 hover:underline text-left"
            >
              申請する
            </button>
          )}
          {dateRequests.length === 1 && (
            <button
              onClick={() => openApplicationDialog(date)}
              className="text-xs text-blue-600 hover:text-blue-800 hover:underline text-left"
            >
              追加申請
            </button>
          )}
        </div>
      );
    },
    [getRequestsForDate, openApplicationDialog, setSelectedRequest, timeRecordCorrections]
  );

  return (
    <div className="space-y-6">
      <ApprovedRequestDetailModal
        request={selectedRequest}
        onClose={() => setSelectedRequest(null)}
      />

      <CorrectionDetailModal
        date={selectedCorrection?.date ?? null}
        corrections={selectedCorrection?.items ?? []}
        onClose={() => setSelectedCorrection(null)}
      />

      <ApplicationDialog
        isOpen={isDialogOpen}
        onClose={closeApplicationDialog}
        onSubmit={handleApplicationSubmit}
        date={selectedDate}
        leaveSettings={leaveSettings}
        existingTypes={selectedDate ? getExistingTypesForDate(selectedDate) : []}
      />

      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">勤務実績</h1>
        <MonthSelector
          value={format(getCurrentPeriodMonth(), 'yyyy-MM')}
          onChange={handlePeriodSelect}
          formatLabel={getPeriodLabel}
        />
      </div>

      <WorkReportTable
        workSummaries={workSummaries}
        monthlySummary={monthlySummary}
        startDate={filters.start_date}
        endDate={filters.end_date}
        todayWorkStart={todayWorkStart}
        shifts={shifts}
        timeRecords={timeRecords}
        summaryLabel={getPeriodLabel(getCurrentPeriodMonth())}
        lastColumnHeader="備考/申請"
        renderLastColumn={renderLastColumn}
      />
    </div>
  );
}

StaffReportsPage.layout = (page: React.ReactNode) => <StaffLayout>{page}</StaffLayout>;

export default StaffReportsPage;
