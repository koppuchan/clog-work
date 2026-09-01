/**
 * 勤務実績レポート関連の型定義
 */

/**
 * スタッフ情報
 */
export interface ReportUser {
  id: number;
  name: string;
  employee_code: string | null;
  department: { id: number; name: string } | null;
}

/**
 * 日次勤務サマリー
 */
export interface WorkSummary {
  id: number;
  user_id: number;
  work_date: string;
  scheduled_start_time: string | null;
  scheduled_end_time: string | null;
  work_start: string | null;
  work_end: string | null;
  work_minutes: number;
  break_minutes: number;
  net_work_minutes: number;
  night_minutes: number;
  holiday_minutes: number;
  overtime_minutes: number;
  late_minutes: number;
  early_leave_minutes: number;
  /** 承認済みの休暇の種別。入っている日は遅刻早退として扱わない */
  leave_type: number | null;
  is_cross_day: boolean;
  record_source: { value: number; label: string };
  note: string | null;
}

/**
 * 月間サマリー
 */
export interface MonthlySummary {
  total_work_minutes: number;
  total_break_minutes: number;
  total_net_work_minutes: number;
  total_overtime_minutes: number;
  total_night_minutes: number;
  total_holiday_minutes: number;
  total_late_minutes: number;
  total_early_leave_minutes: number;
  late_count: number;
  early_leave_count: number;
  work_days: number;
  paid_leave_days: number;
  special_leave_days: number;
  absence_days: number;
}

/**
 * フィルター条件
 */
export interface ReportFilters {
  start_date: string;
  end_date: string;
  user_id: number | null;
  shiftDisplayPeriod: string;
  payrollClosingDay: number;
}

/**
 * 承認済み申請
 */
export interface ApprovedRequest {
  id: number;
  target_date: string;
  type: { value: number; code: string; label: string };
  start_time: string | null;
  end_time: string | null;
  reason: string | null;
  decided_at: string | null;
}

/**
 * シフト情報（日次）
 */
export interface ShiftInfo {
  label: string;
  break_start: string | null;
  break_end: string | null;
  // break_mode=1（分数のみ指定）の場合の表示用
  break_minutes?: number | null;
  break_mode?: number | null;
}

/**
 * 打刻レコード
 */
export interface TimeRecord {
  id: number;
  user_id: number;
  record_date: string;
  record_time: string;
  record_type: {
    value: string;
    label: string;
    is_work_start: boolean;
    is_work_end: boolean;
    is_break: boolean;
  };
  record_source: {
    value: string;
    label: string;
    badge_color: string;
    can_edit: boolean;
  };
  note: string | null;
  rounded_time: string | null;
}

/**
 * 打刻修正履歴
 */
export interface TimeRecordCorrectionItem {
  before_time: string;
  after_time: string;
  corrected_at: string;
  record_type: { value: number; label: string };
  correction_source: 'request' | 'admin';
}

/**
 * レポートページのProps
 */
export interface ReportsPageProps {
  users: ReportUser[];
  workSummaries: WorkSummary[];
  timeRecords: TimeRecord[];
  approvedRequests: ApprovedRequest[];
  timeRecordCorrections: Record<string, TimeRecordCorrectionItem[]>;
  monthlySummary: MonthlySummary | null;
  canExport: boolean;
  exportBatchSize?: number;
  shifts: Record<string, ShiftInfo>;
  filters: ReportFilters;
}

/**
 * エクスポートフォーマット
 */
export interface ExportFormats {
  csv: boolean;
  excel: boolean;
}
