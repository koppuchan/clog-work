import { format, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import { AttendanceRecord, Application, ShiftTypeInfo } from '@/types';

interface ReportTableProps {
  daysInMonth: Date[];
  getAttendanceForDate: (date: Date) => AttendanceRecord | undefined;
  getShiftForDate: (date: Date) => ShiftTypeInfo | undefined;
  formatClockOut: (clockIn: string, clockOut: string) => string;
  calculateOvertimeMinutes: (attendance: AttendanceRecord | undefined, shift: { timeRange: string } | undefined) => number;
  calculateHolidayMinutes: (date: Date, attendance: AttendanceRecord | undefined) => number;
  calculateNightMinutes: (attendance: AttendanceRecord | undefined) => number;
  calculateLateEarlyMinutes: (attendance: AttendanceRecord | undefined, shift: { timeRange: string } | undefined) => { lateMinutes: number; earlyMinutes: number };
  getApprovedApplicationForDate: (date: Date) => Application | undefined;
  getApplicationTypeText: (type: string) => string;
}

/**
 * 月間の勤務実績を表形式で表示するコンポーネント
 */
export default function ReportTable({
  daysInMonth,
  getAttendanceForDate,
  getShiftForDate,
  formatClockOut,
  calculateOvertimeMinutes,
  calculateHolidayMinutes,
  calculateNightMinutes,
  calculateLateEarlyMinutes,
  getApprovedApplicationForDate,
  getApplicationTypeText,
}: ReportTableProps) {
  return (
    <div className="bg-white shadow rounded-lg overflow-hidden">
      <div className="overflow-auto max-h-[70vh]">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50 sticky top-0 z-10 shadow-[0_1px_0_0_rgb(229,231,235)]">
            <tr>
              <TableHeader label="日付" widthClass="w-24" />
              <TableHeader label="シフト" widthClass="w-32" />
              <TableHeader label="勤務時間" widthClass="w-32" />
              <TableHeader label="休憩" widthClass="w-20" />
              <TableHeader label="労働時間" widthClass="w-20" />
              <TableHeader label="時間外" widthClass="w-24" />
              <TableHeader label="休日" widthClass="w-20" />
              <TableHeader label="深夜" widthClass="w-20" />
              <TableHeader label="遅刻早退" widthClass="w-20" />
              <TableHeader label="備考" widthClass="w-32" extraClass="px-4" />
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
                    {attendance?.totalHours ? formatHourMinutes(attendance.totalHours) : '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    {renderMinutes(calculateOvertimeMinutes(attendance, shift), 'text-orange-600')}
                  </td>
                  <td className="px-4 py-4 whitespace-nowrap text-sm">
                    {renderMinutes(calculateHolidayMinutes(date, attendance), 'text-red-600')}
                  </td>
                  <td className="px-4 py-4 whitespace-nowrap text-sm">
                    {renderMinutes(calculateNightMinutes(attendance), 'text-indigo-600')}
                  </td>
                  <td className="px-4 py-4 whitespace-nowrap text-sm">
                    {(() => {
                      const { lateMinutes, earlyMinutes } = calculateLateEarlyMinutes(attendance, shift);
                      return renderMinutes(lateMinutes + earlyMinutes, 'text-red-600');
                    })()}
                  </td>
                  <td className="px-4 py-4 text-sm text-gray-900">
                    {renderApplicationOrLeave(date, attendance, getApprovedApplicationForDate, getApplicationTypeText)}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function TableHeader({ label, widthClass, extraClass = '' }: { label: string; widthClass: string; extraClass?: string }) {
  return (
    <th className={`px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${widthClass} ${extraClass}`}>
      {label}
    </th>
  );
}

function formatHourMinutes(totalHours: number): string {
  const hours = Math.floor(totalHours);
  const mins = Math.round((totalHours - hours) * 60);
  return `${hours}:${String(mins).padStart(2, '0')}`;
}

function renderMinutes(minutes: number, accentClass: string) {
  if (minutes > 0) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return <span className={`font-medium ${accentClass}`}>{hours}:{String(mins).padStart(2, '0')}</span>;
  }
  return <span className="text-gray-400">-</span>;
}

function renderApplicationOrLeave(
  date: Date,
  attendance: AttendanceRecord | undefined,
  getApprovedApplicationForDate: (date: Date) => Application | undefined,
  getApplicationTypeText: (type: string) => string
) {
  const application = getApprovedApplicationForDate(date);

  if (attendance?.status === 'paid-leave') {
    return (
      <ApplicationBadge label="有給休暇" className="bg-yellow-100 text-yellow-800">
        {application?.reason && application.type === 'vacation' ? application.reason : undefined}
      </ApplicationBadge>
    );
  }

  if (attendance?.status === 'special-leave') {
    return (
      <ApplicationBadge label="特別休暇" className="bg-blue-100 text-blue-800">
        {application?.reason}
      </ApplicationBadge>
    );
  }

  if (application) {
    return (
      <ApplicationBadge label={getApplicationTypeText(application.type)} className={getApplicationBadgeStyle(application.type)}>
        {application.reason}
      </ApplicationBadge>
    );
  }

  return <span className="text-gray-400">-</span>;
}

function ApplicationBadge({
  label,
  className,
  children,
}: {
  label: string;
  className: string;
  children?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1">
      <span className={`px-2 py-1 text-xs font-medium rounded ${className}`}>
        {label}
      </span>
      {children && (
        <span className="text-xs text-gray-600">{children}</span>
      )}
    </div>
  );
}

function getApplicationBadgeStyle(type: Application['type']): string {
  switch (type) {
    case 'overtime':
      return 'bg-orange-100 text-orange-800';
    case 'sick-leave':
      return 'bg-purple-100 text-purple-800';
    case 'shift-change':
      return 'bg-green-100 text-green-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}
