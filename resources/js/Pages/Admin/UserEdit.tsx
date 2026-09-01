import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useForm, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Role, DepartmentData, ShiftPattern } from '@/types';
import BasicInfoFields from '@/Components/User/BasicInfoFields';
import RoleDepartmentFields from '@/Components/User/RoleDepartmentFields';
import WorkScheduleFields from '@/Components/User/WorkScheduleFields';
import PermissionFields from '@/Components/User/PermissionFields';
import UserConfirmView from '@/Components/User/UserConfirmView';
import ValidationErrors from '@/Components/ValidationErrors';

/**
 * ユーザー編集ページのプロップス
 */
interface Props {
  id: number;
  user: any;
  roles: Role[];
  departments: DepartmentData[];
  shiftPatterns: ShiftPattern[];
  step?: 'input' | 'confirm';
  validatedData?: any;
  error?: string;
  isLastAdmin?: boolean;
  isOwner?: boolean;
}

/**
 * ユーザー編集ページコンポーネント
 *
 * 既存ユーザーの情報を編集する画面。
 * 新規作成と同様に2ステップのフローを採用：
 * 1. 入力画面（step='input'）: ユーザー情報の編集
 * 2. 確認画面（step='confirm'）: 変更内容の確認と確定
 *
 * 新規作成との違い：
 * - 既存のユーザー情報を初期値として表示
 * - パスワードフィールドは空の場合、変更しない
 * - 退職日の設定が可能
 * - 退職日に応じて自動的にis_retiredフラグを設定
 *
 * エラーハンドリング：
 * - ユーザーが見つからない場合はエラーメッセージを表示
 */
export default function EditUserPage({
  id,
  user: serverUser,
  roles,
  departments,
  shiftPatterns,
  step: serverStep = 'input',
  validatedData,
  error,
  isLastAdmin = false,
  isOwner = false
}: Props) {
  const userId = id;
  const [localErrors, setLocalErrors] = React.useState<Record<string, string>>({});

  /**
   * ユーザーのrolesから最初のrole_idを取得
   * 既存ユーザーの役割を初期値として設定するために使用
   */
  const determineRoleId = (user: any): number | undefined => {
    if (!user?.roles || user.roles.length === 0) return undefined;
    return user.roles[0].id;
  };

  const { data, setData, processing, errors } = useForm<{
    name: string;
    name_kana: string;
    employee_code: string;
    email: string;
    department_id: number | undefined;
    role_id: number | undefined;
    password: string;
    stamp_password: string;
    shift_patterns: {
      sunday: number | undefined;
      monday: number | undefined;
      tuesday: number | undefined;
      wednesday: number | undefined;
      thursday: number | undefined;
      friday: number | undefined;
      saturday: number | undefined;
    };
    shift_view_permission: 'self' | 'department' | 'company';
    attendance_view_permission: 'self' | 'department' | 'company';
    approval_permission: 'department' | 'company';
    shift_edit_permission: 'department' | 'company';
    is_stamp_hidden: boolean;
    is_shift_hidden: boolean;
    felica_idm: string;
    is_retired: boolean;
    retirement_date: string;
    role_ids: number[];
  }>({
    name: validatedData?.name || serverUser?.name || '',
    name_kana: validatedData?.name_kana || serverUser?.name_kana || '',
    employee_code: validatedData?.employee_code || serverUser?.employee_code || '',
    email: validatedData?.email || serverUser?.email || '',
    department_id: validatedData?.department_id || serverUser?.department_id || undefined,
    role_id: validatedData?.role_id || determineRoleId(serverUser),
    password: validatedData?.password || '',
    stamp_password: validatedData?.stamp_password || '',
    shift_patterns: validatedData?.shift_patterns || serverUser?.shift_patterns || {
      sunday: undefined,
      monday: undefined,
      tuesday: undefined,
      wednesday: undefined,
      thursday: undefined,
      friday: undefined,
      saturday: undefined,
    },
    shift_view_permission: (validatedData?.shift_view_permission || serverUser?.shift_view_permission || 'self') as 'self' | 'department' | 'company',
    attendance_view_permission: (validatedData?.attendance_view_permission || serverUser?.attendance_view_permission || 'self') as 'self' | 'department' | 'company',
    approval_permission: (validatedData?.approval_permission || serverUser?.approval_permission || 'department') as 'department' | 'company',
    shift_edit_permission: (validatedData?.shift_edit_permission || serverUser?.shift_edit_permission || 'department') as 'department' | 'company',
    is_stamp_hidden: validatedData?.is_stamp_hidden !== undefined ? validatedData.is_stamp_hidden : (serverUser?.is_stamp_hidden || false),
    is_shift_hidden: validatedData?.is_shift_hidden !== undefined ? validatedData.is_shift_hidden : (serverUser?.is_shift_hidden || false),
    felica_idm: validatedData?.felica_idm ?? serverUser?.felica_idm ?? '',
    is_retired: validatedData?.is_retired !== undefined ? validatedData.is_retired : (serverUser?.is_retired || false),
    retirement_date: validatedData?.retirement_date || serverUser?.retirement_date || '',
    role_ids: validatedData?.role_ids || serverUser?.roles?.map((r: any) => r.id) || [],
  });

  /**
   * 編集フォーム送信処理
   * バリデーションを行い、確認画面へ遷移
   */
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLocalErrors({});
    router.post(`/admin/users/${userId}/confirm`, data);
  };

  /**
   * 確認画面での更新確定処理
   * サーバーにPUTリクエストを送信してユーザー情報を更新
   */
  const handleConfirm = () => {
    router.put(`/admin/users/${userId}`, data);
  };

  /**
   * 確認画面から編集画面へ戻る処理
   */
  const handleBack = () => {
    router.get(`/admin/users/${userId}/edit`);
  };

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex items-center space-x-4">
          <button
            onClick={() => window.history.back()}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ArrowLeft className="h-5 w-5 text-gray-600" />
          </button>
          <h1 className="text-2xl font-bold text-gray-900">ユーザーが見つかりません</h1>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <p className="text-gray-600">{error}</p>
        </div>
      </div>
    );
  }

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
          {serverStep === 'input' ? 'ユーザー編集' : 'ユーザー編集 - 確認'}
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
              isEditing={true}
            />

            <RoleDepartmentFields
              data={data}
              roles={roles}
              departments={departments}
              setData={setData}
              errors={errors}
              processing={processing}
              isLastAdmin={isLastAdmin}
              isOwner={isOwner}
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

            <div>
              <label className="flex items-start cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.is_stamp_hidden}
                  onChange={(e) => setData('is_stamp_hidden', e.target.checked)}
                  className="mt-1 mr-3 h-4 w-4"
                  disabled={processing}
                />
                <div>
                  <span className="text-sm font-medium text-gray-700">打刻画面を非表示にする</span>
                  <p className="text-xs text-gray-500 mt-1">
                    このユーザーの打刻画面を非表示にします。
                  </p>
                </div>
              </label>
            </div>

            <div>
              <label className="flex items-start cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.is_shift_hidden}
                  onChange={(e) => setData('is_shift_hidden', e.target.checked)}
                  className="mt-1 mr-3 h-4 w-4"
                  disabled={processing}
                />
                <div>
                  <span className="text-sm font-medium text-gray-700">シフト表に表示しない</span>
                  <p className="text-xs text-gray-500 mt-1">
                    シフト管理画面の一覧および人数集計から除外します。管理者など、シフトに入らないユーザーに使用します。
                  </p>
                </div>
              </label>
            </div>

            <div>
              <label htmlFor="felica_idm" className="block text-sm font-medium text-gray-700 mb-2">
                FeliCaカード
              </label>
              <div className="flex items-center gap-2">
                <input
                  id="felica_idm"
                  type="text"
                  value={data.felica_idm}
                  onChange={(e) => setData('felica_idm', e.target.value.trim())}
                  placeholder="0123456789abcdef"
                  maxLength={16}
                  className="flex-1 border border-gray-300 rounded-lg px-3 py-2 font-mono text-sm"
                  disabled={processing}
                />
                {data.felica_idm && (
                  <button
                    type="button"
                    onClick={() => setData('felica_idm', '')}
                    className="px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50"
                    disabled={processing}
                  >
                    登録解除
                  </button>
                )}
              </div>
              {errors.felica_idm && (
                <p className="text-xs text-red-600 mt-1">{errors.felica_idm}</p>
              )}
              <p className="text-xs text-gray-500 mt-1">
                打刻カードのIDm（16進数16桁）を入力します。空にすると登録が解除されます。
                {serverUser?.felica_registered_at && (
                  <span className="block">登録日時: {serverUser.felica_registered_at}</span>
                )}
              </p>
            </div>

            <div>
              <label htmlFor="retirement_date" className="block text-sm font-medium text-gray-700 mb-2">
                退職日
              </label>
              <input
                type="date"
                id="retirement_date"
                name="retirement_date"
                value={data.retirement_date}
                onChange={(e) => {
                  const date = e.target.value;
                  const today = new Date().toISOString().split('T')[0];
                  const isRetired = !!(date && date <= today);
                  setData({
                    ...data,
                    retirement_date: date,
                    is_retired: isRetired
                  });
                }}
                className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={processing}
              />
              <p className="text-xs text-gray-500 mt-1">
                退職日を設定すると、該当日以降のシフトに影響します
              </p>
              {errors.retirement_date && <p className="text-red-500 text-sm mt-1">{errors.retirement_date}</p>}
            </div>

            {data.is_retired && (
              <div className="bg-red-50 border-l-4 border-red-500 p-3 rounded">
                <p className="text-sm text-red-800">
                  <strong>注意:</strong> 退職済みとしてマークされています。このユーザーは一覧で区別して表示されます。
                </p>
              </div>
            )}

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
          isEditMode={true}
        />
      )}
    </div>
  );
}

EditUserPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
