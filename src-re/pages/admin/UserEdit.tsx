
import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { mockUsers, mockDepartments, shiftTypeInfo } from '@/lib/mockData';
import { ShiftType } from '@/types';

export default function EditUserPage() {
  const navigate = useNavigate();
  const params = useParams();
  const userId = params.id as string;

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

  const [user, setUser] = useState({
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
    isRetired: false,
    retirementDate: '',
  });

  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    // TODO: APIからユーザー情報を取得
    const foundUser = mockUsers.find(u => u.id === userId);

    if (foundUser) {
      setUser({
        name: foundUser.name,
        nameKana: foundUser.nameKana || '',
        employeeCode: foundUser.employeeCode || '',
        email: foundUser.email,
        role: foundUser.role,
        department: foundUser.department,
        availableWorkDays: foundUser.availableWorkDays || {
          sunday: true,
          monday: true,
          tuesday: true,
          wednesday: true,
          thursday: true,
          friday: true,
          saturday: true,
        },
        shiftPatterns: {
          sunday: foundUser.shiftPatterns?.sunday || 'early',
          monday: foundUser.shiftPatterns?.monday || 'early',
          tuesday: foundUser.shiftPatterns?.tuesday || 'early',
          wednesday: foundUser.shiftPatterns?.wednesday || 'early',
          thursday: foundUser.shiftPatterns?.thursday || 'early',
          friday: foundUser.shiftPatterns?.friday || 'early',
          saturday: foundUser.shiftPatterns?.saturday || 'early',
        },
        workOnHolidays: foundUser.workOnHolidays || false,
        shiftViewPermission: foundUser.shiftViewPermission || 'department',
        attendanceViewPermission: foundUser.attendanceViewPermission || (foundUser.role === 'employee' ? 'self' : 'department'),
        approvalPermission: foundUser.approvalPermission || 'department',
        shiftEditPermission: foundUser.shiftEditPermission || 'department',
        isRetired: foundUser.isRetired || false,
        retirementDate: foundUser.retirementDate || '',
      });
      setLoading(false);
    } else {
      setNotFound(true);
      setLoading(false);
    }
  }, [userId]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    console.log('ユーザー更新:', userId, user);
    // TODO: API呼び出しを実装
    navigate('/admin/users');
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-600">読み込み中...</div>
      </div>
    );
  }

  if (notFound) {
    return (
      <div className="space-y-6">
        <div className="flex items-center space-x-4">
          <button
            onClick={() => navigate(-1)}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ArrowLeft className="h-5 w-5 text-gray-600" />
          </button>
          <h1 className="text-2xl font-bold text-gray-900">ユーザーが見つかりません</h1>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <p className="text-gray-600">指定されたユーザーは存在しません。</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center space-x-4">
        <button
          onClick={() => navigate(-1)}
          className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </button>
        <h1 className="text-2xl font-bold text-gray-900">ユーザー編集</h1>
      </div>

      <div className="bg-white shadow rounded-lg p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              氏名 <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={user.name}
              onChange={(e) => setUser({ ...user, name: e.target.value })}
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
              value={user.nameKana}
              onChange={(e) => setUser({ ...user, nameKana: e.target.value })}
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
                value={user.employeeCode}
                onChange={(e) => setUser({ ...user, employeeCode: e.target.value })}
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
                value={user.email}
                onChange={(e) => setUser({ ...user, email: e.target.value })}
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
                value={user.role}
                onChange={(e) => {
                  const newRole = e.target.value as 'admin' | 'manager' | 'employee';
                  // 権限変更時にデフォルト値を設定
                  const defaultAttendance = newRole === 'employee' ? 'self' : 'department';
                  setUser({
                    ...user,
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
                value={user.department}
                onChange={(e) => setUser({ ...user, department: e.target.value })}
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

          {/* 退職日 */}
          <div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                退職日
              </label>
              <input
                type="date"
                value={user.retirementDate}
                onChange={(e) => {
                  const date = e.target.value;
                  const today = new Date().toISOString().split('T')[0];
                  const isRetired = !!(date && date <= today);
                  setUser({ ...user, retirementDate: date, isRetired });
                }}
                className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <p className="text-xs text-gray-500 mt-1">
                退職日を設定すると、該当日以降のシフトに影響します
              </p>
            </div>

            {user.isRetired && (
              <div className="bg-red-50 border-l-4 border-red-500 p-3 rounded mt-3">
                <p className="text-sm text-red-800">
                  <strong>注意:</strong> 退職済みとしてマークされています。このユーザーは一覧で区別して表示されます。
                </p>
              </div>
            )}
          </div>

          <div className="border-t pt-6">
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
                const selectedShift = shiftTypeInfo.find(s => s.type === user.shiftPatterns[day.key as keyof typeof user.shiftPatterns]);
                const isCompanyClosedDay = companyClosedDays[day.key as keyof typeof companyClosedDays];
                return (
                  <div key={day.key} className="flex flex-col space-y-2">
                    <label
                      className={`flex flex-col items-center p-3 border rounded-lg transition-colors ${
                        isCompanyClosedDay
                          ? 'bg-gray-100 border-gray-300 cursor-not-allowed'
                          : user.availableWorkDays[day.key as keyof typeof user.availableWorkDays]
                          ? 'bg-blue-50 border-blue-500 cursor-pointer'
                          : 'bg-white border-gray-300 hover:bg-gray-50 cursor-pointer'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={user.availableWorkDays[day.key as keyof typeof user.availableWorkDays]}
                        onChange={() => setUser({
                          ...user,
                          availableWorkDays: {
                            ...user.availableWorkDays,
                            [day.key]: !user.availableWorkDays[day.key as keyof typeof user.availableWorkDays]
                          }
                        })}
                        disabled={isCompanyClosedDay}
                        className="mb-2"
                      />
                      <span className={`text-sm font-medium ${isCompanyClosedDay ? 'text-gray-400' : day.color}`}>{day.label}</span>
                    </label>
                    <select
                      value={user.shiftPatterns[day.key as keyof typeof user.shiftPatterns] || ''}
                      onChange={(e) => setUser({
                        ...user,
                        shiftPatterns: {
                          ...user.shiftPatterns,
                          [day.key]: e.target.value || undefined
                        }
                      })}
                      disabled={isCompanyClosedDay || !user.availableWorkDays[day.key as keyof typeof user.availableWorkDays]}
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
                checked={user.workOnHolidays}
                onChange={(e) => setUser({ ...user, workOnHolidays: e.target.checked })}
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

          {user.role === 'employee' && (
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
                      checked={user.shiftViewPermission === 'self'}
                      onChange={(e) => setUser({ ...user, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
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
                      checked={user.shiftViewPermission === 'department'}
                      onChange={(e) => setUser({ ...user, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
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

          {user.role === 'admin' && (
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

          {user.role === 'manager' && (
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
                      checked={user.attendanceViewPermission === 'department'}
                      onChange={(e) => setUser({ ...user, attendanceViewPermission: e.target.value as 'department' | 'company' })}
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
                      checked={user.attendanceViewPermission === 'company'}
                      onChange={(e) => setUser({ ...user, attendanceViewPermission: e.target.value as 'department' | 'company' })}
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
                      checked={user.approvalPermission === 'department'}
                      onChange={(e) => setUser({ ...user, approvalPermission: e.target.value as 'department' | 'company' })}
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
                      checked={user.approvalPermission === 'company'}
                      onChange={(e) => setUser({ ...user, approvalPermission: e.target.value as 'department' | 'company' })}
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
                      checked={user.shiftViewPermission === 'department'}
                      onChange={(e) => setUser({ ...user, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
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
                      checked={user.shiftViewPermission === 'company'}
                      onChange={(e) => setUser({ ...user, shiftViewPermission: e.target.value as 'self' | 'department' | 'company' })}
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
                      checked={user.shiftEditPermission === 'department'}
                      onChange={(e) => setUser({ ...user, shiftEditPermission: e.target.value as 'department' | 'company' })}
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
                      checked={user.shiftEditPermission === 'company'}
                      onChange={(e) => setUser({ ...user, shiftEditPermission: e.target.value as 'department' | 'company' })}
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
              更新
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
