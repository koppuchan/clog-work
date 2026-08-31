import { Role, DepartmentData as Department, ShiftPattern } from '@/types';

/**
 * ユーザー確認画面のプロップス
 */
interface UserConfirmViewProps {
  data: {
    name: string;
    name_kana: string;
    employee_code: string;
    email: string;
    department_id: number | undefined;
    role_id: number | undefined;
    shift_patterns: {
      sunday: number | undefined;
      monday: number | undefined;
      tuesday: number | undefined;
      wednesday: number | undefined;
      thursday: number | undefined;
      friday: number | undefined;
      saturday: number | undefined;
    };
    stamp_password?: string;
    shift_view_permission?: 'self' | 'department' | 'company';
    attendance_view_permission?: 'self' | 'department' | 'company';
    approval_permission?: 'department' | 'company';
    shift_edit_permission?: 'department' | 'company';
  };
  roles: Role[];
  departments: Department[];
  shiftPatterns: ShiftPattern[];
  validatedData?: any;
  processing: boolean;
  onBack: () => void;
  onConfirm: () => void;
  isEditMode?: boolean;
}

/**
 * ユーザー確認画面コンポーネント
 *
 * ユーザー作成・編集の最終確認画面：
 * - 入力された全ての情報を読み取り専用で表示
 * - 基本情報（氏名、フリガナ、個人コード、メールアドレス）
 * - 役割と部署
 * - 各曜日のシフトパターン（色付きバッジ表示）
 * - 各種権限設定（シフト閲覧、勤務実績閲覧、申請承認、シフト編集）
 * - 初期パスワード（新規作成時のみ）
 *
 * 操作：
 * - 「戻る」ボタン: 入力画面へ戻る
 * - 「作成」/「更新」ボタン: サーバーへデータを送信
 */
export default function UserConfirmView({
  data,
  roles,
  departments,
  shiftPatterns,
  validatedData,
  processing,
  onBack,
  onConfirm,
  isEditMode = false,
}: UserConfirmViewProps) {
  // 曜日の定義（完全表記）
  const days = [
    { key: 'sunday', label: '日曜日' },
    { key: 'monday', label: '月曜日' },
    { key: 'tuesday', label: '火曜日' },
    { key: 'wednesday', label: '水曜日' },
    { key: 'thursday', label: '木曜日' },
    { key: 'friday', label: '金曜日' },
    { key: 'saturday', label: '土曜日' },
  ] as const;

  // 権限スコープのラベル
  const permissionScopeLabels = {
    self: '自分のみ',
    department: '部署',
    company: '全社',
  };

  return (
    <div className="bg-white shadow rounded-lg p-6">
      <div className="space-y-6">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <p className="text-sm font-medium text-gray-700">
            以下の内容でユーザーを{isEditMode ? '更新' : '作成'}します。内容を確認してください。
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-500 mb-1">氏名</label>
            <p className="text-base text-gray-900">{data.name || '未入力'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-500 mb-1">フリガナ</label>
            <p className="text-base text-gray-900">{data.name_kana || '未入力'}</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-500 mb-1">個人コード</label>
            <p className="text-base text-gray-900 font-mono">{data.employee_code || '未入力'}</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-500 mb-1">メールアドレス</label>
            <p className="text-base text-gray-900">{data.email || '未入力'}</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-500 mb-1">部署</label>
            <p className="text-base text-gray-900">
              {data.department_id ? departments.find(d => d.id === data.department_id)?.name : '未入力'}
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-500 mb-1">役割</label>
            <p className="text-base text-gray-900">
              {data.role_id ? roles.find(r => r.id === data.role_id)?.name : '未選択'}
            </p>
          </div>
        </div>

        {!isEditMode && data.email && (
          <div className="bg-blue-50 border border-blue-300 rounded-lg p-4">
            <p className="text-sm text-blue-800">
              登録後、入力されたメールアドレス宛にログイン情報（会社コード・個人コード・初期パスワード）が送信されます。
            </p>
          </div>
        )}

        {!isEditMode && !data.email && validatedData?.initial_password_display && (
          <div className="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4">
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-semibold text-gray-700">初期パスワード</label>
                <p className="text-2xl font-mono font-bold text-gray-900 tracking-wider">
                  {validatedData.initial_password_display}
                </p>
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700">打刻パスワード（初期値）</label>
                <p className="text-2xl font-mono font-bold text-gray-900 tracking-wider">1111</p>
              </div>
              <p className="text-xs text-gray-600">
                ※ メールアドレスが未入力のため、上記のパスワードをユーザーに直接お伝えください。
              </p>
            </div>
          </div>
        )}

        {isEditMode && data.stamp_password && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-500 mb-1">打刻パスワード</label>
              <p className="text-base text-gray-900">****（変更あり）</p>
            </div>
          </div>
        )}

        <div>
          <label className="block text-sm font-medium text-gray-500 mb-2">シフトパターン</label>
          <div className="space-y-2">
            {days.map(day => {
              const shiftId = data.shift_patterns[day.key as keyof typeof data.shift_patterns];
              const shift = shiftId ? shiftPatterns.find(s => s.id === shiftId) : null;
              return (
                <div key={day.key} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                  <span className="text-sm text-gray-700">{day.label}</span>
                  <span className={`px-3 py-1 rounded text-sm font-medium ${shift ? `${shift.background_color} ${shift.text_color}` : 'bg-gray-200 text-gray-600'}`}>
                    {shift?.name || '休日'}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-500 mb-3">閲覧・編集権限</label>
          {data.role_id === 1 ? (
            <div className="space-y-3">
              {['シフト閲覧権限', '勤務実績閲覧権限', '申請承認権限', 'シフト編集権限'].map((label) => (
                <div key={label} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <span className="text-sm font-medium text-gray-700">{label}</span>
                  <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm font-medium">
                    全員
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="space-y-3">
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-sm font-medium text-gray-700">シフト閲覧権限</span>
                <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm font-medium">
                  {data.shift_view_permission ? permissionScopeLabels[data.shift_view_permission] : '未設定'}
                </span>
              </div>
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-sm font-medium text-gray-700">勤務実績閲覧権限</span>
                <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-md text-sm font-medium">
                  {data.attendance_view_permission ? permissionScopeLabels[data.attendance_view_permission] : '未設定'}
                </span>
              </div>
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-sm font-medium text-gray-700">申請承認権限</span>
                <span className="px-3 py-1 bg-green-100 text-green-800 rounded-md text-sm font-medium">
                  {data.approval_permission ? permissionScopeLabels[data.approval_permission] : '未設定'}
                </span>
              </div>
              <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-sm font-medium text-gray-700">シフト編集権限</span>
                <span className="px-3 py-1 bg-purple-100 text-purple-800 rounded-md text-sm font-medium">
                  {data.shift_edit_permission ? permissionScopeLabels[data.shift_edit_permission] : '未設定'}
                </span>
              </div>
            </div>
          )}
        </div>

        <div className="flex space-x-3 pt-4 border-t">
          <button
            type="button"
            onClick={onBack}
            className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md transition-colors"
            disabled={processing}
          >
            戻る
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition-colors disabled:opacity-50"
            disabled={processing}
          >
            {processing ? (isEditMode ? '更新中...' : '作成中...') : (isEditMode ? '更新' : '作成')}
          </button>
        </div>
      </div>
    </div>
  );
}
