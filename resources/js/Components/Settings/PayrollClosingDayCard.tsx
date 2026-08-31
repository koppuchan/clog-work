import { Clock } from 'lucide-react';
import SettingsCard from './SettingsCard';
import { generateClosingDayOptions } from '@/utils/settingsHelpers';

interface PayrollClosingDayCardProps {
  payrollClosingDay: string;
  onPayrollClosingDayChange: (value: string) => void;
}

export default function PayrollClosingDayCard({
  payrollClosingDay,
  onPayrollClosingDayChange,
}: PayrollClosingDayCardProps) {
  const closingDayOptions = generateClosingDayOptions();

  return (
    <SettingsCard title="給与締切日設定" icon={Clock}>
      <div className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              締切日
            </label>
            <div className="flex items-start space-x-2">
              <span className="text-sm text-gray-700 pt-2">毎月</span>
              <select
                value={payrollClosingDay}
                onChange={(e) => onPayrollClosingDayChange(e.target.value)}
                className="border border-gray-300 rounded-md p-2 pr-8 text-gray-900 appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2024%2024%22%20fill%3d%22none%22%20stroke%3d%22%23666%22%20stroke-width%3d%222%22%20stroke-linecap%3d%22round%22%20stroke-linejoin%3d%22round%22%3e%3cpolyline%20points%3d%226%209%2012%2015%2018%209%22%3e%3c%2fpolyline%3e%3c%2fsvg%3e')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat"
              >
                {closingDayOptions.map((day) => (
                  <option key={day} value={day}>
                    {day === 31 ? `${day}（末日）` : day}
                  </option>
                ))}
              </select>
              <span className="text-sm text-gray-700 pt-2">日</span>
            </div>
            <p className="text-xs text-gray-500 mt-2">
              給与計算の締切日を設定してください。末日の場合は31日になります。
            </p>
          </div>
        </div>
      </div>
    </SettingsCard>
  );
}
