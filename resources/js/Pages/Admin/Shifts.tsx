import { useState, useMemo, useEffect, useCallback } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { format, addDays, startOfMonth, endOfMonth } from 'date-fns';
import { ShiftType, User, Shift } from '@/types';
import { ShiftsPageProps } from '@/types/shift';
import ShiftPatternDialog from '@/Components/Shift/ShiftPatternDialog';
import BulkPatternDialog from '@/Components/Shift/BulkPatternDialog';
import ShiftCalendar from '@/Components/Shift/ShiftCalendar';
import ShiftLegend from '@/Components/Shift/ShiftLegend';
import MonthSelector from '@/Components/MonthSelector';
import { useShiftStats } from '@/hooks/shift/useShiftStats';
import { useShiftOperations } from '@/hooks/shift/useShiftOperations';
import { convertBackendUsers, convertBackendShifts, convertShiftPatterns, getShiftTypeInfo as getShiftTypeInfoUtil, restShiftInfo, upsertShift, removeShift } from '@/utils/shiftDataConverter';
import { getActiveUsersForMonth as filterActiveUsers } from '@/utils/userFilters';
import { router } from '@inertiajs/react';
import axios from 'axios';
import InlineFlashMessage from '@/Components/InlineFlashMessage';
import { AlertTriangle } from 'lucide-react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';
import { useNavigationGuard } from '@/hooks/useNavigationGuard';

/**
 * 管理者用シフト管理ページコンポーネント
 *
 * 主な機能：
 * - 月次シフトカレンダーの表示と編集
 * - 個別スタッフのシフトパターン適用
 * - 全スタッフへのシフトパターン一括適用
 * - リアルタイムのシフト統計情報（出勤日数、勤務時間、休日）
 * - セル単位・日単位でのシフト編集
 * - アクティブスタッフの自動フィルタリング（退職者除外）
 *
 * データフロー：
 * 1. バックエンドからスタッフ、部署、シフト、シフトパターンを取得
 * 2. フロントエンド形式に変換（convertBackend*関数）
 * 3. カスタムフックで状態管理とシフト操作ロジックを処理
 * 4. ShiftCalendarコンポーネントで月次カレンダーを表示
 *
 * カスタムフック：
 * - useShiftOperations: シフトの選択・編集・一括操作の状態管理
 * - useShiftStats: シフト統計情報の計算
 */
export default function ShiftsPage({ users: backendUsers, departments: backendDepartments, shifts: backendShifts, shiftPatterns, leaves, filters, shiftDisplayPeriod, payrollClosingDay }: ShiftsPageProps) {
  // バックエンドから渡された日付を使用して初期値を設定
  const selectedDate = useMemo(() => new Date(filters.start_date), [filters.start_date]);
  const [showPatternDialog, setShowPatternDialog] = useState<{userId: string, userName: string} | null>(null);
  const [showBulkPatternDialog, setShowBulkPatternDialog] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [loadingAction, setLoadingAction] = useState<'saving' | 'applying' | null>(null);
  const [isChangingMonth, setIsChangingMonth] = useState(false);

  // バックエンドデータを変換
  const initialUsers = useMemo(() => convertBackendUsers(backendUsers, backendDepartments), [backendUsers, backendDepartments]);
  const [users, setUsers] = useState<User[]>(initialUsers);
  const shiftTypeInfo = useMemo(() => convertShiftPatterns(shiftPatterns), [shiftPatterns]);

  // シフトパターンID -> ShiftType のマッピングを作成
  const shiftPatternIdMap = useMemo(() => {
    const map = new Map<number, ShiftType>();
    shiftTypeInfo.forEach(info => {
      map.set(info.id, info.type);
    });
    return map;
  }, [shiftTypeInfo]);

  const initialShifts = useMemo(() => convertBackendShifts(backendShifts, shiftPatternIdMap), [backendShifts, shiftPatternIdMap]);

  // バックエンドのスタッフデータが変更されたら更新
  useEffect(() => {
    setUsers(initialUsers);
  }, [initialUsers]);

  // カレンダー表示期間を計算
  const periodDays = useMemo(() => {
    const startDate = new Date(filters.start_date);
    const endDate = new Date(filters.end_date);
    const days: Date[] = [];
    let currentDate = startDate;
    while (currentDate <= endDate) {
      days.push(new Date(currentDate));
      currentDate = addDays(currentDate, 1);
    }
    return days;
  }, [filters.start_date, filters.end_date]);

  // 後方互換性のためにmonthDaysも維持
  const monthDays = periodDays;

  // アクティブスタッフをフィルタリング
  const activeUsers = useMemo(() => filterActiveUsers(users, selectedDate), [users, selectedDate]);

  // カスタムフック：シフト操作
  const {
    shifts,
    setShifts,
    selectedCell,
    selectedDayForBulk,
    handleCellClick,
    handleDayHeaderClick,
    handleShiftTypeSelect,
    handleBulkShiftTypeSelect,
    generateShiftFromPattern: generatePattern,
    isDirty,
    resetDirty,
  } = useShiftOperations(initialShifts, users, selectedDate, activeUsers, shiftTypeInfo, { startDate: filters.start_date, endDate: filters.end_date });

  // 未保存変更のナビゲーションガード
  const { dialogProps: unsavedDialogProps, openDialog: openUnsavedDialog } = useConfirmDialog();

  const handleNavigationBlocked = useCallback((proceed: () => void) => {
    openUnsavedDialog({
      title: '未保存の変更があります',
      message: '保存されていないシフトの変更があります。ページを離れますか？',
      description: '変更内容は破棄されます。',
      icon: <AlertTriangle className="h-6 w-6 text-yellow-600" />,
      iconBgClass: 'bg-yellow-100',
      confirmLabel: 'ページを離れる',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: proceed,
    });
  }, [openUnsavedDialog]);

  useNavigationGuard(isDirty, handleNavigationBlocked);

  // カスタムフック：シフト統計（期間情報を渡す）
  const { getMonthlyStats, getAllStaffTotals, getShiftCountsByDate } = useShiftStats(selectedDate, shifts, activeUsers, {
    startDate: filters.start_date,
    endDate: filters.end_date,
  });

  // バックエンドのシフトデータが変更されたらstateを更新
  useEffect(() => {
    setShifts(initialShifts);
  }, [initialShifts, setShifts]);

  // ShiftTypeInfoを取得するヘルパー関数
  const getShiftTypeInfo = (shiftType: ShiftType) => {
    return getShiftTypeInfoUtil(shiftType, shiftTypeInfo);
  };

  /**
   * シフト表示期間に応じた日付範囲を計算
   */
  const getDateRangeForPeriod = (baseDate: Date): { start: Date, end: Date } => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      // 締め日翌日スタート
      const closingDay = payrollClosingDay;
      const daysInPrevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 0).getDate();

      // 開始日: 前月の締め日+1（月末考慮）
      let start: Date;
      if (closingDay >= daysInPrevMonth) {
        start = new Date(baseDate.getFullYear(), baseDate.getMonth(), 1);
      } else {
        start = new Date(baseDate.getFullYear(), baseDate.getMonth() - 1, closingDay + 1);
      }

      // 終了日: 当月の締め日（月末考慮）
      const daysInMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() + 1, 0).getDate();
      const endDay = Math.min(closingDay, daysInMonth);
      const end = new Date(baseDate.getFullYear(), baseDate.getMonth(), endDay);

      return { start, end };
    } else {
      // 月初〜月末
      return {
        start: startOfMonth(baseDate),
        end: endOfMonth(baseDate)
      };
    }
  };

  /**
   * 期間選択ハンドラー
   * ドロップダウンから選択された期間に表示を切り替える
   * Inertia.jsのページ遷移を使用してバックエンドから新しいデータを取得
   */
  const handlePeriodSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const [year, month] = e.target.value.split('-');
    const baseDate = new Date(parseInt(year), parseInt(month) - 1, 1);
    const { start, end } = getDateRangeForPeriod(baseDate);

    setIsChangingMonth(true);
    router.visit(`/admin/shifts?start_date=${format(start, 'yyyy-MM-dd')}&end_date=${format(end, 'yyyy-MM-dd')}`, {
      preserveState: false,
      preserveScroll: false,
      onFinish: () => setIsChangingMonth(false),
    });
  };

  /**
   * 期間表示ラベルを取得
   */
  const getPeriodLabel = (baseDate: Date) => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      const closingDay = payrollClosingDay;
      const prevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() - 1, 1);
      const daysInPrevMonth = new Date(baseDate.getFullYear(), baseDate.getMonth(), 0).getDate();
      const startDay = closingDay + 1;
      if (closingDay >= daysInPrevMonth) {
        return `${format(baseDate, 'yyyy年M月')}1日〜${format(baseDate, 'M月')}${closingDay}日`;
      }
      return `${format(prevMonth, 'yyyy年M月')}${startDay}日〜${format(baseDate, 'M月')}${closingDay}日`;
    } else {
      return format(baseDate, 'yyyy年MM月');
    }
  };

  /**
   * 現在選択されている期間の基準月を取得
   */
  const getCurrentPeriodMonth = () => {
    if (shiftDisplayPeriod === 'closing_day_based') {
      // end_dateから基準月を計算（end_dateが締め日のある月）
      const endDate = new Date(filters.end_date);
      return new Date(endDate.getFullYear(), endDate.getMonth(), 1);
    } else {
      return new Date(filters.start_date);
    }
  };

  /**
   * スタッフ名クリックハンドラー
   * バックエンドからスタッフのシフトパターンと勤務可能曜日を取得し、
   * シフトパターン適用ダイアログを表示する
   */
  const handleUserNameClick = async (userId: string) => {
    const user = users.find(u => u.id === userId);
    if (!user) return;

    setLoadingAction('applying');
    try {
      // バックエンドからスタッフのシフトパターンと勤務可能曜日を取得
      const response = await axios.get(`/admin/shifts/users/${userId}/info`);
      const { shift_patterns } = response.data;

      // shift_patterns: { sunday: number|null, monday: number|null, ... }
      // バックエンドのシフトパターンIDをフロントエンドのShiftTypeに変換
      const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
      const shiftPatterns: any = {};

      dayKeys.forEach(dayKey => {
        const patternId = shift_patterns[dayKey];
        if (patternId) {
          const shiftType = shiftPatternIdMap.get(patternId);
          if (shiftType) shiftPatterns[dayKey] = shiftType;
        }
      });

      // スタッフ情報を更新
      const updatedUsers = users.map(u =>
        u.id === userId
          ? { ...u, shiftPatterns }
          : u
      );
      setUsers(updatedUsers);

      setShowPatternDialog({ userId, userName: user.name });
    } catch (error) {
      console.error('Failed to fetch shift patterns:', error);
      setErrorMessage('シフトパターンの取得に失敗しました');
      setTimeout(() => setErrorMessage(null), 5000);
    } finally {
      setLoadingAction(null);
    }
  };

  /**
   * スタッフのシフトパターンから月次シフトを生成
   * 選択されたスタッフの曜日別シフトパターンを
   * 当月の全ての日に適用する
   */
  const generateShiftFromPattern = (userId: string) => {
    generatePattern(userId);
    const user = users.find(u => u.id === userId);
    if (user) {
      setShowPatternDialog(null);
      setSuccessMessage(`${user.name}のシフトパターンを${getPeriodLabel(getCurrentPeriodMonth())}に適用しました`);
      setTimeout(() => setSuccessMessage(null), 3000);
    }
  };

  /**
   * ShiftTypeからシフトパターンIDを取得
   */
  const getShiftPatternIdFromShiftType = (shiftType: ShiftType): number | null => {
    if (shiftType === 'rest') return null;
    const info = shiftTypeInfo.find(i => i.type === shiftType);
    return info?.id ?? null;
  };

  /**
   * シフトデータを保存
   * 現在のシフトデータをバックエンドに送信して永続化する
   */
  const handleSaveShifts = async () => {
    setLoadingAction('saving');
    try {
      // シフトデータをバックエンド形式に変換（restはshift_pattern_id: nullで送信）
      const shiftsToSave = shifts
        .map(shift => ({
          user_id: parseInt(shift.userId),
          shift_date: shift.date,
          shift_pattern_id: shift.shiftType === 'rest'
            ? null
            : (shift.shiftPatternId ?? getShiftPatternIdFromShiftType(shift.shiftType)),
          note: null,
        }));

      // バックエンドにシフトデータを送信
      await axios.post('/admin/shifts/upsert', {
        shifts: shiftsToSave,
      });

      resetDirty();
      setSuccessMessage(`${getPeriodLabel(getCurrentPeriodMonth())}のシフトを保存しました`);
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error: any) {
      console.error('Failed to save shifts:', error);
      // バリデーションエラー（422）の場合、詳細なエラーメッセージを表示
      if (error.response?.status === 422 && error.response?.data?.errors) {
        const validationErrors = error.response.data.errors;
        const errorMessages = Object.values(validationErrors).flat().join('\n');
        setErrorMessage(errorMessages);
      } else {
        setErrorMessage('シフトの保存に失敗しました');
      }
      setTimeout(() => setErrorMessage(null), 5000);
    } finally {
      setLoadingAction(null);
    }
  };

  /**
   * 全員のシフトパターンを一括適用
   * アクティブな全スタッフのシフトパターンをバックエンドから取得し、
   * 当月の全ての日に一括適用する
   */
  const generateAllUsersShiftPatterns = async () => {
    setShowBulkPatternDialog(false);
    setLoadingAction('applying');
    try {
      // 全スタッフのシフトパターンを取得
      const updatedUsers = [...users];

      for (const user of activeUsers) {
        try {
          const response = await axios.get(`/admin/shifts/users/${user.id}/info`);
          const { shift_patterns } = response.data;

          // バックエンドのシフトパターンIDをフロントエンドのShiftTypeに変換
          const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
          const shiftPatterns: any = {};

          dayKeys.forEach(dayKey => {
            const patternId = shift_patterns[dayKey];
            if (patternId) {
              const shiftType = shiftPatternIdMap.get(patternId);
              if (shiftType) shiftPatterns[dayKey] = shiftType;
            }
          });

          // スタッフ情報を更新
          const userIndex = updatedUsers.findIndex(u => u.id === user.id);
          if (userIndex >= 0) {
            updatedUsers[userIndex] = {
              ...updatedUsers[userIndex],
              shiftPatterns,
            };
          }
        } catch (error) {
          console.error(`Failed to fetch shift patterns for user ${user.id}:`, error);
        }
      }

      setUsers(updatedUsers);

      // 更新されたスタッフ情報を使ってシフトパターンを適用
      const periodStartDate = new Date(filters.start_date);
      const periodEndDate = new Date(filters.end_date);
      let newShifts = shifts;

      updatedUsers.forEach(user => {
        if (!user.shiftPatterns) return;

        let currentDate = periodStartDate;
        while (currentDate <= periodEndDate) {
          const dateStr = format(currentDate, 'yyyy-MM-dd');
          const dayOfWeek = currentDate.getDay();
          const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
          const dayKey = dayKeys[dayOfWeek];

          const shiftType = user.shiftPatterns[dayKey];

          const existingShift = newShifts.find(shift =>
            shift.userId === user.id && shift.date === dateStr
          );

          if (shiftType) {
            const newShift: Shift = {
              id: existingShift ? existingShift.id : `shift_${Date.now()}_${user.id}_${dateStr}`,
              userId: user.id,
              date: dateStr,
              shiftType,
              shiftPatternId: getShiftPatternIdFromShiftType(shiftType),
            };

            newShifts = upsertShift(newShifts, newShift);
          } else if (existingShift) {
            // パターン未設定（休日）の曜日: 既存シフトを削除
            newShifts = removeShift(newShifts, user.id, dateStr);
          }

          currentDate = addDays(currentDate, 1);
        }
      });

      setShifts(newShifts);
      setSuccessMessage(`全員（${activeUsers.length}名）のシフトパターンを${getPeriodLabel(getCurrentPeriodMonth())}に適用しました`);
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Failed to apply patterns to all users:', error);
      setErrorMessage('全員のシフトパターンの適用に失敗しました');
      setTimeout(() => setErrorMessage(null), 5000);
    } finally {
      setLoadingAction(null);
    }
  };

  return (
    <AdminLayout>
      {/* ローディングオーバーレイ */}
      {(loadingAction || isChangingMonth) && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-8 flex flex-col items-center space-y-4">
            <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600"></div>
            <p className="text-lg font-medium text-gray-900">
              {isChangingMonth ? '月を変更中...' : loadingAction === 'applying' ? 'シフトパターンを適用中...' : '保存中...'}
            </p>
          </div>
        </div>
      )}

      <ShiftPatternDialog
        showDialog={showPatternDialog}
        selectedDate={selectedDate}
        users={users}
        getShiftTypeInfo={getShiftTypeInfo}
        onApply={generateShiftFromPattern}
        onCancel={() => setShowPatternDialog(null)}
      />

      <BulkPatternDialog
        showDialog={showBulkPatternDialog}
        periodLabel={getPeriodLabel(getCurrentPeriodMonth())}
        activeUsers={activeUsers}
        onApply={generateAllUsersShiftPatterns}
        onCancel={() => setShowBulkPatternDialog(false)}
      />

      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900">シフト管理</h1>
          <div className="flex items-center gap-2 sm:gap-4">
            <button
              onClick={() => setShowBulkPatternDialog(true)}
              className="bg-green-600 hover:bg-green-700 text-white px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-medium transition-colors"
            >
              全員のシフトパターンを反映
            </button>
            <MonthSelector
              value={format(getCurrentPeriodMonth(), 'yyyy-MM')}
              onChange={handlePeriodSelect}
              formatLabel={getPeriodLabel}
            />
          </div>
        </div>

        <InlineFlashMessage type="success" message={successMessage} />
        <InlineFlashMessage type="error" message={errorMessage} />

        <div className="bg-white shadow rounded-lg p-3 sm:p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-gray-900">{getPeriodLabel(getCurrentPeriodMonth())}</h2>
            <button
              onClick={handleSaveShifts}
              className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors flex items-center space-x-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
              </svg>
              <span>保存</span>
            </button>
          </div>

          <ShiftCalendar
            monthDays={monthDays}
            activeUsers={activeUsers}
            departments={backendDepartments}
            shifts={shifts}
            selectedCell={selectedCell}
            selectedDayForBulk={selectedDayForBulk}
            shiftTypeInfo={shiftTypeInfo}
            restShiftInfo={restShiftInfo}
            onCellClick={handleCellClick}
            onDayHeaderClick={handleDayHeaderClick}
            onShiftTypeSelect={handleShiftTypeSelect}
            onBulkShiftTypeSelect={handleBulkShiftTypeSelect}
            onUserNameClick={handleUserNameClick}
            getShiftTypeInfo={getShiftTypeInfo}
            getMonthlyStats={getMonthlyStats}
            getAllStaffTotals={getAllStaffTotals}
            getShiftCountsByDate={getShiftCountsByDate}
            leaves={leaves}
          />

          <ShiftLegend shiftTypeInfo={shiftTypeInfo} />
        </div>
      </div>

      <ConfirmDialog {...unsavedDialogProps} />
    </AdminLayout>
  );
}
