
import { useState } from 'react';
import { Settings, Save, Plus, Edit, Trash2, Clock, Building, AlertTriangle } from 'lucide-react';
import { shiftTypeInfo, mockDepartments } from '@/lib/mockData';

export default function SettingsPage() {
  const [settings, setSettings] = useState({
    companyName: '株式会社サンプル',
    payrollClosingDay: '25',
    shiftDisplayPeriod: 'monthly', // 'monthly' or 'custom'
    clockRounding: 'none', // 'none', '5min', '10min', '15min', '30min'
    paidLeaveHalfDay: false, // 半日単位の有給を付与
    paidLeaveHourly: false, // 時間単位の有給を付与
    dailyWorkingHours: '8', // 1日の所定労働時間
    // 労務アラート設定
    alertOvertimeNotification: '45', // 残業時間の通知時間（時間/月）
    alertOvertimeLimit: '80', // 残業時間の限度時間（時間/月）
    alertConsecutiveWorkDays: '6', // 連続勤務日数の警告閾値（日）
    // アラートメッセージ設定
    alertMessages: {
      warningTitle: '【警告】残業時間超過',
      warningMessage: '今月の残業時間が{hours}時間に達しています。残業時間が非常に多くなっています。健康管理に十分注意してください。',
      cautionTitle: '【注意】残業時間が多くなっています',
      cautionMessage: '今月の残業時間が{hours}時間に達しています。残業時間が多くなっています。体調管理に注意してください。',
    },
  });

  const [weekendDays, setWeekendDays] = useState({
    sunday: true,
    monday: false,
    tuesday: false,
    wednesday: false,
    thursday: false,
    friday: false,
    saturday: false,
  });

  const [includeNationalHolidays, setIncludeNationalHolidays] = useState(true);

  const [departments, setDepartments] = useState(mockDepartments);
  const [showDepartmentForm, setShowDepartmentForm] = useState(false);
  const [editingDepartment, setEditingDepartment] = useState<string | null>(null);
  const [newDepartment, setNewDepartment] = useState({
    id: '',
    name: ''
  });

  const [shiftPatterns, setShiftPatterns] = useState(shiftTypeInfo);
  const [showShiftForm, setShowShiftForm] = useState(false);
  const [editingShift, setEditingShift] = useState<string | null>(null);
  const [newShift, setNewShift] = useState({
    type: '',
    name: '',
    timeRange: '',
    color: 'bg-gray-100',
    textColor: 'text-gray-800'
  });
  const [shiftBreakMode, setShiftBreakMode] = useState<'duration' | 'timeRange'>('duration');
  const [shiftBreakDuration, setShiftBreakDuration] = useState('60');
  const [shiftBreakStart, setShiftBreakStart] = useState('12:00');
  const [shiftBreakEnd, setShiftBreakEnd] = useState('13:00');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    console.log('設定を保存:', {
      ...settings,
      weekendDays,
      includeNationalHolidays,
    });
    alert('設定を保存しました');
  };

  const handleShiftSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // 始業・終業時刻からtimeRangeを生成
    const startTime = (document.getElementById('shiftStartTime') as HTMLInputElement).value;
    const endTime = (document.getElementById('shiftEndTime') as HTMLInputElement).value;
    const timeRange = `${startTime}-${endTime}`;

    const shiftData = {
      ...newShift,
      timeRange,
      type: newShift.type as 'early' | 'day' | 'late' | 'night' | 'off-duty' | 'rest'
    };

    if (editingShift) {
      // 編集
      setShiftPatterns(shiftPatterns.map(shift =>
        shift.type === editingShift ? shiftData : shift
      ));
      alert('シフトパターンを更新しました');
    } else {
      // 新規追加
      setShiftPatterns([...shiftPatterns, shiftData]);
      alert('シフトパターンを追加しました');
    }
    setShowShiftForm(false);
    setEditingShift(null);
    setNewShift({
      type: '',
      name: '',
      timeRange: '',
      color: 'bg-gray-100',
      textColor: 'text-gray-800'
    });
    setShiftBreakMode('duration');
    setShiftBreakDuration('60');
    setShiftBreakStart('12:00');
    setShiftBreakEnd('13:00');
  };

  const handleEditShift = (type: string) => {
    const shift = shiftPatterns.find(s => s.type === type);
    if (shift) {
      setNewShift({ ...shift });
      setEditingShift(type);
      setShowShiftForm(true);

      // timeRangeから開始・終了時刻を抽出
      const times = shift.timeRange.split('-');
      if (times.length === 2) {
        setTimeout(() => {
          const startInput = document.getElementById('shiftStartTime') as HTMLInputElement;
          const endInput = document.getElementById('shiftEndTime') as HTMLInputElement;
          if (startInput) startInput.value = times[0];
          if (endInput) endInput.value = times[1];
        }, 0);
      }
    }
  };

  const handleDeleteShift = (type: string) => {
    if (confirm('このシフトパターンを削除しますか？')) {
      setShiftPatterns(shiftPatterns.filter(shift => shift.type !== type));
      alert('シフトパターンを削除しました');
    }
  };

  const handleWeekendChange = (day: keyof typeof weekendDays) => {
    setWeekendDays({ ...weekendDays, [day]: !weekendDays[day] });
  };

  const handleDepartmentSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingDepartment) {
      // 編集
      setDepartments(departments.map(dept =>
        dept.id === editingDepartment ? { ...dept, name: newDepartment.name } : dept
      ));
      alert('部署を更新しました');
    } else {
      // 新規追加 - IDを自動生成
      const newId = `dept-${Date.now()}`;
      setDepartments([...departments, { id: newId, name: newDepartment.name, managerId: '1' }]);
      alert('部署を追加しました');
    }
    setShowDepartmentForm(false);
    setEditingDepartment(null);
    setNewDepartment({ id: '', name: '' });
  };

  const handleEditDepartment = (id: string) => {
    const dept = departments.find(d => d.id === id);
    if (dept) {
      setNewDepartment({ ...dept });
      setEditingDepartment(id);
      setShowDepartmentForm(true);
    }
  };

  const handleDeleteDepartment = (id: string) => {
    if (confirm('この部署を削除しますか？')) {
      setDepartments(departments.filter(dept => dept.id !== id));
      alert('部署を削除しました');
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">システム設定</h1>
      </div>

      {/* 会社設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <Settings className="h-5 w-5 mr-2 text-blue-600" />
          会社設定
        </h3>
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                会社名
              </label>
              <input
                type="text"
                value={settings.companyName}
                onChange={(e) => setSettings({ ...settings, companyName: e.target.value })}
                className="w-full border border-gray-300 rounded-md p-2"
              />
            </div>
          </div>

          {/* 休日設定（曜日） */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-3">定休日設定</label>
            <div className="grid grid-cols-7 gap-2">
              {[
                { key: 'sunday', label: '日', color: 'text-red-600' },
                { key: 'monday', label: '月', color: 'text-gray-700' },
                { key: 'tuesday', label: '火', color: 'text-gray-700' },
                { key: 'wednesday', label: '水', color: 'text-gray-700' },
                { key: 'thursday', label: '木', color: 'text-gray-700' },
                { key: 'friday', label: '金', color: 'text-gray-700' },
                { key: 'saturday', label: '土', color: 'text-blue-600' },
              ].map((day) => (
                <label
                  key={day.key}
                  className={`flex flex-col items-center p-3 border rounded-lg cursor-pointer transition-colors ${
                    weekendDays[day.key as keyof typeof weekendDays]
                      ? 'bg-blue-50 border-blue-500'
                      : 'bg-white border-gray-300 hover:bg-gray-50'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={weekendDays[day.key as keyof typeof weekendDays]}
                    onChange={() => handleWeekendChange(day.key as keyof typeof weekendDays)}
                    className="mb-2"
                  />
                  <span className={`text-sm font-medium ${day.color}`}>{day.label}</span>
                </label>
              ))}
            </div>
            <p className="text-xs text-gray-500 mt-2">
              定休日として設定する曜日にチェックを入れてください
            </p>
          </div>

          {/* 祝日設定 */}
          <div className="pt-4 border-t">
            <label className="flex items-start cursor-pointer">
              <input
                type="checkbox"
                checked={includeNationalHolidays}
                onChange={(e) => setIncludeNationalHolidays(e.target.checked)}
                className="mt-1 mr-3 h-4 w-4"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">祝日を定休日に含める</span>
                <p className="text-xs text-gray-500 mt-1">
                  チェックを入れると、日本の国民の祝日が自動的に定休日として扱われます
                </p>
              </div>
            </label>
          </div>
        </div>

      </div>

      {/* 部署設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-lg font-semibold text-gray-900 flex items-center">
            <Building className="h-5 w-5 mr-2 text-blue-600" />
            部署設定
          </h3>
          <button
            type="button"
            onClick={() => setShowDepartmentForm(true)}
            className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg flex items-center space-x-1 text-sm"
          >
            <Plus className="h-3 w-3" />
            <span>追加</span>
          </button>
        </div>
        <div className="space-y-3">
          {departments.map((dept) => (
            <div key={dept.id} className="border rounded-lg p-4 flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <span className="px-3 py-1 rounded font-medium text-sm bg-gray-100 text-gray-800">
                  {dept.name}
                </span>
              </div>
              <div className="flex space-x-2">
                <button
                  type="button"
                  onClick={() => handleEditDepartment(dept.id)}
                  className="text-blue-600 hover:text-blue-900 p-1"
                >
                  <Edit className="h-4 w-4" />
                </button>
                <button
                  type="button"
                  onClick={() => handleDeleteDepartment(dept.id)}
                  className="text-red-600 hover:text-red-900 p-1"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 給与締切日設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <Clock className="h-5 w-5 mr-2 text-blue-600" />
          給与締切日設定
        </h3>
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                締切日
              </label>
              <div className="flex items-start space-x-2">
                <span className="text-sm text-gray-700 pt-2">毎月</span>
                <select
                  value={settings.payrollClosingDay}
                  onChange={(e) => setSettings({ ...settings, payrollClosingDay: e.target.value })}
                  className="border border-gray-300 rounded-md p-2 text-gray-900"
                  size={1}
                >
                  {Array.from({ length: 31 }, (_, i) => i + 1).map(day => (
                    <option key={day} value={day}>{day}</option>
                  ))}
                  <option value="end">末日</option>
                </select>
                <span className="text-sm text-gray-700 pt-2">日</span>
              </div>
              <p className="text-xs text-gray-500 mt-2">
                給与計算の締切日を設定してください。末日を選択した場合は月の最終日が締切日となります。
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* シフト表表示期間設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <Clock className="h-5 w-5 mr-2 text-blue-600" />
          シフト表表示期間設定
        </h3>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              表示期間
            </label>
            <div className="space-y-3">
              <label className="flex items-center cursor-pointer">
                <input
                  type="radio"
                  value="monthly"
                  checked={settings.shiftDisplayPeriod === 'monthly'}
                  onChange={(e) => setSettings({ ...settings, shiftDisplayPeriod: e.target.value as 'monthly' | 'custom' })}
                  className="mr-3"
                />
                <span className="text-sm text-gray-700">月初〜月末（1日〜30/31日）</span>
              </label>
              <label className="flex items-center cursor-pointer">
                <input
                  type="radio"
                  value="custom"
                  checked={settings.shiftDisplayPeriod === 'custom'}
                  onChange={(e) => setSettings({ ...settings, shiftDisplayPeriod: e.target.value as 'monthly' | 'custom' })}
                  className="mr-3"
                />
                <span className="text-sm text-gray-700">前月21日〜当月20日</span>
              </label>
            </div>
            <p className="text-xs text-gray-500 mt-2">
              シフト表の表示期間を設定してください。
            </p>
          </div>

          {/* 表示期間の例示 */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p className="text-sm font-medium text-gray-700 mb-2">表示期間の例</p>
            <ul className="text-sm text-gray-600 space-y-1">
              <li>• 月初〜月末: 1月1日〜1月31日、2月1日〜2月28日</li>
              <li>• 前月21日〜当月20日: 12月21日〜1月20日、1月21日〜2月20日</li>
            </ul>
          </div>
        </div>
      </div>

      {/* 勤務時間（シフトパターン）設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-lg font-semibold text-gray-900 flex items-center">
            <Clock className="h-5 w-5 mr-2 text-blue-600" />
            勤務時間（シフトパターン）設定
          </h3>
          <button
            type="button"
            onClick={() => setShowShiftForm(true)}
            className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg flex items-center space-x-1 text-sm"
          >
            <Plus className="h-3 w-3" />
            <span>追加</span>
          </button>
        </div>
        <div className="space-y-3">
          {shiftPatterns.map((pattern) => (
            <div key={pattern.type} className="border rounded-lg p-4 flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <span className={`px-3 py-1 rounded font-medium text-sm ${pattern.color} ${pattern.textColor}`}>
                  {pattern.name}
                </span>
                <span className="text-sm text-gray-600">{pattern.timeRange}</span>
                <span className="text-xs text-gray-500">({pattern.type})</span>
              </div>
              <div className="flex space-x-2">
                <button
                  type="button"
                  onClick={() => handleEditShift(pattern.type)}
                  className="text-blue-600 hover:text-blue-900 p-1"
                >
                  <Edit className="h-4 w-4" />
                </button>
                <button
                  type="button"
                  onClick={() => handleDeleteShift(pattern.type)}
                  className="text-red-600 hover:text-red-900 p-1"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 打刻設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <Clock className="h-5 w-5 mr-2 text-blue-600" />
          打刻設定
        </h3>
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                丸め単位
              </label>
              <select
                value={settings.clockRounding}
                onChange={(e) => setSettings({ ...settings, clockRounding: e.target.value })}
                className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
              >
                <option value="none">丸めない</option>
                <option value="5min">5分単位</option>
                <option value="10min">10分単位</option>
                <option value="15min">15分単位</option>
                <option value="30min">30分単位</option>
              </select>
              <p className="text-xs text-gray-500 mt-2">
                打刻時間を指定した単位で切り捨てます。出勤・退勤の両方に適用されます。
              </p>
            </div>
          </div>

          {/* 丸め設定の例示 */}
          {settings.clockRounding !== 'none' && (
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <p className="text-sm font-medium text-gray-700 mb-2">
                丸め設定の例（{
                  settings.clockRounding === '5min' ? '5分単位' :
                  settings.clockRounding === '10min' ? '10分単位' :
                  settings.clockRounding === '15min' ? '15分単位' :
                  '30分単位'
                }の場合）
              </p>
              <ul className="text-sm text-gray-600 space-y-1">
                {settings.clockRounding === '5min' && (
                  <>
                    <li>• 9:00 〜 9:04 → 9:00</li>
                    <li>• 9:05 〜 9:09 → 9:05</li>
                    <li>• 9:10 〜 9:14 → 9:10</li>
                    <li>• 9:15 〜 9:19 → 9:15</li>
                  </>
                )}
                {settings.clockRounding === '10min' && (
                  <>
                    <li>• 9:00 〜 9:09 → 9:00</li>
                    <li>• 9:10 〜 9:19 → 9:10</li>
                    <li>• 9:20 〜 9:29 → 9:20</li>
                    <li>• 9:30 〜 9:39 → 9:30</li>
                  </>
                )}
                {settings.clockRounding === '15min' && (
                  <>
                    <li>• 9:00 〜 9:14 → 9:00</li>
                    <li>• 9:15 〜 9:29 → 9:15</li>
                    <li>• 9:30 〜 9:44 → 9:30</li>
                    <li>• 9:45 〜 9:59 → 9:45</li>
                  </>
                )}
                {settings.clockRounding === '30min' && (
                  <>
                    <li>• 9:00 〜 9:29 → 9:00</li>
                    <li>• 9:30 〜 9:59 → 9:30</li>
                    <li>• 10:00 〜 10:29 → 10:00</li>
                    <li>• 10:30 〜 10:59 → 10:30</li>
                  </>
                )}
              </ul>
            </div>
          )}
        </div>
      </div>

      {/* 有給休暇設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <Clock className="h-5 w-5 mr-2 text-blue-600" />
          有給休暇設定
        </h3>
        <div className="space-y-4">
          <div className="space-y-3">
            <label className="flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={settings.paidLeaveHalfDay}
                onChange={(e) => setSettings({ ...settings, paidLeaveHalfDay: e.target.checked })}
                className="mr-3 h-4 w-4"
              />
              <span className="text-sm text-gray-700">半日単位の有給を付与する</span>
            </label>
            <label className="flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={settings.paidLeaveHourly}
                onChange={(e) => setSettings({ ...settings, paidLeaveHourly: e.target.checked })}
                className="mr-3 h-4 w-4"
              />
              <span className="text-sm text-gray-700">時間単位の有給を付与する</span>
            </label>
          </div>

          {/* 時間単位の有給が選択されている場合のみ表示 */}
          {settings.paidLeaveHourly && (
            <div className="mt-4 pl-7 border-l-2 border-blue-200">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    1日の所定労働時間
                  </label>
                  <div className="flex items-center space-x-2">
                    <input
                      type="number"
                      value={settings.dailyWorkingHours}
                      onChange={(e) => setSettings({ ...settings, dailyWorkingHours: e.target.value })}
                      className="w-24 border border-gray-300 rounded-md p-2 text-gray-900"
                      min="1"
                      max="24"
                      step="0.5"
                    />
                    <span className="text-sm text-gray-700">時間</span>
                  </div>
                  <p className="text-xs text-gray-500 mt-2">
                    時間単位の有給取得時に使用される1日の所定労働時間を設定してください。
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* 労務アラート設定 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
          <AlertTriangle className="h-5 w-5 mr-2 text-red-600" />
          労務アラート設定
        </h3>
        <div className="space-y-6">
          {/* 残業時間設定 */}
          <div>
            <h4 className="text-sm font-semibold text-gray-800 mb-3">月間残業時間の管理</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  通知時間
                </label>
                <div className="flex items-center space-x-2">
                  <input
                    type="number"
                    value={settings.alertOvertimeNotification}
                    onChange={(e) => setSettings({ ...settings, alertOvertimeNotification: e.target.value })}
                    className="w-24 border border-gray-300 rounded-md p-2 text-gray-900"
                    min="0"
                    max="200"
                    step="5"
                  />
                  <span className="text-sm text-gray-700">時間/月</span>
                </div>
                <p className="text-xs font-medium text-yellow-700 mt-1">
                  設定した時間を超過すると、該当する従業員に注意喚起の通知が届きます
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  限度時間
                </label>
                <div className="flex items-center space-x-2">
                  <input
                    type="number"
                    value={settings.alertOvertimeLimit}
                    onChange={(e) => setSettings({ ...settings, alertOvertimeLimit: e.target.value })}
                    className="w-24 border border-gray-300 rounded-md p-2 text-gray-900"
                    min="0"
                    max="200"
                    step="5"
                  />
                  <span className="text-sm text-gray-700">時間/月</span>
                </div>
                <p className="text-xs font-medium text-red-700 mt-1">
                  設定した時間を超過すると、該当する従業員に警告通知が届きます
                </p>
              </div>
            </div>
          </div>

          {/* アラートメッセージ設定 */}
          <div className="border-t pt-6">
            <h4 className="text-sm font-semibold text-gray-800 mb-3">アラートメッセージのカスタマイズ</h4>
            <div className="space-y-4">
              {/* 警告アラート */}
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <h5 className="text-sm font-semibold text-red-800 mb-3">警告アラート（限度時間超過時）</h5>
                <div className="space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      タイトル
                    </label>
                    <input
                      type="text"
                      value={settings.alertMessages.warningTitle}
                      onChange={(e) => setSettings({
                        ...settings,
                        alertMessages: { ...settings.alertMessages, warningTitle: e.target.value }
                      })}
                      className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                      placeholder="例: 【警告】残業時間超過"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      メッセージ
                    </label>
                    <textarea
                      value={settings.alertMessages.warningMessage}
                      onChange={(e) => setSettings({
                        ...settings,
                        alertMessages: { ...settings.alertMessages, warningMessage: e.target.value }
                      })}
                      className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                      rows={3}
                      placeholder="例: 今月の残業時間が{hours}時間に達しています。"
                    />
                    <p className="text-xs text-gray-600 mt-1">
                      ※ {'{hours}'} は残業時間に自動置換されます
                    </p>
                  </div>
                </div>
              </div>

              {/* 注意アラート */}
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h5 className="text-sm font-semibold text-yellow-800 mb-3">注意アラート（通知時間超過時）</h5>
                <div className="space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      タイトル
                    </label>
                    <input
                      type="text"
                      value={settings.alertMessages.cautionTitle}
                      onChange={(e) => setSettings({
                        ...settings,
                        alertMessages: { ...settings.alertMessages, cautionTitle: e.target.value }
                      })}
                      className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                      placeholder="例: 【注意】残業時間が多くなっています"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      メッセージ
                    </label>
                    <textarea
                      value={settings.alertMessages.cautionMessage}
                      onChange={(e) => setSettings({
                        ...settings,
                        alertMessages: { ...settings.alertMessages, cautionMessage: e.target.value }
                      })}
                      className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                      rows={3}
                      placeholder="例: 今月の残業時間が{hours}時間に達しています。"
                    />
                    <p className="text-xs text-gray-600 mt-1">
                      ※ {'{hours}'} は残業時間に自動置換されます
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* 現在のプラン */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          現在のプラン
        </h3>
        <div className="space-y-4">
          <div>
            <p className="text-sm font-medium text-gray-700">プラン名</p>
            <p className="text-lg font-semibold text-gray-900">無料トライアル（1ヶ月間）</p>
          </div>
          <div className="bg-yellow-100 border-2 border-yellow-400 rounded-lg p-4 shadow-md">
            <p className="text-sm font-semibold text-gray-800">
              無料期間終了後も利用を続けるには、有料プランへの切り替えが必要です。
            </p>
          </div>
          <div className="pt-4">
            <button
              type="button"
              className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
            >
              プラン変更
            </button>
          </div>
        </div>
      </div>

      {/* 保存ボタン */}
      <div className="flex justify-end">
        <button
          type="button"
          onClick={handleSubmit}
          className="bg-blue-600 hover:bg-blue-700 text-white px-12 py-3 rounded-lg flex items-center justify-center space-x-2 text-base font-semibold min-w-[200px]"
        >
          <Save className="h-5 w-5" />
          <span>保存</span>
        </button>
      </div>

      {/* 部署追加・編集フォーム */}
      {showDepartmentForm && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              {editingDepartment ? '部署編集' : '部署追加'}
            </h3>
            <form onSubmit={handleDepartmentSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  部署名
                </label>
                <input
                  type="text"
                  value={newDepartment.name}
                  onChange={(e) => setNewDepartment({ ...newDepartment, name: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  placeholder="例: 営業部"
                  required
                />
              </div>
              <div className="flex space-x-3 pt-4">
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md"
                >
                  {editingDepartment ? '更新' : '追加'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowDepartmentForm(false);
                    setEditingDepartment(null);
                    setNewDepartment({ id: '', name: '' });
                  }}
                  className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md"
                >
                  キャンセル
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* シフトパターン追加・編集フォーム */}
      {showShiftForm && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              {editingShift ? 'シフトパターン編集' : 'シフトパターン追加'}
            </h3>
            <form onSubmit={handleShiftSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  シフトタイプID
                </label>
                <input
                  type="text"
                  value={newShift.type}
                  onChange={(e) => setNewShift({ ...newShift, type: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  placeholder="例: morning"
                  required
                  disabled={!!editingShift}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  シフト名
                </label>
                <input
                  type="text"
                  value={newShift.name}
                  onChange={(e) => setNewShift({ ...newShift, name: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  placeholder="例: 早番"
                  required
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    始業時刻
                  </label>
                  <input
                    type="time"
                    id="shiftStartTime"
                    className="w-full border border-gray-300 rounded-md p-2"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    終業時刻
                  </label>
                  <input
                    type="time"
                    id="shiftEndTime"
                    className="w-full border border-gray-300 rounded-md p-2"
                    required
                  />
                </div>
              </div>

              {/* 休憩時間入力方式選択 */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  休憩時間の入力方式
                </label>
                <div className="flex gap-4">
                  <label className="flex items-center cursor-pointer">
                    <input
                      type="radio"
                      value="duration"
                      checked={shiftBreakMode === 'duration'}
                      onChange={(e) => setShiftBreakMode(e.target.value as 'duration' | 'timeRange')}
                      className="mr-2"
                    />
                    <span className="text-sm text-gray-700">休憩時間（分）</span>
                  </label>
                  <label className="flex items-center cursor-pointer">
                    <input
                      type="radio"
                      value="timeRange"
                      checked={shiftBreakMode === 'timeRange'}
                      onChange={(e) => setShiftBreakMode(e.target.value as 'duration' | 'timeRange')}
                      className="mr-2"
                    />
                    <span className="text-sm text-gray-700">休憩入～休憩終</span>
                  </label>
                </div>
              </div>

              {/* 休憩時間入力フィールド */}
              {shiftBreakMode === 'duration' ? (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    休憩時間（分）
                  </label>
                  <input
                    type="number"
                    value={shiftBreakDuration}
                    onChange={(e) => setShiftBreakDuration(e.target.value)}
                    className="w-full border border-gray-300 rounded-md p-2"
                    min="0"
                    step="15"
                  />
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      休憩入
                    </label>
                    <input
                      type="time"
                      value={shiftBreakStart}
                      onChange={(e) => setShiftBreakStart(e.target.value)}
                      className="w-full border border-gray-300 rounded-md p-2"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      休憩終
                    </label>
                    <input
                      type="time"
                      value={shiftBreakEnd}
                      onChange={(e) => setShiftBreakEnd(e.target.value)}
                      className="w-full border border-gray-300 rounded-md p-2"
                    />
                  </div>
                </div>
              )}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  背景色クラス
                </label>
                <select
                  value={newShift.color}
                  onChange={(e) => setNewShift({ ...newShift, color: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                >
                  <option value="bg-blue-100">青 (bg-blue-100)</option>
                  <option value="bg-green-100">緑 (bg-green-100)</option>
                  <option value="bg-yellow-100">黄 (bg-yellow-100)</option>
                  <option value="bg-red-100">赤 (bg-red-100)</option>
                  <option value="bg-purple-100">紫 (bg-purple-100)</option>
                  <option value="bg-gray-100">グレー (bg-gray-100)</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  文字色クラス
                </label>
                <select
                  value={newShift.textColor}
                  onChange={(e) => setNewShift({ ...newShift, textColor: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                >
                  <option value="text-blue-800">青 (text-blue-800)</option>
                  <option value="text-green-800">緑 (text-green-800)</option>
                  <option value="text-yellow-800">黄 (text-yellow-800)</option>
                  <option value="text-red-800">赤 (text-red-800)</option>
                  <option value="text-purple-800">紫 (text-purple-800)</option>
                  <option value="text-gray-800">グレー (text-gray-800)</option>
                </select>
              </div>
              <div className="flex space-x-3 pt-4">
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md"
                >
                  {editingShift ? '更新' : '追加'}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowShiftForm(false);
                    setEditingShift(null);
                    setNewShift({
                      type: '',
                      name: '',
                      timeRange: '',
                      color: 'bg-gray-100',
                      textColor: 'text-gray-800'
                    });
                    setShiftBreakMode('duration');
                    setShiftBreakDuration('60');
                    setShiftBreakStart('12:00');
                    setShiftBreakEnd('13:00');
                  }}
                  className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md"
                >
                  キャンセル
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
