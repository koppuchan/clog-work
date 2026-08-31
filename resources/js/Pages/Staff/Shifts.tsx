import React, { useCallback } from 'react';
import StaffLayout from '@/Layouts/StaffLayout';
import { format } from 'date-fns';
import { useStaffShifts } from '@/hooks/staff/shifts/useStaffShifts';
import { ShiftTable, ShiftLegend } from '@/Components/Staff/Shifts';
import MonthSelector from '@/Components/MonthSelector';
import type { StaffShiftsPageProps } from '@/types/staff/shifts';

function StaffShiftsPage({ users, shifts, shiftPatterns, filters, shiftDisplayPeriod, payrollClosingDay }: StaffShiftsPageProps) {
  const {
    daysInMonth,
    periodMonth,
    getHoliday,
    getShiftForDate,
    getTimeRange,
    handleMonthSelect,
  } = useStaffShifts({ shifts, filters, shiftDisplayPeriod, payrollClosingDay });

  const getPeriodLabel = useCallback((baseDate: Date) => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const closingDay = payrollClosingDay;
      const prevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() - 1, 1);
      const daysInPrevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 0).getDate();
      const startDay = closingDay + 1;
      if (closingDay >= daysInPrevMonth) {
        return `${format(baseDate, 'yyyy年M月')}1日〜${format(baseDate, 'M月')}${closingDay}日`;
      }
      return `${format(prevMonth, 'yyyy年M月')}${startDay}日〜${format(baseDate, 'M月')}${closingDay}日`;
    }
    return format(baseDate, 'yyyy年M月');
  }, [shiftDisplayPeriod, payrollClosingDay]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">シフト確認</h1>
        <MonthSelector
          value={format(periodMonth, 'yyyy-MM')}
          onChange={(e) => handleMonthSelect(e.target.value)}
          formatLabel={getPeriodLabel}
        />
      </div>

      <ShiftTable
        users={users}
        daysInMonth={daysInMonth}
        getHoliday={getHoliday}
        getShiftForDate={getShiftForDate}
        getTimeRange={getTimeRange}
      />

      <ShiftLegend shiftPatterns={shiftPatterns} />
    </div>
  );
}

StaffShiftsPage.layout = (page: React.ReactNode) => <StaffLayout>{page}</StaffLayout>;

export default StaffShiftsPage;
