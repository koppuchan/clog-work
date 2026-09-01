import type { ShiftLeave } from '@/types/shift';

export interface ShiftUser {
  id: number;
  name: string;
  employee_code?: string | null;
  department_name?: string | null;
  is_self: boolean;
}

export interface ShiftData {
  id: number;
  shift_pattern_id: number | null;
  shift_pattern_name: string | null;
  start_time: string | null;
  end_time: string | null;
  color: string | null;
  note: string | null;
}

export interface ShiftPattern {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  background_color: string;
  text_color: string;
}

export interface StaffShiftsPageProps {
  users: ShiftUser[];
  shifts: Record<string, Record<number, ShiftData>>;
  shiftPatterns: ShiftPattern[];
  leaves?: ShiftLeave[];
  currentUserId: number;
  scope: 'self' | 'department' | 'company';
  filters: {
    start_date: string;
    end_date: string;
  };
  shiftDisplayPeriod: 'monthly' | 'closing_day_based';
  payrollClosingDay: number;
}
