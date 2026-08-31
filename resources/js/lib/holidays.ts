/**
 * 祝日管理ユーティリティ
 * japanese-holidaysパッケージを使用して日本の祝日を判定する
 */

import JapaneseHolidays from 'japanese-holidays';
import { format, parse } from 'date-fns';

export interface Holiday {
  date: string; // YYYY-MM-DD形式
  name: string; // 祝日名
}

/**
 * 指定された日付が祝日かどうかを判定する
 * @param date YYYY-MM-DD形式の日付文字列
 * @returns 祝日の場合はHolidayオブジェクト、そうでない場合はnull
 */
export function getHoliday(date: string): Holiday | null {
  const dateObj = parse(date, 'yyyy-MM-dd', new Date());
  const holidayName = JapaneseHolidays.isHoliday(dateObj);
  if (holidayName) {
    return { date, name: holidayName };
  }
  return null;
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
 * Dateオブジェクトから祝日名を取得する
 * @param date Dateオブジェクト
 * @returns 祝日名、祝日でない場合はundefined
 */
export function getHolidayName(date: Date): string | undefined {
  return JapaneseHolidays.isHoliday(date) || undefined;
}

/**
 * 指定された年の全ての祝日を取得する
 * @param year 年
 * @returns その年の祝日配列
 */
export function getHolidaysByYear(year: number): Holiday[] {
  const holidays = JapaneseHolidays.getHolidaysOf(year);
  return holidays.map((h) => ({
    date: format(h.date, 'yyyy-MM-dd'),
    name: h.name,
  }));
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
