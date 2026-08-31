import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useForm, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Role, DepartmentData as Department, ShiftPattern } from '@/types';
import BasicInfoFields from '@/Components/User/BasicInfoFields';
import RoleDepartmentFields from '@/Components/User/RoleDepartmentFields';
import WorkScheduleFields from '@/Components/User/WorkScheduleFields';
import PermissionFields from '@/Components/User/PermissionFields';
import UserConfirmView from '@/Components/User/UserConfirmView';
import ValidationErrors from '@/Components/ValidationErrors';

/**
 * 新規ユーザー作成ページのプロップス
 */
interface WeekdayFlags {
  sunday: boolean;
  monday: boolean;
  tuesday: boolean;
  wednesday: boolean;
  thursday: boolean;
  friday: boolean;
  saturday: boolean;
}

interface Props {
  roles: Role[];
  departments: Department[];
  shiftPatterns: ShiftPattern[];
  defaultShiftPatternId?: number | null;
  companyRegularHolidays?: WeekdayFlags;
  step?: 'input' | 'confirm';
  validatedData?: any;
}

type ShiftPatternsMap = {
  sunday: number | undefined;
  monday: number | undefined;
  tuesday: number | undefined;
  wednesday: number | undefined;
  thursday: number | undefined;
  friday: number | undefined;
  saturday: number | undefined;
};

/**
 * 新規ユーザー作成ページコンポーネント
 *
 * 2ステップの作成フロー：
 * 1. 入力画面（step='input'）: ユーザー情報の入力
 * 2. 確認画面（step='confirm'）: 入力内容の確認と確定
 *
 * 入力内容：
 * - 基本情報（氏名、フリガナ、個人コード、メールアドレス）
 * - 役割と部署
 * - 勤務スケジュール（各曜日のシフトパターン、祝日勤務）
 * - 権限設定（シフト閲覧、勤務実績閲覧、承認、シフト編集）
 */
export default function NewUserPage({
  roles,
  departments,
  shiftPatterns,
  defaultShiftPatternId,
  companyRegularHolidays,
  step: serverStep = 'input',
  validatedData
}: Props) {
  const [localErrors, setLocalErrors] = React.useState<Record<string, string>>({});

  const { data, setData, get, processing, errors } = useForm<{
    name: string;
    name_kana: string;
    employee_code: string;
    email: string;
    department_id: number | undefined;
    role_id: number | undefined;
    password: string;
    shift_patterns: ShiftPatternsMap;
    shift_view_permission: 'self' | 'department' | 'company';
    attendance_view_permission: 'self' | 'department' | 'company';
    approval_permission: 'department' | 'company';
    shift_edit_permission: 'department' | 'company';
    role_ids: number[];
  }>({
    name: validatedData?.name || '',
    name_kana: validatedData?.name_kana || '',
    employee_code: validatedData?.employee_code || '',
    email: validatedData?.email || '',
    department_id: validatedData?.department_id || undefined,
    role_id: validatedData?.role_id || undefined,
    password: validatedData?.password || '',
    shift_patterns: validatedData?.shift_patterns || {
      sunday: companyRegularHolidays?.sunday ? undefined : (defaultShiftPatternId ?? undefined),
      monday: companyRegularHolidays?.monday ? undefined : (defaultShiftPatternId ?? undefined),
      tuesday: companyRegularHolidays?.tuesday ? undefined : (defaultShiftPatternId ?? undefined),
      wednesday: companyRegularHolidays?.wednesday ? undefined : (defaultShiftPatternId ?? undefined),
      thursday: companyRegularHolidays?.thursday ? undefined : (defaultShiftPatternId ?? undefined),
      friday: companyRegularHolidays?.friday ? undefined : (defaultShiftPatternId ?? undefined),
      saturday: companyRegularHolidays?.saturday ? undefined : (defaultShiftPatternId ?? undefined),
    },
    shift_view_permission: (validatedData?.shift_view_permission || 'self') as 'self' | 'department' | 'company',
    attendance_view_permission: (validatedData?.attendance_view_permission || 'self') as 'self' | 'department' | 'company',
    approval_permission: (validatedData?.approval_permission || 'department') as 'department' | 'company',
    shift_edit_permission: (validatedData?.shift_edit_permission || 'department') as 'department' | 'company',
    role_ids: validatedData?.role_ids || [],
  });

  /**
   * 入力フォーム送信処理
   * バリデーションを行い、確認画面へ遷移
   */
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLocalErrors({});
    router.post('/admin/users/confirm', data);
  };

  /**
   * 確認画面での作成確定処理
   * サーバーにPOSTリクエストを送信してユーザーを作成
   */
  const handleConfirm = () => {
    router.post('/admin/users', data);
  };

  /**
   * 確認画面から入力画面へ戻る処理
   */
  const handleBack = () => {
    get('/admin/users/new');
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center space-x-4">
        <button
          onClick={() => serverStep === 'confirm' ? handleBack() : window.history.back()}
          className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </button>
        <h1 className="text-2xl font-bold text-gray-900">
          {serverStep === 'input' ? '新規ユーザー作成' : '新規ユーザー作成 - 確認'}
        </h1>
      </div>

      <ValidationErrors localErrors={localErrors} />

      {serverStep === 'input' && (
        <div className="bg-white shadow rounded-lg p-6">
          <form onSubmit={handleSubmit} className="space-y-6">
            <BasicInfoFields
              data={data}
              setData={setData}
              errors={errors}
              processing={processing}
            />

            <RoleDepartmentFields
              data={data}
              roles={roles}
              departments={departments}
              setData={setData}
              errors={errors}
              processing={processing}
            />

            <WorkScheduleFields
              data={data}
              shiftPatterns={shiftPatterns}
              setData={setData}
              processing={processing}
            />

            <PermissionFields
              role_id={data.role_id}
              data={data}
              setData={setData}
              processing={processing}
            />

            <div className="flex space-x-3 pt-4 border-t">
              <button
                type="button"
                onClick={() => window.history.back()}
                className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md transition-colors"
                disabled={processing}
              >
                キャンセル
              </button>
              <button
                type="submit"
                className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition-colors disabled:opacity-50"
                disabled={processing}
              >
                確認画面へ
              </button>
            </div>
          </form>
        </div>
      )}

      {serverStep === 'confirm' && (
        <UserConfirmView
          data={data}
          roles={roles}
          departments={departments}
          shiftPatterns={shiftPatterns}
          validatedData={validatedData}
          processing={processing}
          onBack={handleBack}
          onConfirm={handleConfirm}
        />
      )}
    </div>
  );
}

NewUserPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
