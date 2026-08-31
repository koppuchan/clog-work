/**
 * Controllerから返される申請データの型定義
 */
export interface CorrectionDetail {
    record_type_label: string;
    original_record_time: string | null;
    corrected_record_time: string | null;
}

export interface RequestData {
    id: number;
    request_type: 'normal' | 'correction';
    type: number | string;
    type_label: string;
    target_date: string;
    start_time: string | null;
    end_time: string | null;
    reason: string;
    status: number;
    status_label: string;
    status_css_class: string;
    requested_by: {
        id: number;
        name: string;
    };
    approver: {
        id: number;
        name: string;
    } | null;
    decided_at: string | null;
    decision_note: string | null;
    created_at: string;
    correction_details?: CorrectionDetail[];
}

export interface StatusOption {
    value: number;
    label: string;
}

export interface Applicant {
    id: number;
    name: string;
}

export interface RequestStats {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
}

export interface ApplicationTypeOption {
    value: string;
    label: string;
}

// ステータス定数
export const STATUS_PENDING = 1;
export const STATUS_APPROVED = 2;
export const STATUS_REJECTED = 3;
export const STATUS_CANCELLED = 4;
