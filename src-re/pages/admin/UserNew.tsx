
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { mockDepartments, shiftTypeInfo } from '@/lib/mockData';
import { ShiftType } from '@/types';

export default function NewUserPage() {
  const navigate = useNavigate();

  // 会社の定休日設定（実際にはAPIや設定から取得）
  const companyClosedDays = {
    sunday: true,
    monday: false,
    tuesday: false,
    wednesday: false,
    thursday: false,
    friday: false,
    saturday: false,
  };

  const [newUser, setNewUser] = useState({
    name: '',
    nameKana: '',
    employeeCode: '',
    email: '',
    role: 'employee' as 'admin' | 'manager' | 'employee',
    department: '',
    availableWorkDays: {
      sunday: true,
      monday: true,
      tuesday: true,
      wednesday: true,
      thursday: true,
      friday: true,
      saturday: true,
    },
    shiftPatterns: {
      sunday: 'early' as ShiftType | undefined,
      monday: 'early' as ShiftType | undefined,
      tuesday: 'early' as ShiftType | undefined,
      wednesday: 'early' as ShiftType | undefined,
      thursday: 'early' as ShiftType | undefined,
      friday: 'early' as ShiftType | undefined,
      saturday: 'early' as ShiftType | undefined,
    },
    workOnHolidays: false,
    shiftViewPermission: 'department' as 'self' | 'department' | 'company',
    attendanceViewPermission: 'self' as 'self' | 'department' | 'company',
    approvalPermission: 'department' as 'department' | 'company',
    shiftEditPermission: 'department' as 'department' | 'company',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    console.log('ユーザー新規作成:', newUser);
    // TODO: API呼び出しを実装
    navigate('/admin/users');
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center space-x-4">
        <button
          onClick={() => navigate(-1)}
          className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </button>
        <h1 className="text-2xl font-bold text-gray-900">新規ユーザー作成</h1>
      </div>

      <div className="bg-white shadow rounded-lg p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              氏名 <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={newUser.name}
              onChange={(e) => setNewUser({ ...newUser, name: e.target.value })}
              className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="山田太郎"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              フリガナ
            </label>
            <input
              type="text"
              value={newUser.nameKana}
              onChange={(e) => setNewUser({ ...newUser, nameKana: e.target.value })}
              className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="ヤマダタロウ"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                個人コード（6桁）
              </label>
              <input
                type="text"
                value={newUser.employeeCode}
                onChange={(e) => setNewUser({ ...newUser, employeeCode: e.target.value })}
                className="w-full border border-gray-300 rounded-md p-2 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="000001"
                pattern="[0-9]{6}"
                maxLength={6}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                メールアドレス <span className="text-red-500">*</span>
              </label>
              <input
                type="email"
                value={newUser.email}
                onChange={(e) => setNewUser({ ...newUser, email: e.target.value })}
                className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="yamada@company.com"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                権限 <span className="text-red-500">*</span>
              </label>
              <select
                value={newUser.role}
                onChange={(e) => {
                  const newRole = e.target.value as 'admin' | 'manager' | 'employee';
                  // 権限変更時にデフォルト値を設定
                  const defaultAttendance = newRole === 'employee' ? 'self' : 'department';
                  setNewUser({
                    ...newUser,
                    role: newRole,
                    shiftViewPermission: 'department',
                    attendanceViewPermission: defaultAttendance
                  });
                }}
                className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="employee">一般</option>
                <option value="manager">責任者</option>
                <option value="admin">管理者</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                部署 <span className="text-red-500">*</span>
              </label>
              <select
                value={newUser.department}
                onChange={(e) => setNewUser({ ...newUser, department: e.target.value })}
                className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="">選択してください</option>
                {mockDepartments.map(dept => (
                  <option key={dept.id} value={dept.name}>{dept.name}</option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-3">
              勤務日とシフトパターン
            </label>
            <div className="grid grid-cols-7 gap-2">
              {[
                { key: 'sunday', label: '日', color: 'text-red-600' },
                { key: 'monday', label: '月', color: 'text-gray-700' },
                { key: 'tuesday', label: '火', color: 'text-gray-700' },
                { key: 'wednesday', label: '水', color: 'text-gray-700' },
                { key: 'thursday', label: '木', color: 'text-gray-700' },
                { key: 'friday', label: '金', color: 'text-gray-700' },
                { key: 'saturday', label: '土', color: 'text-blue-600' },
              ].map((day) => {
                const selectedShift = shiftTypeInfo.find(s => s.type === newUser.shiftPatterns[day.key as keyof typeof newUser.shiftPatterns]);
                const isCompanyClosedDay = companyClosedDays[day.key as keyof typeof companyClosedDays];
                return (
                  <div key={day.key} className="flex flex-col space-y-2">
                    <label
                      className={`flex flex-col items-center p-3 border rounded-lg transition-colors ${
                        isCompanyClosedDay
                          ? 'bg-gray-100 border-gray-300 cursor-not-allowed'
                          : newUser.availableWorkDays[day.key as keyof typeof newUser.availableWorkDays]
                          ? 'bg-blue-50 border-blue-500 cursor-pointer'
                          : 'bg-white border-gray-300 hover:bg-gray-50 cursor-pointer'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={newUser.availableWorkDays[day.key as keyof typeof newUser.availableWorkDays]}
                        onChange={() => setNewUser({
                          ...newUser,
                          availableWorkDays: {
                            ...newUser.availableWorkDays,
                            [day.key]: !newUser.availableWorkDays[day.key as keyof typeof newUser.availableWorkDays]
                          }
                        })}
                        disabled={isCompanyClosedDay}
                        className="mb-2"
                      />
                      <span className={`text-sm font-medium ${isCompanyClosedDay ? 'text-gray-400' : day.color}`}>{day.label}</span>
                    </label>
                    <select
                      value={newUser.shiftPatterns[day.key as keyof typeof newUser.shiftPatterns] || ''}
                      onChange={(e) => setNewUser({
                        ...newUser,
                        shiftPatterns: {
                          ...newUser.shiftPatterns,
                          [day.key]: e.target.value || undefined
                        }
                      })}
                      disabled={isCompanyClosedDay || !newUser.availableWorkDays[day.key as keyof typeof newUser.availableWorkDays]}
                      className={`w-full border border-gray-300 rounded-md p-3 text-base font-medium disabled:bg-gray-100 disabled:text-gray-400 ${selectedShift ? selectedShift.color + ' ' + selectedShift.textColor : ''}`}
                    >
                      <option value="">選択</option>
                      {shiftTypeInfo.map((shift) => (
                        <option key={shift.type} value={shift.type}>
                          {shift.name}
                        </option>
                      ))}
                    </select>
                  </div>
                );
              })}
            </div>
            <p className="text-xs text-gray-500 mt-2">
              勤務可能な曜日にチェックを入れ、各曜日のシフトパターンを選択してください
            </p>
          </div>

          <div>
            <label className="flex items-start cursor-pointer">
              <input
                type="checkbox"
                checked={newUser.workOnHolidays}
                onChange={(e) => setNewUser({ ...newUser, workOnHolidays: e.target.checked })}
                className="mt-1 mr-3 h-4 w-4"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">祝日も勤務する</span>
                <p className="text-xs text-gray-500 mt-1">
                  チェックを入れると、祝日も勤務日として扱われます
                </p>
              </div>
            </label>
          </div>

          {newUser.role === 'employee' && (
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
                      checked={newUser.shiftViewPermission === 'self'}
                      onChange={(e) => setNewUser({ ...newUser, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.shiftViewPermission === 'department'}
                      onChange={(e) => setNewUser({ ...newUser, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                        一般ユーザーは自分の勤務実績のみ閲覧できます
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {newUser.role === 'admin' && (
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
          )}

          {newUser.role === 'manager' && (
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
                      checked={newUser.attendanceViewPermission === 'department'}
                      onChange={(e) => setNewUser({ ...newUser, attendanceViewPermission: e.target.value as 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.attendanceViewPermission === 'company'}
                      onChange={(e) => setNewUser({ ...newUser, attendanceViewPermission: e.target.value as 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.approvalPermission === 'department'}
                      onChange={(e) => setNewUser({ ...newUser, approvalPermission: e.target.value as 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.approvalPermission === 'company'}
                      onChange={(e) => setNewUser({ ...newUser, approvalPermission: e.target.value as 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.shiftViewPermission === 'department'}
                      onChange={(e) => setNewUser({ ...newUser, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.shiftViewPermission === 'company'}
                      onChange={(e) => setNewUser({ ...newUser, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.shiftEditPermission === 'department'}
                      onChange={(e) => setNewUser({ ...newUser, shiftEditPermission: e.target.value as 'department' | 'company' })}
                      className="mt-1 mr-3"
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
                      checked={newUser.shiftEditPermission === 'company'}
                      onChange={(e) => setNewUser({ ...newUser, shiftEditPermission: e.target.value as 'department' | 'company' })}
                      className="mt-1 mr-3"
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
          )}

          <div className="flex space-x-3 pt-4 border-t">
            <button
              type="button"
              onClick={() => navigate(-1)}
              className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md transition-colors"
            >
              キャンセル
            </button>
            <button
              type="submit"
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition-colors"
            >
              作成
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
