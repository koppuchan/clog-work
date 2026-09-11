import React, { useMemo, useCallback } from 'react';
import { format, parseISO, eachDayOfInterval } from 'date-fns';
import { ja } from 'date-fns/locale';
import { getHolidayName } from '@/lib/holidays';
import { formatMinutesToHM } from '@/utils/timeFormat';
import {
  getBreakPeriodsForDate as getBreakPeriodsForDateUtil,
  getRawWorkTimesForDate as getRawWorkTimesForDateUtil,
} from '@/utils/attendanceRecords';
import type { BreakPeriod } from '@/utils/attendanceRecords';
import type { WorkSummary, MonthlySummary, TimeRecord, ShiftInfo, TimeRecordCorrectionItem } from '@/types/reports';

type CorrectionMap = Record<string, TimeRecordCorrectionItem[]>;

/** 打刻種別。勤務開始 / 勤務終了 / 日付越え終了 / 休憩開始 / 休憩終了 */
const WORK_START_TYPES = [1];
const WORK_END_TYPES = [2, 3];
const BREAK_START_TYPES = [4];
const BREAK_END_TYPES = [5];

export type AttendanceIssueMap = Record<string, string[]>;

export const ISSUE_LABELS: Record<string, string> = {
  missing_clock_out: '退勤忘れ',
  not_calculated: '未計算',
  missing_break_end: '休憩漏れ',
};

/** 要対応の種類ごとの色分け。退勤忘れと休憩漏れを見分けやすくする */
const ISSUE_BADGE_STYLES: Record<string, string> = {
  missing_clock_out: 'bg-orange-100 text-orange-800',
  missing_break_end: 'bg-purple-100 text-purple-800',
  not_calculated: 'bg-amber-100 text-amber-800',
};

/**
 * 指定日の指定種別に打刻修正があるか
 */
const hasCorrection = (
  corrections: CorrectionMap | undefined,
  dateStr: string,
  types: number[],
): boolean =>
  (corrections?.[dateStr] ?? []).some((c) => types.includes(c.record_type.value));

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
  /** 日付ごとの打刻修正履歴。修正された時刻を強調表示するために使う */
  corrections?: Record<string, TimeRecordCorrectionItem[]>;
  /** 日付ごとの要対応状態（退勤忘れ・休憩打刻漏れ等）。日付の左に表示する */
  attendanceIssues?: AttendanceIssueMap;
  /** 修正された時刻がクリックされたときに履歴を開く。typesでクリックされた項目（出勤・退勤・休憩開始・休憩終了）だけに絞り込む */
  onCorrectionClick?: (date: string, types: number[]) => void;
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
  corrections,
  onCorrectionClick,
  extraColumns,
  attendanceIssues,
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

  const getBreakPeriodsForDate = useCallback(
    (date: Date): BreakPeriod[] => getBreakPeriodsForDateUtil(timeRecords, date),
    [timeRecords]
  );

  // 打刻レコードから生の出退勤時刻を取得（丸めなし）
  // 日跨ぎ夜勤の場合、退勤は翌日の WORK_END_NEXT_DAY から取得する
  const getRawWorkTimesForDate = useCallback(
    (date: Date): { rawStart: string | null; rawEnd: string | null } => getRawWorkTimesForDateUtil(timeRecords, date),
    [timeRecords]
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
              <span className="text-gray-600">休日: </span>
              <span className="font-semibold text-red-600">
                {formatMinutesToHM(monthlySummary?.total_holiday_minutes || 0)}
              </span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">深夜: </span>
              <span className="font-semibold text-indigo-600">
                {formatMinutesToHM(monthlySummary?.total_night_minutes || 0)}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* テーブル */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <div className="overflow-auto max-h-[70vh]" scroll-region="">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50 sticky top-0 z-10 shadow-[0_1px_0_0_rgb(229,231,235)]">
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
                const dateStr = format(date, 'yyyy-MM-dd');
                const issuesForDate = attendanceIssues?.[dateStr] ?? [];

                return (
                  <tr
                    key={date.toString()}
                    className={`${issuesForDate.length > 0 ? 'bg-orange-50' : isCurrentDay ? 'bg-green-50' : ''} hover:bg-gray-50`}
                  >
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
                      {issuesForDate.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-1">
                          {issuesForDate.map((kind) => (
                            <span
                              key={kind}
                              className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${ISSUE_BADGE_STYLES[kind] ?? 'bg-amber-100 text-amber-800'}`}
                            >
                              {ISSUE_LABELS[kind] ?? kind}
                            </span>
                          ))}
                        </div>
                      )}
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
                        const dateStr = format(date, 'yyyy-MM-dd');
                        // 修正のあった側だけを示すため、開始と終了を別々に判定する
                        const startCorrected = hasCorrection(corrections, dateStr, WORK_START_TYPES);
                        const endCorrected = hasCorrection(corrections, dateStr, WORK_END_TYPES);

                        const startText = displayStart ?? (isCurrentDay ? todayWorkStart : null);

                        if (! startText && ! displayEnd) {
                          return '-';
                        }

                        // 修正された時刻だけオレンジで示し、押すと履歴を開く。
                        // typesを渡して、クリックした側（出勤 or 退勤）だけの履歴に絞り込む
                        const part = (value: string | null | undefined, corrected: boolean, types: number[]) => {
                          if (! value) {
                            return <span>-</span>;
                          }

                          if (! corrected) {
                            return <span>{value}</span>;
                          }

                          return (
                            <button
                              type="button"
                              onClick={() => onCorrectionClick?.(dateStr, types)}
                              title="打刻修正あり。クリックで履歴を表示します"
                              className="font-medium text-orange-600 underline decoration-dotted underline-offset-2 hover:text-orange-700"
                            >
                              {value}
                            </button>
                          );
                        };

                        return (
                          <span>
                            {part(startText, startCorrected, WORK_START_TYPES)}
                            <span className="mx-1">~</span>
                            {part(displayEnd, endCorrected, WORK_END_TYPES)}
                            {summary?.is_cross_day ? ' (翌)' : ''}
                          </span>
                        );
                      })()}
                    </td>
                    <td className="px-3 py-3 text-sm text-gray-900">
                      {(() => {
                        const breakDateStr = format(date, 'yyyy-MM-dd');
                        const breakStartCorrected = hasCorrection(corrections, breakDateStr, BREAK_START_TYPES);
                        const breakEndCorrected = hasCorrection(corrections, breakDateStr, BREAK_END_TYPES);

                        // 修正のあった側だけを示す。typesでクリックした側（休憩開始 or 休憩終了）だけの履歴に絞り込む
                        const breakPart = (value: string, corrected: boolean, types: number[]) => {
                          if (! corrected) {
                            return <span>{value}</span>;
                          }

                          return (
                            <button
                              type="button"
                              onClick={() => onCorrectionClick?.(breakDateStr, types)}
                              title="休憩の打刻修正あり。クリックで履歴を表示します"
                              className="font-medium text-orange-600 underline decoration-dotted underline-offset-2 hover:text-orange-700"
                            >
                              {value}
                            </button>
                          );
                        };

                        // 1. 打刻があればそれを優先表示
                        const breakPeriods = getBreakPeriodsForDate(date);
                        if (breakPeriods.length > 0) {
                          return breakPeriods.map((p, i) => (
                            <div key={i}>
                              {breakPart(p.start, breakStartCorrected, BREAK_START_TYPES)}
                              <span className="mx-0.5">~</span>
                              {breakPart(p.end, breakEndCorrected, BREAK_END_TYPES)}
                            </div>
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
                            const text = `${shiftInfo.break_start}~${shiftInfo.break_end}`;

                            // 打刻ではなくシフトから当てた休憩は薄く表示して区別する
                            return shiftInfo.auto_fill_break ? (
                              <span className="text-gray-400" title="打刻がないためシフトの休憩を適用しています">
                                {text}
                              </span>
                            ) : (
                              text
                            );
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
                      {/* 承認済みの休暇がある日は遅刻早退として扱わない */}
                      {!summary?.leave_type &&
                      ((summary?.late_minutes && summary.late_minutes > 0) ||
                        (summary?.early_leave_minutes && summary.early_leave_minutes > 0)) ? (
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


