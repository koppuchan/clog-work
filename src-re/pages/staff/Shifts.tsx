
import { useState } from 'react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, addMonths, subMonths, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { mockUsers, mockShifts, shiftTypeInfo } from '@/lib/mockData';
import { Shift, ShiftTypeInfo, User } from '@/types';

export default function StaffShiftsPage() {
  const [currentDate, setCurrentDate] = useState(new Date());

  // 仮のログインユーザー
  const currentUser = mockUsers[0];

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

  // 祝日判定
  const getHoliday = (date: Date): string | undefined => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return holidays[dateStr];
  };

  // 指定日のシフトを取得
  const getShiftForDate = (date: Date, userId: string) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    const shift = mockShifts.find((s: Shift) => s.date === dateStr && s.userId === userId);
    if (shift) {
      return shiftTypeInfo.find((info: ShiftTypeInfo) => info.type === shift.shiftType);
    }
    return undefined;
  };

  // 前月へ
  const previousMonth = () => {
    setCurrentDate(subMonths(currentDate, 1));
  };

  // 次月へ
  const nextMonth = () => {
    setCurrentDate(addMonths(currentDate, 1));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">シフト確認</h1>

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

      {/* シフト表 */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32 sticky left-0 bg-gray-50 z-10">
                  スタッフ
                </th>
                {daysInMonth.map((date) => {
                  const dayOfWeek = date.getDay();
                  const dayName = format(date, 'E', { locale: ja });
                  const holiday = getHoliday(date);
                  const isCurrentDay = isToday(date);

                  return (
                    <th
                      key={date.toString()}
                      className={`px-2 py-3 text-center text-xs font-medium uppercase tracking-wider min-w-[60px] ${
                        isCurrentDay ? 'bg-green-100' : ''
                      }`}
                    >
                      <div className="flex flex-col items-center gap-1">
                        <span className="text-gray-900">{format(date, 'd')}</span>
                        <span className={`text-xs ${
                          holiday || dayOfWeek === 0 ? 'text-red-600' :
                          dayOfWeek === 6 ? 'text-blue-600' :
                          'text-gray-500'
                        }`}>
                          {dayName}
                        </span>
                      </div>
                    </th>
                  );
                })}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {mockUsers.map((user: User) => (
                <tr key={user.id} className={user.id === currentUser.id ? 'bg-green-50' : ''}>
                  <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white z-10">
                    <div className="flex items-center gap-2">
                      {user.name}
                      {user.id === currentUser.id && (
                        <span className="px-2 py-0.5 text-xs font-medium bg-green-600 text-white rounded">自分</span>
                      )}
                    </div>
                  </td>
                  {daysInMonth.map((date) => {
                    const shift = getShiftForDate(date, user.id);
                    const isCurrentDay = isToday(date);

                    return (
                      <td
                        key={date.toString()}
                        className={`px-2 py-3 text-center text-xs ${
                          isCurrentDay ? 'bg-green-50' : ''
                        } ${user.id === currentUser.id ? 'bg-green-50' : ''}`}
                      >
                        {shift ? (
                          <div className="flex flex-col items-center gap-1">
                            <span className={`px-2 py-1 rounded font-medium ${shift.color} ${shift.textColor}`}>
                              {shift.name}
                            </span>
                            <span className="text-gray-600 text-xs whitespace-nowrap">
                              {shift.timeRange}
                            </span>
                          </div>
                        ) : (
                          <span className="text-gray-400">-</span>
                        )}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* シフト凡例 */}
      <div className="bg-white shadow rounded-lg p-4">
        <h3 className="text-sm font-semibold text-gray-900 mb-3">シフト凡例</h3>
        <div className="flex flex-wrap gap-4">
          {shiftTypeInfo.map((info: ShiftTypeInfo) => (
            <div key={info.type} className="flex items-center gap-2">
              <span className={`px-3 py-1 text-xs font-medium rounded ${info.color} ${info.textColor}`}>
                {info.name}
              </span>
              <span className="text-sm text-gray-600">{info.timeRange}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
