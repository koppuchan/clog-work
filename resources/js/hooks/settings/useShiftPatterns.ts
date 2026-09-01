import { useState, useCallback } from 'react';
import axios from 'axios';
import {
  ShiftColor,
  SettingsShiftPattern,
  NewShiftPatternForm,
  BREAK_MODE,
} from '@/types/settings';
import {
  getInitialShiftPatternForm,
  DEFAULT_BREAK_SETTINGS,
} from '@/utils/settingsHelpers';

export function useShiftPatterns(
  initialPatterns: SettingsShiftPattern[] = [],
  availableColors: ShiftColor[] = [],
) {
  const [shiftPatterns, setShiftPatterns] = useState<SettingsShiftPattern[]>(initialPatterns);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState<NewShiftPatternForm>(getInitialShiftPatternForm());
  const [breakStart, setBreakStart] = useState(DEFAULT_BREAK_SETTINGS.start);
  const [breakEnd, setBreakEnd] = useState(DEFAULT_BREAK_SETTINGS.end);
  const [selectedColorId, setSelectedColorId] = useState<number | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const resetBreakSettings = useCallback(() => {
    setBreakStart(DEFAULT_BREAK_SETTINGS.start);
    setBreakEnd(DEFAULT_BREAK_SETTINGS.end);
  }, []);

  const openAddForm = useCallback(() => {
    setFormData(getInitialShiftPatternForm());
    setEditingId(null);
    setError(null);
    resetBreakSettings();
    setSelectedColorId(availableColors.length > 0 ? availableColors[0].id : null);
    setShowForm(true);
  }, [resetBreakSettings, availableColors]);

  const openEditForm = useCallback((id: number) => {
    const shift = shiftPatterns.find((s) => s.id === id);
    if (shift) {
      setFormData({ ...shift });
      setEditingId(id);
      setError(null);
      setSelectedColorId(shift.colorId ?? null);
      setShowForm(true);

      setBreakStart(shift.breakStart ?? DEFAULT_BREAK_SETTINGS.start);
      setBreakEnd(shift.breakEnd ?? DEFAULT_BREAK_SETTINGS.end);

      // 始業・終業時刻を設定
      setTimeout(() => {
        const startInput = document.getElementById('shiftStartTime') as HTMLInputElement;
        const endInput = document.getElementById('shiftEndTime') as HTMLInputElement;
        if (startInput && shift.startTime) startInput.value = shift.startTime;
        if (endInput && shift.endTime) endInput.value = shift.endTime;
      }, 0);
    }
  }, [shiftPatterns]);

  const closeForm = useCallback(() => {
    setShowForm(false);
    setEditingId(null);
    setFormData(getInitialShiftPatternForm());
    setError(null);
    resetBreakSettings();
    setSelectedColorId(null);
  }, [resetBreakSettings]);

  // 時刻をHH:MM形式に変換（HH:MM:SS形式の場合は先頭5文字を取得）
  const formatTime = (time: string | null): string | null => {
    if (!time) return null;
    return time.substring(0, 5);
  };

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    const startTime = (document.getElementById('shiftStartTime') as HTMLInputElement)?.value || null;
    const endTime = (document.getElementById('shiftEndTime') as HTMLInputElement)?.value || null;

    const requestData = {
      name: formData.name,
      startTime: formatTime(startTime),
      endTime: formatTime(endTime),
      workMinutes: formData.workMinutes,
      breakMode: BREAK_MODE.RANGE,
      breakMinutes: null,
      breakStart: formatTime(breakStart),
      breakEnd: formatTime(breakEnd),
      autoFillBreak: formData.autoFillBreak ?? false,
      colorId: selectedColorId,
    };

    try {
      if (editingId !== null) {
        // 更新
        const response = await axios.put(`/admin/settings/shift-patterns/${editingId}`, requestData);
        setShiftPatterns((prev) =>
          prev.map((shift) => (shift.id === editingId ? response.data : shift))
        );
      } else {
        // 新規追加
        const response = await axios.post('/admin/settings/shift-patterns', requestData);
        setShiftPatterns((prev) => [...prev, response.data]);
      }
      closeForm();
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.data?.errors) {
        const errors = err.response.data.errors;
        const firstError = Object.values(errors)[0];
        setError(Array.isArray(firstError) ? firstError[0] : String(firstError));
      } else if (axios.isAxiosError(err) && err.response?.data?.error) {
        setError(err.response.data.error);
      } else {
        setError('保存に失敗しました');
      }
    } finally {
      setIsSubmitting(false);
    }
  }, [editingId, formData, breakStart, breakEnd, selectedColorId, closeForm]);

  const handleDelete = useCallback(async (id: number) => {
    try {
      await axios.delete(`/admin/settings/shift-patterns/${id}`);
      setShiftPatterns((prev) => prev.filter((shift) => shift.id !== id));
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.data?.error) {
        alert(err.response.data.error);
      } else {
        alert('削除に失敗しました');
      }
    }
  }, []);

  // 後方互換性のためにダミーの関数を返す
  const getSubmitData = useCallback(() => {
    return {
      shiftPatterns: shiftPatterns.map(pattern => ({
        id: pattern.id,
        name: pattern.name,
        startTime: formatTime(pattern.startTime),
        endTime: formatTime(pattern.endTime),
        workMinutes: pattern.workMinutes,
        breakMode: pattern.breakMode,
        breakMinutes: pattern.breakMinutes,
        breakStart: formatTime(pattern.breakStart),
        breakEnd: formatTime(pattern.breakEnd),
        autoFillBreak: pattern.autoFillBreak ?? false,
      })),
      deletedShiftPatternIds: [],
    };
  }, [shiftPatterns]);

  return {
    shiftPatterns,
    showForm,
    isEditing: editingId !== null,
    formData,
    setFormData,
    breakStart,
    setBreakStart,
    breakEnd,
    setBreakEnd,
    selectedColorId,
    setSelectedColorId,
    availableColors,
    openAddForm,
    openEditForm,
    closeForm,
    handleSubmit,
    handleDelete,
    getSubmitData,
    isSubmitting,
    error,
  };
}
