/**
 * 設定画面用の型定義
 */

/**
 * 会社情報
 */
export interface Company {
  id: number;
  company_code: string;
  name: string;
  is_closed_on_holidays: boolean;
  payroll_closing_day: number | null;
  paid_leave_half_day: boolean;
  paid_leave_hourly: boolean;
  daily_working_hours: number | null;
  is_stamp_hidden: boolean;
  public_stamp_url: string;
}

/**
 * 会社設定（CompanySettingServiceから取得）
 */
export interface CompanySettingsData {
  name: string;
  weekendDays: number[];
  includeNationalHolidays: boolean;
  payrollClosingDay: number | null;
  defaultShiftPatternId: number | null;
  isStampHidden: boolean;
  paidLeave: {
    halfDay: boolean;
    hourly: boolean;
  };
  shiftDisplay: {
    periodId: number | null;
  };
  clockRounding: {
    roundingUnitId: number | null;
  };
}

/**
 * 部署（設定画面用）
 */
export interface SettingsDepartment {
  id: number;
  name: string;
  userCount: number;
  isStampHidden: boolean;
}

/**
 * シフトカラー（設定画面用）
 */
export interface ShiftColor {
  id: number;
  backgroundColor: string;
  textColor: string;
}

/**
 * シフトパターン（設定画面用）
 */
export interface SettingsShiftPattern {
  id: number;
  name: string;
  startTime: string | null;
  endTime: string | null;
  workMinutes: number | null;
  breakMode: number | null;
  breakMinutes: number | null;
  breakStart: string | null;
  breakEnd: string | null;
  autoFillBreak?: boolean;
  isInUse: boolean;
  colorId: number | null;
  backgroundColor: string | null;
  textColor: string | null;
}

/**
 * 労務アラート設定
 */
export interface LaborAlertSettings {
  overtimeNotification: number;
  overtimeLimit: number;
  messages: {
    warning: { title: string; message: string };
    caution: { title: string; message: string };
  };
}

/**
 * アラートメッセージ設定のフォーム用
 */
export interface AlertMessagesForm {
  warningTitle: string;
  warningMessage: string;
  cautionTitle: string;
  cautionMessage: string;
}

/**
 * 曜日設定
 */
export interface WeekdaySettings {
  sunday: boolean;
  monday: boolean;
  tuesday: boolean;
  wednesday: boolean;
  thursday: boolean;
  friday: boolean;
  saturday: boolean;
}

/**
 * 休憩モード
 */
export const BREAK_MODE = {
  RANGE: 2,     // 時間帯指定
} as const;

/**
 * 新規シフトパターンフォーム用の型
 */
export type NewShiftPatternForm = Omit<SettingsShiftPattern, 'id'> & { id?: number };

/**
 * 新規部署フォーム用の型
 */
export interface NewDepartmentForm {
  id: number;
  name: string;
  isStampHidden: boolean;
}

/**
 * 設定ページのProps
 */
export interface SettingsPageProps {
  company: Company;
  companyRegularHolidays: WeekdaySettings;
  companySettings: CompanySettingsData;
  departments: SettingsDepartment[];
  shiftPatterns: SettingsShiftPattern[];
  shiftColors: ShiftColor[];
  laborAlertSettings: LaborAlertSettings;
  canEdit: boolean;
}
