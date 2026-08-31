/**
 * 祝日管理ユーティリティ
 * 日本の祝日データを管理し、指定された日付が祝日かどうかを判定する
 */

export interface Holiday {
  date: string; // YYYY-MM-DD形式
  name: string; // 祝日名
}

/**
 * 2024年と2025年の日本の祝日データ
 * 将来的には外部APIやライブラリに置き換え可能
 */
export const japaneseHolidays2024: Holiday[] = [
  { date: '2024-01-01', name: '元日' },
  { date: '2024-01-08', name: '成人の日' },
  { date: '2024-02-11', name: '建国記念の日' },
  { date: '2024-02-12', name: '振替休日' },
  { date: '2024-02-23', name: '天皇誕生日' },
  { date: '2024-03-20', name: '春分の日' },
  { date: '2024-04-29', name: '昭和の日' },
  { date: '2024-05-03', name: '憲法記念日' },
  { date: '2024-05-04', name: 'みどりの日' },
  { date: '2024-05-05', name: 'こどもの日' },
  { date: '2024-05-06', name: '振替休日' },
  { date: '2024-07-15', name: '海の日' },
  { date: '2024-08-11', name: '山の日' },
  { date: '2024-08-12', name: '振替休日' },
  { date: '2024-09-16', name: '敬老の日' },
  { date: '2024-09-22', name: '秋分の日' },
  { date: '2024-09-23', name: '振替休日' },
  { date: '2024-10-14', name: 'スポーツの日' },
  { date: '2024-11-03', name: '文化の日' },
  { date: '2024-11-04', name: '振替休日' },
  { date: '2024-11-23', name: '勤労感謝の日' },
];

export const japaneseHolidays2025: Holiday[] = [
  { date: '2025-01-01', name: '元日' },
  { date: '2025-01-13', name: '成人の日' },
  { date: '2025-02-11', name: '建国記念の日' },
  { date: '2025-02-23', name: '天皇誕生日' },
  { date: '2025-02-24', name: '振替休日' },
  { date: '2025-03-20', name: '春分の日' },
  { date: '2025-04-29', name: '昭和の日' },
  { date: '2025-05-03', name: '憲法記念日' },
  { date: '2025-05-04', name: 'みどりの日' },
  { date: '2025-05-05', name: 'こどもの日' },
  { date: '2025-05-06', name: '振替休日' },
  { date: '2025-07-21', name: '海の日' },
  { date: '2025-08-11', name: '山の日' },
  { date: '2025-09-15', name: '敬老の日' },
  { date: '2025-09-23', name: '秋分の日' },
  { date: '2025-10-13', name: 'スポーツの日' },
  { date: '2025-11-03', name: '文化の日' },
  { date: '2025-11-23', name: '勤労感謝の日' },
  { date: '2025-11-24', name: '振替休日' },
];

/**
 * 全ての祝日データを年でマッピング
 */
export const holidaysByYear: Record<number, Holiday[]> = {
  2024: japaneseHolidays2024,
  2025: japaneseHolidays2025,
};

/**
 * 指定された日付が祝日かどうかを判定する
 * @param date YYYY-MM-DD形式の日付文字列
 * @returns 祝日の場合はHolidayオブジェクト、そうでない場合はnull
 */
export function getHoliday(date: string): Holiday | null {
  const year = parseInt(date.substring(0, 4), 10);
  const holidays = holidaysByYear[year] || [];
  return holidays.find((holiday) => holiday.date === date) || null;
}

/**
 * 指定された日付が祝日かどうかを判定する
 * @param date YYYY-MM-DD形式の日付文字列
 * @returns 祝日の場合はtrue
 */
export function isHoliday(date: string): boolean {
  return getHoliday(date) !== null;
}

/**
 * 指定された年の全ての祝日を取得する
 * @param year 年
 * @returns その年の祝日配列
 */
export function getHolidaysByYear(year: number): Holiday[] {
  return holidaysByYear[year] || [];
}

/**
 * 指定された月の祝日を取得する
 * @param year 年
 * @param month 月（1-12）
 * @returns その月の祝日配列
 */
export function getHolidaysByMonth(year: number, month: number): Holiday[] {
  const holidays = getHolidaysByYear(year);
  const monthStr = month.toString().padStart(2, '0');
  return holidays.filter((holiday) => {
    const holidayMonth = holiday.date.substring(5, 7);
    return holidayMonth === monthStr;
  });
}
