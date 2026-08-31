import React, { useMemo, useCallback } from 'react';
import { format, parseISO, eachDayOfInterval, addDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import { getHolidayName } from '@/lib/holidays';
import { formatMinutesToHM } from '@/utils/timeFormat';
import type { WorkSummary, MonthlySummary, TimeRecord, ShiftInfo } from '@/types/reports';

interface BreakPeriod {
  start: string;
  end: string;
}

interface WorkReportTableProps {
  workSummaries: WorkSummary[];
  monthlySummary: MonthlySummary | null;
  startDate: string;
  endDate: string;
  todayWorkStart?: string | null;
  shifts?: Record<string, ShiftInfo>;
  timeRecords?: TimeRecord[];
  summaryLabel: string;
  lastColumnHeader?: string;
  renderLastColumn: (date: Date, summary: WorkSummary | undefined) => React.ReactNode;
  extraColumns?: {
    header: React.ReactNode;
    render: (date: Date, summary: WorkSummary | undefined) => React.ReactNode;
  }[];
}

function isTodayDate(date: Date): boolean {
  const today = new Date();
  return (
    date.getDate() === today.getDate() &&
    date.getMonth() === today.getMonth() &&
    date.getFullYear() === today.getFullYear()
  );
}

export default function WorkReportTable({
  workSummaries,
  monthlySummary,
  startDate,
  endDate,
  todayWorkStart,
  shifts,
  timeRecords,
  summaryLabel,
  lastColumnHeader = '備考',
  renderLastColumn,
  extraColumns,
}: WorkReportTableProps) {
  const daysInMonth = useMemo(() => {
    return eachDayOfInterval({ start: parseISO(startDate), end: parseISO(endDate) });
  }, [startDate, endDate]);

  const getSummaryForDate = useCallback(
    (date: Date): WorkSummary | undefined => {
      const dateStr = format(date, 'yyyy-MM-dd');
      return workSummaries.find((s) => s.work_date === dateStr);
    },
    [workSummaries]
  );

  // 対象日の打刻レコード + 日跨ぎ夜勤の場合のみ翌日のレコードをマージして返す
  // 翌日レコードは WORK_END_NEXT_DAY (=3) と、その時刻以前の BREAK_START/END のみ対象
  const getRecordsForDateIncludingNextDayCarryOver = useCallback(
    (date: Date): TimeRecord[] => {
      if (!timeRecords) return [];
      const dateStr = format(date, 'yyyy-MM-dd');
      const nextDateStr = format(addDays(date, 1), 'yyyy-MM-dd');

      const dayRecords = timeRecords.filter((r) => r.record_date === dateStr);
      const nextDayRecords = timeRecords.filter((r) => r.record_date === nextDateStr);

      // 翌日に WORK_END_NEXT_DAY があるかチェック（日跨ぎ夜勤の判定）
      const nextDayCrossEnd = nextDayRecords.find((r) => String(r.record_type.value) === '3');
      if (!nextDayCrossEnd) {
        return dayRecords;
      }

      // 翌日の WORK_END_NEXT_DAY と、その時刻以前の翌日休憩レコードもマージ
      const carriedNextDayRecords = nextDayRecords.filter((r) => {
        if (String(r.record_type.value) === '3') return true;
        if (r.record_type.is_break) {
          return r.record_time.localeCompare(nextDayCrossEnd.record_time) <= 0;
        }
        return false;
      });

      return [...dayRecords, ...carriedNextDayRecords];
    },
    [timeRecords]
  );

  const getBreakPeriodsForDate = useCallback(
    (date: Date): BreakPeriod[] => {
      const records = getRecordsForDateIncludingNextDayCarryOver(date);

      // その日に出勤打刻 (WORK_START) がない場合は休憩を表示しない。
      // 前日の夜勤で翌日日付の record_time を持つ休憩レコードが
      // 当日の dayRecords に混入して表示されるのを防ぐ。
      const hasWorkStart = records.some((r) => r.record_type.is_work_start);
      if (!hasWorkStart) {
        // 当日に出勤がなくても、前日から夜勤継続中の場合は休憩を表示する
        // (getRecordsForDateIncludingNextDayCarryOver が翌日レコードをマージ済み)
        const dateStr = format(date, 'yyyy-MM-dd');
        const prevDateStr = format(addDays(date, -1), 'yyyy-MM-dd');
        const prevDayRecords = timeRecords?.filter((r) => r.record_date === prevDateStr) ?? [];
        const prevDayHasWorkStart = prevDayRecords.some((r) => r.record_type.is_work_start);
        const prevDayHasWorkEnd = prevDayRecords.some((r) => r.record_type.is_work_end);
        // 前日に出勤があり退勤がない場合のみ夜勤継続 → 当日の休憩を表示
        if (!prevDayHasWorkStart || prevDayHasWorkEnd) {
          return [];
        }
      }

      // BREAK_START=4, BREAK_END=5
      const starts = records
        .filter((r) => String(r.record_type.value) === '4')
        .sort((a, b) => a.record_time.localeCompare(b.record_time));
      const ends = records
        .filter((r) => String(r.record_type.value) === '5')
        .sort((a, b) => a.record_time.localeCompare(b.record_time));
      const periods: BreakPeriod[] = [];
      for (let i = 0; i < starts.length; i++) {
        if (ends[i]) {
          periods.push({ start: starts[i].record_time, end: ends[i].record_time });
        }
      }
      return periods.slice(0, 2);
    },
    [getRecordsForDateIncludingNextDayCarryOver, timeRecords]
  );

  // 打刻レコードから生の出退勤時刻を取得（丸めなし）
  // 日跨ぎ夜勤の場合、退勤は翌日の WORK_END_NEXT_DAY から取得する
  const getRawWorkTimesForDate = useCallback(
    (date: Date): { rawStart: string | null; rawEnd: string | null } => {
      const records = getRecordsForDateIncludingNextDayCarryOver(date);
      const workStartRecord = records.find((r) => r.record_type.is_work_start);
      const workEndRecord = [...records].reverse().find((r) => r.record_type.is_work_end);
      return {
        rawStart: workStartRecord?.record_time ?? null,
        rawEnd: workEndRecord?.record_time ?? null,
      };
    },
    [getRecordsForDateIncludingNextDayCarryOver]
  );

  return (
    <>
      {/* サマリー */}
      <div className="bg-white shadow rounded-lg px-6 py-4">
        <div className="flex items-center justify-between flex-wrap gap-4">
          <div className="text-lg font-bold text-gray-900">{summaryLabel}</div>
          <div className="flex items-center gap-6 flex-wrap">
            <div className="text-sm">
              <span className="text-gray-600">出勤日数: </span>
              <span className="font-semibold text-gray-900">{monthlySummary?.work_days || 0}日</span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">労働時間: </span>
              <span className="font-semibold text-gray-900">
                {formatMinutesToHM(monthlySummary?.total_net_work_minutes || 0)}
              </span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">時間外: </span>
              <span className="font-semibold text-orange-600">
                {formatMinutesToHM(monthlySummary?.total_overtime_minutes || 0)}
              </span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">深夜: </span>
              <span className="font-semibold text-indigo-600">
                {formatMinutesToHM(monthlySummary?.total_night_minutes || 0)}
              </span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">遅刻早退: </span>
              <span className="font-semibold text-yellow-600">
                {(monthlySummary?.late_count || 0) + (monthlySummary?.early_leave_count || 0)}回
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* テーブル */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                  日付
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                  シフト
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                  勤務時間
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                  休憩
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                  労働時間
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-orange-600 uppercase tracking-wider w-20">
                  時間外
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-red-600 uppercase tracking-wider w-20">
                  休日
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-indigo-600 uppercase tracking-wider w-20">
                  深夜
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-yellow-600 uppercase tracking-wider w-24 whitespace-nowrap">
                  遅刻早退
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                  {lastColumnHeader}
                </th>
                {extraColumns?.map((col, i) => (
                  <React.Fragment key={i}>{col.header}</React.Fragment>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {daysInMonth.map((date) => {
                const summary = getSummaryForDate(date);
                const isCurrentDay = isTodayDate(date);
                const dayOfWeek = date.getDay();
                const dayName = format(date, 'E', { locale: ja });
                const holiday = getHolidayName(date);

                return (
                  <tr key={date.toString()} className={`${isCurrentDay ? 'bg-green-50' : ''} hover:bg-gray-50`}>
                    <td className="px-3 py-3 whitespace-nowrap">
                      <div className="flex items-center gap-1.5">
                        <span className="text-sm font-medium text-gray-900">{format(date, 'd')}日</span>
                        <span
                          className={`text-sm font-medium ${
                            dayOfWeek === 0 || holiday
                              ? 'text-red-600'
                              : dayOfWeek === 6
                              ? 'text-blue-600'
                              : 'text-gray-700'
                          }`}
                        >
                          ({dayName})
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-3 text-sm text-gray-500">
                      {(() => {
                        const dateKey = format(date, 'yyyy-MM-dd');
                        const shiftInfo = shifts?.[dateKey];
                        const hasSchedule = summary?.scheduled_start_time && summary?.scheduled_end_time;
                        if (shiftInfo) {
                          return (
                            <div>
                              <span className="text-gray-900 font-medium whitespace-nowrap">{shiftInfo.label}</span>
                              {shiftInfo.break_start && shiftInfo.break_end && (
                                <div className="text-xs text-gray-400 whitespace-nowrap">
                                  (休憩 {shiftInfo.break_start}~{shiftInfo.break_end})
                                </div>
                              )}
                            </div>
                          );
                        }
                        return hasSchedule
                          ? `${String(summary.scheduled_start_time).substring(0, 5)}~${String(summary.scheduled_end_time).substring(0, 5)}`
                          : <span className="text-gray-400">休み</span>;
                      })()}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                      {(() => {
                        const { rawStart, rawEnd } = getRawWorkTimesForDate(date);
                        const displayStart = rawStart ?? summary?.work_start;
                        const displayEnd = rawEnd ?? summary?.work_end;
                        if (displayStart && displayEnd) {
                          return `${displayStart} ~ ${displayEnd}${summary?.is_cross_day ? ' (翌)' : ''}`;
                        }
                        if (displayStart) {
                          return `${displayStart} ~ -`;
                        }
                        if (isCurrentDay && todayWorkStart) {
                          return `${todayWorkStart} ~ -`;
                        }
                        return '-';
                      })()}
                    </td>
                    <td className="px-3 py-3 text-sm text-gray-900">
                      {(() => {
                        // 1. 打刻があればそれを優先表示
                        const breakPeriods = getBreakPeriodsForDate(date);
                        if (breakPeriods.length > 0) {
                          return breakPeriods.map((p, i) => (
                            <div key={i}>{p.start}~{p.end}</div>
                          ));
                        }

                        // 出勤実績がない日はシフトパターンの休憩を表示しない
                        const { rawStart } = getRawWorkTimesForDate(date);
                        const hasWorked = !!(rawStart ?? summary?.work_start);

                        // 2. サマリーの実績値を優先（打刻修正で休憩を消した場合は0になる）
                        if (hasWorked && summary) {
                          if (summary.break_minutes === 0) {
                            return '-';
                          }
                          // シフトパターンで時間帯指定がある場合はそれを表示
                          const dateKey = format(date, 'yyyy-MM-dd');
                          const shiftInfo = shifts?.[dateKey];
                          if (shiftInfo?.break_start && shiftInfo?.break_end) {
                            return `${shiftInfo.break_start}~${shiftInfo.break_end}`;
                          }
                          return formatMinutesToHM(summary.break_minutes);
                        }

                        // 3. 出勤実績なしの場合
                        return '-';
                      })()}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                      {formatMinutesToHM(summary?.net_work_minutes || 0)}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm">
                      {summary?.overtime_minutes && summary.overtime_minutes > 0 ? (
                        <span className="font-medium text-orange-600">
                          {formatMinutesToHM(summary.overtime_minutes)}
                        </span>
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm">
                      {summary?.holiday_minutes && summary.holiday_minutes > 0 ? (
                        <span className="font-medium text-red-600">
                          {formatMinutesToHM(summary.holiday_minutes)}
                        </span>
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm">
                      {summary?.night_minutes && summary.night_minutes > 0 ? (
                        <span className="font-medium text-indigo-600">
                          {formatMinutesToHM(summary.night_minutes)}
                        </span>
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm">
                      {(summary?.late_minutes && summary.late_minutes > 0) ||
                      (summary?.early_leave_minutes && summary.early_leave_minutes > 0) ? (
                        <span className="font-medium text-yellow-600">
                          {formatMinutesToHM((summary?.late_minutes || 0) + (summary?.early_leave_minutes || 0))}
                        </span>
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-3 text-sm text-gray-900">{renderLastColumn(date, summary)}</td>
                    {extraColumns?.map((col, i) => (
                      <React.Fragment key={i}>{col.render(date, summary)}</React.Fragment>
                    ))}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}


