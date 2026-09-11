import { addDays, format } from 'date-fns';
import type { TimeRecord } from '@/types/reports';

export interface BreakPeriod {
  start: string;
  end: string;
}

/**
 * 対象日の打刻レコード + 日跨ぎ夜勤の場合のみ翌日のレコードをマージして返す
 *
 * 翌日レコードは WORK_END_NEXT_DAY (=3) と、その時刻以前の BREAK_START/END のみ対象
 */
export function getRecordsForDateIncludingNextDayCarryOver(
  timeRecords: TimeRecord[] | undefined,
  date: Date
): TimeRecord[] {
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
}

/**
 * 対象日の休憩期間一覧（最大2件）を取得する
 */
export function getBreakPeriodsForDate(timeRecords: TimeRecord[] | undefined, date: Date): BreakPeriod[] {
  const records = getRecordsForDateIncludingNextDayCarryOver(timeRecords, date);

  // その日に出勤打刻 (WORK_START) がない場合は休憩を表示しない。
  // 前日の夜勤で翌日日付の record_time を持つ休憩レコードが
  // 当日の dayRecords に混入して表示されるのを防ぐ。
  const hasWorkStart = records.some((r) => r.record_type.is_work_start);
  if (!hasWorkStart) {
    // 当日の日付越え退勤（type=3）は前日の夜勤の終わりであり、
    // 前日の行で（getRecordsForDateIncludingNextDayCarryOver により）表示済みのため、
    // 当日の行には表示しない
    const hasCrossDayEndToday = records.some((r) => String(r.record_type.value) === '3');
    if (hasCrossDayEndToday) {
      return [];
    }

    // 当日に出勤がなくても、前日から夜勤継続中の場合は休憩を表示する
    // (getRecordsForDateIncludingNextDayCarryOver が翌日レコードをマージ済み)
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
}

/**
 * 打刻レコードから生の出退勤時刻を取得（丸めなし）
 *
 * 日跨ぎ夜勤の場合、退勤は翌日の WORK_END_NEXT_DAY から取得する
 */
export function getRawWorkTimesForDate(
  timeRecords: TimeRecord[] | undefined,
  date: Date
): { rawStart: string | null; rawEnd: string | null } {
  const records = getRecordsForDateIncludingNextDayCarryOver(timeRecords, date);
  const hasWorkStart = records.some((r) => r.record_type.is_work_start);

  // 当日に出勤打刻がなく、日付越え退勤（type=3）だけがある場合は
  // 前日の夜勤の終わりが当日の日付で記録されているだけであり、
  // 前日の行で既に表示済みのため当日には何も表示しない
  if (!hasWorkStart && records.some((r) => String(r.record_type.value) === '3')) {
    return { rawStart: null, rawEnd: null };
  }

  // 退勤は「今回の出勤より後」に発生したものだけを対象にする。
  // 日跨ぎ夜勤の翌日欄には、前夜の残り(WORK_END_NEXT_DAY、当日の新しい出勤より前)と
  // 当日の新しい出勤が混在することがある。出勤位置より前を含めて探すと、
  // 前夜の退勤時刻を当日の退勤として誤表示してしまう。
  const workStartIndex = records.findIndex((r) => r.record_type.is_work_start);
  const workStartRecord = workStartIndex >= 0 ? records[workStartIndex] : undefined;
  const workEndSearchRecords = workStartIndex >= 0 ? records.slice(workStartIndex) : records;
  const workEndRecord = [...workEndSearchRecords].reverse().find((r) => r.record_type.is_work_end);
  return {
    rawStart: workStartRecord?.record_time ?? null,
    rawEnd: workEndRecord?.record_time ?? null,
  };
}
