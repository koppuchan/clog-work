import SettingsCard from './SettingsCard';

interface PlanCardProps {
  planName?: string;
  onPlanChange?: () => void;
}

export default function PlanCard({
  planName = '無料トライアル（1ヶ月間）',
  onPlanChange,
}: PlanCardProps) {
  return (
    <SettingsCard title="現在のプラン">
      <div className="space-y-4">
        <div>
          <p className="text-sm font-medium text-gray-700">プラン名</p>
          <p className="text-lg font-semibold text-gray-900">{planName}</p>
        </div>
        <div className="bg-yellow-100 border border-yellow-400 rounded-lg p-4">
          <p className="text-sm font-medium text-gray-700">
            無料期間終了後も利用を続けるには、有料プランへの切り替えが必要です。
          </p>
        </div>
        <div className="pt-4">
          <button
            type="button"
            onClick={onPlanChange}
            className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
          >
            プラン変更
          </button>
        </div>
      </div>
    </SettingsCard>
  );
}
