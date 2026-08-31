import { useState, useCallback, useMemo } from 'react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, addMonths, subMonths, parseISO } from 'date-fns';
import { router } from '@inertiajs/react';
import { getHolidayName } from '@/lib/holidays';
import type { ShiftData, StaffShiftsPageProps } from '@/types/staff/shifts';

export function useStaffShifts({ shifts, filters, shiftDisplayPeriod, payrollClosingDay }: Pick<StaffShiftsPageProps, 'shifts' | 'filters' | 'shiftDisplayPeriod' | 'payrollClosingDay'>) {
  const [currentDate, setCurrentDate] = useState(() => {
    if (filters.start_date) {
      return parseISO(filters.start_date);
    }
    return new Date();
  });

  const daysInMonth = useMemo(
    () => eachDayOfInterval({
      start: parseISO(filters.start_date),
      end: parseISO(filters.end_date),
    }),
    [filters.start_date, filters.end_date]
  );

  const periodMonth = useMemo(() => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const endDate = parseISO(filters.end_date);
      return new Date(endDate.getFullYear(), endDate.getMonth(), 1);
    }
    return parseISO(filters.start_date);
  }, [shiftDisplayPeriod, filters.start_date, filters.end_date]);

  const getHoliday = useCallback((date: Date): string | undefined => {
    return getHolidayName(date);
  }, []);

  const getShiftForDate = useCallback(
    (date: Date, userId: number): ShiftData | undefined => {
      const dateStr = format(date, 'yyyy-MM-dd');
      return shifts[dateStr]?.[userId];
    },
    [shifts]
  );

  const getTimeRange = useCallback((shift: ShiftData): string => {
    if (shift.start_time && shift.end_time) {
      const start = shift.start_time.substring(0, 5);
      const end = shift.end_time.substring(0, 5);
      return `${start}-${end}`;
    }
    return '';
  }, []);

  const getDateRangeForPeriod = useCallback((baseDate: Date): { start: Date; end: Date } => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const closingDay = payrollClosingDay;
      const daysInPrevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 0).getDate();

      let start: Date;
      if (closingDay >= daysInPrevMonth) {
        start = new Date(baseDate.getFullYear(), baseDate.getMonth(), 1);
      } else {
        start = new Date(baseDate.getFullYear(), baseDate.getMonth() - 1, closingDay + 1);
      }

      const daysInCurrentMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() + 1, 0).getDate();
      const endDay = Math.min(closingDay, daysInCurrentMonth);
      const end = new Date(baseDate.getFullYear(), baseDate.getMonth(), endDay);

      return { start, end };
    } else {
      return {
        start: startOfMonth(baseDate),
        end: endOfMonth(baseDate),
      };
    }
  }, [shiftDisplayPeriod, payrollClosingDay]);

  const changeMonth = useCallback((date: Date) => {
    setCurrentDate(date);
    const { start, end } = getDateRangeForPeriod(date);
    const startDate = format(start, 'yyyy-MM-dd');
    const endDate = format(end, 'yyyy-MM-dd');

    router.get(
      '/staff/shifts',
      {
        start_date: startDate,
        end_date: endDate,
      },
      {
        preserveState: true,
        preserveScroll: true,
      }
    );
  }, [getDateRangeForPeriod]);

  const previousMonth = useCallback(() => {
    changeMonth(subMonths(currentDate, 1));
  }, [currentDate, changeMonth]);

  const nextMonth = useCallback(() => {
    changeMonth(addMonths(currentDate, 1));
  }, [currentDate, changeMonth]);

  const handleYearChange = useCallback(
    (year: number) => {
      changeMonth(new Date(year, currentDate.getMonth(), 1));
    },
    [currentDate, changeMonth]
  );

  const handleMonthChange = useCallback(
    (month: number) => {
      changeMonth(new Date(currentDate.getFullYear(), month, 1));
    },
    [currentDate, changeMonth]
  );

  const handleMonthSelect = useCallback(
    (yearMonth: string) => {
      const [year, month] = yearMonth.split('-').map(Number);
      changeMonth(new Date(year, month - 1, 1));
    },
    [changeMonth]
  );

  return {
    currentDate,
    daysInMonth,
    periodMonth,
    getHoliday,
    getShiftForDate,
    getTimeRange,
    previousMonth,
    nextMonth,
    handleYearChange,
    handleMonthChange,
    handleMonthSelect,
  };
}
