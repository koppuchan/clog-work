import { format, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import { AttendanceRecord } from '@/types';

interface ShiftInfo {
  timeRange: string;
  color: string;
  textColor: string;
}

interface Application {
  type: string;
  reason: string;
  status?: {
    value: number;
    label: string;
  };
}

interface ReportTableRowProps {
  date: Date;
  attendance: AttendanceRecord | undefined;
  shift: ShiftInfo | undefined;
  holiday: string | undefined;
  application: Application | undefined;
  hasApplication: boolean;
  canApply: boolean;
  overtimeMinutes: number;
  holidayMinutes: number;
  nightMinutes: number;
  lateMinutes: number;
  earlyMinutes: number;
  formatClockOut: (clockIn: string, clockOut: string) => string;
  formatMinutesToHM: (minutes: number) => string;
  onApplicationClick: (date: Date) => void;
}

export function ReportTableRow({
  date,
  attendance,
  shift,
  holiday,
  application,
  hasApplication,
  canApply,
  overtimeMinutes,
  holidayMinutes,
  nightMinutes,
  lateMinutes,
  earlyMinutes,
  formatClockOut,
  formatMinutesToHM,
  onApplicationClick,
}: ReportTableRowProps) {
  const isCurrentDay = isToday(date);
  const dayOfWeek = date.getDay();
  const dayName = format(date, 'E', { locale: ja });
  const totalLateEarlyMinutes = lateMinutes + earlyMinutes;

  const renderWorkTime = () => {
    if (attendance?.clockIn && attendance?.clockOut) {
      const clockOut = formatClockOut(attendance.clockIn, attendance.clockOut);
      return `${attendance.clockIn} ~ ${clockOut}`;
    }
    if (attendance?.clockIn) {
      return `${attendance.clockIn} ~ -`;
    }
    return '-';
  };

  const renderTotalHours = () => {
    if (attendance?.totalHours) {
      const hours = Math.floor(attendance.totalHours);
      const mins = Math.round((attendance.totalHours - hours) * 60);
      return `${hours}:${String(mins).padStart(2, '0')}`;
    }
    return '-';
  };

  const renderApplicationCell = () => {
    if (attendance?.status === 'paid-leave') {
      return (
        <div className="flex flex-col gap-1">
          <span className="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">有給休暇</span>
          {application?.reason && <span className="text-xs text-gray-600">{application.reason}</span>}
        </div>
      );
    }

    if (attendance?.status === 'special-leave') {
      return (
        <div className="flex flex-col gap-1">
          <span className="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">特別休暇</span>
          {application?.reason && <span className="text-xs text-gray-600">{application.reason}</span>}
        </div>
      );
    }

    if (application) {
      // ステータスに応じたスタイルを決定（1=申請中, 2=承認, 3=却下, 4=取消）
      const isPending = !application.status || application.status.value === 1;
      const isApproved = application.status?.value === 2;
      const isRejected = application.status?.value === 3;

      const statusBadge = isPending ? (
        <span className="px-1.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800">未承認</span>
      ) : isApproved ? (
        <span className="px-1.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800">承認済</span>
      ) : isRejected ? (
        <span className="px-1.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-800">却下</span>
      ) : null;

      return (
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-1.5">
            <span className="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800">{application.type}</span>
            {statusBadge}
          </div>
          {application.reason && <span className="text-xs text-gray-600 truncate max-w-[150px]">{application.reason}</span>}
        </div>
      );
    }

    if (hasApplication) {
      return (
        <span className="px-3 py-1.5 text-xs font-medium text-gray-500 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">
          申請済み
        </span>
      );
    }

    if (!canApply) {
      return (
        <span className="px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-md cursor-not-allowed">
          申請不可
        </span>
      );
    }

    return (
      <button
        className="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100 transition-colors"
        onClick={() => onApplicationClick(date)}
      >
        申請
      </button>
    );
  };

  return (
    <tr className={`${isCurrentDay ? 'bg-green-50' : ''} hover:bg-gray-50`}>
      <td className="px-3 py-3 whitespace-nowrap">
        <div className="flex items-center gap-1.5">
          <span className="text-sm font-medium text-gray-900">{format(date, 'd')}日</span>
          <span
            className={`text-sm font-medium ${
              holiday || dayOfWeek === 0 ? 'text-red-600' : dayOfWeek === 6 ? 'text-blue-600' : 'text-gray-700'
            }`}
          >
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
      <td className="px-3 py-4 whitespace-nowrap text-sm text-gray-900">{renderWorkTime()}</td>
      <td className="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
        {attendance?.breakStart && attendance?.breakEnd ? `${attendance.breakStart}-${attendance.breakEnd}` : '-'}
      </td>
      <td className="px-3 py-3 whitespace-nowrap text-sm text-gray-900">{renderTotalHours()}</td>
      <td className="px-6 py-4 whitespace-nowrap text-sm">
        {overtimeMinutes > 0 ? (
          <span className="font-medium text-orange-600">{formatMinutesToHM(overtimeMinutes)}</span>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>
      <td className="px-4 py-4 whitespace-nowrap text-sm">
        {holidayMinutes > 0 ? (
          <span className="font-medium text-red-600">{formatMinutesToHM(holidayMinutes)}</span>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>
      <td className="px-4 py-4 whitespace-nowrap text-sm">
        {nightMinutes > 0 ? (
          <span className="font-medium text-indigo-600">{formatMinutesToHM(nightMinutes)}</span>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>
      <td className="px-4 py-4 whitespace-nowrap text-sm">
        {totalLateEarlyMinutes > 0 ? (
          <span className="font-medium text-red-600">{formatMinutesToHM(totalLateEarlyMinutes)}</span>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </td>
      <td className="px-6 py-4 whitespace-nowrap">{renderApplicationCell()}</td>
    </tr>
  );
}
