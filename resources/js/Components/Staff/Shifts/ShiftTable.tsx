import React, { useRef, useEffect } from 'react';
import { format, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import type { ShiftUser, ShiftData } from '@/types/staff/shifts';

interface ShiftTableProps {
  users: ShiftUser[];
  daysInMonth: Date[];
  getHoliday: (date: Date) => string | undefined;
  getShiftForDate: (date: Date, userId: number) => ShiftData | undefined;
  getTimeRange: (shift: ShiftData) => string;
}

export function ShiftTable({
  users,
  daysInMonth,
  getHoliday,
  getShiftForDate,
  getTimeRange,
}: ShiftTableProps) {
  const todayRef = useRef<HTMLTableCellElement>(null);

  useEffect(() => {
    todayRef.current?.scrollIntoView({ inline: 'center', behavior: 'instant' });
  }, [daysInMonth]);

  return (
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
                    ref={isCurrentDay ? todayRef : undefined}
                    className={`px-2 py-3 text-center text-xs font-medium uppercase tracking-wider min-w-[60px] ${
                      isCurrentDay ? 'bg-green-100' : ''
                    }`}
                  >
                    <div className="flex flex-col items-center gap-1">
                      <span className="text-gray-900">{format(date, 'd')}</span>
                      <span
                        className={`text-xs ${
                          holiday || dayOfWeek === 0
                            ? 'text-red-600'
                            : dayOfWeek === 6
                            ? 'text-blue-600'
                            : 'text-gray-500'
                        }`}
                      >
                        {dayName}
                      </span>
                    </div>
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {users.map((user) => (
              <tr key={user.id} className={user.is_self ? 'bg-green-50' : ''}>
                <td
                  className={`px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 z-10 ${
                    user.is_self ? 'bg-green-50' : 'bg-white'
                  }`}
                >
                  <div className="flex items-center gap-2">
                    {user.name}
                    {user.is_self && (
                      <span className="px-2 py-0.5 text-xs font-medium bg-green-600 text-white rounded">
                        自分
                      </span>
                    )}
                  </div>
                </td>
                {daysInMonth.map((date) => {
                  const shift = getShiftForDate(date, user.id);
                  const isCurrentDay = isToday(date);

                  return (
                    <td
                      key={date.toString()}
                      className={`px-2 py-3 text-center text-xs ${isCurrentDay ? 'bg-green-50' : ''} ${
                        user.is_self ? 'bg-green-50' : ''
                      }`}
                    >
                      {shift ? (
                        <div className="flex flex-col items-center gap-1">
                          <span
                            className={`px-2 py-1 rounded font-medium ${shift.color || 'bg-gray-100'} ${
                              shift.color?.replace('bg-', 'text-').replace('-100', '-800') || 'text-gray-800'
                            }`}
                          >
                            {shift.shift_pattern_name || '-'}
                          </span>
                          <span className="text-gray-600 text-xs whitespace-nowrap">{getTimeRange(shift)}</span>
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
  );
}
