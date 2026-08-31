export interface User {
  id: string;
  name: string;
  nameKana?: string;
  employeeCode?: string;
  email: string;
  role: 'admin' | 'manager' | 'employee';
  department: string;
  departmentId?: number;
  shiftPatterns?: {
    sunday?: ShiftType;
    monday?: ShiftType;
    tuesday?: ShiftType;
    wednesday?: ShiftType;
    thursday?: ShiftType;
    friday?: ShiftType;
    saturday?: ShiftType;
  };
  workOnHolidays?: boolean; // 祝日も勤務するかどうか
  shiftViewPermission?: 'self' | 'department' | 'company'; // シフト閲覧権限（一般: self/department, 責任者: department/company）
  attendanceViewPermission?: 'self' | 'department' | 'company'; // 勤務実績の閲覧権限（一般: self固定, 責任者: department/company）
  approvalPermission?: 'department' | 'company'; // 申請承認と取り消し権限（責任者のみ）
  shiftEditPermission?: 'department' | 'company'; // シフト入力と修正権限（責任者のみ）
  isRetired?: boolean; // 退職済みかどうか
  retirementDate?: string; // 退職日（YYYY-MM-DD形式）
}

export interface AttendanceRecord {
  id: string;
  userId: string;
  date: string;
  clockIn?: string;
  clockOut?: string;
  breakStart?: string;
  breakEnd?: string;
  status: 'present' | 'absent' | 'late' | 'early-leave' | 'paid-leave' | 'special-leave';
  totalHours: number;
}

export type ShiftType = string;

export interface Shift {
  id: string;
  userId: string;
  date: string;
  shiftType: ShiftType;
  shiftPatternId: number | null;
}

export interface ShiftTypeInfo {
  id: number;
  type: ShiftType;
  name: string;
  timeRange: string;
  color: string;
  textColor: string;
}

export interface Application {
  id: string;
  userId: string;
  type: 'vacation' | 'sick-leave' | 'overtime' | 'shift-change';
  startDate: string;
  endDate: string;
  reason: string;
  status: 'pending' | 'approved' | 'rejected';
  approvedBy?: string;
  submittedAt: string;
}

export interface Department {
  id: string;
  name: string;
  managerId: string;
}

// Backend data types (from Laravel API)
export interface Role {
  id: number;
  name: string;
}

export interface DepartmentData {
  id: number;
  name: string;
}

export interface ShiftPattern {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  work_minutes: number;
  break_minutes: number;
  background_color: string;
  text_color: string;
}

export interface CompanyRegularHolidays {
  sunday: boolean;
  monday: boolean;
  tuesday: boolean;
  wednesday: boolean;
  thursday: boolean;
  friday: boolean;
  saturday: boolean;
}

export interface CompanySettings {
  companyName: string;
  payrollClosingDay: string;
  shiftDisplayPeriod: 'monthly' | 'custom';
  clockRounding: 'none' | '5min' | '10min' | '15min' | '30min';
  paidLeaveHalfDay: boolean;
  paidLeaveHourly: boolean;
  alertOvertimeNotification: string;
  alertOvertimeLimit: string;
  alertConsecutiveWorkDays: string;
  weekendDays: {
    sunday: boolean;
    monday: boolean;
    tuesday: boolean;
    wednesday: boolean;
    thursday: boolean;
    friday: boolean;
    saturday: boolean;
  };
  includeNationalHolidays: boolean; // 祝日を定休日に含めるかどうか
  alertMessages?: {
    warningTitle?: string; // 警告アラートのタイトル
    warningMessage?: string; // 警告アラートのメッセージ
    cautionTitle?: string; // 注意アラートのタイトル
    cautionMessage?: string; // 注意アラートのメッセージ
  };
}