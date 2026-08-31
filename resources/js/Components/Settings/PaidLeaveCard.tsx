import { Clock } from 'lucide-react';
import SettingsCard from './SettingsCard';

interface PaidLeaveCardProps {
  paidLeaveHalfDay: boolean;
  paidLeaveHourly: boolean;
  dailyWorkingHours: string;
  onPaidLeaveHalfDayChange: (checked: boolean) => void;
  onPaidLeaveHourlyChange: (checked: boolean) => void;
  onDailyWorkingHoursChange: (value: string) => void;
}

type LeaveUnitType = 'day_only' | 'half_day' | 'hourly';

export default function PaidLeaveCard({
  paidLeaveHalfDay,
  paidLeaveHourly,
  dailyWorkingHours,
  onPaidLeaveHalfDayChange,
  onPaidLeaveHourlyChange,
  onDailyWorkingHoursChange,
}: PaidLeaveCardProps) {
  const currentUnit: LeaveUnitType = paidLeaveHourly ? 'hourly' : paidLeaveHalfDay ? 'half_day' : 'day_only';

  const handleChange = (unit: LeaveUnitType) => {
    onPaidLeaveHalfDayChange(unit === 'half_day');
    onPaidLeaveHourlyChange(unit === 'hourly');
  };

  return (
    <SettingsCard title="有給休暇設定" icon={Clock}>
      <div className="space-y-3">
        <p className="text-sm font-medium text-gray-700 mb-2">有給休暇の付与単位</p>
        <label className="flex items-center cursor-pointer">
          <input
            type="radio"
            name="paid-leave-unit"
            checked={currentUnit === 'day_only'}
            onChange={() => handleChange('day_only')}
            className="mr-3 h-4 w-4"
          />
          <span className="text-sm text-gray-700">1日単位のみ</span>
        </label>
        <label className="flex items-center cursor-pointer">
          <input
            type="radio"
            name="paid-leave-unit"
            checked={currentUnit === 'half_day'}
            onChange={() => handleChange('half_day')}
            className="mr-3 h-4 w-4"
          />
          <span className="text-sm text-gray-700">半日単位</span>
        </label>
        <label className="flex items-center cursor-pointer">
          <input
            type="radio"
            name="paid-leave-unit"
            checked={currentUnit === 'hourly'}
            onChange={() => handleChange('hourly')}
            className="mr-3 h-4 w-4"
          />
          <span className="text-sm text-gray-700">時間単位</span>
        </label>

        {paidLeaveHourly && (
          <div className="mt-4 pt-4 border-t border-gray-200">
            <label htmlFor="daily-working-hours" className="block text-sm font-medium text-gray-700 mb-2">
              1日あたりの所定労働時間（時間）
            </label>
            <input
              id="daily-working-hours"
              type="number"
              min="1"
              max="24"
              step="1"
              value={dailyWorkingHours}
              onChange={(e) => onDailyWorkingHoursChange(e.target.value)}
              className="w-32 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p className="text-xs text-gray-500 mt-1">時間有給の計算に使用されます</p>
          </div>
        )}
      </div>
    </SettingsCard>
  );
}
