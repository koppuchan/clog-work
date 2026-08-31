import { useState, useCallback, useMemo, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, addMonths, subMonths, parseISO } from 'date-fns';
import { getHolidayName } from '@/lib/holidays';
import { formatMinutesToHM } from '@/utils/timeFormat';
import { AttendanceRecord } from '@/types';
import axios from 'axios';

// バックエンドから渡される打刻レコードの型
interface TimeRecordData {
  id: number;
  record_time: string; // "HH:mm" 形式
  record_type: {
    value: string;
    label: string;
    is_work_start: boolean;
    is_work_end: boolean;
    is_break_start: boolean;
    is_break_end: boolean;
  };
  record_source: {
    value: string;
    label: string;
  };
  note: string | null;
}

// バックエンドから渡されるシフトの型
interface ShiftData {
  id: number;
  shift_pattern_id: number | null;
  shift_pattern_name: string | null;
  start_time: string | null;
  end_time: string | null;
  work_minutes: number | null;
  break_minutes: number | null;
  color: string | null;
  note: string | null;
}

// バックエンドから渡される申請の型
interface RequestData {
  id: number;
  type: {
    value: number;
    label: string;
  };
  reason: string;
  status: {
    value: number;
    label: string;
  };
  created_at: string;
}

// Inertia Propsの型
interface ReportsPageProps {
  timeRecordsByDate: Record<string, TimeRecordData[]>;
  shifts: Record<string, ShiftData>;
  requests: Record<string, RequestData>;
  summary: {
    work_days: number;
    total_work_minutes: number;
    total_break_minutes: number;
  };
  filters: {
    month: string;
    start_date: string;
    end_date: string;
  };
}

interface ShiftInfo {
  timeRange: string;
  color: string;
  textColor: string;
}

interface Application {
  type: string;
  reason: string;
  status?: {
    value: number;
    label: string;
  };
}

export function useStaffReports() {
  const { timeRecordsByDate, shifts, requests: serverRequests, filters } = usePage<{ props: ReportsPageProps }>().props as unknown as ReportsPageProps;

  // フィルターから現在の月を取得（フォールバックとして今日の日付）
  const getDateFromFilters = () => {
    return filters?.month
      ? parseISO(`${filters.month}-01`)
      : new Date();
  };

  const [currentDate, setCurrentDate] = useState(getDateFromFilters);
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);

  const [applications, setApplications] = useState<Record<string, Application>>({});

  // filtersが変更されたらcurrentDateを更新（ページ遷移時用）
  useEffect(() => {
    setCurrentDate(getDateFromFilters());
  }, [filters?.month]);

  // サーバーから取得した申請データをローカルstateに反映
  useEffect(() => {
    if (!serverRequests) {
      setApplications({});
      return;
    }
    const apps: Record<string, Application> = {};
    Object.entries(serverRequests).forEach(([dateKey, req]) => {
      apps[dateKey] = {
        type: req.type.label,
        reason: req.reason,
        status: req.status,
      };
    });
    setApplications(apps);
  }, [serverRequests]);

  // TimeRecordからAttendanceRecordに変換
  const userAttendances = useMemo(() => {
    if (!timeRecordsByDate) return [];

    const attendances: AttendanceRecord[] = [];

    Object.entries(timeRecordsByDate).forEach(([dateStr, records]) => {
      const workStart = records.find(r => r.record_type.is_work_start);
      const workEnd = records.find(r => r.record_type.is_work_end);
      const breakStart = records.find(r => r.record_type.is_break_start);
      const breakEnd = records.find(r => r.record_type.is_break_end);

      // 出退勤記録があればAttendanceRecordを作成
      if (workStart || workEnd) {
        let totalHours = 0;

        // 出退勤時間から総労働時間を計算
        if (workStart && workEnd) {
          const [startH, startM] = workStart.record_time.split(':').map(Number);
          const [endH, endM] = workEnd.record_time.split(':').map(Number);
          let startMinutes = startH * 60 + startM;
          let endMinutes = endH * 60 + endM;

          // 翌日の退勤の場合
          if (endMinutes < startMinutes) {
            endMinutes += 24 * 60;
          }

          const workMinutes = endMinutes - startMinutes;

          // 休憩時間を引く
          let breakMinutes = 0;
          if (breakStart && breakEnd) {
            const [bStartH, bStartM] = breakStart.record_time.split(':').map(Number);
            const [bEndH, bEndM] = breakEnd.record_time.split(':').map(Number);
            breakMinutes = (bEndH * 60 + bEndM) - (bStartH * 60 + bStartM);
          }

          totalHours = Math.max(0, workMinutes - breakMinutes) / 60;
        }

        attendances.push({
          id: String(workStart?.id ?? workEnd?.id ?? 0),
          userId: '0', // 現在のユーザー
          date: dateStr,
          clockIn: workStart?.record_time,
          clockOut: workEnd?.record_time,
          breakStart: breakStart?.record_time,
          breakEnd: breakEnd?.record_time,
          status: 'present',
          totalHours,
        });
      }
    });

    return attendances;
  }, [timeRecordsByDate]);

  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(currentDate);
  const daysInMonth = eachDayOfInterval({ start: monthStart, end: monthEnd });

  const getAttendanceForDate = useCallback(
    (date: Date): AttendanceRecord | undefined => {
      const dateStr = format(date, 'yyyy-MM-dd');
      return userAttendances.find((attendance) => attendance.date === dateStr);
    },
    [userAttendances]
  );

  const formatClockOut = useCallback((clockIn: string, clockOut: string): string => {
    const [inHour] = clockIn.split(':').map(Number);
    const [outHour, outMin] = clockOut.split(':').map(Number);

    if (inHour >= 13 && outHour >= 0 && outHour < 6) {
      const adjustedHour = outHour + 24;
      return `${adjustedHour}:${String(outMin).padStart(2, '0')}`;
    }
    return clockOut;
  }, []);

  const getHoliday = useCallback((date: Date): string | undefined => {
    return getHolidayName(date);
  }, []);

  const getShiftForDate = useCallback(
    (date: Date): ShiftInfo | undefined => {
      if (!shifts) return undefined;

      const dateStr = format(date, 'yyyy-MM-dd');
      const shift = shifts[dateStr];

      if (shift && shift.start_time && shift.end_time) {
        return {
          timeRange: `${shift.start_time.slice(0, 5)}-${shift.end_time.slice(0, 5)}`,
          color: shift.color ?? '#e5e7eb',
          textColor: '#374151',
        };
      }
      return undefined;
    },
    [shifts]
  );

  const calculateOvertimeMinutes = useCallback(
    (attendance: AttendanceRecord | undefined, shift: ShiftInfo | undefined): number => {
      if (!attendance || !shift || !attendance.clockIn || !attendance.clockOut) {
        return 0;
      }

      const match = shift.timeRange.match(/(\d{2}):(\d{2})-(\d{2}):(\d{2})/);
      if (!match) return 0;

      const shiftEndHour = parseInt(match[3]);
      const shiftEndMin = parseInt(match[4]);
      const shiftEndMinutes = shiftEndHour * 60 + shiftEndMin;

      const clockOutParts = attendance.clockOut.split(':');
      const actualEndHour = parseInt(clockOutParts[0]);
      const actualEndMin = parseInt(clockOutParts[1]);
      const actualEndMinutes = actualEndHour * 60 + actualEndMin;

      return Math.max(0, actualEndMinutes - shiftEndMinutes);
    },
    []
  );

  const calculateHolidayMinutes = useCallback(
    (date: Date, attendance: AttendanceRecord | undefined): number => {
      if (!attendance || !attendance.totalHours) return 0;

      const dayOfWeek = date.getDay();
      const holiday = getHoliday(date);

      if (dayOfWeek === 0 || dayOfWeek === 6 || holiday) {
        return Math.round(attendance.totalHours * 60);
      }
      return 0;
    },
    [getHoliday]
  );

  const calculateNightMinutes = useCallback((attendance: AttendanceRecord | undefined): number => {
    if (!attendance || !attendance.clockIn || !attendance.clockOut) return 0;

    const clockInParts = attendance.clockIn.split(':');
    const clockOutParts = attendance.clockOut.split(':');
    const startHour = parseInt(clockInParts[0]);
    const startMin = parseInt(clockInParts[1]);
    let endHour = parseInt(clockOutParts[0]);
    const endMin = parseInt(clockOutParts[1]);

    if (startHour >= 13 && endHour >= 0 && endHour < 6) {
      endHour += 24;
    }

    let nightMinutes = 0;
    const startMinutes = startHour * 60 + startMin;
    const endMinutes = endHour * 60 + endMin;

    const night1Start = 22 * 60;
    const night1End = 24 * 60;
    if (endMinutes > night1Start && startMinutes < night1End) {
      const actualStart = Math.max(startMinutes, night1Start);
      const actualEnd = Math.min(endMinutes, night1End);
      nightMinutes += Math.max(0, actualEnd - actualStart);
    }

    const night2Start = 24 * 60;
    const night2End = 29 * 60;
    if (endMinutes > night2Start && startMinutes < night2End) {
      const actualStart = Math.max(startMinutes, night2Start);
      const actualEnd = Math.min(endMinutes, night2End);
      nightMinutes += Math.max(0, actualEnd - actualStart);
    }

    return nightMinutes;
  }, []);

  const calculateLateEarlyMinutes = useCallback(
    (
      attendance: AttendanceRecord | undefined,
      shift: ShiftInfo | undefined
    ): { lateMinutes: number; earlyMinutes: number } => {
      if (!attendance || !shift || !attendance.clockIn || !attendance.clockOut) {
        return { lateMinutes: 0, earlyMinutes: 0 };
      }

      const match = shift.timeRange.match(/(\d{2}):(\d{2})-(\d{2}):(\d{2})/);
      if (!match) return { lateMinutes: 0, earlyMinutes: 0 };

      const shiftStartHour = parseInt(match[1]);
      const shiftStartMin = parseInt(match[2]);
      const shiftStartMinutes = shiftStartHour * 60 + shiftStartMin;

      const shiftEndHour = parseInt(match[3]);
      const shiftEndMin = parseInt(match[4]);
      const shiftEndMinutes = shiftEndHour * 60 + shiftEndMin;

      const clockInParts = attendance.clockIn.split(':');
      const actualStartHour = parseInt(clockInParts[0]);
      const actualStartMin = parseInt(clockInParts[1]);
      const actualStartMinutes = actualStartHour * 60 + actualStartMin;

      const clockOutParts = attendance.clockOut.split(':');
      let actualEndHour = parseInt(clockOutParts[0]);
      const actualEndMin = parseInt(clockOutParts[1]);

      if (actualStartHour >= 13 && actualEndHour >= 0 && actualEndHour < 6) {
        actualEndHour += 24;
      }
      const actualEndMinutes = actualEndHour * 60 + actualEndMin;

      const lateMinutes = Math.max(0, actualStartMinutes - shiftStartMinutes);
      const earlyMinutes = Math.max(0, shiftEndMinutes - actualEndMinutes);

      return { lateMinutes, earlyMinutes };
    },
    []
  );

  // 月変更時にサーバーからデータを再取得
  const navigateToMonth = useCallback((newDate: Date) => {
    const monthStr = format(newDate, 'yyyy-MM');
    router.get('/staff/reports', { month: monthStr }, {
      preserveState: true,
      preserveScroll: true,
      only: ['timeRecordsByDate', 'shifts', 'summary', 'requests', 'filters'],
    });
  }, []);

  const previousMonth = useCallback(() => {
    const newDate = subMonths(currentDate, 1);
    setCurrentDate(newDate);
    navigateToMonth(newDate);
  }, [currentDate, navigateToMonth]);

  const nextMonth = useCallback(() => {
    const newDate = addMonths(currentDate, 1);
    setCurrentDate(newDate);
    navigateToMonth(newDate);
  }, [currentDate, navigateToMonth]);

  const handleYearChange = useCallback((year: number) => {
    const newDate = new Date(year, currentDate.getMonth(), 1);
    setCurrentDate(newDate);
    navigateToMonth(newDate);
  }, [currentDate, navigateToMonth]);

  const handleMonthChange = useCallback((month: number) => {
    const newDate = new Date(currentDate.getFullYear(), month, 1);
    setCurrentDate(newDate);
    navigateToMonth(newDate);
  }, [currentDate, navigateToMonth]);

  const openApplicationDialog = useCallback((date: Date) => {
    setSelectedDate(date);
    setIsDialogOpen(true);
  }, []);

  const closeApplicationDialog = useCallback(() => {
    setIsDialogOpen(false);
    setSelectedDate(null);
  }, []);

  const handleApplicationSubmit = useCallback(
    async (type: string, reason: string, clockErrorData?: { startTime?: string; endTime?: string; breakStartTime?: string; breakEndTime?: string }) => {
      if (selectedDate) {
        const dateKey = format(selectedDate, 'yyyy-MM-dd');

        try {
          const requestBody: Record<string, unknown> = {
            target_date: dateKey,
            type,
            reason,
          };

          // 打刻間違いの場合は時刻データも送信
          if (type === 'clock-error' && clockErrorData) {
            if (clockErrorData.startTime) {
              requestBody.start_time = clockErrorData.startTime;
            }
            if (clockErrorData.endTime) {
              requestBody.end_time = clockErrorData.endTime;
            }
            if (clockErrorData.breakStartTime) {
              requestBody.break_start_time = clockErrorData.breakStartTime;
            }
            if (clockErrorData.breakEndTime) {
              requestBody.break_end_time = clockErrorData.breakEndTime;
            }
          }

          const response = await axios.post('/staff/requests', requestBody);

          if (response.data.success) {
            // ローカルstateにも追加（APIレスポンスの日本語名を使用）
            const requestData = response.data.request;
            setApplications((prev) => ({
              ...prev,
              [dateKey]: {
                type: requestData.type.label,
                reason: requestData.reason,
                status: requestData.status,
              },
            }));
          }
        } catch (error) {
          console.error('申請の送信に失敗しました:', error);
          throw error;
        }
      }
    },
    [selectedDate]
  );

  const hasApplication = useCallback(
    (date: Date): boolean => {
      const dateKey = format(date, 'yyyy-MM-dd');
      return dateKey in applications;
    },
    [applications]
  );

  const getApplication = useCallback(
    (date: Date): Application | undefined => {
      const dateKey = format(date, 'yyyy-MM-dd');
      return applications[dateKey];
    },
    [applications]
  );

  const canApply = useCallback((date: Date): boolean => {
    const today = new Date();
    const twoMonthsAgo = subMonths(today, 2);
    const twoMonthsAgoStart = startOfMonth(twoMonthsAgo);
    return date >= twoMonthsAgoStart;
  }, []);

  const summary = useMemo(() => {
    const workDays = daysInMonth.filter((date) => {
      const attendance = getAttendanceForDate(date);
      return attendance && attendance.clockIn;
    }).length;

    const leaveDays = daysInMonth.filter((date) => {
      const dateKey = format(date, 'yyyy-MM-dd');
      const attendance = getAttendanceForDate(date);
      const application = applications[dateKey];
      return (
        (attendance && (attendance.status === 'paid-leave' || attendance.status === 'special-leave')) ||
        (application && (application.type === 'paid-leave' || application.type === 'special-leave'))
      );
    }).length;

    const totalMinutes = daysInMonth.reduce((sum, date) => {
      const attendance = getAttendanceForDate(date);
      if (attendance && attendance.totalHours) {
        return sum + attendance.totalHours * 60;
      }
      return sum;
    }, 0);

    return { workDays, leaveDays, totalMinutes };
  }, [daysInMonth, getAttendanceForDate, applications]);

  return {
    // State
    currentDate,
    isDialogOpen,
    selectedDate,
    daysInMonth,
    summary,
    // Helpers
    getAttendanceForDate,
    formatClockOut,
    getHoliday,
    getShiftForDate,
    calculateOvertimeMinutes,
    calculateHolidayMinutes,
    calculateNightMinutes,
    calculateLateEarlyMinutes,
    formatMinutesToHM,
    hasApplication,
    getApplication,
    canApply,
    // Handlers
    previousMonth,
    nextMonth,
    handleYearChange,
    handleMonthChange,
    openApplicationDialog,
    closeApplicationDialog,
    handleApplicationSubmit,
  };
}
