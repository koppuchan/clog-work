import { useState, useMemo, useEffect, useCallback } from 'react';
import { format, addDays, startOfMonth, getDaysInMonth, endOfMonth } from 'date-fns';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { ShiftType, User, Shift, ShiftPattern } from '@/types';
import { convertBackendUsers, convertBackendShifts, convertShiftPatterns, getShiftTypeInfo as getShiftTypeInfoUtil } from '@/utils/shiftDataConverter';
import { getActiveUsersForMonth as filterActiveUsers } from '@/utils/userFilters';
import { useShiftOperations } from './useShiftOperations';
import { useShiftStats } from './useShiftStats';
import type { BackendUser, BackendDepartment, BackendShift } from '@/types/shift';

interface UseShiftPageProps {
  backendUsers: BackendUser[];
  backendDepartments: BackendDepartment[];
  backendShifts: BackendShift[];
  shiftPatterns: ShiftPattern[];
  filters: {
    start_date: string;
    end_date: string;
  };
}

export function useShiftPage({
  backendUsers,
  backendDepartments,
  backendShifts,
  shiftPatterns,
  filters,
}: UseShiftPageProps) {
  // バックエンドから渡された日付を使用して初期値を設定
  const selectedDate = useMemo(() => new Date(filters.start_date), [filters.start_date]);
  const [showPatternDialog, setShowPatternDialog] = useState<{ userId: string; userName: string } | null>(null);
  const [showBulkPatternDialog, setShowBulkPatternDialog] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [isLoadingPattern, setIsLoadingPattern] = useState(false);
  const [isChangingMonth, setIsChangingMonth] = useState(false);

  // バックエンドデータを変換
  const initialUsers = useMemo(
    () => convertBackendUsers(backendUsers, backendDepartments),
    [backendUsers, backendDepartments]
  );
  const [users, setUsers] = useState<User[]>(initialUsers);
  const initialShifts = useMemo(() => convertBackendShifts(backendShifts), [backendShifts]);
  const shiftTypeInfo = useMemo(() => convertShiftPatterns(shiftPatterns), [shiftPatterns]);

  // バックエンドのスタッフデータが変更されたら更新
  useEffect(() => {
    setUsers(initialUsers);
  }, [initialUsers]);

  // 月間カレンダーデータ
  const monthStart = startOfMonth(selectedDate);
  const daysInMonth = getDaysInMonth(selectedDate);
  const monthDays = Array.from({ length: daysInMonth }, (_, i) => addDays(monthStart, i));

  // アクティブスタッフをフィルタリング
  const activeUsers = useMemo(() => filterActiveUsers(users, selectedDate), [users, selectedDate]);

  // カスタムフック：シフト操作
  const shiftOperations = useShiftOperations(initialShifts, users, selectedDate, activeUsers);
  const { shifts, setShifts } = shiftOperations;

  // カスタムフック：シフト統計
  const shiftStats = useShiftStats(selectedDate, shifts, activeUsers);

  // バックエンドのシフトデータが変更されたらstateを更新
  useEffect(() => {
    setShifts(initialShifts);
  }, [initialShifts, setShifts]);

  // ShiftTypeInfoを取得するヘルパー関数
  const getShiftTypeInfo = useCallback(
    (shiftType: ShiftType) => {
      return getShiftTypeInfoUtil(shiftType, shiftTypeInfo);
    },
    [shiftTypeInfo]
  );

  // 月選択ハンドラー
  const handleMonthSelect = useCallback((e: React.ChangeEvent<HTMLSelectElement>) => {
    const [year, month] = e.target.value.split('-');
    const newDate = new Date(parseInt(year), parseInt(month) - 1, 1);
    const startDate = format(startOfMonth(newDate), 'yyyy-MM-dd');
    const endDate = format(endOfMonth(newDate), 'yyyy-MM-dd');

    setIsChangingMonth(true);
    router.visit(`/admin/shifts?start_date=${startDate}&end_date=${endDate}`, {
      preserveState: false,
      preserveScroll: false,
      onFinish: () => setIsChangingMonth(false),
    });
  }, []);

  // スタッフ名クリックハンドラー
  const handleUserNameClick = useCallback(
    async (userId: string) => {
      const user = users.find((u) => u.id === userId);
      if (!user) return;

      setIsLoadingPattern(true);
      try {
        const response = await axios.get(`/admin/shifts/users/${userId}/info`);
        const { shift_patterns } = response.data;

        const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
        const shiftPatterns: Record<string, ShiftType> = {};

        dayKeys.forEach((dayKey) => {
          const patternId = shift_patterns[dayKey];
          if (patternId) {
            const info = shiftTypeInfo.find(i => i.id === patternId);
            if (info) shiftPatterns[dayKey] = info.type;
          }
        });

        const updatedUsers = users.map((u) =>
          u.id === userId ? { ...u, shiftPatterns } : u
        );
        setUsers(updatedUsers);
        setShowPatternDialog({ userId, userName: user.name });
      } catch (error) {
        console.error('Failed to fetch shift patterns:', error);
        alert('シフトパターンの取得に失敗しました');
      } finally {
        setIsLoadingPattern(false);
      }
    },
    [users, shiftTypeInfo]
  );

  // スタッフのシフトパターンから月次シフトを生成
  const generateShiftFromPattern = useCallback(
    (userId: string) => {
      shiftOperations.generateShiftFromPattern(userId);
      const user = users.find((u) => u.id === userId);
      if (user) {
        setShowPatternDialog(null);
        setSuccessMessage(`${user.name}のシフトパターンを${format(selectedDate, 'yyyy年MM月')}に適用しました`);
        setTimeout(() => setSuccessMessage(null), 3000);
      }
    },
    [shiftOperations, users, selectedDate]
  );

  // ShiftTypeからシフトパターンIDを取得
  const getShiftPatternIdFromShiftType = useCallback((shiftType: ShiftType): number | null => {
    if (shiftType === 'rest') return null;
    const info = shiftTypeInfo.find(i => i.type === shiftType);
    return info?.id ?? null;
  }, [shiftTypeInfo]);

  // シフトデータを保存
  const handleSaveShifts = useCallback(async () => {
    setIsLoadingPattern(true);
    try {
      const shiftsToSave = shifts.map((shift) => ({
        user_id: parseInt(shift.userId),
        shift_date: shift.date,
        shift_pattern_id: getShiftPatternIdFromShiftType(shift.shiftType),
        note: null,
      }));

      await axios.post('/admin/shifts/upsert', { shifts: shiftsToSave });

      setSuccessMessage(`${format(selectedDate, 'yyyy年MM月')}のシフトを保存しました`);
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Failed to save shifts:', error);
      alert('シフトの保存に失敗しました');
    } finally {
      setIsLoadingPattern(false);
    }
  }, [shifts, selectedDate, getShiftPatternIdFromShiftType]);

  // 全員のシフトパターンを一括適用
  const generateAllUsersShiftPatterns = useCallback(async () => {
    setIsLoadingPattern(true);
    try {
      const updatedUsers = [...users];

      for (const user of activeUsers) {
        try {
          const response = await axios.get(`/admin/shifts/users/${user.id}/info`);
          const { shift_patterns } = response.data;

          const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
          const shiftPatterns: Record<string, ShiftType> = {};

          dayKeys.forEach((dayKey) => {
            const patternId = shift_patterns[dayKey];
            if (patternId) {
              const info = shiftTypeInfo.find(i => i.id === patternId);
              if (info) shiftPatterns[dayKey] = info.type;
            }
          });

          const userIndex = updatedUsers.findIndex((u) => u.id === user.id);
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

      const monthStartDate = startOfMonth(selectedDate);
      const monthEndDate = endOfMonth(selectedDate);
      const newShifts = [...shifts];

      updatedUsers.forEach((user) => {
        if (!user.shiftPatterns) return;

        let currentDate = monthStartDate;
        while (currentDate <= monthEndDate) {
          const dateStr = format(currentDate, 'yyyy-MM-dd');
          const dayOfWeek = currentDate.getDay();
          const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
          const dayKey = dayKeys[dayOfWeek];

          const shiftType = user.shiftPatterns[dayKey];

          const existingShiftIndex = newShifts.findIndex(
            (shift) => shift.userId === user.id && shift.date === dateStr
          );

          if (shiftType) {
            const newShift: Shift = {
              id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${user.id}_${dateStr}`,
              userId: user.id,
              date: dateStr,
              shiftType,
            };

            if (existingShiftIndex >= 0) {
              newShifts[existingShiftIndex] = newShift;
            } else {
              newShifts.push(newShift);
            }
          }

          currentDate = addDays(currentDate, 1);
        }
      });

      setShifts(newShifts);
      setShowBulkPatternDialog(false);
      setSuccessMessage(
        `全員（${activeUsers.length}名）のシフトパターンを${format(selectedDate, 'yyyy年MM月')}に適用しました`
      );
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Failed to apply patterns to all users:', error);
      alert('全員のシフトパターンの適用に失敗しました');
    } finally {
      setIsLoadingPattern(false);
    }
  }, [users, activeUsers, selectedDate, shifts, setShifts, shiftTypeInfo]);

  return {
    // State
    selectedDate,
    showPatternDialog,
    setShowPatternDialog,
    showBulkPatternDialog,
    setShowBulkPatternDialog,
    successMessage,
    isLoadingPattern,
    isChangingMonth,
    users,
    shifts,
    monthDays,
    activeUsers,
    shiftTypeInfo,
    // Operations from useShiftOperations
    selectedCell: shiftOperations.selectedCell,
    selectedDayForBulk: shiftOperations.selectedDayForBulk,
    handleCellClick: shiftOperations.handleCellClick,
    handleDayHeaderClick: shiftOperations.handleDayHeaderClick,
    handleShiftTypeSelect: shiftOperations.handleShiftTypeSelect,
    handleBulkShiftTypeSelect: shiftOperations.handleBulkShiftTypeSelect,
    // Stats from useShiftStats
    getMonthlyStats: shiftStats.getMonthlyStats,
    getAllStaffTotals: shiftStats.getAllStaffTotals,
    getShiftCountsByDate: shiftStats.getShiftCountsByDate,
    // Handlers
    getShiftTypeInfo,
    handleMonthSelect,
    handleUserNameClick,
    generateShiftFromPattern,
    handleSaveShifts,
    generateAllUsersShiftPatterns,
  };
}
