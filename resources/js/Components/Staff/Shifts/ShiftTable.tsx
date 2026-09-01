import { Fragment, useRef, useEffect, useMemo } from 'react';
import { format, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import type { ShiftUser, ShiftData } from '@/types/staff/shifts';
import type { ShiftLeave } from '@/types/shift';

interface ShiftTableProps {
  users: ShiftUser[];
  daysInMonth: Date[];
  getHoliday: (date: Date) => string | undefined;
  getShiftForDate: (date: Date, userId: number) => ShiftData | undefined;
  getTimeRange: (shift: ShiftData) => string;
  leaves?: ShiftLeave[];
}

/** 部署名が未設定のスタッフをまとめる際の表示名 */
const NO_DEPARTMENT = '未所属';

export function ShiftTable({
  users,
  daysInMonth,
  getHoliday,
  getShiftForDate,
  getTimeRange,
  leaves = [],
}: ShiftTableProps) {
  const todayRef = useRef<HTMLTableCellElement>(null);

  // 承認済みの休暇をユーザー×日付で引けるようにする
  const leaveByUserDate = useMemo(() => {
    const map = new Map<string, ShiftLeave>();
    leaves.forEach((leave) => map.set(`${leave.user_id}-${leave.date}`, leave));
    return map;
  }, [leaves]);

  const leaveFor = (date: Date, userId: number): ShiftLeave | undefined =>
    leaveByUserDate.get(`${userId}-${format(date, 'yyyy-MM-dd')}`);

  useEffect(() => {
    todayRef.current?.scrollIntoView({ inline: 'center', behavior: 'instant' });
  }, [daysInMonth]);

  // 部署ごとにまとめる。並び順はサーバー側で整えてあるため保持する。
  const groups = useMemo(() => {
    const map = new Map<string, ShiftUser[]>();

    users.forEach((user) => {
      const key = user.department_name ?? NO_DEPARTMENT;
      map.set(key, [...(map.get(key) ?? []), user]);
    });

    return Array.from(map, ([name, members]) => ({ name, members }));
  }, [users]);

  // 部署がひとつしかない場合は見出しを出さず、従来どおりの見た目にする
  const showGroupHeader = groups.length > 1;

  const columnCount = daysInMonth.length + 1;

  /**
   * その日に出勤予定のスタッフ数
   *
   * 承認済みの休暇の分を差し引く。全日は1人、半日有給は0.5人、
   * 時間有給は所定労働時間に対する按分。
   */
  const countForDate = (date: Date, members: ShiftUser[]): number => {
    const scheduled = members.filter((user) => getShiftForDate(date, user.id)).length;
    const deduction = members.reduce(
      (sum, user) => sum + (leaveFor(date, user.id)?.deduction ?? 0),
      0
    );

    return Math.max(0, Math.round((scheduled - deduction) * 100) / 100);
  };

  return (
    <div className="bg-white shadow rounded-lg overflow-hidden">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-20">
                スタッフ
              </th>
              {daysInMonth.map((date) => {
                const holiday = getHoliday(date);
                const dayName = format(date, 'E', { locale: ja });
                const isCurrentDay = isToday(date);

                return (
                  <th
                    key={date.toString()}
                    ref={isCurrentDay ? todayRef : undefined}
                    className={`px-2 py-3 text-center text-xs font-medium uppercase tracking-wider min-w-[60px] ${
                      isCurrentDay ? 'bg-green-100' : ''
                    }`}
                  >
                    <div className="flex flex-col items-center">
                      <span className="text-gray-900">{format(date, 'd')}</span>
                      <span
                        className={
                          holiday || date.getDay() === 0
                            ? 'text-red-600'
                            : date.getDay() === 6
                              ? 'text-blue-600'
                              : 'text-gray-500'
                        }
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
            {groups.map((group) => (
              <Fragment key={group.name}>
                {showGroupHeader && (
                  <tr className="bg-gray-100">
                    <td
                      colSpan={columnCount}
                      className="px-4 py-2 text-sm font-semibold text-gray-700 sticky left-0 bg-gray-100"
                    >
                      {group.name}
                    </td>
                  </tr>
                )}

                {group.members.map((user) => (
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
                      const leave = leaveFor(date, user.id);

                      return (
                        <td
                          key={date.toString()}
                          className={`px-2 py-3 text-center text-xs ${isCurrentDay ? 'bg-green-50' : ''} ${
                            user.is_self ? 'bg-green-50' : ''
                          }`}
                        >
                          {leave?.is_full_day ? (
                            <span
                              className="inline-block px-2 py-1 rounded font-bold"
                              style={{
                                backgroundColor: leave.background_color,
                                color: leave.text_color,
                              }}
                            >
                              {leave.label}
                            </span>
                          ) : (
                            <div className="flex flex-col items-center gap-1">
                              {shift ? (
                                <>
                                  <span
                                    className={`px-2 py-1 rounded font-medium ${shift.color || 'bg-gray-100'}`}
                                  >
                                    {shift.shift_pattern_name ?? '—'}
                                  </span>
                                  {shift.start_time && (
                                    <span className="text-gray-500">{getTimeRange(shift)}</span>
                                  )}
                                </>
                              ) : (
                                !leave && <span className="text-gray-300">—</span>
                              )}
                              {leave && (
                                <span
                                  className="px-2 py-0.5 rounded font-bold"
                                  style={{
                                    backgroundColor: leave.background_color,
                                    color: leave.text_color,
                                  }}
                                >
                                  {leave.label}
                                </span>
                              )}
                            </div>
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}

                <tr className="bg-gray-50 text-xs">
                  <td className="px-4 py-2 font-medium text-gray-600 sticky left-0 bg-gray-50">
                    {showGroupHeader ? `${group.name} 合計` : '合計'}
                  </td>
                  {daysInMonth.map((date) => (
                    <td key={date.toString()} className="px-2 py-2 text-center text-gray-700">
                      {countForDate(date, group.members)}人
                    </td>
                  ))}
                </tr>
              </Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
