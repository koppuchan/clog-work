const MINUTES_PER_DAY = 24 * 60;

function toMinutes(time: string): number {
  const [hours, minutes] = time.split(':').map(Number);
  return hours * 60 + minutes;
}

function toTimeString(minutes: number): string {
  const wrapped = minutes % MINUTES_PER_DAY;
  const hours = Math.floor(wrapped / 60);
  const mins = wrapped % 60;
  return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
}

/**
 * シフトの休憩のうち、実労働時間と重なる範囲を求める（HH:mm形式）
 *
 * サーバー側の集計（AutoBreakFillService::resolvePeriod）と同じ判定。
 * 重なりがない場合は集計でも休憩を補完しないため null を返す。
 * 勤務終了が勤務開始以前の場合は日跨ぎ勤務として扱う。
 */
export function resolveAutoFillBreakPeriod(
  breakStart: string,
  breakEnd: string,
  workStart: string,
  workEnd: string
): { start: string; end: string } | null {
  const workStartMinutes = toMinutes(workStart);
  let workEndMinutes = toMinutes(workEnd);
  if (workEndMinutes <= workStartMinutes) {
    workEndMinutes += MINUTES_PER_DAY;
  }

  let breakStartMinutes = toMinutes(breakStart);
  let breakEndMinutes = toMinutes(breakEnd);
  if (breakEndMinutes <= breakStartMinutes) {
    breakEndMinutes += MINUTES_PER_DAY;
  }
  // 夜勤で休憩が翌日側にある場合は勤務開始より後ろへ寄せる
  if (breakEndMinutes <= workStartMinutes) {
    breakStartMinutes += MINUTES_PER_DAY;
    breakEndMinutes += MINUTES_PER_DAY;
  }

  const start = Math.max(breakStartMinutes, workStartMinutes);
  const end = Math.min(breakEndMinutes, workEndMinutes);
  if (end <= start) {
    return null;
  }

  return { start: toTimeString(start), end: toTimeString(end) };
}
