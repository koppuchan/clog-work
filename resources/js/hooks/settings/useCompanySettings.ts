import { useState, useCallback, useRef, useMemo } from 'react';
import { Company, WeekdaySettings, CompanySettingsData } from '@/types/settings';

const DEFAULT_WEEKEND_DAYS: WeekdaySettings = {
  sunday: false,
  monday: false,
  tuesday: false,
  wednesday: false,
  thursday: false,
  friday: false,
  saturday: false,
};

/**
 * 打刻丸め単位IDを設定値に変換
 */
const roundingUnitIdToValue = (roundingUnitId: number | null): string => {
  switch (roundingUnitId) {
    case 2: return '5min';
    case 3: return '10min';
    case 4: return '15min';
    case 5: return '30min';
    default: return 'none';
  }
};

/**
 * シフト表示期間タイプ
 */
export type ShiftDisplayPeriodType = 'monthly' | 'closing_day_based';

/**
 * シフト表示期間IDを設定値に変換
 */
const shiftPeriodIdToValue = (periodId: number | null): ShiftDisplayPeriodType => {
  switch (periodId) {
    case 2: return 'closing_day_based';
    default: return 'monthly';
  }
};

export function useCompanySettings(
  company?: Company,
  companyRegularHolidays?: WeekdaySettings,
  companySettings?: CompanySettingsData
) {
  // 初期値を共通変数で定義し、useStateとuseRefの不一致を防止
  const companyNameInit = company?.name ?? '';
  const weekendDaysInit = companyRegularHolidays ?? DEFAULT_WEEKEND_DAYS;
  const includeNationalHolidaysInit = company?.is_closed_on_holidays ?? true;
  const payrollClosingDayInit = String(company?.payroll_closing_day ?? 25);
  const shiftDisplayPeriodInit = shiftPeriodIdToValue(companySettings?.shiftDisplay?.periodId ?? null);
  const clockRoundingInit = roundingUnitIdToValue(companySettings?.clockRounding?.roundingUnitId ?? null);
  const defaultShiftPatternIdInit: number | null = companySettings?.defaultShiftPatternId ?? null;
  const isStampHiddenInit = company?.is_stamp_hidden ?? false;
  const paidLeaveHalfDayInit = company?.paid_leave_half_day ?? false;
  const paidLeaveHourlyInit = company?.paid_leave_hourly ?? false;
  const dailyWorkingHoursInit = String(company?.daily_working_hours ?? 8);

  const [companyCode] = useState(company?.company_code ?? '');
  const [companyName, setCompanyName] = useState(companyNameInit);
  const [weekendDays, setWeekendDays] = useState<WeekdaySettings>(weekendDaysInit);
  const [includeNationalHolidays, setIncludeNationalHolidays] = useState(includeNationalHolidaysInit);
  const [payrollClosingDay, setPayrollClosingDay] = useState(payrollClosingDayInit);
  const [shiftDisplayPeriod, setShiftDisplayPeriod] = useState<ShiftDisplayPeriodType>(shiftDisplayPeriodInit);
  const [clockRounding, setClockRounding] = useState(clockRoundingInit);
  const [defaultShiftPatternId, setDefaultShiftPatternId] = useState<number | null>(defaultShiftPatternIdInit);
  const [isStampHidden, setIsStampHidden] = useState(isStampHiddenInit);
  const [paidLeaveHalfDay, setPaidLeaveHalfDay] = useState(paidLeaveHalfDayInit);
  const [paidLeaveHourly, setPaidLeaveHourly] = useState(paidLeaveHourlyInit);
  const [dailyWorkingHours, setDailyWorkingHours] = useState(dailyWorkingHoursInit);

  const handleWeekendChange = useCallback((day: keyof WeekdaySettings) => {
    setWeekendDays((prev) => ({ ...prev, [day]: !prev[day] }));
  }, []);

  const initialValues = useRef({
    companyName: companyNameInit,
    weekendDays: weekendDaysInit,
    includeNationalHolidays: includeNationalHolidaysInit,
    payrollClosingDay: payrollClosingDayInit,
    shiftDisplayPeriod: shiftDisplayPeriodInit,
    clockRounding: clockRoundingInit,
    defaultShiftPatternId: defaultShiftPatternIdInit,
    isStampHidden: isStampHiddenInit,
    paidLeaveHalfDay: paidLeaveHalfDayInit,
    paidLeaveHourly: paidLeaveHourlyInit,
    dailyWorkingHours: dailyWorkingHoursInit,
  });

  const [dirtyResetVersion, setDirtyResetVersion] = useState(0);

  const isDirty = useMemo(() => {
    const init = initialValues.current;
    return (
      companyName !== init.companyName ||
      JSON.stringify(weekendDays) !== JSON.stringify(init.weekendDays) ||
      includeNationalHolidays !== init.includeNationalHolidays ||
      payrollClosingDay !== init.payrollClosingDay ||
      shiftDisplayPeriod !== init.shiftDisplayPeriod ||
      clockRounding !== init.clockRounding ||
      defaultShiftPatternId !== init.defaultShiftPatternId ||
      isStampHidden !== init.isStampHidden ||
      paidLeaveHalfDay !== init.paidLeaveHalfDay ||
      paidLeaveHourly !== init.paidLeaveHourly ||
      dailyWorkingHours !== init.dailyWorkingHours
    );
  }, [companyName, weekendDays, includeNationalHolidays, payrollClosingDay, shiftDisplayPeriod, clockRounding, defaultShiftPatternId, isStampHidden, paidLeaveHalfDay, paidLeaveHourly, dailyWorkingHours, dirtyResetVersion]);

  return {
    // 会社基本設定
    companyCode,
    companyName,
    setCompanyName,
    weekendDays,
    handleWeekendChange,
    includeNationalHolidays,
    setIncludeNationalHolidays,
    // 給与・シフト設定
    payrollClosingDay,
    setPayrollClosingDay,
    shiftDisplayPeriod,
    setShiftDisplayPeriod,
    clockRounding,
    setClockRounding,
    // 基本シフト
    defaultShiftPatternId,
    setDefaultShiftPatternId,
    // 打刻画面非表示設定
    isStampHidden,
    setIsStampHidden,
    // 有給設定
    paidLeaveHalfDay,
    setPaidLeaveHalfDay,
    paidLeaveHourly,
    setPaidLeaveHourly,
    dailyWorkingHours,
    setDailyWorkingHours,
    // 変更検知
    isDirty,
    resetDirty: useCallback(() => {
      initialValues.current = {
        companyName,
        weekendDays,
        includeNationalHolidays,
        payrollClosingDay,
        shiftDisplayPeriod,
        clockRounding,
        defaultShiftPatternId,
        isStampHidden,
        paidLeaveHalfDay,
        paidLeaveHourly,
        dailyWorkingHours,
      };
      setDirtyResetVersion(v => v + 1);
    }, [companyName, weekendDays, includeNationalHolidays, payrollClosingDay, shiftDisplayPeriod, clockRounding, defaultShiftPatternId, isStampHidden, paidLeaveHalfDay, paidLeaveHourly, dailyWorkingHours]),
  };
}
