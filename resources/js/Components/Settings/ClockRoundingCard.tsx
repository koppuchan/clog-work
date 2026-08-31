import { Clock } from 'lucide-react';
import SettingsCard from './SettingsCard';
import { CLOCK_ROUNDING_OPTIONS } from '@/utils/settingsHelpers';

interface ClockRoundingCardProps {
  clockRounding: string;
  onClockRoundingChange: (value: string) => void;
}

export default function ClockRoundingCard({
  clockRounding,
  onClockRoundingChange,
}: ClockRoundingCardProps) {
  return (
    <SettingsCard title="打刻設定" icon={Clock}>
      <div className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              丸め単位
            </label>
            <select
              value={clockRounding}
              onChange={(e) => onClockRoundingChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2 pr-8 text-gray-900 appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2024%2024%22%20fill%3d%22none%22%20stroke%3d%22%23666%22%20stroke-width%3d%222%22%20stroke-linecap%3d%22round%22%20stroke-linejoin%3d%22round%22%3e%3cpolyline%20points%3d%226%209%2012%2015%2018%209%22%3e%3c%2fpolyline%3e%3c%2fsvg%3e')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat"
            >
              {CLOCK_ROUNDING_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <p className="text-xs text-gray-500 mt-2">
              打刻時間を指定した単位で切り捨てます。出勤・退勤の両方に適用されます。
            </p>
          </div>
        </div>

      </div>
    </SettingsCard>
  );
}
