/**
 * 分を H:MM 形式の文字列に変換する
 * 0以下の場合は '-' を返す
 */
export function formatMinutesToHM(minutes: number): string {
  if (minutes <= 0) return '-';
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return `${hours}:${String(mins).padStart(2, '0')}`;
}
