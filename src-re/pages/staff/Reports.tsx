
import { useState } from 'react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, isToday, addMonths, subMonths } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { mockUsers, mockAttendanceRecords, mockShifts, shiftTypeInfo } from '@/lib/mockData';
import { AttendanceRecord } from '@/types';

// 申請ダイアログコンポーネント
function ApplicationDialog({
  isOpen,
  onClose,
  onSubmit,
  date
}: {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (type: string, reason: string) => void;
  date: Date | null;
}) {
  const [applicationType, setApplicationType] = useState('');
  const [reason, setReason] = useState('');
  const [correctClockIn, setCorrectClockIn] = useState('');
  const [correctClockOut, setCorrectClockOut] = useState('');
  const [correctBreakEnd, setCorrectBreakEnd] = useState('');

  const applicationTypes = [
    { value: 'paid-leave', label: '有給休暇' },
    { value: 'clock-error', label: '打刻間違い' },
    { value: 'late', label: '遅刻' },
    { value: 'early-leave', label: '早退' },
    { value: 'special-leave', label: '特別休暇' },
    { value: 'absence', label: '欠勤' },
    { value: 'overtime', label: '残業申請' },
    { value: 'other', label: 'その他' },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (applicationType && reason) {
      onSubmit(applicationType, reason);
      setApplicationType('');
      setReason('');
      setCorrectClockIn('');
      setCorrectClockOut('');
      setCorrectBreakEnd('');
      onClose();
    }
  };

  const handleClose = () => {
    setApplicationType('');
    setReason('');
    setCorrectClockIn('');
    setCorrectClockOut('');
    setCorrectBreakEnd('');
    onClose();
  };

  if (!isOpen || !date) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      {/* 背景オーバーレイ */}
      <div className="fixed inset-0 bg-gray-500 bg-opacity-50 transition-opacity" onClick={handleClose}></div>

      {/* モーダルコンテナ */}
      <div className="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div
          className="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
          onClick={(e) => e.stopPropagation()}
        >
          {/* ヘッダー */}
          <div className="bg-gradient-to-r from-green-600 to-green-500 px-6 py-4">
            <div className="flex items-center justify-between">
              <h3 className="text-xl font-semibold text-white" id="modal-title">
                {format(date, 'M月d日')}の申請
              </h3>
              <button
                type="button"
                onClick={handleClose}
                className="text-white hover:text-green-100 transition-colors"
              >
                <X className="h-6 w-6" />
              </button>
            </div>
          </div>

          {/* フォーム */}
          <form onSubmit={handleSubmit} className="bg-white px-6 py-6">
            <div className="space-y-5">
              <div>
                <label htmlFor="application-type" className="block text-sm font-semibold text-gray-900 mb-2">
                  申請種別
                </label>
                <select
                  id="application-type"
                  value={applicationType}
                  onChange={(e) => setApplicationType(e.target.value)}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                  required
                >
                  <option value="">選択してください</option>
                  {applicationTypes.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* 打刻間違いの場合の入力欄 */}
              {applicationType === 'clock-error' && (
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-4">
                  <h4 className="text-sm font-semibold text-gray-900">正しい打刻時刻</h4>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="correct-clock-in" className="block text-sm font-medium text-gray-700 mb-1">
                        出勤時刻
                      </label>
                      <input
                        type="time"
                        id="correct-clock-in"
                        value={correctClockIn}
                        onChange={(e) => setCorrectClockIn(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                      />
                    </div>
                    <div>
                      <label htmlFor="correct-clock-out" className="block text-sm font-medium text-gray-700 mb-1">
                        退勤時刻
                      </label>
                      <input
                        type="time"
                        id="correct-clock-out"
                        value={correctClockOut}
                        onChange={(e) => setCorrectClockOut(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                      />
                    </div>
                  </div>

                  <div>
                    <label htmlFor="correct-break-end" className="block text-sm font-medium text-gray-700 mb-1">
                      休憩終了時刻
                    </label>
                    <input
                      type="time"
                      id="correct-break-end"
                      value={correctBreakEnd}
                      onChange={(e) => setCorrectBreakEnd(e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                    />
                  </div>

                  <p className="text-xs text-gray-600">
                    ※ 間違えた項目のみ入力してください。変更のない項目は空欄で構いません。
                  </p>
                </div>
              )}

              <div>
                <label htmlFor="reason" className="block text-sm font-semibold text-gray-900 mb-2">
                  申請理由
                </label>
                <textarea
                  id="reason"
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  rows={4}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none text-gray-900"
                  placeholder="申請理由を入力してください"
                  required
                />
              </div>
            </div>

            {/* ボタン */}
            <div className="mt-6 flex gap-3 sm:flex-row-reverse">
              <button
                type="submit"
                className="inline-flex w-full justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors sm:w-auto"
              >
                申請する
              </button>
              <button
                type="button"
                onClick={handleClose}
                className="inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors sm:w-auto"
              >
                キャンセル
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}

export default function StaffReportsPage() {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [applications, setApplications] = useState<{ [key: string]: { type: string; reason: string } }>({
    '2025-09-10': { type: 'paid-leave', reason: '家族旅行のため' },
    '2025-09-22': { type: 'early-leave', reason: '体調不良のため' },
    '2025-09-23': { type: 'paid-leave', reason: '家族旅行のため' },
    '2025-10-04': { type: 'paid-leave', reason: '私用のため' },
    '2025-10-05': { type: 'paid-leave', reason: '私用のため' },
    '2025-10-10': { type: 'paid-leave', reason: '私用のため' }
  });

  // 仮のログインユーザー
  const currentUser = mockUsers[0];

  // ユーザーの勤務実績を取得
  const userAttendances = mockAttendanceRecords.filter(
    (attendance: AttendanceRecord) => attendance.userId === currentUser.id
  );

  // 現在の月の日付を取得
  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(currentDate);
  const daysInMonth = eachDayOfInterval({ start: monthStart, end: monthEnd });

  // 日本の祝日データ（2025年）
  const holidays: { [key: string]: string } = {
    '2025-01-01': '元日',
    '2025-01-13': '成人の日',
    '2025-02-11': '建国記念の日',
    '2025-02-23': '天皇誕生日',
    '2025-02-24': '振替休日',
    '2025-03-20': '春分の日',
    '2025-04-29': '昭和の日',
    '2025-05-03': '憲法記念日',
    '2025-05-04': 'みどりの日',
    '2025-05-05': 'こどもの日',
    '2025-05-06': '振替休日',
    '2025-07-21': '海の日',
    '2025-08-11': '山の日',
    '2025-09-15': '敬老の日',
    '2025-09-23': '秋分の日',
    '2025-10-13': 'スポーツの日',
    '2025-11-03': '文化の日',
    '2025-11-23': '勤労感謝の日',
    '2025-11-24': '振替休日',
  };

  // 指定日の勤務記録を取得
  const getAttendanceForDate = (date: Date): AttendanceRecord | undefined => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return userAttendances.find((attendance) => attendance.date === dateStr);
  };

  // 退勤時刻を24時間超え表記に変換（例: 01:30 -> 25:30）
  const formatClockOut = (clockIn: string, clockOut: string) => {
    const [inHour] = clockIn.split(':').map(Number);
    const [outHour, outMin] = clockOut.split(':').map(Number);

    // 出勤が13時以降で、退勤が0~5時の場合は日付をまたいでいると判断
    if (inHour >= 13 && outHour >= 0 && outHour < 6) {
      const adjustedHour = outHour + 24;
      return `${adjustedHour}:${String(outMin).padStart(2, '0')}`;
    }
    return clockOut;
  };

  // 祝日判定
  const getHoliday = (date: Date): string | undefined => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return holidays[dateStr];
  };

  // 指定日のシフトを取得
  const getShiftForDate = (date: Date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    const shift = mockShifts.find((s) => s.date === dateStr && s.userId === currentUser.id);
    if (shift) {
      return shiftTypeInfo.find((info) => info.type === shift.shiftType);
    }
    return undefined;
  };

  // 時間外労働時間を計算（分単位で返す）
  const calculateOvertimeMinutes = (attendance: AttendanceRecord | undefined, shift: { timeRange: string } | undefined) => {
    if (!attendance || !shift || !attendance.clockIn || !attendance.clockOut) {
      return 0;
    }

    // シフトの時間範囲を解析（例: "09:00-18:00"）
    const timeRange = shift.timeRange;
    const match = timeRange.match(/(\d{2}):(\d{2})-(\d{2}):(\d{2})/);
    if (!match) return 0;

    const shiftEndHour = parseInt(match[3]);
    const shiftEndMin = parseInt(match[4]);
    const shiftEndMinutes = shiftEndHour * 60 + shiftEndMin;

    // 実際の退勤時刻を解析
    const clockOutParts = attendance.clockOut.split(':');
    const actualEndHour = parseInt(clockOutParts[0]);
    const actualEndMin = parseInt(clockOutParts[1]);
    const actualEndMinutes = actualEndHour * 60 + actualEndMin;

    // 時間外を計算（分単位）
    return Math.max(0, actualEndMinutes - shiftEndMinutes);
  };

  // 休日労働時間を計算（分単位で返す）
  const calculateHolidayMinutes = (date: Date, attendance: AttendanceRecord | undefined) => {
    if (!attendance || !attendance.totalHours) return 0;

    const dayOfWeek = date.getDay();
    const holiday = getHoliday(date);

    // 土曜日、日曜日または祝日の場合、その日の総労働時間を休日労働とする
    if (dayOfWeek === 0 || dayOfWeek === 6 || holiday) {
      return Math.round(attendance.totalHours * 60);
    }
    return 0;
  };

  // 深夜労働時間を計算（分単位で返す）
  // 深夜時間帯: 22:00-翌5:00
  const calculateNightMinutes = (attendance: AttendanceRecord | undefined) => {
    if (!attendance || !attendance.clockIn || !attendance.clockOut) return 0;

    const clockInParts = attendance.clockIn.split(':');
    const clockOutParts = attendance.clockOut.split(':');
    const startHour = parseInt(clockInParts[0]);
    const startMin = parseInt(clockInParts[1]);
    let endHour = parseInt(clockOutParts[0]);
    const endMin = parseInt(clockOutParts[1]);

    // 出勤が13時以降で退勤が0~5時の場合は日付をまたいでいる
    if (startHour >= 13 && endHour >= 0 && endHour < 6) {
      endHour += 24; // 25:30 のように扱う
    }

    let nightMinutes = 0;
    const startMinutes = startHour * 60 + startMin;
    const endMinutes = endHour * 60 + endMin;

    // 22:00-24:00（翌日0:00）の深夜時間
    const night1Start = 22 * 60; // 22:00
    const night1End = 24 * 60;   // 24:00
    if (endMinutes > night1Start && startMinutes < night1End) {
      const actualStart = Math.max(startMinutes, night1Start);
      const actualEnd = Math.min(endMinutes, night1End);
      nightMinutes += Math.max(0, actualEnd - actualStart);
    }

    // 24:00-29:00（翌日0:00-5:00）の深夜時間
    const night2Start = 24 * 60; // 24:00 (翌0:00)
    const night2End = 29 * 60;   // 29:00 (翌5:00)
    if (endMinutes > night2Start && startMinutes < night2End) {
      const actualStart = Math.max(startMinutes, night2Start);
      const actualEnd = Math.min(endMinutes, night2End);
      nightMinutes += Math.max(0, actualEnd - actualStart);
    }

    return nightMinutes;
  };

  // 遅刻・早退時間を計算（分単位で返す）
  const calculateLateEarlyMinutes = (attendance: AttendanceRecord | undefined, shift: { timeRange: string } | undefined) => {
    if (!attendance || !shift || !attendance.clockIn || !attendance.clockOut) {
      return { lateMinutes: 0, earlyMinutes: 0 };
    }

    const timeRange = shift.timeRange;
    const match = timeRange.match(/(\d{2}):(\d{2})-(\d{2}):(\d{2})/);
    if (!match) return { lateMinutes: 0, earlyMinutes: 0 };

    const shiftStartHour = parseInt(match[1]);
    const shiftStartMin = parseInt(match[2]);
    const shiftStartMinutes = shiftStartHour * 60 + shiftStartMin;

    const shiftEndHour = parseInt(match[3]);
    const shiftEndMin = parseInt(match[4]);
    const shiftEndMinutes = shiftEndHour * 60 + shiftEndMin;

    // 実際の出勤時刻を解析
    const clockInParts = attendance.clockIn.split(':');
    const actualStartHour = parseInt(clockInParts[0]);
    const actualStartMin = parseInt(clockInParts[1]);
    const actualStartMinutes = actualStartHour * 60 + actualStartMin;

    // 実際の退勤時刻を解析
    const clockOutParts = attendance.clockOut.split(':');
    let actualEndHour = parseInt(clockOutParts[0]);
    const actualEndMin = parseInt(clockOutParts[1]);

    // 出勤が13時以降で退勤が0~5時の場合は日付をまたいでいる
    if (actualStartHour >= 13 && actualEndHour >= 0 && actualEndHour < 6) {
      actualEndHour += 24; // 25:30 のように扱う
    }
    const actualEndMinutes = actualEndHour * 60 + actualEndMin;

    // 遅刻時間を計算
    const lateMinutes = Math.max(0, actualStartMinutes - shiftStartMinutes);

    // 早退時間を計算（シフト終了時刻より早く退勤した場合のみ）
    const earlyMinutes = Math.max(0, shiftEndMinutes - actualEndMinutes);

    return { lateMinutes, earlyMinutes };
  };

  // 前月へ
  const previousMonth = () => {
    setCurrentDate(subMonths(currentDate, 1));
  };

  // 次月へ
  const nextMonth = () => {
    setCurrentDate(addMonths(currentDate, 1));
  };

  // 申請ボタンをクリックしたときの処理
  const handleApplicationClick = (date: Date) => {
    setSelectedDate(date);
    setIsDialogOpen(true);
  };

  // 申請を送信
  const handleApplicationSubmit = (type: string, reason: string) => {
    if (selectedDate) {
      const dateKey = format(selectedDate, 'yyyy-MM-dd');
      setApplications({
        ...applications,
        [dateKey]: { type, reason }
      });
    }
  };

  // 指定日の申請が存在するか確認
  const hasApplication = (date: Date): boolean => {
    const dateKey = format(date, 'yyyy-MM-dd');
    return dateKey in applications;
  };

  // 申請可能かどうかを判定（先々月より前は申請不可）
  const canApply = (date: Date): boolean => {
    const today = new Date();
    const twoMonthsAgo = subMonths(today, 2);
    const twoMonthsAgoStart = startOfMonth(twoMonthsAgo);
    return date >= twoMonthsAgoStart;
  };

  return (
    <div className="space-y-6">
      {/* 申請ダイアログ */}
      <ApplicationDialog
        isOpen={isDialogOpen}
        onClose={() => setIsDialogOpen(false)}
        onSubmit={handleApplicationSubmit}
        date={selectedDate}
      />

      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">勤務実績</h1>

        {/* 月選択 */}
        <div className="flex items-center gap-4">
          <button
            onClick={previousMonth}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ChevronLeft className="h-5 w-5 text-gray-600" />
          </button>

          <div className="flex items-center gap-2">
            <select
              value={currentDate.getFullYear()}
              onChange={(e) => setCurrentDate(new Date(parseInt(e.target.value), currentDate.getMonth(), 1))}
              className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm font-semibold text-gray-900"
            >
              {Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i).map(year => (
                <option key={year} value={year}>{year}年</option>
              ))}
            </select>

            <select
              value={currentDate.getMonth()}
              onChange={(e) => setCurrentDate(new Date(currentDate.getFullYear(), parseInt(e.target.value), 1))}
              className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm font-semibold text-gray-900"
            >
              {Array.from({ length: 12 }, (_, i) => i).map(month => (
                <option key={month} value={month}>{month + 1}月</option>
              ))}
            </select>
          </div>

          <button
            onClick={nextMonth}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ChevronRight className="h-5 w-5 text-gray-600" />
          </button>
        </div>
      </div>

      {/* 集計情報 */}
      <div className="bg-white shadow rounded-lg px-6 py-4">
        <div className="flex items-center justify-between">
          <div className="text-lg font-bold text-gray-900">
            {format(currentDate, 'yyyy年MM月', { locale: ja })}
          </div>
          <div className="flex items-center gap-8">
            <div className="text-sm">
              <span className="text-gray-600">出勤日数: </span>
              <span className="font-semibold text-gray-900">
                {daysInMonth.filter(date => {
                  const attendance = getAttendanceForDate(date);
                  return attendance && attendance.clockIn;
                }).length}日
              </span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">休暇日数: </span>
              <span className="font-semibold text-gray-900">
                {daysInMonth.filter(date => {
                  const dateKey = format(date, 'yyyy-MM-dd');
                  const attendance = getAttendanceForDate(date);
                  const application = applications[dateKey];
                  // attendance.statusが有給or特別休暇の場合、またはapplicationsに有給or特別休暇の申請がある場合
                  return (attendance && (attendance.status === 'paid-leave' || attendance.status === 'special-leave')) ||
                         (application && (application.type === 'paid-leave' || application.type === 'special-leave'));
                }).length}日
              </span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">総労働時間: </span>
              <span className="font-semibold text-gray-900">
                {(() => {
                  const totalMinutes = daysInMonth.reduce((sum, date) => {
                    const attendance = getAttendanceForDate(date);
                    if (attendance && attendance.totalHours) {
                      return sum + (attendance.totalHours * 60);
                    }
                    return sum;
                  }, 0);
                  const hours = Math.floor(totalMinutes / 60);
                  const mins = Math.round(totalMinutes % 60);
                  return `${hours}:${String(mins).padStart(2, '0')}`;
                })()}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* カレンダー（リスト形式） */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        {/* テーブルヘッダー */}
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                  日付
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                  シフト
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                  勤務時間
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                  休憩
                </th>
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                  労働時間
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                  時間外
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                  休日
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                  深夜
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                  遅刻早退
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                  備考/申請
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {daysInMonth.map((date) => {
                const attendance = getAttendanceForDate(date);
                const isCurrentDay = isToday(date);
                const dayOfWeek = date.getDay();
                const dayName = format(date, 'E', { locale: ja });
                const holiday = getHoliday(date);
                const shift = getShiftForDate(date);

                return (
                  <tr
                    key={date.toString()}
                    className={`${isCurrentDay ? 'bg-green-50' : ''} hover:bg-gray-50`}
                  >
                    <td className="px-3 py-3 whitespace-nowrap">
                      <div className="flex items-center gap-1.5">
                        <span className="text-sm font-medium text-gray-900">
                          {format(date, 'd')}日
                        </span>
                        <span className={`text-sm font-medium ${
                          holiday || dayOfWeek === 0 ? 'text-red-600' :
                          dayOfWeek === 6 ? 'text-blue-600' :
                          'text-gray-700'
                        }`}>
                          ({dayName})
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap">
                      {shift ? (
                        <span className={`px-2 py-1 text-xs font-medium rounded ${shift.color} ${shift.textColor}`}>
                          {shift.timeRange}
                        </span>
                      ) : (
                        <span className="text-sm text-gray-400">-</span>
                      )}
                    </td>
                    <td className="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                      {(() => {
                        if (attendance?.clockIn && attendance?.clockOut) {
                          const clockOut = formatClockOut(attendance.clockIn, attendance.clockOut);
                          return `${attendance.clockIn} ~ ${clockOut}`;
                        }
                        if (attendance?.clockIn) {
                          return `${attendance.clockIn} ~ -`;
                        }
                        return '-';
                      })()}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                      {attendance?.breakStart && attendance?.breakEnd
                        ? `${attendance.breakStart}-${attendance.breakEnd}`
                        : '-'}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                      {attendance?.totalHours ? (() => {
                        const hours = Math.floor(attendance.totalHours);
                        const mins = Math.round((attendance.totalHours - hours) * 60);
                        return `${hours}:${String(mins).padStart(2, '0')}`;
                      })() : '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {(() => {
                        const overtimeMinutes = calculateOvertimeMinutes(attendance, shift);
                        if (overtimeMinutes > 0) {
                          const hours = Math.floor(overtimeMinutes / 60);
                          const mins = overtimeMinutes % 60;
                          return <span className="font-medium text-orange-600">{hours}:{String(mins).padStart(2, '0')}</span>;
                        }
                        return <span className="text-gray-400">-</span>;
                      })()}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm">
                      {(() => {
                        const holidayMinutes = calculateHolidayMinutes(date, attendance);
                        if (holidayMinutes > 0) {
                          const hours = Math.floor(holidayMinutes / 60);
                          const mins = holidayMinutes % 60;
                          return <span className="font-medium text-red-600">{hours}:{String(mins).padStart(2, '0')}</span>;
                        }
                        return <span className="text-gray-400">-</span>;
                      })()}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm">
                      {(() => {
                        const nightMinutes = calculateNightMinutes(attendance);
                        if (nightMinutes > 0) {
                          const hours = Math.floor(nightMinutes / 60);
                          const mins = nightMinutes % 60;
                          return <span className="font-medium text-indigo-600">{hours}:{String(mins).padStart(2, '0')}</span>;
                        }
                        return <span className="text-gray-400">-</span>;
                      })()}
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm">
                      {(() => {
                        const { lateMinutes, earlyMinutes } = calculateLateEarlyMinutes(attendance, shift);
                        const totalMinutes = lateMinutes + earlyMinutes;
                        if (totalMinutes > 0) {
                          const hours = Math.floor(totalMinutes / 60);
                          const mins = totalMinutes % 60;
                          return <span className="font-medium text-red-600">{hours}:{String(mins).padStart(2, '0')}</span>;
                        }
                        return <span className="text-gray-400">-</span>;
                      })()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {(() => {
                        const dateKey = format(date, 'yyyy-MM-dd');
                        const application = applications[dateKey];

                        // attendanceのstatusを確認して有給・特別休暇を表示
                        if (attendance?.status === 'paid-leave') {
                          return (
                            <div className="flex flex-col gap-1">
                              <span className="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">有給休暇</span>
                              {application?.reason && (
                                <span className="text-xs text-gray-600">{application.reason}</span>
                              )}
                            </div>
                          );
                        }
                        if (attendance?.status === 'special-leave') {
                          return (
                            <div className="flex flex-col gap-1">
                              <span className="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">特別休暇</span>
                              {application?.reason && (
                                <span className="text-xs text-gray-600">{application.reason}</span>
                              )}
                            </div>
                          );
                        }

                        // applicationsから申請を確認
                        if (application) {
                          // 申請タイプに応じたスタイルを適用
                          let badgeStyle = 'px-2 py-1 text-xs font-medium rounded ';
                          let labelText = '';

                          switch(application.type) {
                            case 'paid-leave':
                              badgeStyle += 'bg-yellow-100 text-yellow-800';
                              labelText = '有給休暇';
                              break;
                            case 'special-leave':
                              badgeStyle += 'bg-blue-100 text-blue-800';
                              labelText = '特別休暇';
                              break;
                            case 'overtime':
                              badgeStyle += 'bg-orange-100 text-orange-800';
                              labelText = '残業申請';
                              break;
                            case 'sick-leave':
                              badgeStyle += 'bg-purple-100 text-purple-800';
                              labelText = '病欠';
                              break;
                            case 'early-leave':
                              badgeStyle += 'bg-red-100 text-red-800';
                              labelText = '早退';
                              break;
                            case 'late':
                              badgeStyle += 'bg-red-100 text-red-800';
                              labelText = '遅刻';
                              break;
                            case 'clock-error':
                              badgeStyle += 'bg-indigo-100 text-indigo-800';
                              labelText = '打刻間違い';
                              break;
                            default:
                              badgeStyle += 'bg-gray-100 text-gray-800';
                              labelText = 'その他';
                          }

                          return (
                            <div className="flex flex-col gap-1">
                              <span className={badgeStyle}>
                                {labelText}
                              </span>
                              {application.reason && (
                                <span className="text-xs text-gray-600">{application.reason}</span>
                              )}
                            </div>
                          );
                        }

                        // 申請ボタンの表示
                        if (hasApplication(date)) {
                          return (
                            <span className="px-3 py-1.5 text-xs font-medium text-gray-500 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">
                              申請済み
                            </span>
                          );
                        }
                        if (!canApply(date)) {
                          return (
                            <span className="px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-md cursor-not-allowed">
                              申請不可
                            </span>
                          );
                        }
                        return (
                          <button
                            className="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100 transition-colors"
                            onClick={() => handleApplicationClick(date)}
                          >
                            申請
                          </button>
                        );
                      })()}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  );
}
