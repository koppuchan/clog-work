
import { useState } from 'react';
import { Download } from 'lucide-react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import { mockAttendanceRecords, mockUsers, mockShifts, shiftTypeInfo, mockApplications } from '@/lib/mockData';
import { AttendanceRecord } from '@/types';

export default function ReportsPage() {
  const [selectedMonth, setSelectedMonth] = useState(new Date());
  const [selectedUser, setSelectedUser] = useState(mockUsers[0]?.id || '');
  const [showExportModal, setShowExportModal] = useState(false);
  const [exportFormats, setExportFormats] = useState({
    csv: false,
    pdf: false
  });

  const monthStart = startOfMonth(selectedMonth);
  const monthEnd = endOfMonth(selectedMonth);
  const daysInMonth = eachDayOfInterval({ start: monthStart, end: monthEnd });

  const filteredRecords = mockAttendanceRecords.filter(record => {
    const recordDate = new Date(record.date);
    const isInSelectedMonth = recordDate >= monthStart && recordDate <= monthEnd;
    const isSelectedUser = record.userId === selectedUser;
    return isInSelectedMonth && isSelectedUser;
  });

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

  // 祝日判定
  const getHoliday = (date: Date): string | undefined => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return holidays[dateStr];
  };

  const getUserName = (userId: string) => {
    const user = mockUsers.find(u => u.id === userId);
    return user?.name || '不明';
  };

  const calculateTotalHours = () => {
    const total = filteredRecords.reduce((sum, record) => sum + record.totalHours, 0);
    return Number(total.toFixed(2));
  };

  // 指定日の勤務記録を取得
  const getAttendanceForDate = (date: Date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return filteredRecords.find((attendance) => attendance.date === dateStr);
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

  // 指定日のシフトを取得
  const getShiftForDate = (date: Date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    const shift = mockShifts.find((s) => s.date === dateStr && s.userId === selectedUser);
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

  // 承認された申請を取得
  const getApprovedApplicationForDate = (date: Date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return mockApplications.find(app =>
      app.userId === selectedUser &&
      app.status === 'approved' &&
      dateStr >= app.startDate &&
      dateStr <= app.endDate
    );
  };

  // 申請タイプを日本語に変換
  const getApplicationTypeText = (type: string) => {
    switch (type) {
      case 'vacation': return '有給休暇';
      case 'sick-leave': return '病欠';
      case 'overtime': return '残業申請';
      case 'shift-change': return 'シフト変更';
      default: return type;
    }
  };

  const getStatusCounts = () => {
    const counts = { present: 0, late: 0, absent: 0, 'early-leave': 0, 'paid-leave': 0, 'special-leave': 0 };
    filteredRecords.forEach(record => {
      if (record.status in counts) {
        counts[record.status as keyof typeof counts]++;
      }
    });
    return counts;
  };

  const exportToCSV = () => {
    const headers = ['日付', '曜日', 'シフト', '出勤時刻', '退勤時刻', '休憩', '勤務時間', '時間外', '休日', '深夜', '遅刻早退', 'ステータス', '備考'];
    const rows = daysInMonth.map(day => {
      const dayRecord = getAttendanceForDate(day);
      const shift = getShiftForDate(day);
      const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][day.getDay()];
      const overtimeMinutes = calculateOvertimeMinutes(dayRecord, shift);
      const holidayMinutes = calculateHolidayMinutes(day, dayRecord);
      const nightMinutes = calculateNightMinutes(dayRecord);
      const { lateMinutes, earlyMinutes } = calculateLateEarlyMinutes(dayRecord, shift);
      const lateEarlyTotal = lateMinutes + earlyMinutes;
      const application = getApprovedApplicationForDate(day);

      return [
        format(day, 'yyyy/MM/dd'),
        dayOfWeek,
        shift?.timeRange || '',
        dayRecord?.clockIn || '',
        dayRecord?.clockOut || '',
        dayRecord?.breakStart && dayRecord?.breakEnd ? `${dayRecord.breakStart}-${dayRecord.breakEnd}` : '',
        dayRecord ? (() => {
          const hours = Math.floor(dayRecord.totalHours);
          const mins = Math.round((dayRecord.totalHours - hours) * 60);
          return `${hours}:${String(mins).padStart(2, '0')}`;
        })() : '',
        overtimeMinutes > 0 ? (() => {
          const hours = Math.floor(overtimeMinutes / 60);
          const mins = overtimeMinutes % 60;
          return `${hours}:${String(mins).padStart(2, '0')}`;
        })() : '',
        holidayMinutes > 0 ? (() => {
          const hours = Math.floor(holidayMinutes / 60);
          const mins = holidayMinutes % 60;
          return `${hours}:${String(mins).padStart(2, '0')}`;
        })() : '',
        nightMinutes > 0 ? (() => {
          const hours = Math.floor(nightMinutes / 60);
          const mins = nightMinutes % 60;
          return `${hours}:${String(mins).padStart(2, '0')}`;
        })() : '',
        lateEarlyTotal > 0 ? (() => {
          const hours = Math.floor(lateEarlyTotal / 60);
          const mins = lateEarlyTotal % 60;
          return `${hours}:${String(mins).padStart(2, '0')}`;
        })() : '',
        dayRecord ? (
          dayRecord.status === 'present' ? '出勤' :
          dayRecord.status === 'late' ? '遅刻' :
          dayRecord.status === 'absent' ? '欠勤' :
          dayRecord.status === 'early-leave' ? '早退' :
          dayRecord.status === 'paid-leave' ? '有給休暇' :
          dayRecord.status === 'special-leave' ? '特別休暇' : ''
        ) : '',
        application ? `${getApplicationTypeText(application.type)}${application.reason ? ': ' + application.reason : ''}` : ''
      ];
    });

    const statusCounts = getStatusCounts();
    const csvContent = [
      [`対象月,${format(selectedMonth, 'yyyy年MM月')}`],
      [`氏名,${getUserName(selectedUser)}`],
      [`総勤務時間,${calculateTotalHours()}時間`],
      [`出勤日数,${statusCounts.present}日`],
      [`休暇日数,${statusCounts['paid-leave'] + statusCounts['special-leave']}日`],
      [`遅刻回数,${statusCounts.late}回`],
      [`欠勤日数,${statusCounts.absent}日`],
      [],
      headers,
      ...rows
    ].map(row => row.join(',')).join('\n');

    const bom = '\uFEFF';
    const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `勤務実績_${format(selectedMonth, 'yyyy年MM月')}_${getUserName(selectedUser)}.csv`);
    link.click();
  };

  const handleExport = () => {
    if (!exportFormats.csv) {
      alert('出力形式を選択してください');
      return;
    }

    exportToCSV();

    setShowExportModal(false);
    setExportFormats({ csv: false, pdf: false });
  };

  const statusCounts = getStatusCounts();

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">勤務実績</h1>
        <button
          onClick={() => setShowExportModal(true)}
          className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2"
        >
          <Download className="h-4 w-4" />
          <span>出力</span>
        </button>
      </div>

      {/* フィルター */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              対象月
            </label>
            <input
              type="month"
              value={format(selectedMonth, 'yyyy-MM')}
              onChange={(e) => setSelectedMonth(new Date(e.target.value))}
              className="w-full border border-gray-300 rounded-md p-2"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              従業員
            </label>
            <select
              value={selectedUser}
              onChange={(e) => setSelectedUser(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2"
            >
              {mockUsers.map(user => (
                <option key={user.id} value={user.id}>{user.name}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* 集計情報 */}
      <div className="bg-white shadow rounded-lg px-6 py-4">
        <div className="flex items-center justify-between">
          <div className="text-lg font-bold text-gray-900">
            {format(selectedMonth, 'yyyy年MM月', { locale: ja })} - {getUserName(selectedUser)}
          </div>
          <div className="flex items-center gap-8">
            <div className="text-sm">
              <span className="text-gray-600">出勤日数: </span>
              <span className="font-semibold text-gray-900">{statusCounts.present}日</span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">休暇日数: </span>
              <span className="font-semibold text-gray-900">{statusCounts['paid-leave'] + statusCounts['special-leave']}日</span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">遅刻回数: </span>
              <span className="font-semibold text-gray-900">{statusCounts.late}回</span>
            </div>
            <div className="text-sm">
              <span className="text-gray-600">総労働時間: </span>
              <span className="font-semibold text-gray-900">
                {(() => {
                  const totalMinutes = filteredRecords.reduce((sum, record) => {
                    return sum + (record.totalHours * 60);
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
                <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
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
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                  遅刻早退
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                  備考
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {daysInMonth.map((date) => {
                const attendance = getAttendanceForDate(date);
                const isCurrentDay = isToday(date);
                const dayOfWeek = date.getDay();
                const dayName = format(date, 'E', { locale: ja });
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
                          dayOfWeek === 0 ? 'text-red-600' :
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
                    <td className="px-4 py-4 text-sm text-gray-900">
                      {(() => {
                        const application = getApprovedApplicationForDate(date);

                        // attendanceのstatusを確認して有給・特別休暇を表示
                        if (attendance?.status === 'paid-leave') {
                          return (
                            <div className="flex flex-col gap-1">
                              <span className="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">有給休暇</span>
                              {application?.reason && application.type === 'vacation' && (
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

                        // 申請がある場合は表示（残業申請など）
                        if (application) {
                          // 申請タイプに応じたスタイルを適用
                          let badgeStyle = 'px-2 py-1 text-xs font-medium rounded ';
                          switch(application.type) {
                            case 'overtime':
                              badgeStyle += 'bg-orange-100 text-orange-800';
                              break;
                            case 'sick-leave':
                              badgeStyle += 'bg-purple-100 text-purple-800';
                              break;
                            case 'shift-change':
                              badgeStyle += 'bg-green-100 text-green-800';
                              break;
                            default:
                              badgeStyle += 'bg-gray-100 text-gray-800';
                          }

                          return (
                            <div className="flex flex-col gap-1">
                              <span className={badgeStyle}>
                                {getApplicationTypeText(application.type)}
                              </span>
                              {application.reason && (
                                <span className="text-xs text-gray-600">{application.reason}</span>
                              )}
                            </div>
                          );
                        }
                        return <span className="text-gray-400">-</span>;
                      })()}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* 出力フォーマット選択モーダル */}
      {showExportModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">出力形式を選択</h3>
            <div className="space-y-3 mb-6">
              <label className="flex items-center space-x-3">
                <input
                  type="checkbox"
                  checked={exportFormats.csv}
                  onChange={(e) => setExportFormats({ ...exportFormats, csv: e.target.checked })}
                  className="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                />
                <span className="text-sm font-medium text-gray-700">CSV形式</span>
              </label>
              <label className="flex items-center space-x-3 opacity-50 cursor-not-allowed">
                <input
                  type="checkbox"
                  checked={exportFormats.pdf}
                  onChange={(e) => setExportFormats({ ...exportFormats, pdf: e.target.checked })}
                  className="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                  disabled
                />
                <span className="text-sm font-medium text-gray-700">PDF形式（開発中）</span>
              </label>
            </div>
            <div className="flex space-x-3">
              <button
                type="button"
                onClick={handleExport}
                className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md font-medium"
              >
                出力
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowExportModal(false);
                  setExportFormats({ csv: false, pdf: false });
                }}
                className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md font-medium"
              >
                キャンセル
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}