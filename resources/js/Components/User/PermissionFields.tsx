import React from 'react';

/**
 * 権限設定フィールドのプロップス
 */
interface PermissionFieldsProps {
  role_id: number | undefined;
  data: {
    shift_view_permission: 'self' | 'department' | 'company';
    attendance_view_permission: 'self' | 'department' | 'company';
    approval_permission: 'department' | 'company';
    shift_edit_permission: 'department' | 'company';
  };
  setData: ((key: string, value: any) => void) | ((values: any) => void);
  processing: boolean;
}

/**
 * 権限設定フィールドコンポーネント
 *
 * スタッフの役割に応じて異なる権限設定フィールドを表示：
 *
 * 【一般スタッフ（role_id=3）】
 * - シフト閲覧権限: 本人のみ / 部署全体（選択可能）
 * - 勤務実績閲覧権限: 本人のみ（固定）
 *
 * 【責任者（role_id=2）】
 * - シフト閲覧権限: 所属部署のみ / 会社全員（選択可能）
 * - 勤務実績閲覧権限: 所属部署のみ / 会社全員（選択可能）
 * - 申請承認と取り消し権限: 所属部署のみ / 会社全員（選択可能）
 * - シフト入力と修正権限: 所属部署のみ / 会社全員（選択可能）
 *
 * 【管理者（role_id=1）】
 * - すべての権限が「全員」に固定
 * - シフト閲覧、勤務実績閲覧、申請承認、シフト編集すべて「全員」
 */
export default function PermissionFields({ role_id, data, setData, processing }: PermissionFieldsProps) {
  // role_id: 1=admin, 2=manager, 3=employee
  // 一般スタッフの権限設定
  if (role_id === 3) {
    return (
      <div className="space-y-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            シフト閲覧権限 <span className="text-red-500">*</span>
          </label>
          <div className="space-y-2">
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="shiftViewPermission"
                value="self"
                checked={data.shift_view_permission === 'self'}
                onChange={(e) => setData('shift_view_permission', e.target.value as 'self' | 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">本人のみ</span>
                <p className="text-xs text-gray-500 mt-1">
                  自分のシフトのみ閲覧できます
                </p>
              </div>
            </label>
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="shiftViewPermission"
                value="department"
                checked={data.shift_view_permission === 'department'}
                onChange={(e) => setData('shift_view_permission', e.target.value as 'self' | 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">部署全体</span>
                <p className="text-xs text-gray-500 mt-1">
                  同じ部署のメンバーのシフトも閲覧できます
                </p>
              </div>
            </label>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            勤務実績の閲覧権限
          </label>
          <div className="p-3 border rounded-lg bg-gray-50">
            <div className="flex items-start">
              <input
                type="radio"
                checked
                disabled
                className="mt-1 mr-3"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">本人のみ（固定）</span>
                <p className="text-xs text-gray-500 mt-1">
                  一般スタッフは自分の勤務実績のみ閲覧できます
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // 管理者の権限設定（すべて固定）
  if (role_id === 1) {
    return (
      <div className="space-y-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            勤務実績の閲覧権限
          </label>
          <div className="p-3 border rounded-lg bg-gray-50">
            <div className="flex items-start">
              <input
                type="radio"
                checked
                disabled
                className="mt-1 mr-3"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">全員（固定）</span>
                <p className="text-xs text-gray-500 mt-1">
                  管理者は全員の勤務実績を閲覧できます
                </p>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            申請承認と取り消し権限
          </label>
          <div className="p-3 border rounded-lg bg-gray-50">
            <div className="flex items-start">
              <input
                type="radio"
                checked
                disabled
                className="mt-1 mr-3"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">全員（固定）</span>
                <p className="text-xs text-gray-500 mt-1">
                  管理者は全員の申請を承認・取り消しできます
                </p>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            シフト閲覧権限
          </label>
          <div className="p-3 border rounded-lg bg-gray-50">
            <div className="flex items-start">
              <input
                type="radio"
                checked
                disabled
                className="mt-1 mr-3"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">全員（固定）</span>
                <p className="text-xs text-gray-500 mt-1">
                  管理者は全員のシフトを閲覧できます
                </p>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            シフト入力と修正権限
          </label>
          <div className="p-3 border rounded-lg bg-gray-50">
            <div className="flex items-start">
              <input
                type="radio"
                checked
                disabled
                className="mt-1 mr-3"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">全員（固定）</span>
                <p className="text-xs text-gray-500 mt-1">
                  管理者は全員のシフトを入力・修正できます
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // 責任者の権限設定（カスタマイズ可能）
  if (role_id === 2) {
    return (
      <div className="space-y-6">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            勤務実績の閲覧権限 <span className="text-red-500">*</span>
          </label>
          <div className="space-y-2">
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="attendanceViewPermission"
                value="department"
                checked={data.attendance_view_permission === 'department'}
                onChange={(e) => setData('attendance_view_permission', e.target.value as 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">所属部署のみ</span>
                <p className="text-xs text-gray-500 mt-1">
                  所属する部署のメンバーの勤務実績のみ閲覧できます
                </p>
              </div>
            </label>
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="attendanceViewPermission"
                value="company"
                checked={data.attendance_view_permission === 'company'}
                onChange={(e) => setData('attendance_view_permission', e.target.value as 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">会社全員</span>
                <p className="text-xs text-gray-500 mt-1">
                  会社全員の勤務実績を閲覧できます
                </p>
              </div>
            </label>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            申請承認と取り消し権限 <span className="text-red-500">*</span>
          </label>
          <div className="space-y-2">
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="approvalPermission"
                value="department"
                checked={data.approval_permission === 'department'}
                onChange={(e) => setData('approval_permission', e.target.value as 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">所属部署のみ</span>
                <p className="text-xs text-gray-500 mt-1">
                  所属する部署のメンバーの申請のみ承認・取り消しができます
                </p>
              </div>
            </label>
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="approvalPermission"
                value="company"
                checked={data.approval_permission === 'company'}
                onChange={(e) => setData('approval_permission', e.target.value as 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">会社全員</span>
                <p className="text-xs text-gray-500 mt-1">
                  会社全員の申請を承認・取り消しができます
                </p>
              </div>
            </label>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            シフト閲覧権限 <span className="text-red-500">*</span>
          </label>
          <div className="space-y-2">
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="shiftViewPermission"
                value="department"
                checked={data.shift_view_permission === 'department'}
                onChange={(e) => setData('shift_view_permission', e.target.value as 'self' | 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">所属部署のみ</span>
                <p className="text-xs text-gray-500 mt-1">
                  所属する部署のメンバーのシフトのみ閲覧できます
                </p>
              </div>
            </label>
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="shiftViewPermission"
                value="company"
                checked={data.shift_view_permission === 'company'}
                onChange={(e) => setData('shift_view_permission', e.target.value as 'self' | 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">会社全員</span>
                <p className="text-xs text-gray-500 mt-1">
                  会社全員のシフトを閲覧できます
                </p>
              </div>
            </label>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            シフト入力と修正権限 <span className="text-red-500">*</span>
          </label>
          <div className="space-y-2">
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="shiftEditPermission"
                value="department"
                checked={data.shift_edit_permission === 'department'}
                onChange={(e) => setData('shift_edit_permission', e.target.value as 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">所属部署のみ</span>
                <p className="text-xs text-gray-500 mt-1">
                  所属する部署のメンバーのシフトのみ入力・修正できます
                </p>
              </div>
            </label>
            <label className="flex items-start cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition-colors">
              <input
                type="radio"
                name="shiftEditPermission"
                value="company"
                checked={data.shift_edit_permission === 'company'}
                onChange={(e) => setData('shift_edit_permission', e.target.value as 'department' | 'company')}
                className="mt-1 mr-3"
                disabled={processing}
              />
              <div>
                <span className="text-sm font-medium text-gray-700">会社全員</span>
                <p className="text-xs text-gray-500 mt-1">
                  会社全員のシフトを入力・修正できます
                </p>
              </div>
            </label>
          </div>
        </div>
      </div>
    );
  }

  return null;
}
