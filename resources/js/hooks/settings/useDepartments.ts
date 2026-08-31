import { useState, useCallback } from 'react';
import axios from 'axios';
import { SettingsDepartment, NewDepartmentForm } from '@/types/settings';

const INITIAL_DEPARTMENT: NewDepartmentForm = { id: 0, name: '', isStampHidden: false };

export function useDepartments(initialDepartments: SettingsDepartment[] = []) {
  const [departments, setDepartments] = useState<SettingsDepartment[]>(initialDepartments);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState<NewDepartmentForm>(INITIAL_DEPARTMENT);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const openAddForm = useCallback(() => {
    setFormData(INITIAL_DEPARTMENT);
    setEditingId(null);
    setError(null);
    setShowForm(true);
  }, []);

  const openEditForm = useCallback((id: number) => {
    const dept = departments.find((d) => d.id === id);
    if (dept) {
      setFormData({ id: dept.id, name: dept.name, isStampHidden: dept.isStampHidden });
      setEditingId(id);
      setError(null);
      setShowForm(true);
    }
  }, [departments]);

  const closeForm = useCallback(() => {
    setShowForm(false);
    setEditingId(null);
    setFormData(INITIAL_DEPARTMENT);
    setError(null);
  }, []);

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      if (editingId !== null) {
        // 更新
        const response = await axios.put(`/admin/settings/departments/${editingId}`, {
          name: formData.name,
          is_stamp_hidden: formData.isStampHidden,
        });
        setDepartments((prev) =>
          prev.map((dept) =>
            dept.id === editingId ? response.data : dept
          )
        );
      } else {
        // 新規追加
        const response = await axios.post('/admin/settings/departments', {
          name: formData.name,
          is_stamp_hidden: formData.isStampHidden,
        });
        setDepartments((prev) => [...prev, response.data]);
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
  }, [editingId, formData.name, formData.isStampHidden, closeForm]);

  const handleDelete = useCallback(async (id: number) => {
    try {
      await axios.delete(`/admin/settings/departments/${id}`);
      setDepartments((prev) => prev.filter((dept) => dept.id !== id));
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
      departments: departments.map(dept => ({
        id: dept.id,
        name: dept.name,
      })),
      deletedDepartmentIds: [],
    };
  }, [departments]);

  return {
    departments,
    showForm,
    isEditing: editingId !== null,
    formData,
    setFormData,
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
