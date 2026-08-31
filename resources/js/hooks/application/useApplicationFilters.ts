import { useCallback } from 'react';
import { router } from '@inertiajs/react';

interface UseApplicationFiltersProps {
  currentStatus: number | null;
  currentName: string | null;
  currentTargetMonth: string | null;
  currentApprover: number | null;
  currentType: string | null;
}

export function useApplicationFilters({
  currentStatus,
  currentName,
  currentTargetMonth,
  currentApprover,
  currentType,
}: UseApplicationFiltersProps) {
  const buildParams = useCallback(
    (overrides: Record<string, string | number | null> = {}) => {
      const params: Record<string, string | number> = {};
      const status = overrides.status !== undefined ? overrides.status : currentStatus;
      const name =
        overrides.name !== undefined ? overrides.name : currentName;
      const targetMonth =
        overrides.target_month !== undefined ? overrides.target_month : currentTargetMonth;
      const approver =
        overrides.approver_id !== undefined ? overrides.approver_id : currentApprover;
      const type = overrides.type !== undefined ? overrides.type : currentType;
      const page = overrides.page !== undefined ? overrides.page : null;

      // status が null = 「すべて」選択。デフォルト（URL未指定）は申請中になるため、
      // 「すべて」を明示するために status=all を渡す
      if (status === null) {
        params.status = 'all';
      } else {
        params.status = status;
      }
      if (name !== null) params.name = name;
      if (targetMonth !== null) params.target_month = targetMonth;
      if (approver !== null) params.approver_id = approver;
      if (type !== null) params.type = type;
      if (page !== null) params.page = page;

      return params;
    },
    [currentStatus, currentName, currentTargetMonth, currentApprover, currentType]
  );

  const handleFilterChange = useCallback(
    (status: number | null) => {
      router.get('/admin/applications', buildParams({ status, page: 1 }), {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [buildParams]
  );

  const handleNameChange = useCallback(
    (name: string | null) => {
      router.get('/admin/applications', buildParams({ name, page: 1 }), {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [buildParams]
  );

  const handleTargetMonthChange = useCallback(
    (targetMonth: string | null) => {
      router.get('/admin/applications', buildParams({ target_month: targetMonth, page: 1 }), {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [buildParams]
  );

  const handleApproverChange = useCallback(
    (approverId: number | null) => {
      router.get('/admin/applications', buildParams({ approver_id: approverId, page: 1 }), {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [buildParams]
  );

  const handleTypeChange = useCallback(
    (type: string | null) => {
      router.get('/admin/applications', buildParams({ type, page: 1 }), {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [buildParams]
  );

  const handlePageChange = useCallback(
    (page: number) => {
      router.get('/admin/applications', buildParams({ page }), {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [buildParams]
  );

  const handleApproval = useCallback((requestId: number, approved: boolean, requestType: 'normal' | 'correction' = 'normal') => {
    const url = approved
      ? `/admin/applications/${requestId}/approve`
      : `/admin/applications/${requestId}/reject`;

    router.post(url, { request_type: requestType }, { preserveScroll: true });
  }, []);

  const handleRevert = useCallback((requestId: number, requestType: 'normal' | 'correction' = 'normal') => {
    router.post(`/admin/applications/${requestId}/revert`, { request_type: requestType }, { preserveScroll: true });
  }, []);

  const handleCancel = useCallback((requestId: number, requestType: 'normal' | 'correction' = 'normal') => {
    router.post(`/admin/applications/${requestId}/cancel`, { request_type: requestType }, { preserveScroll: true });
  }, []);

  return {
    handleFilterChange,
    handleNameChange,
    handleTargetMonthChange,
    handleApproverChange,
    handleTypeChange,
    handlePageChange,
    handleApproval,
    handleRevert,
    handleCancel,
  };
}
