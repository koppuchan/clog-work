import React from 'react';
import { Role, DepartmentData as Department } from '@/types';

/**
 * 役割・部署選択フィールドのプロップス
 */
interface RoleDepartmentFieldsProps {
  data: {
    role_id: number | undefined;
    department_id: number | undefined;
    shift_view_permission: 'self' | 'department' | 'company';
    attendance_view_permission: 'self' | 'department' | 'company';
  };
  roles: Role[];
  departments: Department[];
  setData: ((key: string, value: any) => void) | ((values: any) => void);
  errors: Partial<Record<string, string>>;
  processing: boolean;
  isLastAdmin?: boolean;
  isOwner?: boolean;
}

/**
 * 役割・部署選択フィールドコンポーネント
 *
 * ユーザーの役割と部署を選択するフィールド：
 * - 役割（管理者/責任者/一般）の選択（必須）
 * - 部署の選択（必須）
 *
 * 役割に応じた権限の自動設定：
 * - 一般ユーザー（role_id=3）: シフト閲覧と勤務実績閲覧を「本人のみ」に設定
 * - 責任者・管理者: シフト閲覧と勤務実績閲覧を「部署全体」に設定
 */
export default function RoleDepartmentFields({
  data,
  roles,
  departments,
  setData,
  errors,
  processing,
  isLastAdmin = false,
  isOwner = false
}: RoleDepartmentFieldsProps) {
  // オーナーまたは唯一の管理者の場合、役割変更を無効にする
  const isRoleLocked = (isOwner || isLastAdmin) && data.role_id === 1;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label htmlFor="role_id" className="block text-sm font-medium text-gray-700 mb-2">
          権限 <span className="text-red-500">*</span>
        </label>
        <select
          id="role_id"
          name="role_id"
          value={data.role_id || ''}
          onChange={(e) => {
            const newRoleId = e.target.value ? parseInt(e.target.value) : undefined;
            // role_idから権限レベルを判定（1: admin, 2: manager, 3: employee）
            // 一般ユーザーは「本人のみ」、それ以外は「部署全体」をデフォルトに設定
            const defaultShiftView = newRoleId === 3 ? 'self' : 'department';
            const defaultAttendance = newRoleId === 3 ? 'self' : 'department';
            setData({
              ...data,
              role_id: newRoleId,
              shift_view_permission: defaultShiftView,
              attendance_view_permission: defaultAttendance
            });
          }}
          className={`w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${
            isRoleLocked ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : ''
          }`}
          required
          disabled={processing || isRoleLocked}
        >
          <option value="">選択してください</option>
          {roles.map(role => (
            <option key={role.id} value={role.id}>
              {role.name}
            </option>
          ))}
        </select>
        {isRoleLocked && (
          <p className="text-amber-600 text-xs mt-1">
            {isOwner
              ? '最初に作成されたユーザーの役割は変更できません'
              : '会社に管理者が1人しかいないため、役割を変更できません'}
          </p>
        )}
        {errors.role_id && <p className="text-red-500 text-sm mt-1">{errors.role_id}</p>}
      </div>

      <div>
        <label htmlFor="department_id" className="block text-sm font-medium text-gray-700 mb-2">
          部署 <span className="text-red-500">*</span>
        </label>
        <select
          id="department_id"
          name="department_id"
          value={data.department_id || ''}
          onChange={(e) => setData('department_id', e.target.value ? parseInt(e.target.value) : undefined)}
          className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          required
          disabled={processing}
        >
          <option value="">選択してください</option>
          {departments.map(dept => (
            <option key={dept.id} value={dept.id}>{dept.name}</option>
          ))}
        </select>
        {errors.department_id && <p className="text-red-500 text-sm mt-1">{errors.department_id}</p>}
      </div>
    </div>
  );
}
