import { useState, useMemo, useCallback } from 'react';
import { format, parseISO, eachDayOfInterval, addDays } from 'date-fns';
import { router } from '@inertiajs/react';
import { getHolidayName } from '@/lib/holidays';
import { formatMinutesToHM } from '@/utils/timeFormat';
import type { ReportUser, WorkSummary, ApprovedRequest, ExportFormats, TimeRecord } from '@/types/reports';
import type { ExportScope } from '@/Components/Reports/ReportExportModal';

interface UseReportsProps {
  users: ReportUser[];
  workSummaries: WorkSummary[];
  timeRecords: TimeRecord[];
  approvedRequests: ApprovedRequest[];
  filters: {
    start_date: string;
    end_date: string;
    user_id: number | null;
  };
}

export interface BreakPeriodForm {
  start: string;
  end: string;
}

export interface EditForm {
  work_start: string;
  work_end: string;
  break_periods: BreakPeriodForm[];
}

export function useReports({ users, workSummaries, timeRecords, approvedRequests, filters }: UseReportsProps) {
  const [showExportModal, setShowExportModal] = useState(false);
  const [exportFormats, setExportFormats] = useState<ExportFormats>({
    csv: false,
    excel: false,
  });
  const [exportScope, setExportScope] = useState<ExportScope>('single');
  // 全員一括のときは null。バッチ番号を選ぶと1始まりでその区切りだけを出力する
  const [exportBatch, setExportBatch] = useState<number | null>(null);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editingSummary, setEditingSummary] = useState<WorkSummary | null>(null);
  const [editingDate, setEditingDate] = useState<string | null>(null);
  const defaultBreakPeriods: BreakPeriodForm[] = [{ start: '', end: '' }, { start: '', end: '' }];
  const [editForm, setEditForm] = useState<EditForm>({ work_start: '', work_end: '', break_periods: defaultBreakPeriods });
  const [isSaving, setIsSaving] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [editErrors, setEditErrors] = useState<string[]>([]);

  const selectedUserId = filters.user_id;

  const daysInMonth = useMemo(() => {
    return eachDayOfInterval({ start: parseISO(filters.start_date), end: parseISO(filters.end_date) });
  }, [filters.start_date, filters.end_date]);

  // 祝日判定
  const getHoliday = useCallback((date: Date): string | undefined => {
    return getHolidayName(date);
  }, []);

  // スタッフ名取得
  const getUserName = useCallback(
    (userId: number | null) => {
      if (!userId) return '不明';
      const user = users.find((u) => u.id === userId);
      return user?.name || '不明';
    },
    [users]
  );

  // 指定日の勤務実績を取得（workSummaryがない場合はtimeRecordsから生成）
  const getSummaryForDate = useCallback(
    (date: Date): WorkSummary | undefined => {
      const dateStr = format(date, 'yyyy-MM-dd');

      // まずworkSummariesから探す
      const existingSummary = workSummaries.find((summary) => summary.work_date === dateStr);
      if (existingSummary) {
        return existingSummary;
      }

      // workSummaryがない場合、timeRecordsから生成（本日分など）
      const dayRecords = timeRecords.filter((record) => record.record_date === dateStr);
      if (dayRecords.length === 0) {
        return undefined;
      }

      const workStartRecord = dayRecords.find((r) => r.record_type.is_work_start);
      const workEndRecord = dayRecords.find((r) => r.record_type.is_work_end);
      const breakRecords = dayRecords.filter((r) => r.record_type.is_break);

      // 出勤記録がなければundefined
      if (!workStartRecord) {
        return undefined;
      }

      // 勤務時間の計算
      let workMinutes = 0;
      let breakMinutes = 0;

      if (workStartRecord && workEndRecord) {
        const [startH, startM] = workStartRecord.record_time.split(':').map(Number);
        const [endH, endM] = workEndRecord.record_time.split(':').map(Number);
        let startMins = startH * 60 + startM;
        let endMins = endH * 60 + endM;

        // 翌日退勤の場合
        if (endMins < startMins) {
          endMins += 24 * 60;
        }

        workMinutes = endMins - startMins;

        // 休憩時間の計算（ペアになっているもの）
        for (let i = 0; i < breakRecords.length - 1; i += 2) {
          const breakStart = breakRecords[i];
          const breakEnd = breakRecords[i + 1];
          if (breakStart && breakEnd) {
            const [bsH, bsM] = breakStart.record_time.split(':').map(Number);
            const [beH, beM] = breakEnd.record_time.split(':').map(Number);
            breakMinutes += (beH * 60 + beM) - (bsH * 60 + bsM);
          }
        }
      }

      const netWorkMinutes = Math.max(0, workMinutes - breakMinutes);

      // TimeRecordから生成したWorkSummary
      return {
        id: 0, // 仮ID
        user_id: workStartRecord.user_id,
        work_date: dateStr,
        scheduled_start_time: null,
        scheduled_end_time: null,
        work_start: workStartRecord.record_time,
        work_end: workEndRecord?.record_time ?? null,
        work_minutes: workMinutes,
        break_minutes: breakMinutes,
        net_work_minutes: netWorkMinutes,
        night_minutes: 0, // 簡易計算では省略
        holiday_minutes: 0,
        overtime_minutes: 0,
        late_minutes: 0,
        early_leave_minutes: 0,
        is_cross_day: workEndRecord ? workEndRecord.record_time < workStartRecord.record_time : false,
        record_source: workStartRecord.record_source as unknown as { value: number; label: string },
        note: null,
      };
    },
    [workSummaries, timeRecords]
  );

  // 指定日の承認済み申請を取得
  const getApprovedRequestsForDate = useCallback(
    (date: Date): ApprovedRequest[] => {
      const dateStr = format(date, 'yyyy-MM-dd');
      return approvedRequests.filter((req) => req.target_date === dateStr);
    },
    [approvedRequests]
  );

  // 今日かどうか判定
  const isToday = useCallback((date: Date): boolean => {
    const today = new Date();
    return (
      date.getDate() === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear()
    );
  }, []);

  // スタッフ変更ハンドラー
  const handleUserChange = useCallback(
    (userId: string) => {
      router.get(
        '/admin/reports',
        {
          start_date: filters.start_date,
          end_date: filters.end_date,
          user_id: userId,
        },
        { preserveState: true }
      );
    },
    [filters.start_date, filters.end_date]
  );

  // 選択中のスタッフ名
  const selectedUserName = useMemo(() => getUserName(selectedUserId), [selectedUserId, getUserName]);

  // エクスポート関連
  const handleFormatChange = useCallback((formatType: 'csv' | 'excel', checked: boolean) => {
    setExportFormats((prev) => ({
      ...prev,
      [formatType]: checked,
    }));
  }, []);

  const openExportModal = useCallback(() => {
    setShowExportModal(true);
  }, []);

  const closeExportModal = useCallback(() => {
    setShowExportModal(false);
    setExportFormats({ csv: false, excel: false });
    setExportScope('single');
    setExportBatch(null);
  }, []);

  const handleScopeChange = useCallback((scope: ExportScope) => {
    setExportScope(scope);
    setExportBatch(null);
  }, []);

  const handleBatchChange = useCallback((batch: number | null) => {
    setExportBatch(batch);
  }, []);

  const exportToCSV = useCallback(() => {
    const params = new URLSearchParams({
      start_date: filters.start_date,
      end_date: filters.end_date,
      ...(exportScope === 'all'
        ? { scope: 'all', ...(exportBatch !== null ? { batch: String(exportBatch) } : {}) }
        : { user_id: String(selectedUserId || '') }),
    });
    window.location.href = `/admin/reports/export/csv?${params.toString()}`;
  }, [filters.start_date, filters.end_date, selectedUserId, exportScope, exportBatch]);

  const exportToExcel = useCallback(() => {
    const params = new URLSearchParams({
      start_date: filters.start_date,
      end_date: filters.end_date,
      ...(exportScope === 'all'
        ? { scope: 'all', ...(exportBatch !== null ? { batch: String(exportBatch) } : {}) }
        : { user_id: String(selectedUserId || '') }),
    });
    window.location.href = `/admin/reports/export/excel?${params.toString()}`;
  }, [filters.start_date, filters.end_date, selectedUserId, exportScope, exportBatch]);

  const handleExport = useCallback(() => {
    if (!exportFormats.csv && !exportFormats.excel) {
      alert('出力形式を選択してください');
      return;
    }
    if (exportFormats.csv) {
      exportToCSV();
    }
    if (exportFormats.excel) {
      exportToExcel();
    }
    closeExportModal();
  }, [exportFormats.csv, exportFormats.excel, exportToCSV, exportToExcel, closeExportModal]);

  // 対象日の打刻 + 日跨ぎ夜勤の場合のみ翌日のレコード(WORK_END_NEXT_DAY と該当時刻以前の休憩)をマージ
  const getRecordsIncludingNextDayCarryOver = useCallback(
    (dateStr: string): TimeRecord[] => {
      const nextDateStr = format(addDays(parseISO(dateStr), 1), 'yyyy-MM-dd');
      const dayRecords = timeRecords.filter((r) => r.record_date === dateStr);
      const nextDayRecords = timeRecords.filter((r) => r.record_date === nextDateStr);

      const nextDayCrossEnd = nextDayRecords.find((r) => String(r.record_type.value) === '3');
      if (!nextDayCrossEnd) {
        return dayRecords;
      }

      const carriedNextDayRecords = nextDayRecords.filter((r) => {
        if (String(r.record_type.value) === '3') return true;
        if (r.record_type.is_break) {
          return r.record_time.localeCompare(nextDayCrossEnd.record_time) <= 0;
        }
        return false;
      });

      return [...dayRecords, ...carriedNextDayRecords];
    },
    [timeRecords]
  );

  // 指定日の休憩時間帯をtimeRecordsから取得
  const getBreakPeriodsFromRecords = useCallback(
    (dateStr: string): BreakPeriodForm[] => {
      const records = getRecordsIncludingNextDayCarryOver(dateStr);
      const starts = records
        .filter((r) => String(r.record_type.value) === '4')
        .sort((a, b) => a.record_time.localeCompare(b.record_time));
      const ends = records
        .filter((r) => String(r.record_type.value) === '5')
        .sort((a, b) => a.record_time.localeCompare(b.record_time));
      const periods: BreakPeriodForm[] = [];
      for (let i = 0; i < starts.length; i++) {
        if (ends[i]) {
          periods.push({ start: starts[i].record_time, end: ends[i].record_time });
        }
      }
      // 最低2行は表示
      while (periods.length < 2) {
        periods.push({ start: '', end: '' });
      }
      return periods;
    },
    [getRecordsIncludingNextDayCarryOver]
  );

  // 指定日の出退勤時刻をtimeRecordsから取得（丸めなしの生の値）
  // 日跨ぎ夜勤の場合、退勤は翌日の WORK_END_NEXT_DAY から取得する
  const getWorkTimesFromRecords = useCallback(
    (dateStr: string): { workStart: string | null; workEnd: string | null } => {
      const records = getRecordsIncludingNextDayCarryOver(dateStr);
      const workStartRecord = records.find((r) => r.record_type.is_work_start);
      const workEndRecord = [...records].reverse().find((r) => r.record_type.is_work_end);
      return {
        workStart: workStartRecord?.record_time ?? null,
        workEnd: workEndRecord?.record_time ?? null,
      };
    },
    [getRecordsIncludingNextDayCarryOver]
  );

  // モーダル編集: 編集開始（既存summaryまたは日付のみ）
  const openEditModal = useCallback(
    (summaryOrDate: WorkSummary | string) => {
      if (typeof summaryOrDate === 'string') {
        // 日付文字列の場合: 新規作成モード
        setEditingSummary(null);
        setEditingDate(summaryOrDate);
        setEditForm({ work_start: '', work_end: '', break_periods: [{ start: '', end: '' }, { start: '', end: '' }] });
      } else {
        // WorkSummaryの場合: 既存編集モード
        // work_start/work_endはtimeRecordsの生の値（丸めなし）を優先し、
        // 打刻が無い場合のみsummaryの値（バッチ計算結果）にフォールバック
        const { workStart, workEnd } = getWorkTimesFromRecords(summaryOrDate.work_date);
        setEditingSummary(summaryOrDate);
        setEditingDate(summaryOrDate.work_date);
        setEditForm({
          work_start: workStart ?? summaryOrDate.work_start ?? '',
          work_end: workEnd ?? summaryOrDate.work_end ?? '',
          break_periods: getBreakPeriodsFromRecords(summaryOrDate.work_date),
        });
      }
      setEditErrors([]);
      setShowEditModal(true);
    },
    [getBreakPeriodsFromRecords, getWorkTimesFromRecords]
  );

  // モーダル編集: キャンセル
  const closeEditModal = useCallback(() => {
    setShowEditModal(false);
    setEditingSummary(null);
    setEditingDate(null);
    setEditForm({ work_start: '', work_end: '', break_periods: [{ start: '', end: '' }, { start: '', end: '' }] });
    setEditErrors([]);
  }, []);

  // モーダル編集: 保存
  const saveEdit = useCallback(() => {
    if (!editingDate) return;

    // バリデーション: 休憩時間の開始のみ or 終了のみ入力チェック
    const errors: string[] = [];
    editForm.break_periods.forEach((p, idx) => {
      if (p.start && !p.end) {
        errors.push(`休憩${idx + 1}の終了時刻を入力してください。`);
      }
      if (!p.start && p.end) {
        errors.push(`休憩${idx + 1}の開始時刻を入力してください。`);
      }
    });
    if (errors.length > 0) {
      setEditErrors(errors);
      return;
    }
    setEditErrors([]);
    setIsSaving(true);

    // 空でない休憩時間帯のみ送信
    const validBreakPeriods = editForm.break_periods
      .filter((p) => p.start && p.end)
      .map((p) => ({ start: p.start, end: p.end }));

    const payload = {
      work_start: editForm.work_start || null,
      work_end: editForm.work_end || null,
      break_periods: validBreakPeriods,
    };

    const onSuccess = () => {
      setShowEditModal(false);
      setEditingSummary(null);
      setEditingDate(null);
      setEditForm({ work_start: '', work_end: '', break_periods: [{ start: '', end: '' }, { start: '', end: '' }] });
    };

    const onError = (errors: Record<string, string>) => {
      setEditErrors(Object.values(errors));
    };

    const onFinish = () => {
      setIsSaving(false);
    };

    if (editingSummary && editingSummary.id > 0) {
      // 既存レコードの更新
      router.put(`/admin/reports/${editingSummary.id}`, payload, {
        preserveState: false,
        onSuccess,
        onError,
        onFinish,
      });
    } else {
      // 新規作成
      router.post('/admin/reports', {
        ...payload,
        user_id: selectedUserId,
        work_date: editingDate,
      }, {
        preserveState: false,
        onSuccess,
        onError,
        onFinish,
      });
    }
  }, [editingSummary, editingDate, editForm, selectedUserId]);

  // モーダル編集: 削除（打刻データごと削除）
  const deleteEdit = useCallback(() => {
    if (!editingSummary || editingSummary.id <= 0) {
      return;
    }
    setEditErrors([]);
    setIsDeleting(true);

    router.delete(`/admin/reports/${editingSummary.id}`, {
      preserveState: false,
      onSuccess: () => {
        setShowEditModal(false);
        setEditingSummary(null);
        setEditingDate(null);
        setEditForm({ work_start: '', work_end: '', break_periods: [{ start: '', end: '' }, { start: '', end: '' }] });
      },
      onError: (errors: Record<string, string>) => {
        setEditErrors(Object.values(errors));
      },
      onFinish: () => {
        setIsDeleting(false);
      },
    });
  }, [editingSummary]);

  return {
    // State
    showExportModal,
    exportFormats,
    exportScope,
    exportBatch,
    selectedUserId,
    daysInMonth,
    selectedUserName,
    // Helpers
    getHoliday,
    getUserName,
    getSummaryForDate,
    getApprovedRequestsForDate,
    formatMinutesToHM,
    isToday,
    // Handlers
    handleUserChange,
    handleFormatChange,
    handleScopeChange,
    handleBatchChange,
    openExportModal,
    closeExportModal,
    handleExport,
    setExportFormats,
    // Edit modal
    showEditModal,
    editingSummary,
    editingDate,
    editForm,
    editErrors,
    isSaving,
    isDeleting,
    openEditModal,
    closeEditModal,
    saveEdit,
    deleteEdit,
    setEditForm,
  };
}
