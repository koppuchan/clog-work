import { ShiftType, ShiftTypeInfo, Shift, User, ShiftPattern, DepartmentData } from './index';

// シフト表示期間タイプ
export type ShiftDisplayPeriodType = 'monthly' | 'custom' | 'closing_day_based';

// Shifts page props (from Laravel controller)
export interface ShiftsPageProps {
  shifts: BackendShift[];
  users: BackendUser[];
  departments: BackendDepartment[];
  shiftPatterns: ShiftPattern[];
  filters: {
    start_date: string;
    end_date: string;
    department_id?: number;
  };
  shiftDisplayPeriod: ShiftDisplayPeriodType;
  payrollClosingDay: number;
}

// Backend shift data
export interface BackendShift {
  id: number;
  user_id: number;
  user_name: string;
  shift_date: string;
  shift_pattern_id: number | null;
  shift_pattern_name: string | null;
  shift_pattern_color: {
    id: number;
    shift_pattern_id: number;
    shift_color_id: number;
    display_order: number;
    shiftColor: {
      id: number;
      color_code: string;
      color_name: string;
    };
  } | null;
  note: string | null;
}

// Backend user data
export interface BackendUser {
  id: number;
  name: string;
  employee_code: string;
  department_id: number | null;
}

// Backend department data
export interface BackendDepartment {
  id: number;
  name: string;
}

// ShiftPatternDialog component props
export interface ShiftPatternDialogProps {
  showDialog: { userId: string; userName: string } | null;
  selectedDate: Date;
  users: User[];
  getShiftTypeInfo: (shiftType: ShiftType) => ShiftTypeInfo;
  onApply: (userId: string) => void;
  onCancel: () => void;
}

// BulkPatternDialog component props
export interface BulkPatternDialogProps {
  showDialog: boolean;
  selectedDate: Date;
  activeUsers: User[];
  onApply: () => void;
  onCancel: () => void;
}

// ShiftCalendar component props
export interface ShiftCalendarProps {
  monthDays: Date[];
  activeUsers: User[];
  departments: BackendDepartment[];
  shifts: Shift[];
  selectedCell: { userId: string; date: string; dayIndex: number } | null;
  selectedDayForBulk: { date: string; dayIndex: number } | null;
  shiftTypeInfo: ShiftTypeInfo[];
  restShiftInfo: ShiftTypeInfo;
  onCellClick: (userId: string, date: string, dayIndex: number) => void;
  onDayHeaderClick: (date: string, dayIndex: number) => void;
  onShiftTypeSelect: (shiftType: ShiftType) => void;
  onBulkShiftTypeSelect: (shiftType: ShiftType) => void;
  onUserNameClick: (userId: string) => void;
  getShiftTypeInfo: (shiftType: ShiftType) => ShiftTypeInfo;
  getMonthlyStats: (userId: string) => { totalHours: number; restDays: number; workDays: number };
  getAllStaffTotals: () => { totalHours: number; totalRestDays: number; totalWorkDays: number };
  getShiftCountsByDate: (date: Date) => { [key: string]: number };
}

// ShiftLegend component props
export interface ShiftLegendProps {
  shiftTypeInfo: ShiftTypeInfo[];
}
