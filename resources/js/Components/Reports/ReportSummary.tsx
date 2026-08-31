import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

export type ReportStatusCounts = {
  present: number;
  late: number;
  absent: number;
  'early-leave': number;
  'paid-leave': number;
  'special-leave': number;
};

interface ReportSummaryProps {
  selectedMonth: Date;
  selectedUserName: string;
  statusCounts: ReportStatusCounts;
  totalWorkTimeLabel: string;
}

/**
 * 勤務実績の集計情報を表示するコンポーネント
 */
export default function ReportSummary({
  selectedMonth,
  selectedUserName,
  statusCounts,
  totalWorkTimeLabel,
}: ReportSummaryProps) {
  return (
    <div className="bg-white shadow rounded-lg px-6 py-4">
      <div className="flex items-center justify-between">
        <div className="text-lg font-bold text-gray-900">
          {format(selectedMonth, 'yyyy年MM月', { locale: ja })} - {selectedUserName}
        </div>
        <div className="flex items-center gap-8">
          <SummaryItem label="出勤日数" value={`${statusCounts.present}日`} />
          <SummaryItem label="休暇日数" value={`${statusCounts['paid-leave'] + statusCounts['special-leave']}日`} />
          <SummaryItem label="遅刻回数" value={`${statusCounts.late}回`} />
          <SummaryItem label="総労働時間" value={totalWorkTimeLabel} />
        </div>
      </div>
    </div>
  );
}

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="text-sm">
      <span className="text-gray-600">{label}: </span>
      <span className="font-semibold text-gray-900">{value}</span>
    </div>
  );
}
