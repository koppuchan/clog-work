import React from 'react';
import StaffLayout from '@/Layouts/StaffLayout';
import { useState, useEffect } from 'react';
import { format, startOfMonth, endOfMonth } from 'date-fns';
import { ja } from 'date-fns/locale';
import { mockAttendanceRecords } from '@/lib/mockData';
import { AlertTriangle, AlertCircle } from 'lucide-react';

function StaffPage() {
  const [currentTime, setCurrentTime] = useState(new Date());
  const [breakStartCount, setBreakStartCount] = useState(0);
  const [breakEndCount, setBreakEndCount] = useState(0);

  // 現在時刻を更新
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentTime(new Date());
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  // ログインスタッフID（仮: 田中太郎）
  const currentUserId = '1'; // 実際は認証システムから取得

  // 今月の労働時間を計算
  const calculateMonthlyOvertimeHours = () => {
    const monthStart = startOfMonth(currentTime);
    const monthEnd = endOfMonth(currentTime);

    const monthlyRecords = mockAttendanceRecords.filter(record => {
      const recordDate = new Date(record.date);
      return record.userId === currentUserId &&
             recordDate >= monthStart &&
             recordDate <= monthEnd;
    });

    const totalHours = monthlyRecords.reduce((sum, record) => sum + record.totalHours, 0);
    // 法定労働時間を超えた時間を計算（月の稼働日数 × 8時間を基準とする簡易計算）
    const workDays = monthlyRecords.filter(r => r.clockIn).length;
    const standardHours = workDays * 8;
    const overtimeHours = Math.max(0, totalHours - standardHours);

    return { totalHours, overtimeHours };
  };

  const { totalHours, overtimeHours } = calculateMonthlyOvertimeHours();
  const notificationThreshold = 45; // 通知時間
  const limitThreshold = 80; // 限度時間

  const handleClockIn = () => {
    console.log('出勤打刻');
    // 出勤打刻のロジック
  };

  const handleClockOut = () => {
    console.log('退勤打刻');
    // 退勤打刻のロジック
  };

  const handleBreakStart = () => {
    if (breakStartCount < 2) {
      setBreakStartCount(breakStartCount + 1);
      console.log(`休憩開始 (${breakStartCount + 1}回目)`);
      // 休憩開始のロジック
    }
  };

  const handleBreakEnd = () => {
    if (breakEndCount < 2) {
      setBreakEndCount(breakEndCount + 1);
      console.log(`休憩終了 (${breakEndCount + 1}回目)`);
      // 休憩終了のロジック
    }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">打刻</h1>

      {/* 労務アラート */}
      {overtimeHours >= limitThreshold && (
        <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow">
          <div className="flex items-start">
            <AlertCircle className="h-6 w-6 text-red-600 mt-0.5 flex-shrink-0" />
            <div className="ml-3">
              <h3 className="text-sm font-bold text-red-800">【警告】残業時間超過</h3>
              <p className="mt-1 text-sm text-red-700">
                今月の残業時間が<strong>{overtimeHours.toFixed(1)}時間</strong>に達しています。
                残業時間が非常に多くなっています。健康管理に十分注意してください。
              </p>
            </div>
          </div>
        </div>
      )}

      {overtimeHours >= notificationThreshold && overtimeHours < limitThreshold && (
        <div className="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg shadow">
          <div className="flex items-start">
            <AlertTriangle className="h-6 w-6 text-yellow-600 mt-0.5 flex-shrink-0" />
            <div className="ml-3">
              <h3 className="text-sm font-bold text-yellow-800">【注意】残業時間が多くなっています</h3>
              <p className="mt-1 text-sm text-yellow-700">
                今月の残業時間が<strong>{overtimeHours.toFixed(1)}時間</strong>に達しています。
                残業時間が多くなっています。体調管理に注意してください。
              </p>
            </div>
          </div>
        </div>
      )}

      {/* 打刻画面 */}
      <div className="bg-white shadow rounded-lg p-8">
        <div className="text-center space-y-6">
          <div className="text-6xl font-bold text-gray-900 font-mono">
            {format(currentTime, 'HH:mm:ss')}
          </div>
          <div className="text-xl text-gray-600">
            {format(currentTime, 'yyyy年MM月dd日 (E)', { locale: ja })}
          </div>

          <div className="grid grid-cols-2 gap-4 max-w-md mx-auto mt-8">
            <button
              onClick={handleClockIn}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-6 px-4 rounded-lg text-lg transition-colors"
            >
              出勤
            </button>
            <button
              onClick={handleClockOut}
              className="bg-red-600 hover:bg-red-700 text-white font-semibold py-6 px-4 rounded-lg text-lg transition-colors"
            >
              退勤
            </button>
            <button
              onClick={handleBreakStart}
              disabled={breakStartCount >= 2}
              className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                breakStartCount >= 2
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-yellow-600 hover:bg-yellow-700 text-white'
              }`}
            >
              休憩開始{breakStartCount > 0 && ` (${breakStartCount}/2)`}
            </button>
            <button
              onClick={handleBreakEnd}
              disabled={breakEndCount >= 2}
              className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                breakEndCount >= 2
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-green-600 hover:bg-green-700 text-white'
              }`}
            >
              休憩終了{breakEndCount > 0 && ` (${breakEndCount}/2)`}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

StaffPage.layout = (page: React.ReactNode) => <StaffLayout>{page}</StaffLayout>;

export default StaffPage;
