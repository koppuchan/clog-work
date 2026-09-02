import { useRef, useState, useEffect, useMemo } from 'react';
import { format } from 'date-fns';
import { ShiftCalendarProps, ShiftLeave } from '@/types/shift';
import { User } from '@/types';
import { getHolidayName } from '@/lib/holidays';

interface DepartmentGroup {
  department: { id: number; name: string } | null;
  users: User[];
}

/**
 * シフトカレンダーコンポーネント
 *
 * 月次シフト表を表示する中核コンポーネント。
 *
 * 主な機能：
 * - 部署ごとにスタッフをグループ化して表示
 * - スタッフ名の固定列と横スクロール可能な日付列
 * - 各セルでのシフトタイプ表示と選択
 * - 日付ヘッダークリックでの一括シフト設定
 * - スタッフ名クリックでのシフトパターン適用
 * - 月次統計（出勤日数、勤務時間、休日）の表示
 * - 日次統計（シフトタイプ別人数）の折りたたみ可能な表示
 * - 勤務可能曜日に基づくスタイリング
 */
export default function ShiftCalendar({
  monthDays,
  activeUsers,
  departments,
  shifts,
  selectedCell,
  selectedDayForBulk,
  shiftTypeInfo,
  restShiftInfo,
  onCellClick,
  onDayHeaderClick,
  onShiftTypeSelect,
  onBulkShiftTypeSelect,
  onUserNameClick,
  getShiftTypeInfo,
  getMonthlyStats,
  getAllStaffTotals,
  getShiftCountsByDate,
  leaves = [],
}: ShiftCalendarProps) {
  // 承認済みの休暇をユーザー×日付で引けるようにする
  const leaveByUserDate = useMemo(() => {
    const map = new Map<string, ShiftLeave>();
    leaves.forEach((leave) => map.set(`${leave.user_id}-${leave.date}`, leave));
    return map;
  }, [leaves]);

  // 日ごとに出勤予定人数から差し引く人数
  const leaveDeductionByDate = useMemo(() => {
    const map = new Map<string, number>();
    leaves.forEach((leave) => {
      map.set(leave.date, (map.get(leave.date) ?? 0) + leave.deduction);
    });
    return map;
  }, [leaves]);

  const calendarContainerRef = useRef<HTMLDivElement>(null);
  const headerContainerRef = useRef<HTMLDivElement>(null);
  const [bulkModalPos, setBulkModalPos] = useState<{ top: number; left: number } | null>(null);
  const [cellModalPos, setCellModalPos] = useState<{ top: number; left: number } | null>(null);
  const [isPatternCountsOpen, setIsPatternCountsOpen] = useState(false);

  // スタッフを部署ごとにグループ化
  const groupedUsers = useMemo((): DepartmentGroup[] => {
    const groups = new Map<number | 'none', DepartmentGroup>();

    activeUsers.forEach(user => {
      const deptId = user.departmentId ?? 'none';
      if (!groups.has(deptId)) {
        const dept = typeof deptId === 'number'
          ? (departments ?? []).find(d => d.id === deptId) ?? null
          : null;
        groups.set(deptId, {
          department: dept ? { id: dept.id, name: dept.name } : null,
          users: [],
        });
      }
      groups.get(deptId)!.users.push(user);
    });

    // 部署IDの昇順でソート、未所属は末尾
    return Array.from(groups.entries())
      .sort(([a], [b]) => {
        if (a === 'none') return 1;
        if (b === 'none') return -1;
        return (a as number) - (b as number);
      })
      .map(([, group]) => group);
  }, [activeUsers, departments]);



  // 一括シフト選択UIの位置をヘッダーのクリック位置から計算
  useEffect(() => {
    if (!selectedDayForBulk || !headerContainerRef.current) {
      setBulkModalPos(null);
      return;
    }
    const headerEl = headerContainerRef.current;
    const dayHeaders = headerEl.querySelectorAll('[data-day-index]');
    const targetHeader = dayHeaders[selectedDayForBulk.dayIndex] as HTMLElement | undefined;
    if (targetHeader) {
      const rect = targetHeader.getBoundingClientRect();
      setBulkModalPos({
        top: rect.bottom + 4,
        left: Math.max(8, rect.left - 170),
      });
    }
  }, [selectedDayForBulk]);

  const handleCalendarScroll = (e: React.UIEvent<HTMLDivElement>) => {
    const scrollLeft = e.currentTarget.scrollLeft;
    if (headerContainerRef.current) {
      headerContainerRef.current.scrollLeft = scrollLeft;
    }
  };

  const getShiftsForDate = (date: Date, userId?: string) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    const dayShifts = shifts.filter(shift => shift.date === dateStr);
    if (userId) {
      return dayShifts.filter(shift => shift.userId === userId);
    }
    return dayShifts;
  };

  return (
    <>
      {/* 一括シフト選択UI（fixedでoverflow制約から解放） */}
      {selectedDayForBulk && bulkModalPos && (
        <div
          className="fixed z-50 bg-white border-2 border-blue-500 rounded-lg shadow-xl p-4"
          style={{
            top: `${bulkModalPos.top}px`,
            left: `${bulkModalPos.left}px`,
            width: '400px'
          }}
        >
          <div className="text-sm font-semibold text-gray-900 mb-3">
            {format(new Date(selectedDayForBulk.date), 'yyyy年MM月dd日')} - 全スタッフのシフトを設定
          </div>
          <div className="text-xs text-gray-600 mb-3">
            選択したシフトパターンを全スタッフに一括適用します
          </div>
          <div className="grid grid-cols-3 gap-2 mb-3">
            {shiftTypeInfo.map((info) => (
              <button
                key={info.type}
                onClick={() => onBulkShiftTypeSelect(info.type)}
                className={`p-2 rounded text-xs transition-all ${info.color} ${info.textColor} border-2 border-transparent hover:border-gray-400 hover:shadow-lg`}
              >
                <div className="font-medium text-xs mb-1">{info.name}</div>
                <div className="text-[10px] opacity-75">{info.timeRange}</div>
              </button>
            ))}
            <button
              key={restShiftInfo.type}
              onClick={() => onBulkShiftTypeSelect(restShiftInfo.type)}
              className={`p-2 rounded text-xs transition-all ${restShiftInfo.color} ${restShiftInfo.textColor} border-2 border-transparent hover:border-gray-400 hover:shadow-lg`}
            >
              <div className="font-medium text-xs mb-1">{restShiftInfo.name}</div>
              <div className="text-[10px] opacity-75">{restShiftInfo.timeRange}</div>
            </button>
          </div>
          <button
            onClick={() => onDayHeaderClick('', -1)}
            className="w-full text-xs text-gray-600 hover:text-gray-800 py-2 border border-gray-300 rounded hover:bg-gray-50"
          >
            キャンセル
          </button>
        </div>
      )}

      {/* セルクリック時のシフト選択UI（fixedでoverflow制約から解放） */}
      {selectedCell && cellModalPos && (
        <div
          className="fixed z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-2"
          style={{
            top: `${cellModalPos.top}px`,
            left: `${cellModalPos.left}px`,
            width: '300px'
          }}
        >
          <div className="text-xs font-semibold text-gray-700 mb-2">勤務形態を選択</div>
          <div className="grid grid-cols-3 gap-1">
            {shiftTypeInfo.map((info) => (
              <button
                key={info.type}
                onClick={() => onShiftTypeSelect(info.type)}
                className={`p-1 rounded text-xs transition-colors ${info.color} ${info.textColor} border border-transparent hover:border-gray-300 hover:shadow-md`}
              >
                <div className="font-medium text-xs">{info.name}</div>
              </button>
            ))}
            <button
              key={restShiftInfo.type}
              onClick={() => onShiftTypeSelect(restShiftInfo.type)}
              className={`p-1 rounded text-xs transition-colors ${restShiftInfo.color} ${restShiftInfo.textColor} border border-transparent hover:border-gray-300 hover:shadow-md`}
            >
              <div className="font-medium text-xs">{restShiftInfo.name}</div>
            </button>
          </div>
          <button
            onClick={() => { onCellClick('', '', -1); setCellModalPos(null); }}
            className="mt-2 w-full text-xs text-gray-600 hover:text-gray-800"
          >
            キャンセル
          </button>
        </div>
      )}

    <div className="flex" style={{ minHeight: '400px' }}>
      {/* 固定スタッフ名 */}
      <div className="w-24 flex-shrink-0 mr-4">
        <div className='p-1'>
          <div className="font-medium text-gray-900 p-2 bg-gray-50 rounded-lg text-center mb-2">
            スタッフ
          </div>
        </div>
        <div>
          {groupedUsers.map((group) => (
            <div key={group.department?.id ?? 'no-department'} className="border border-gray-300 rounded-lg mb-3">
              {/* 部署名ヘッダー */}
              <div className="bg-gray-100 px-2 py-1 border-b border-gray-300">
                <div className="font-semibold text-gray-600 text-[10px] truncate">
                  {group.department?.name ?? '未所属'}
                </div>
              </div>
              {/* 部署内スタッフ */}
              <div className="p-1">
                {group.users.map((user, idx) => (
                  <div
                    key={user.id}
                    className={`h-10 bg-blue-50 rounded-lg flex items-center justify-start px-2 cursor-pointer hover:bg-blue-100 transition-colors ${idx < group.users.length - 1 ? 'mb-2' : ''}`}
                    onClick={() => onUserNameClick(user.id)}
                    title={`クリックして${user.name}のシフトパターンを適用`}
                  >
                    <div className="font-medium text-gray-900 text-xs truncate">{user.name}</div>
                  </div>
                ))}
                {/* 部署ごとの人数を数えられるよう小計を置く */}
                <div className="h-10 bg-green-50 rounded-lg mt-2 flex items-center justify-center">
                  <div className="font-semibold text-green-700 text-[10px] truncate">
                    {group.department?.name ?? '未所属'} 合計
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
        {/* 合計行 */}
        <div className="border-t-2 border-gray-400 pt-2"></div>
        <div className="p-1">
          <div className="h-10 bg-green-50 rounded-lg mb-2 flex items-center justify-center">
            <div className="font-semibold text-green-700 text-xs">合計</div>
          </div>
        </div>
        {/* シフトパターン別（折りたたみ） */}
        <div
          className="h-8 bg-gray-50 rounded-lg mb-2 flex items-center justify-center cursor-pointer hover:bg-gray-100 transition-colors select-none"
          onClick={() => setIsPatternCountsOpen(!isPatternCountsOpen)}
        >
          <div className="font-medium text-gray-600 text-xs">
            パターン別
          </div>
        </div>
        {isPatternCountsOpen && (
          <div>
            {shiftTypeInfo.map(info => (
              <div key={info.type} className={`h-10 rounded-lg mb-2 flex items-center justify-center ${info.color} ${info.textColor}`}>
                <div className="font-medium text-xs text-center">
                  {info.name}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* スクロール可能なカレンダー部分 */}
      <div className="flex-1 overflow-hidden">
        {/* カレンダーヘッダー */}
        <div
          ref={headerContainerRef}
          className="overflow-x-hidden overflow-y-hidden"
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        >
          <div className="p-1" style={{ minWidth: `${monthDays.length * 60 + 224}px` }}>
          <div className="flex gap-1 mb-2">
            {monthDays.map((day, index) => {
              const dayOfWeek = day.getDay();
              const isHoliday = !!getHolidayName(day);
              const isSunday = dayOfWeek === 0;
              const isSaturday = dayOfWeek === 6;
              const isRedDay = isSunday || isHoliday;
              const isBlueDay = isSaturday && !isHoliday;
              const dateStr = format(day, 'yyyy-MM-dd');
              const isSelected = selectedDayForBulk?.date === dateStr;

              return (
                <div
                  key={index}
                  data-day-index={index}
                  className={`w-14 text-center p-1 rounded-lg cursor-pointer transition-all ${
                    isSelected
                      ? 'bg-blue-500 border-2 border-blue-700 shadow-md'
                      : isRedDay
                        ? 'bg-red-50 border border-red-200 hover:bg-red-100'
                        : isBlueDay
                          ? 'bg-blue-50 border border-blue-200 hover:bg-blue-100'
                          : 'bg-gray-50 hover:bg-gray-100'
                  }`}
                  onClick={() => onDayHeaderClick(dateStr, index)}
                >
                  <div className={`font-medium text-xs ${
                    isSelected
                      ? 'text-white'
                      : isRedDay
                        ? 'text-red-700'
                        : isBlueDay
                          ? 'text-blue-700'
                          : 'text-gray-900'
                  }`}>
                    {['日', '月', '火', '水', '木', '金', '土'][dayOfWeek]}
                  </div>
                  <div className={`text-xs ${
                    isSelected
                      ? 'text-white'
                      : isRedDay
                        ? 'text-red-600'
                        : isBlueDay
                          ? 'text-blue-600'
                          : 'text-gray-600'
                  }`}>
                    {format(day, 'dd')}
                  </div>
                </div>
              );
            })}
            {/* 出勤ヘッダー */}
            <div className="w-16 text-center p-1 rounded-lg bg-green-100 border border-green-300 ml-2 flex items-center justify-center">
              <div className="font-medium text-xs text-green-800">出勤</div>
            </div>
            {/* 勤務時間ヘッダー */}
            <div className="w-16 text-center p-1 rounded-lg bg-blue-100 border border-blue-300 flex items-center justify-center">
              <div className="font-medium text-xs text-blue-800">勤務時間</div>
            </div>
            {/* 休日ヘッダー */}
            <div className="w-16 text-center p-1 rounded-lg bg-orange-100 border border-orange-300 flex items-center justify-center">
              <div className="font-medium text-xs text-orange-800">休日</div>
            </div>
          </div>
          </div>
        </div>

        {/* スタッフ別シフト表 */}
        <div
          ref={calendarContainerRef}
          className="overflow-x-auto overflow-y-visible"
          onScroll={handleCalendarScroll}
        >
          <div
            className="relative"
            style={{ minWidth: `${monthDays.length * 60 + 224}px` }}
          >
            {groupedUsers.map((group) => (
              <div key={group.department?.id ?? 'no-department'} className="border border-gray-300 rounded-lg mb-3">
                {/* 部署名ヘッダー（左固定列と高さ同期） */}
                <div className="bg-gray-100 px-2 py-1 border-b border-gray-300">
                  <div className="font-semibold text-gray-600 text-[10px]">
                    {group.department?.name ?? '未所属'}
                  </div>
                </div>
                {/* 部署内スタッフのシフト行 */}
                <div className="p-1">
                  {group.users.map((user, userIndexInGroup) => {
                    const stats = getMonthlyStats(user.id);
                    return (
                      <div key={user.id} className={`flex gap-1 relative ${userIndexInGroup < group.users.length - 1 ? 'mb-2' : ''}`}>
                        {/* 各日のシフト */}
                        {monthDays.map((day, dayIndex) => {
                          const dayShifts = getShiftsForDate(day, user.id);
                          const dateStr = format(day, 'yyyy-MM-dd');

                          const dayOfWeek = day.getDay();
                          const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
                          const dayKey = dayKeys[dayOfWeek];
                          const isWorkDay = !!user.shiftPatterns?.[dayKey];

                          const isScheduledOff = !isWorkDay && dayShifts.length > 0 && dayShifts[0].shiftType === 'rest';

                          const leave = leaveByUserDate.get(`${user.id}-${dateStr}`);
                          // 全日の休暇はセル全体、半日・時間有給はシフト名の下にバッジを出す
                          const isFullDayLeave = leave?.is_full_day === true;

                          return (
                            <div
                              key={dayIndex}
                              className={`w-14 h-10 shrink-0 border rounded-lg cursor-pointer transition-all overflow-hidden ${
                                isFullDayLeave
                                  ? ''
                                  : dayShifts.length > 0
                                  ? isScheduledOff
                                    ? 'bg-gray-200 hover:bg-gray-300'
                                    : `${getShiftTypeInfo(dayShifts[0].shiftType).color} ${getShiftTypeInfo(dayShifts[0].shiftType).textColor}`
                                  : 'bg-gray-200 hover:bg-gray-300'
                              } ${selectedCell?.userId === user.id && selectedCell?.date === dateStr ? 'ring-2 ring-blue-500' : ''}`}
                              style={
                                isFullDayLeave
                                  ? { backgroundColor: leave!.background_color, color: leave!.text_color }
                                  : undefined
                              }
                              onClick={(e) => {
                                const rect = e.currentTarget.getBoundingClientRect();
                                setCellModalPos({
                                  top: rect.bottom + 4,
                                  left: Math.max(8, rect.left - 100),
                                });
                                onCellClick(user.id, dateStr, dayIndex);
                              }}
                            >
                              {isFullDayLeave ? (
                                <div className="h-full flex items-center justify-center">
                                  <div className="font-bold text-xs text-center">{leave!.label}</div>
                                </div>
                              ) : leave ? (
                                <div className="h-full flex flex-col">
                                  <div className="flex-1 flex items-center justify-center font-medium text-[10px] leading-none">
                                    {dayShifts.length > 0
                                      ? getShiftTypeInfo(dayShifts[0].shiftType).name
                                      : '休み'}
                                  </div>
                                  <div
                                    className="flex-1 flex items-center justify-center font-bold text-[10px] leading-none"
                                    style={{ backgroundColor: leave.background_color, color: leave.text_color }}
                                  >
                                    {leave.label}
                                  </div>
                                </div>
                              ) : dayShifts.length > 0 ? (
                                <div className="h-full flex items-center justify-center">
                                  <div className={`font-medium text-xs text-center ${isScheduledOff ? 'text-gray-600' : ''}`}>
                                    {getShiftTypeInfo(dayShifts[0].shiftType).name}
                                  </div>
                                </div>
                              ) : (
                                <div className="h-full flex items-center justify-center text-xs text-gray-600">
                                  休み
                                </div>
                              )}
                            </div>
                          );
                        })}

                        {/* 出勤セル */}
                        <div className="w-16 h-10 shrink-0 bg-green-100 border border-green-300 rounded-lg flex items-center justify-center ml-2">
                          <div className="text-xs font-semibold text-green-800">
                            {stats.workDays}日
                          </div>
                        </div>
                        {/* 勤務時間セル */}
                        <div className="w-16 h-10 shrink-0 bg-blue-100 border border-blue-300 rounded-lg flex items-center justify-center">
                          <div className="text-xs font-semibold text-blue-800">
                            {stats.totalHours}h
                          </div>
                        </div>
                        {/* 休日セル */}
                        <div className="w-16 h-10 shrink-0 bg-orange-100 border border-orange-300 rounded-lg flex items-center justify-center">
                          <div className="text-xs font-semibold text-orange-800">
                            {stats.restDays}日
                          </div>
                        </div>

                        {/* シフト選択ドロップダウンは fixed でルートに表示 */}
                      </div>
                    );
                  })}

                  {/* 部署ごとの出勤予定人数 */}
                  <div className="flex gap-1 mt-2">
                    {monthDays.map((day, dayIndex) => {
                      const dateStr = format(day, 'yyyy-MM-dd');
                      // 「休み」は出勤ではないため人数に数えない
                      const scheduled = group.users.filter((u) =>
                        getShiftsForDate(day, u.id).some((s) => s.shiftType !== 'rest')
                      ).length;
                      const deducted = group.users.reduce(
                        (sum, u) => sum + (leaveByUserDate.get(`${u.id}-${dateStr}`)?.deduction ?? 0),
                        0
                      );
                      const count = Math.max(0, Math.round((scheduled - deducted) * 100) / 100);

                      return (
                        <div
                          key={dayIndex}
                          className="w-14 h-10 shrink-0 bg-green-50 border rounded-lg flex items-center justify-center"
                        >
                          <div className="text-sm font-semibold text-green-700">{count}人</div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            ))}

            {/* 合計人数行 */}
            <div className="border-t-2 border-gray-400 pt-2"></div>
            <div className='p-1'>
              <div className="flex gap-1 mb-2">
                {monthDays.map((day, dayIndex) => {
                  const shiftCounts = getShiftCountsByDate(day);
                  const scheduledCount = Object.values(shiftCounts).reduce((sum, count) => sum + count, 0);

                  // 承認済みの休暇の分を差し引く（全日-1、半日-0.5、時間有給は按分）
                  const deduction = leaveDeductionByDate.get(format(day, 'yyyy-MM-dd')) ?? 0;
                  const totalCount = Math.max(0, Math.round((scheduledCount - deduction) * 100) / 100);

                  return (
                    <div
                      key={dayIndex}
                      className="w-14 h-10 shrink-0 bg-green-50 border rounded-lg flex items-center justify-center"
                    >
                      <div className="text-sm font-bold text-green-700">{totalCount}人</div>
                    </div>
                  );
                })}
                {(() => {
                  const totals = getAllStaffTotals();
                  return (
                    <>
                      <div className="w-16 h-10 bg-green-100 border border-green-300 rounded-lg flex items-center justify-center ml-2">
                        <div className="text-xs font-bold text-green-800">
                          {totals.totalWorkDays}日
                        </div>
                      </div>
                      <div className="w-16 h-10 bg-blue-100 border border-blue-300 rounded-lg flex items-center justify-center">
                        <div className="text-xs font-bold text-blue-800">
                          {totals.totalHours}h
                        </div>
                      </div>
                      <div className="w-16 h-10 bg-orange-100 border border-orange-300 rounded-lg flex items-center justify-center">
                        <div className="text-xs font-bold text-orange-800">
                          {totals.totalRestDays}日
                        </div>
                      </div>
                    </>
                  );
                })()}
              </div>
            </div>

            {/* シフトパターン別人数行（左固定列のトグルで制御） */}
            {isPatternCountsOpen && <div className="h-8 mb-2"></div>}
            {isPatternCountsOpen && shiftTypeInfo.map(info => (
              <div key={info.type} className="flex gap-1 mb-2">
                {monthDays.map((day, dayIndex) => {
                  const shiftCounts = getShiftCountsByDate(day);
                  const count = shiftCounts[info.type] || 0;

                  return (
                    <div
                      key={dayIndex}
                      className={`w-14 h-10 shrink-0 rounded-lg flex items-center justify-center ${info.color} border`}
                    >
                      <div className={`text-sm font-semibold ${info.textColor}`}>{count}人</div>
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
    </>
  );
}
