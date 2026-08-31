import { useState, useRef, useMemo, useCallback } from 'react';
import { format, startOfMonth, endOfMonth, addDays, parseISO } from 'date-fns';
import { Shift, ShiftType, ShiftTypeInfo, User } from '@/types';

/**
 * シフトの意味的な比較用にキーを正規化する
 * id は新規生成時にランダム値のため除外、rest は DB に保存されないため除外
 */
const normalizeShifts = (shifts: Shift[]): string[] =>
  shifts
    .filter(s => s.shiftType !== 'rest')
    .map(s => `${s.userId}|${s.date}|${s.shiftType}`)
    .sort();

export function useShiftOperations(
  initialShifts: Shift[],
  users: User[],
  selectedDate: Date,
  activeUsers: User[],
  shiftTypeInfo?: ShiftTypeInfo[],
  period?: { startDate: string; endDate: string }
) {
  // ShiftTypeからシフトパターンIDを取得するヘルパー関数
  const getShiftPatternId = (shiftType: ShiftType): number | null => {
    if (shiftType === 'rest' || !shiftTypeInfo) return null;
    const info = shiftTypeInfo.find(i => i.type === shiftType);
    return info?.id ?? null;
  };
  const [shifts, setShifts] = useState<Shift[]>(initialShifts);
  const [selectedCell, setSelectedCell] = useState<{userId: string, date: string, dayIndex: number} | null>(null);
  const [selectedDayForBulk, setSelectedDayForBulk] = useState<{date: string, dayIndex: number} | null>(null);

  // セルクリック処理
  const handleCellClick = (userId: string, date: string, dayIndex: number) => {
    if (userId === '' && date === '' && dayIndex === -1) {
      setSelectedCell(null);
      return;
    }
    setSelectedCell({ userId, date, dayIndex });
    setSelectedDayForBulk(null);
  };

  // 日付ヘッダークリック処理
  const handleDayHeaderClick = (date: string, dayIndex: number) => {
    if (date === '' && dayIndex === -1) {
      setSelectedDayForBulk(null);
      return;
    }
    setSelectedDayForBulk({ date, dayIndex });
    setSelectedCell(null);
  };

  // シフトタイプ選択処理
  const handleShiftTypeSelect = (shiftType: ShiftType) => {
    if (selectedCell) {
      const existingShiftIndex = shifts.findIndex(shift =>
        shift.userId === selectedCell.userId && shift.date === selectedCell.date
      );

      const newShift: Shift = {
        id: existingShiftIndex >= 0 ? shifts[existingShiftIndex].id : `shift_${Date.now()}`,
        userId: selectedCell.userId,
        date: selectedCell.date,
        shiftType,
        shiftPatternId: getShiftPatternId(shiftType),
      };

      if (existingShiftIndex >= 0) {
        const updatedShifts = [...shifts];
        updatedShifts[existingShiftIndex] = newShift;
        setShifts(updatedShifts);
      } else {
        setShifts([...shifts, newShift]);
      }
    }
    setSelectedCell(null);
  };

  // 一括シフトタイプ選択処理
  const handleBulkShiftTypeSelect = (shiftType: ShiftType) => {
    if (selectedDayForBulk) {
      const newShifts = [...shifts];

      activeUsers.forEach(user => {
        const existingShiftIndex = newShifts.findIndex(shift =>
          shift.userId === user.id && shift.date === selectedDayForBulk.date
        );

        const newShift: Shift = {
          id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${user.id}`,
          userId: user.id,
          date: selectedDayForBulk.date,
          shiftType,
          shiftPatternId: getShiftPatternId(shiftType),
        };

        if (existingShiftIndex >= 0) {
          newShifts[existingShiftIndex] = newShift;
        } else {
          newShifts.push(newShift);
        }
      });

      setShifts(newShifts);
    }
    setSelectedDayForBulk(null);
  };

  // ユーザーのシフトパターンから月次シフトを生成
  const generateShiftFromPattern = (userId: string) => {
    const user = users.find(u => u.id === userId);
    if (!user || !user.shiftPatterns) return;

    const monthStartDate = period ? parseISO(period.startDate) : startOfMonth(selectedDate);
    const monthEndDate = period ? parseISO(period.endDate) : endOfMonth(selectedDate);
    const newShifts = [...shifts];

    let currentDate = monthStartDate;
    while (currentDate <= monthEndDate) {
      const dateStr = format(currentDate, 'yyyy-MM-dd');
      const dayOfWeek = currentDate.getDay();
      const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
      const dayKey = dayKeys[dayOfWeek];

      const shiftType = user.shiftPatterns[dayKey];
      const existingShiftIndex = newShifts.findIndex(shift =>
        shift.userId === userId && shift.date === dateStr
      );

      if (shiftType) {
        const newShift: Shift = {
          id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${dateStr}`,
          userId,
          date: dateStr,
          shiftType,
          shiftPatternId: getShiftPatternId(shiftType),
        };

        if (existingShiftIndex >= 0) {
          newShifts[existingShiftIndex] = newShift;
        } else {
          newShifts.push(newShift);
        }
      } else if (existingShiftIndex >= 0) {
        // パターン未設定（休日）の曜日: 既存シフトを削除
        newShifts.splice(existingShiftIndex, 1);
      }

      currentDate = addDays(currentDate, 1);
    }

    setShifts(newShifts);
  };

  // 全スタッフに一括シフトパターンを適用
  const applyPatternToAll = () => {
    const newShifts = [...shifts];
    const monthStartDate = period ? parseISO(period.startDate) : startOfMonth(selectedDate);
    const monthEndDate = period ? parseISO(period.endDate) : endOfMonth(selectedDate);

    activeUsers.forEach(user => {
      if (!user.shiftPatterns) return;

      let currentDate = monthStartDate;
      while (currentDate <= monthEndDate) {
        const dateStr = format(currentDate, 'yyyy-MM-dd');
        const dayOfWeek = currentDate.getDay();
        const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
        const dayKey = dayKeys[dayOfWeek];

        const shiftType = user.shiftPatterns[dayKey];
        const existingShiftIndex = newShifts.findIndex(shift =>
          shift.userId === user.id && shift.date === dateStr
        );

        if (shiftType) {
          const newShift: Shift = {
            id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${user.id}_${dateStr}`,
            userId: user.id,
            date: dateStr,
            shiftType,
            shiftPatternId: getShiftPatternId(shiftType),
          };

          if (existingShiftIndex >= 0) {
            newShifts[existingShiftIndex] = newShift;
          } else {
            newShifts.push(newShift);
          }
        } else if (existingShiftIndex >= 0) {
          // パターン未設定（休日）の曜日: 既存シフトを削除
          newShifts.splice(existingShiftIndex, 1);
        }

        currentDate = addDays(currentDate, 1);
      }
    });

    setShifts(newShifts);
  };

  // 未保存変更の検知
  const initialShiftsRef = useRef(initialShifts);
  const [dirtyResetVersion, setDirtyResetVersion] = useState(0);

  const isDirty = useMemo(() => {
    const a = normalizeShifts(initialShiftsRef.current);
    const b = normalizeShifts(shifts);
    if (a.length !== b.length) return true;
    return a.some((v, i) => v !== b[i]);
  }, [shifts, dirtyResetVersion]);

  const resetDirty = useCallback(() => {
    initialShiftsRef.current = shifts;
    setDirtyResetVersion(v => v + 1);
  }, [shifts]);

  return {
    shifts,
    setShifts,
    selectedCell,
    selectedDayForBulk,
    handleCellClick,
    handleDayHeaderClick,
    handleShiftTypeSelect,
    handleBulkShiftTypeSelect,
    generateShiftFromPattern,
    applyPatternToAll,
    isDirty,
    resetDirty,
  };
}
