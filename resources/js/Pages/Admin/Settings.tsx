import React, { useState, useCallback } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { router, usePage } from '@inertiajs/react';
import { Save, Loader2, Trash2, AlertTriangle } from 'lucide-react';
import { SettingsPageProps } from '@/types/settings';
import { useCompanySettings } from '@/hooks/settings/useCompanySettings';
import { useDepartments } from '@/hooks/settings/useDepartments';
import { useShiftPatterns } from '@/hooks/settings/useShiftPatterns';
import { useLaborAlertSettings } from '@/hooks/settings/useLaborAlertSettings';
import {
  CompanySettingsCard,
  DepartmentSettingsCard,
  PayrollClosingDayCard,
  ShiftDisplayPeriodCard,
  ShiftPatternSettingsCard,
  ClockRoundingCard,
  PaidLeaveCard,
  LaborAlertSettingsCard,
  AccountInfoCard,
  PlanCard,
  DepartmentFormModal,
  ShiftPatternFormModal,
} from '@/Components/Settings';
import ValidationErrors from '@/Components/ValidationErrors';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';
import { useNavigationGuard } from '@/hooks/useNavigationGuard';

function SettingsPage({
  company,
  companyRegularHolidays,
  companySettings: initialCompanySettings,
  departments: initialDepartments,
  shiftPatterns: initialShiftPatterns,
  shiftColors,
  laborAlertSettings: initialLaborAlertSettings,
  canEdit,
}: SettingsPageProps) {
  // ログイン中の管理者は共有プロパティから取得する
  const authUser = usePage().props.auth?.user as { name: string; email: string | null } | undefined;

  // カスタムhooks
  const companySettingsHook = useCompanySettings(company, companyRegularHolidays, initialCompanySettings);
  const departmentSettings = useDepartments(initialDepartments ?? []);
  const shiftPatternSettings = useShiftPatterns(initialShiftPatterns ?? [], shiftColors ?? []);
  const laborAlertSettings = useLaborAlertSettings(initialLaborAlertSettings);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const { dialogProps, openDialog } = useConfirmDialog();
  const { dialogProps: unsavedDialogProps, openDialog: openUnsavedDialog } = useConfirmDialog();

  // 未保存変更の検知
  const isDirty = companySettingsHook.isDirty || laborAlertSettings.isDirty;

  // ナビゲーションガード
  const handleNavigationBlocked = useCallback((proceed: () => void) => {
    openUnsavedDialog({
      title: '未保存の変更があります',
      message: '保存されていない変更があります。ページを離れますか？',
      description: '変更内容は破棄されます。',
      icon: <AlertTriangle className="h-6 w-6 text-yellow-600" />,
      iconBgClass: 'bg-yellow-100',
      confirmLabel: 'ページを離れる',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: proceed,
    });
  }, [openUnsavedDialog]);

  useNavigationGuard(isDirty, handleNavigationBlocked);

  const confirmDeleteDepartment = useCallback((id: number) => {
    openDialog({
      title: '部署の削除',
      message: 'この部署を削除しますか？',
      icon: <Trash2 className="h-6 w-6 text-red-600" />,
      iconBgClass: 'bg-red-100',
      confirmLabel: '削除する',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: () => departmentSettings.handleDelete(id),
    });
  }, [openDialog, departmentSettings.handleDelete]);

  const confirmDeleteShiftPattern = useCallback((id: number) => {
    openDialog({
      title: 'シフトパターンの削除',
      message: 'このシフトパターンを削除しますか？',
      icon: <Trash2 className="h-6 w-6 text-red-600" />,
      iconBgClass: 'bg-red-100',
      confirmLabel: '削除する',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: () => shiftPatternSettings.handleDelete(id),
    });
  }, [openDialog, shiftPatternSettings.handleDelete]);

  const handleSubmit = () => {
    const data = {
      // 会社基本情報
      companyName: companySettingsHook.companyName,
      // 給与締め日
      payrollClosingDay: companySettingsHook.payrollClosingDay,
      // シフト表表示期間
      shiftDisplayPeriod: companySettingsHook.shiftDisplayPeriod,
      // 打刻丸め設定
      clockRounding: companySettingsHook.clockRounding,
      // 基本シフトパターン
      defaultShiftPatternId: companySettingsHook.defaultShiftPatternId,
      // 打刻画面非表示設定
      isStampHidden: companySettingsHook.isStampHidden,
      // 有給休暇設定
      paidLeaveHalfDay: companySettingsHook.paidLeaveHalfDay,
      paidLeaveHourly: companySettingsHook.paidLeaveHourly,
      dailyWorkingHours: companySettingsHook.dailyWorkingHours,
      // 労務アラート設定
      alertOvertimeNotification: laborAlertSettings.overtimeNotification,
      alertOvertimeLimit: laborAlertSettings.overtimeLimit,
      alertMessages: laborAlertSettings.alertMessages,
      // 部署とシフトパターンは即時保存のため不要
      departments: [],
      deletedDepartmentIds: [],
      shiftPatterns: [],
      deletedShiftPatternIds: [],
    };

    setIsSubmitting(true);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    router.put('/admin/settings', data as any, {
      onFinish: () => setIsSubmitting(false),
      onSuccess: () => {
        companySettingsHook.resetDirty();
        laborAlertSettings.resetDirty();
      },
      onError: (errors) => console.error('Validation errors:', errors),
    });
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">システム設定</h1>
      </div>

      <ValidationErrors />

      {/* 読み取り専用メッセージ（責任者向け） */}
      {!canEdit && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <p className="text-sm text-yellow-800">
            設定の変更は管理者のみ可能です。現在の設定内容を閲覧できます。
          </p>
        </div>
      )}

      {/* ログイン情報は閲覧のみのため、編集制限の外に置く */}
      <AccountInfoCard
        userName={authUser?.name ?? ''}
        email={authUser?.email ?? null}
        companyCode={company.company_code}
        companyName={company.name}
      />

      {/* 設定カード（責任者は閲覧のみ） */}
      <div className={`space-y-6 ${!canEdit ? 'pointer-events-none opacity-75' : ''}`}>
        {/* 会社設定 */}
        <CompanySettingsCard
          companyCode={companySettingsHook.companyCode}
          publicStampUrl={company.public_stamp_url}
          companyName={companySettingsHook.companyName}
          isStampHidden={companySettingsHook.isStampHidden}
          onCompanyNameChange={companySettingsHook.setCompanyName}
          onStampHiddenChange={companySettingsHook.setIsStampHidden}
        />

        {/* 部署設定 */}
        <DepartmentSettingsCard
          departments={departmentSettings.departments}
          onAdd={departmentSettings.openAddForm}
          onEdit={departmentSettings.openEditForm}
          onDelete={confirmDeleteDepartment}
        />

        {/* 給与締切日設定 */}
        <PayrollClosingDayCard
          payrollClosingDay={companySettingsHook.payrollClosingDay}
          onPayrollClosingDayChange={companySettingsHook.setPayrollClosingDay}
        />

        {/* シフト表表示期間設定 */}
        <ShiftDisplayPeriodCard
          shiftDisplayPeriod={companySettingsHook.shiftDisplayPeriod}
          payrollClosingDay={Number(companySettingsHook.payrollClosingDay) || 25}
          onShiftDisplayPeriodChange={companySettingsHook.setShiftDisplayPeriod}
        />

        {/* 勤務時間（シフトパターン）設定 */}
        <ShiftPatternSettingsCard
          shiftPatterns={shiftPatternSettings.shiftPatterns}
          defaultShiftPatternId={companySettingsHook.defaultShiftPatternId}
          onDefaultChange={companySettingsHook.setDefaultShiftPatternId}
          onAdd={shiftPatternSettings.openAddForm}
          onEdit={shiftPatternSettings.openEditForm}
          onDelete={confirmDeleteShiftPattern}
        />

        {/* 打刻設定 */}
        <ClockRoundingCard
          clockRounding={companySettingsHook.clockRounding}
          onClockRoundingChange={companySettingsHook.setClockRounding}
        />

        {/* 有給休暇設定 */}
        <PaidLeaveCard
          paidLeaveHalfDay={companySettingsHook.paidLeaveHalfDay}
          paidLeaveHourly={companySettingsHook.paidLeaveHourly}
          dailyWorkingHours={companySettingsHook.dailyWorkingHours}
          onPaidLeaveHalfDayChange={companySettingsHook.setPaidLeaveHalfDay}
          onPaidLeaveHourlyChange={companySettingsHook.setPaidLeaveHourly}
          onDailyWorkingHoursChange={companySettingsHook.setDailyWorkingHours}
        />

        {/* 労務アラート設定 */}
        <LaborAlertSettingsCard
          alertOvertimeNotification={laborAlertSettings.overtimeNotification}
          alertOvertimeLimit={laborAlertSettings.overtimeLimit}
          alertMessages={laborAlertSettings.alertMessages}
          onOvertimeNotificationChange={laborAlertSettings.setOvertimeNotification}
          onOvertimeLimitChange={laborAlertSettings.setOvertimeLimit}
          onAlertMessagesChange={laborAlertSettings.setAlertMessages}
        />

        {/* 現在のプラン */}
        <PlanCard />
      </div>

      {/* 保存ボタン（管理者のみ） */}
      {canEdit && (
        <div className="flex justify-end">
          <button
            type="button"
            onClick={handleSubmit}
            disabled={isSubmitting}
            className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-12 py-3 rounded-lg flex items-center justify-center space-x-2 text-base font-semibold min-w-[200px]"
          >
            {isSubmitting ? (
              <Loader2 className="h-5 w-5 animate-spin" />
            ) : (
              <Save className="h-5 w-5" />
            )}
            <span>{isSubmitting ? '保存中...' : '保存'}</span>
          </button>
        </div>
      )}

      {/* 部署追加・編集モーダル */}
      <DepartmentFormModal
        isOpen={departmentSettings.showForm}
        isEditing={departmentSettings.isEditing}
        department={departmentSettings.formData}
        onDepartmentChange={departmentSettings.setFormData}
        onSubmit={departmentSettings.handleSubmit}
        onClose={departmentSettings.closeForm}
        isSubmitting={departmentSettings.isSubmitting}
        error={departmentSettings.error}
      />

      {/* シフトパターン追加・編集モーダル */}
      <ShiftPatternFormModal
        isOpen={shiftPatternSettings.showForm}
        isEditing={shiftPatternSettings.isEditing}
        shiftPattern={shiftPatternSettings.formData}
        breakStart={shiftPatternSettings.breakStart}
        breakEnd={shiftPatternSettings.breakEnd}
        onShiftPatternChange={shiftPatternSettings.setFormData}
        onBreakStartChange={shiftPatternSettings.setBreakStart}
        onBreakEndChange={shiftPatternSettings.setBreakEnd}
        selectedColorId={shiftPatternSettings.selectedColorId}
        availableColors={shiftPatternSettings.availableColors}
        onColorChange={shiftPatternSettings.setSelectedColorId}
        onSubmit={shiftPatternSettings.handleSubmit}
        onClose={shiftPatternSettings.closeForm}
        isSubmitting={shiftPatternSettings.isSubmitting}
        error={shiftPatternSettings.error}
      />

      <ConfirmDialog {...dialogProps} />
      <ConfirmDialog {...unsavedDialogProps} />
    </div>
  );
}

SettingsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default SettingsPage;
