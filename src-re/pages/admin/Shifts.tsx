
import { useState, useRef } from 'react';
import { format, addDays, addMonths, startOfMonth, getDaysInMonth, endOfMonth } from 'date-fns';
import { mockShifts, mockUsers, shiftTypeInfo, restShiftInfo, mockAttendanceRecords } from '@/lib/mockData';
import { ShiftType, ShiftTypeInfo, Shift } from '@/types';

export default function ShiftsPage() {
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [selectedCell, setSelectedCell] = useState<{userId: string, date: string, dayIndex: number} | null>(null);
  const [selectedDayForBulk, setSelectedDayForBulk] = useState<{date: string, dayIndex: number} | null>(null);
  const [shifts, setShifts] = useState<Shift[]>(mockShifts);
  const [showPatternDialog, setShowPatternDialog] = useState<{userId: string, userName: string} | null>(null);
  const [showBulkPatternDialog, setShowBulkPatternDialog] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const calendarContainerRef = useRef<HTMLDivElement>(null);

  const monthStart = startOfMonth(selectedDate);
  const daysInMonth = getDaysInMonth(selectedDate);
  const monthDays = Array.from({ length: daysInMonth }, (_, i) => addDays(monthStart, i));

  // 表示対象のユーザーをフィルタリング（退職日の翌月以降は非表示）
  const getActiveUsersForMonth = () => {
    return mockUsers.filter(user => {
      // 退職日が設定されていない場合は表示
      if (!user.retirementDate) return true;

      // 退職日を取得
      const retirementDate = new Date(user.retirementDate);
      // 退職日の翌月の初日を取得
      const retirementNextMonth = new Date(retirementDate.getFullYear(), retirementDate.getMonth() + 1, 1);

      // 表示中の月が退職日の翌月より前なら表示
      return selectedDate < retirementNextMonth;
    });
  };

  const activeUsers = getActiveUsersForMonth();

  const getShiftsForDate = (date: Date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return shifts.filter(shift => shift.date === dateStr);
  };

  const getShiftTypeInfo = (shiftType: ShiftType): ShiftTypeInfo => {
    if (shiftType === 'rest') {
      return restShiftInfo;
    }
    return shiftTypeInfo.find(info => info.type === shiftType) || shiftTypeInfo[1];
  };

  // 全スタッフの月間合計を計算
  const getAllStaffTotals = () => {
    let totalHours = 0;
    let totalRestDays = 0;
    let totalWorkDays = 0;

    activeUsers.forEach(user => {
      const stats = getMonthlyStats(user.id);
      totalHours += stats.totalHours;
      totalRestDays += stats.restDays;
      totalWorkDays += stats.workDays;
    });

    return {
      totalHours: Math.round(totalHours * 10) / 10,
      totalRestDays,
      totalWorkDays
    };
  };

  // 月間の勤務時間と休日日数を計算
  const getMonthlyStats = (userId: string) => {
    const monthStartDate = startOfMonth(selectedDate);
    const monthEndDate = endOfMonth(selectedDate);
    const monthStartStr = format(monthStartDate, 'yyyy-MM-dd');
    const monthEndStr = format(monthEndDate, 'yyyy-MM-dd');

    // 勤務実績から総労働時間を計算
    const userAttendances = mockAttendanceRecords.filter(record =>
      record.userId === userId &&
      record.date >= monthStartStr &&
      record.date <= monthEndStr
    );

    const totalHours = userAttendances.reduce((sum, record) => sum + record.totalHours, 0);

    // 出勤日数を計算（総労働時間が0より大きい日）
    const workDays = userAttendances.filter(record => record.totalHours > 0).length;

    // シフトから休日日数を計算（休みまたはシフトなしの日）
    const userShifts = shifts.filter(shift =>
      shift.userId === userId &&
      shift.date >= monthStartStr &&
      shift.date <= monthEndStr
    );

    const restDays = userShifts.filter(shift => shift.shiftType === 'rest').length;
    const scheduledDays = userShifts.length;
    const totalDaysInMonth = getDaysInMonth(selectedDate);
    const unscheduledDays = totalDaysInMonth - scheduledDays;
    const totalRestDays = restDays + unscheduledDays;

    return {
      totalHours: Math.round(totalHours * 10) / 10, // 小数第1位まで
      restDays: totalRestDays,
      workDays: workDays
    };
  };

  // 日付ごとのシフト人数を集計
  const getShiftCountsByDate = (date: Date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    const dayShifts = shifts.filter(shift => shift.date === dateStr);

    const counts: { [key: string]: number } = {};
    dayShifts.forEach(shift => {
      if (counts[shift.shiftType]) {
        counts[shift.shiftType]++;
      } else {
        counts[shift.shiftType] = 1;
      }
    });

    return counts;
  };

  const handleCellClick = (userId: string, date: string, dayIndex: number) => {
    setSelectedCell({ userId, date, dayIndex });
    setSelectedDayForBulk(null); // 個別選択時は一括選択をクリア
  };

  const handleDayHeaderClick = (date: string, dayIndex: number) => {
    setSelectedDayForBulk({ date, dayIndex });
    setSelectedCell(null); // 一括選択時は個別選択をクリア
  };

  const handleShiftTypeSelect = (shiftType: ShiftType) => {
    if (selectedCell) {
      const existingShiftIndex = shifts.findIndex(shift =>
        shift.userId === selectedCell.userId && shift.date === selectedCell.date
      );

      const newShift: Shift = {
        id: existingShiftIndex >= 0 ? shifts[existingShiftIndex].id : `shift_${Date.now()}`,
        userId: selectedCell.userId,
        date: selectedCell.date,
        shiftType
      };

      if (existingShiftIndex >= 0) {
        // 既存のシフトを更新
        const updatedShifts = [...shifts];
        updatedShifts[existingShiftIndex] = newShift;
        setShifts(updatedShifts);
      } else {
        // 新しいシフトを追加
        setShifts([...shifts, newShift]);
      }
    }
    setSelectedCell(null);
  };

  const handleBulkShiftTypeSelect = (shiftType: ShiftType) => {
    if (selectedDayForBulk) {
      const newShifts = [...shifts];

      // 選択した日付のすべてのスタッフのシフトを更新
      activeUsers.forEach(user => {
        const existingShiftIndex = newShifts.findIndex(shift =>
          shift.userId === user.id && shift.date === selectedDayForBulk.date
        );

        const newShift: Shift = {
          id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${user.id}`,
          userId: user.id,
          date: selectedDayForBulk.date,
          shiftType
        };

        if (existingShiftIndex >= 0) {
          // 既存のシフトを更新
          newShifts[existingShiftIndex] = newShift;
        } else {
          // 新しいシフトを追加
          newShifts.push(newShift);
        }
      });

      setShifts(newShifts);
    }
    setSelectedDayForBulk(null);
  };

  const handleMonthSelect = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const [year, month] = e.target.value.split('-');
    setSelectedDate(new Date(parseInt(year), parseInt(month) - 1, 1));
  };

  // ユーザー名クリック時の確認ダイアログ表示
  const handleUserNameClick = (userId: string) => {
    const user = mockUsers.find(u => u.id === userId);
    if (!user) return;
    setShowPatternDialog({ userId, userName: user.name });
  };

  // ユーザーのシフトパターンから月次シフトを生成
  const generateShiftFromPattern = (userId: string) => {
    const user = mockUsers.find(u => u.id === userId);
    if (!user || !user.shiftPatterns) return;

    const monthStartDate = startOfMonth(selectedDate);
    const monthEndDate = endOfMonth(selectedDate);
    const newShifts = [...shifts];

    // その月の全日付をループ
    let currentDate = monthStartDate;
    while (currentDate <= monthEndDate) {
      const dateStr = format(currentDate, 'yyyy-MM-dd');
      const dayOfWeek = currentDate.getDay();
      const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
      const dayKey = dayKeys[dayOfWeek];

      // シフトパターンから該当曜日のシフトタイプを取得
      const shiftType = user.shiftPatterns[dayKey];

      // 既存のシフトを検索
      const existingShiftIndex = newShifts.findIndex(shift =>
        shift.userId === userId && shift.date === dateStr
      );

      if (shiftType) {
        // シフトタイプが設定されている場合
        const newShift: Shift = {
          id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${userId}_${dateStr}`,
          userId,
          date: dateStr,
          shiftType
        };

        if (existingShiftIndex >= 0) {
          newShifts[existingShiftIndex] = newShift;
        } else {
          newShifts.push(newShift);
        }
      } else {
        // シフトタイプがundefinedの場合は休みとして設定
        const newShift: Shift = {
          id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${userId}_${dateStr}`,
          userId,
          date: dateStr,
          shiftType: 'rest'
        };

        if (existingShiftIndex >= 0) {
          newShifts[existingShiftIndex] = newShift;
        } else {
          newShifts.push(newShift);
        }
      }

      currentDate = addDays(currentDate, 1);
    }

    setShifts(newShifts);
    setShowPatternDialog(null);

    // 成功メッセージを表示
    setSuccessMessage(`${user.name}のシフトパターンを${format(selectedDate, 'yyyy年MM月')}に適用しました`);
    setTimeout(() => setSuccessMessage(null), 3000);
  };

  // 全員のシフトパターンを一括適用
  const generateAllUsersShiftPatterns = () => {
    const monthStartDate = startOfMonth(selectedDate);
    const monthEndDate = endOfMonth(selectedDate);
    const newShifts = [...shifts];
    let appliedCount = 0;

    // 全ユーザーに対してシフトパターンを適用
    activeUsers.forEach(user => {
      if (!user.shiftPatterns) return;

      // その月の全日付をループ
      let currentDate = monthStartDate;
      while (currentDate <= monthEndDate) {
        const dateStr = format(currentDate, 'yyyy-MM-dd');
        const dayOfWeek = currentDate.getDay();
        const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
        const dayKey = dayKeys[dayOfWeek];

        // シフトパターンから該当曜日のシフトタイプを取得
        const shiftType = user.shiftPatterns[dayKey];

        // 既存のシフトを検索
        const existingShiftIndex = newShifts.findIndex(shift =>
          shift.userId === user.id && shift.date === dateStr
        );

        if (shiftType) {
          // シフトタイプが設定されている場合
          const newShift: Shift = {
            id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${user.id}_${dateStr}`,
            userId: user.id,
            date: dateStr,
            shiftType
          };

          if (existingShiftIndex >= 0) {
            newShifts[existingShiftIndex] = newShift;
          } else {
            newShifts.push(newShift);
          }
        } else {
          // シフトタイプがundefinedの場合は休みとして設定
          const newShift: Shift = {
            id: existingShiftIndex >= 0 ? newShifts[existingShiftIndex].id : `shift_${Date.now()}_${user.id}_${dateStr}`,
            userId: user.id,
            date: dateStr,
            shiftType: 'rest'
          };

          if (existingShiftIndex >= 0) {
            newShifts[existingShiftIndex] = newShift;
          } else {
            newShifts.push(newShift);
          }
        }

        currentDate = addDays(currentDate, 1);
      }

      appliedCount++;
    });

    setShifts(newShifts);
    setShowBulkPatternDialog(false);

    // 成功メッセージを表示
    setSuccessMessage(`全員（${appliedCount}名）のシフトパターンを${format(selectedDate, 'yyyy年MM月')}に適用しました`);
    setTimeout(() => setSuccessMessage(null), 3000);
  };


  const headerContainerRef = useRef<HTMLDivElement>(null);

  const handleCalendarScroll = (e: React.UIEvent<HTMLDivElement>) => {
    const scrollLeft = e.currentTarget.scrollLeft;
    if (headerContainerRef.current) {
      headerContainerRef.current.scrollLeft = scrollLeft;
    }
  };

  return (
    <>
      {/* シフトパターン適用確認ダイアログ */}
      {showPatternDialog && (
        <div className="fixed top-0 left-0 right-0 bottom-0 bg-gray-500 bg-opacity-10 backdrop-blur-sm flex items-center justify-center z-50 overflow-y-auto">
          <div className="bg-white rounded-lg shadow-2xl border border-gray-200 p-6 max-w-md w-full my-auto">
            <h3 className="text-lg font-bold text-gray-900 mb-4">シフトパターンの適用</h3>
            <p className="text-sm text-gray-700 mb-4">
              <strong>{showPatternDialog.userName}</strong>のシフトパターンを<strong>{format(selectedDate, 'yyyy年MM月')}</strong>に適用しますか？
            </p>

            {/* シフトパターンのプレビュー */}
            {(() => {
              const user = mockUsers.find(u => u.id === showPatternDialog.userId);
              if (!user || !user.shiftPatterns) return null;

              const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
              const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;

              return (
                <div className="bg-gray-50 rounded-lg p-4 mb-4">
                  <h4 className="text-xs font-semibold text-gray-700 mb-2">設定されているシフトパターン:</h4>
                  <div className="grid grid-cols-7 gap-1">
                    {dayKeys.map((dayKey, index) => {
                      const shiftType = user.shiftPatterns?.[dayKey];
                      const shiftInfo = shiftType ? getShiftTypeInfo(shiftType) : null;

                      return (
                        <div key={dayKey} className="text-center">
                          <div className="text-xs text-gray-600 mb-1">{dayNames[index]}</div>
                          <div className={`text-xs font-medium py-1 px-1 rounded ${
                            shiftInfo ? `${shiftInfo.color} ${shiftInfo.textColor}` : 'bg-gray-200 text-gray-600'
                          }`}>
                            {shiftInfo ? shiftInfo.name : '休み'}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })()}

            <div className="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
              <p className="text-xs text-yellow-800">
                既存のシフトがある場合は上書きされます
              </p>
            </div>

            <div className="flex space-x-3">
              <button
                onClick={() => generateShiftFromPattern(showPatternDialog.userId)}
                className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium"
              >
                適用する
              </button>
              <button
                onClick={() => setShowPatternDialog(null)}
                className="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors font-medium"
              >
                キャンセル
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 全員のシフトパターン一括適用確認ダイアログ */}
      {showBulkPatternDialog && (
        <div className="fixed top-0 left-0 right-0 bottom-0 bg-gray-500 bg-opacity-10 backdrop-blur-sm flex items-center justify-center z-50 overflow-y-auto">
          <div className="bg-white rounded-lg shadow-2xl border border-gray-200 p-6 max-w-2xl w-full my-auto">
            <h3 className="text-lg font-bold text-gray-900 mb-4">全員のシフトパターンを一括適用</h3>
            <p className="text-sm text-gray-700 mb-4">
              全員のシフトパターンを<strong>{format(selectedDate, 'yyyy年MM月')}</strong>に適用しますか？
            </p>

            {/* ユーザーリスト */}
            <div className="bg-gray-50 rounded-lg p-4 mb-4 max-h-64 overflow-y-auto">
              <h4 className="text-xs font-semibold text-gray-700 mb-3">適用対象: {activeUsers.length}名</h4>
              <div className="grid grid-cols-2 gap-2">
                {activeUsers.map(user => (
                  <div key={user.id} className="flex items-center space-x-2 bg-white rounded px-3 py-2">
                    <div className="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                      <span className="text-xs font-medium text-blue-600">{user.name.substring(0, 2)}</span>
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">{user.name}</p>
                      <p className="text-xs text-gray-500">{user.department}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
              <p className="text-xs text-yellow-800">
                各ユーザーに設定されているシフトパターンが反映されます。既存のシフトがある場合は上書きされます。
              </p>
            </div>

            <div className="flex space-x-3">
              <button
                onClick={generateAllUsersShiftPatterns}
                className="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium"
              >
                全員に適用する
              </button>
              <button
                onClick={() => setShowBulkPatternDialog(false)}
                className="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors font-medium"
              >
                キャンセル
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <h1 className="text-2xl font-bold text-gray-900">シフト管理</h1>
          <div className="flex items-center space-x-4">
            <button
              onClick={() => setShowBulkPatternDialog(true)}
              className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
            >
              全員のシフトパターンを反映
            </button>
            <select
              value={format(selectedDate, 'yyyy-MM')}
              onChange={handleMonthSelect}
              className="border border-gray-300 rounded-md px-3 py-2 text-sm"
            >
              {Array.from({ length: 24 }, (_, i) => {
                const date = addMonths(new Date(), i - 12);
                return (
                  <option key={i} value={format(date, 'yyyy-MM')}>
                    {format(date, 'yyyy年MM月')}
                  </option>
                );
              })}
            </select>
          </div>
        </div>

        {/* 成功メッセージ */}
        {successMessage && (
          <div className="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow animate-fade-in">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
              </div>
              <div className="ml-3">
                <p className="text-sm font-medium text-green-800">{successMessage}</p>
              </div>
            </div>
          </div>
        )}

        {/* 月別シフト表 */}
        <div className="bg-white shadow rounded-lg p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">{format(selectedDate, 'yyyy年MM月')}</h2>
        </div>

        {/* カレンダーヘッダーとスタッフ別シフト表 */}
        <div className="flex">
          {/* 固定スタッフ名 */}
          <div className="w-24 flex-shrink-0 mr-4">
            <div className="font-medium text-gray-900 p-2 bg-gray-50 rounded-lg text-center mb-2">
              スタッフ
            </div>
            <div>
              {activeUsers.map(user => (
                <div
                  key={user.id}
                  className="h-10 bg-blue-50 rounded-lg mb-2 flex items-center justify-center cursor-pointer hover:bg-blue-100 transition-colors"
                  onClick={() => handleUserNameClick(user.id)}
                  title={`クリックして${user.name}のシフトパターンを適用`}
                >
                  <div className="font-medium text-gray-900 text-xs">{user.name}</div>
                </div>
              ))}
            </div>
            {/* 合計行 */}
            <div className="h-10 bg-green-50 rounded-lg mb-2 flex items-center justify-center">
              <div className="font-semibold text-green-700 text-xs">合計</div>
            </div>
            {/* 区切り線 */}
            <div className="h-2"></div>
            {/* シフトパターン別 */}
            <div>
              {shiftTypeInfo.map(info => (
                <div key={info.type} className={`h-10 rounded-lg mb-2 flex items-center justify-center ${info.color} ${info.textColor}`}>
                  <div className="font-medium text-xs text-center">
                    {info.name}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* スクロール可能なカレンダー部分 */}
          <div className="flex-1 overflow-hidden">
            {/* カレンダーヘッダー */}
            <div
              ref={headerContainerRef}
              className="overflow-x-hidden overflow-y-hidden"
              style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
            >
              <div className="flex gap-1 mb-2" style={{ minWidth: `${monthDays.length * 60 + 204}px` }}>
                {monthDays.map((day, index) => {
                  const dayOfWeek = day.getDay();
                  const isWeekend = dayOfWeek === 0 || dayOfWeek === 6; // 日曜日または土曜日
                  const dateStr = format(day, 'yyyy-MM-dd');
                  const isSelected = selectedDayForBulk?.date === dateStr;

                  return (
                    <div
                      key={index}
                      className={`w-14 text-center p-1 rounded-lg cursor-pointer transition-all ${
                        isSelected
                          ? 'bg-blue-500 border-2 border-blue-700 shadow-md'
                          : isWeekend
                            ? 'bg-red-50 border border-red-200 hover:bg-red-100'
                            : 'bg-gray-50 hover:bg-gray-100'
                      }`}
                      onClick={() => handleDayHeaderClick(dateStr, index)}
                    >
                      <div className={`font-medium text-xs ${
                        isSelected
                          ? 'text-white'
                          : isWeekend
                            ? 'text-red-700'
                            : 'text-gray-900'
                      }`}>
                        {['日', '月', '火', '水', '木', '金', '土'][dayOfWeek]}
                      </div>
                      <div className={`text-xs ${
                        isSelected
                          ? 'text-white'
                          : isWeekend
                            ? 'text-red-600'
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

            {/* スタッフ別シフト表 */}
            <div
              ref={calendarContainerRef}
              className="overflow-x-auto overflow-y-hidden"
              onScroll={handleCalendarScroll}
            >
              <div
                className="relative"
                style={{ minWidth: `${monthDays.length * 60 + 204}px` }}
              >
                {/* 一括シフト選択UI */}
                {selectedDayForBulk && (
                  <div
                    className="absolute z-50 bg-white border-2 border-blue-500 rounded-lg shadow-xl p-4 left-0"
                    style={{
                      top: '0px',
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
                          onClick={() => handleBulkShiftTypeSelect(info.type)}
                          className={`p-2 rounded text-xs transition-all ${info.color} ${info.textColor} border-2 border-transparent hover:border-gray-400 hover:shadow-lg`}
                        >
                          <div className="font-medium text-xs mb-1">{info.name}</div>
                          <div className="text-[10px] opacity-75">{info.timeRange}</div>
                        </button>
                      ))}
                      <button
                        key={restShiftInfo.type}
                        onClick={() => handleBulkShiftTypeSelect(restShiftInfo.type)}
                        className={`p-2 rounded text-xs transition-all ${restShiftInfo.color} ${restShiftInfo.textColor} border-2 border-transparent hover:border-gray-400 hover:shadow-lg`}
                      >
                        <div className="font-medium text-xs mb-1">{restShiftInfo.name}</div>
                        <div className="text-[10px] opacity-75">{restShiftInfo.timeRange}</div>
                      </button>
                    </div>
                    <button
                      onClick={() => setSelectedDayForBulk(null)}
                      className="w-full text-xs text-gray-600 hover:text-gray-800 py-2 border border-gray-300 rounded hover:bg-gray-50"
                    >
                      キャンセル
                    </button>
                  </div>
                )}

                {activeUsers.map((user, userIndex) => {
                  const stats = getMonthlyStats(user.id);
                  return (
                  <div key={user.id} className="flex gap-1 mb-2 relative">
                    {/* 各日のシフト */}
                    {monthDays.map((day, dayIndex) => {
                      const dayShifts = getShiftsForDate(day).filter(shift => shift.userId === user.id);
                      const dateStr = format(day, 'yyyy-MM-dd');

                      // 勤務日設定を確認
                      const dayOfWeek = day.getDay();
                      const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as const;
                      const dayKey = dayKeys[dayOfWeek];
                      const isWorkDay = user.availableWorkDays?.[dayKey];

                      // 定休日（勤務日設定がfalse）かつシフトが休みの場合
                      const isScheduledOff = !isWorkDay && dayShifts.length > 0 && dayShifts[0].shiftType === 'rest';

                      return (
                        <div
                          key={dayIndex}
                          className={`w-14 h-10 border rounded-lg cursor-pointer transition-all ${
                            dayShifts.length > 0
                              ? isScheduledOff
                                ? 'bg-gray-200 hover:bg-gray-300'
                                : `${getShiftTypeInfo(dayShifts[0].shiftType).color} ${getShiftTypeInfo(dayShifts[0].shiftType).textColor}`
                              : 'bg-gray-200 hover:bg-gray-300'
                          } ${selectedCell?.userId === user.id && selectedCell?.date === dateStr ? 'ring-2 ring-blue-500' : ''}`}
                          onClick={() => handleCellClick(user.id, dateStr, dayIndex)}
                        >
                          {dayShifts.length > 0 ? (
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
                    <div className="w-16 h-10 bg-green-100 border border-green-300 rounded-lg flex items-center justify-center ml-2">
                      <div className="text-xs font-semibold text-green-800">
                        {stats.workDays}日
                      </div>
                    </div>
                    {/* 勤務時間セル */}
                    <div className="w-16 h-10 bg-blue-100 border border-blue-300 rounded-lg flex items-center justify-center">
                      <div className="text-xs font-semibold text-blue-800">
                        {stats.totalHours}h
                      </div>
                    </div>
                    {/* 休日セル */}
                    <div className="w-16 h-10 bg-orange-100 border border-orange-300 rounded-lg flex items-center justify-center">
                      <div className="text-xs font-semibold text-orange-800">
                        {stats.restDays}日
                      </div>
                    </div>

                    {/* シフト選択ドロップダウン */}
                    {selectedCell?.userId === user.id && (
                      <div
                        className={`absolute z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-2 ${
                          userIndex >= activeUsers.length - 2 ? '-mt-32' : 'mt-12'
                        }`}
                        style={{
                          left: `${selectedCell.dayIndex * 60}px`,
                          width: '300px'
                        }}
                      >
                        <div className="text-xs font-semibold text-gray-700 mb-2">勤務形態を選択</div>
                        <div className="grid grid-cols-3 gap-1">
                          {shiftTypeInfo.map((info) => (
                            <button
                              key={info.type}
                              onClick={() => handleShiftTypeSelect(info.type)}
                              className={`p-1 rounded text-xs transition-colors ${info.color} ${info.textColor} border border-transparent hover:border-gray-300 hover:shadow-md`}
                            >
                              <div className="font-medium text-xs">{info.name}</div>
                            </button>
                          ))}
                          <button
                            key={restShiftInfo.type}
                            onClick={() => handleShiftTypeSelect(restShiftInfo.type)}
                            className={`p-1 rounded text-xs transition-colors ${restShiftInfo.color} ${restShiftInfo.textColor} border border-transparent hover:border-gray-300 hover:shadow-md`}
                          >
                            <div className="font-medium text-xs">{restShiftInfo.name}</div>
                          </button>
                        </div>
                        <button
                          onClick={() => setSelectedCell(null)}
                          className="mt-2 w-full text-xs text-gray-600 hover:text-gray-800"
                        >
                          キャンセル
                        </button>
                      </div>
                    )}
                  </div>
                  );
                })}

                {/* 合計人数行 */}
                <div className="flex gap-1 mb-2">
                  {monthDays.map((day, dayIndex) => {
                    const shiftCounts = getShiftCountsByDate(day);
                    const totalCount = Object.values(shiftCounts).reduce((sum, count) => sum + count, 0);

                    return (
                      <div
                        key={dayIndex}
                        className="w-14 h-10 bg-green-50 border border-green-200 rounded-lg flex items-center justify-center"
                      >
                        <div className="text-sm font-bold text-green-700">{totalCount}人</div>
                      </div>
                    );
                  })}
                  {/* 合計行の集計セル */}
                  {(() => {
                    const totals = getAllStaffTotals();
                    return (
                      <>
                        {/* 出勤合計 */}
                        <div className="w-16 h-10 bg-green-100 border border-green-300 rounded-lg flex items-center justify-center ml-2">
                          <div className="text-xs font-bold text-green-800">
                            {totals.totalWorkDays}日
                          </div>
                        </div>
                        {/* 勤務時間合計 */}
                        <div className="w-16 h-10 bg-blue-100 border border-blue-300 rounded-lg flex items-center justify-center">
                          <div className="text-xs font-bold text-blue-800">
                            {totals.totalHours}h
                          </div>
                        </div>
                        {/* 休日合計 */}
                        <div className="w-16 h-10 bg-orange-100 border border-orange-300 rounded-lg flex items-center justify-center">
                          <div className="text-xs font-bold text-orange-800">
                            {totals.totalRestDays}日
                          </div>
                        </div>
                      </>
                    );
                  })()}
                </div>

                {/* 区切り線（空白） */}
                <div className="h-2"></div>

                {/* シフトパターン別人数行 */}
                {shiftTypeInfo.map(info => (
                  <div key={info.type} className="flex gap-1 mb-2">
                    {monthDays.map((day, dayIndex) => {
                      const shiftCounts = getShiftCountsByDate(day);
                      const count = shiftCounts[info.type] || 0;

                      return (
                        <div
                          key={dayIndex}
                          className={`w-14 h-10 rounded-lg flex items-center justify-center ${info.color} border border-gray-200`}
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

        {/* シフト凡例 */}
        <div className="mt-4 bg-gray-50 p-3 rounded-lg">
          <h4 className="text-xs font-semibold text-gray-700 mb-2">勤務形態一覧</h4>
          <div className="grid grid-cols-3 gap-2 text-xs">
            {shiftTypeInfo.map((info) => (
              <div key={info.type} className="flex items-center space-x-2">
                <div className={`w-4 h-3 rounded ${info.color}`}></div>
                <span className="text-gray-600">{info.name}: {info.timeRange}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
    </>
  );
}