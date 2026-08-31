import { Clock } from 'lucide-react';
import SettingsCard from './SettingsCard';
import { ShiftDisplayPeriodType } from '@/hooks/settings/useCompanySettings';

interface ShiftDisplayPeriodCardProps {
  shiftDisplayPeriod: ShiftDisplayPeriodType;
  payrollClosingDay: number;
  onShiftDisplayPeriodChange: (value: ShiftDisplayPeriodType) => void;
}

export default function ShiftDisplayPeriodCard({
  shiftDisplayPeriod,
  payrollClosingDay,
  onShiftDisplayPeriodChange,
}: ShiftDisplayPeriodCardProps) {
  // 締め日の翌日を計算（月末考慮）
  const nextDay = payrollClosingDay >= 28 ? 1 : payrollClosingDay + 1;
  const periodDescription = payrollClosingDay >= 28
    ? `翌月1日〜${payrollClosingDay}日`
    : `${nextDay}日〜翌月${payrollClosingDay}日`;

  return (
    <SettingsCard title="シフト表表示期間設定" icon={Clock}>
      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            表示期間
          </label>
          <div className="space-y-3">
            <label className="flex items-center cursor-pointer">
              <input
                type="radio"
                value="monthly"
                checked={shiftDisplayPeriod === 'monthly'}
                onChange={(e) => onShiftDisplayPeriodChange(e.target.value as ShiftDisplayPeriodType)}
                className="mr-3"
              />
              <span className="text-sm text-gray-700">月初〜月末（1日〜30/31日）</span>
            </label>
            <label className="flex items-center cursor-pointer">
              <input
                type="radio"
                value="closing_day_based"
                checked={shiftDisplayPeriod === 'closing_day_based'}
                onChange={(e) => onShiftDisplayPeriodChange(e.target.value as ShiftDisplayPeriodType)}
                className="mr-3"
              />
              <span className="text-sm text-gray-700">
                締め日翌日スタート（{payrollClosingDay}日締め → {periodDescription}）
              </span>
            </label>
          </div>
          <p className="text-xs text-gray-500 mt-2">
            シフト表・勤務実績の表示期間を設定してください。
          </p>
          <p className="text-xs text-amber-600 mt-1">
            ※ この設定は勤務実績の表示期間にも反映されます。
          </p>
        </div>

        {/* 表示期間の例示 */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <p className="text-sm font-medium text-gray-700 mb-2">表示期間の例</p>
          <ul className="text-sm text-gray-600 space-y-1">
            <li>• 月初〜月末: 1月1日〜1月31日、2月1日〜2月28日</li>
            <li>• 締め日翌日スタート（25日締め）: 12月26日〜1月25日、1月26日〜2月25日</li>
          </ul>
        </div>
      </div>
    </SettingsCard>
  );
}
