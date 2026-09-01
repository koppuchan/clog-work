import { useMemo } from 'react';
import { format } from 'date-fns';
import { Shift, User, ShiftTypeInfo } from '@/types';

interface UseShiftStatsOptions {
  startDate?: string;
  endDate?: string;
  shiftTypeInfo?: ShiftTypeInfo[];
}

export function useShiftStats(selectedDate: Date, shifts: Shift[], activeUsers: User[], options?: UseShiftStatsOptions) {
  // 期間の勤務時間と休日日数を計算
  const getMonthlyStats = useMemo(() => {
    return (userId: string) => {
      // 指定された期間または選択日付から期間を決定
      const periodStartStr = options?.startDate || format(selectedDate, 'yyyy-MM-dd');
      const periodEndStr = options?.endDate || format(selectedDate, 'yyyy-MM-dd');

      // スタッフのシフトを期間内でフィルター
      const userShifts = shifts.filter(shift =>
        shift.userId === userId &&
        shift.date >= periodStartStr &&
        shift.date <= periodEndStr
      );

      // 勤務日数（rest以外のシフト）
      const workDays = userShifts.filter(shift => shift.shiftType !== 'rest').length;

      // 休日 = 期間の総日数 - 勤務日数（restはバックエンドに保存されないため）
      const start = new Date(periodStartStr);
      const end = new Date(periodEndStr);
      const totalDays = Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
      const restDays = totalDays - workDays;

      // 勤務時間を計算（シフトパターンの勤務時間から計算）
      // TODO: 実際のシフトパターンの勤務時間を取得して計算する
      // 暫定的に勤務日数 × 8時間で計算
      const totalHours = workDays * 8;

      return {
        totalHours: Math.round(totalHours * 10) / 10,
        restDays,
        workDays
      };
    };
  }, [selectedDate, shifts, options?.startDate, options?.endDate]);

  // 全スタッフの月間合計を計算
  const getAllStaffTotals = useMemo(() => {
    return () => {
      let totalHours = 0;
      let totalRestDays = 0;
      let totalWorkDays = 0;

      activeUsers.forEach(user => {
        const stats = getMonthlyStats(user.id);
        totalHours += stats.totalHours;
        totalRestDays += stats.restDays;
        totalWorkDays += stats.workDays;
      });

      return {
        totalHours: Math.round(totalHours * 10) / 10,
        totalRestDays,
        totalWorkDays
      };
    };
  }, [activeUsers, getMonthlyStats]);

  // 日付ごとのシフト人数を集計
  const getShiftCountsByDate = useMemo(() => {
    return (date: Date) => {
      const dateStr = format(date, 'yyyy-MM-dd');
      const dayShifts = shifts.filter(shift => shift.date === dateStr);

      const counts: { [key: string]: number } = {};
      dayShifts.forEach(shift => {
        if (counts[shift.shiftType]) {
          counts[shift.shiftType]++;
        } else {
          counts[shift.shiftType] = 1;
        }
      });

      return counts;
    };
  }, [shifts]);

  return {
    getMonthlyStats,
    getAllStaffTotals,
    getShiftCountsByDate,
  };
}
