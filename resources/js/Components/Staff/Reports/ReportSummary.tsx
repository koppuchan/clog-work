import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface ReportSummaryProps {
  currentDate: Date;
  workDays: number;
  leaveDays: number;
  totalMinutes: number;
}

export function ReportSummary({ currentDate, workDays, leaveDays, totalMinutes }: ReportSummaryProps) {
  const formatTotalTime = () => {
    const hours = Math.floor(totalMinutes / 60);
    const mins = Math.round(totalMinutes % 60);
    return `${hours}:${String(mins).padStart(2, '0')}`;
  };

  return (
    <div className="bg-white shadow rounded-lg px-6 py-4">
      <div className="flex items-center justify-between">
        <div className="text-lg font-bold text-gray-900">{format(currentDate, 'yyyy年MM月', { locale: ja })}</div>
        <div className="flex items-center gap-8">
          <div className="text-sm">
            <span className="text-gray-600">出勤日数: </span>
            <span className="font-semibold text-gray-900">{workDays}日</span>
          </div>
          <div className="text-sm">
            <span className="text-gray-600">休暇日数: </span>
            <span className="font-semibold text-gray-900">{leaveDays}日</span>
          </div>
          <div className="text-sm">
            <span className="text-gray-600">総労働時間: </span>
            <span className="font-semibold text-gray-900">{formatTotalTime()}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
