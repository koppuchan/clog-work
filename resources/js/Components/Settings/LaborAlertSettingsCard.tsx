import { AlertTriangle } from 'lucide-react';
import SettingsCard from './SettingsCard';
import { AlertMessagesForm } from '@/types/settings';

interface LaborAlertSettingsCardProps {
  alertOvertimeNotification: string;
  alertOvertimeLimit: string;
  alertMessages: AlertMessagesForm;
  onOvertimeNotificationChange: (value: string) => void;
  onOvertimeLimitChange: (value: string) => void;
  onAlertMessagesChange: (messages: AlertMessagesForm) => void;
}

export default function LaborAlertSettingsCard({
  alertOvertimeNotification,
  alertOvertimeLimit,
  alertMessages,
  onOvertimeNotificationChange,
  onOvertimeLimitChange,
  onAlertMessagesChange,
}: LaborAlertSettingsCardProps) {
  const handleMessageChange = (field: keyof AlertMessagesForm, value: string) => {
    onAlertMessagesChange({
      ...alertMessages,
      [field]: value,
    });
  };

  return (
    <SettingsCard title="労務アラート設定" icon={AlertTriangle} iconColor="text-red-600">
      <div className="space-y-6">
        {/* 残業時間設定 */}
        <div>
          <h4 className="text-sm font-semibold text-gray-800 mb-3">月間残業時間の管理</h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                通知時間
              </label>
              <div className="flex items-center space-x-2">
                <input
                  type="number"
                  value={alertOvertimeNotification}
                  onChange={(e) => onOvertimeNotificationChange(e.target.value)}
                  className="w-24 border border-gray-300 rounded-md p-2 text-gray-900"
                  min="0"
                  max="200"
                  step="5"
                />
                <span className="text-sm text-gray-700">時間/月</span>
              </div>
              <p className="text-xs text-yellow-700 mt-1">
                設定した時間を超過すると、該当する従業員に注意喚起の通知が届きます。
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                限度時間
              </label>
              <div className="flex items-center space-x-2">
                <input
                  type="number"
                  value={alertOvertimeLimit}
                  onChange={(e) => onOvertimeLimitChange(e.target.value)}
                  className="w-24 border border-gray-300 rounded-md p-2 text-gray-900"
                  min="0"
                  max="200"
                  step="5"
                />
                <span className="text-sm text-gray-700">時間/月</span>
              </div>
              <p className="text-xs text-red-700 mt-1">
                設定した時間を超過すると、該当する従業員に警告通知が届きます。
              </p>
            </div>
          </div>
        </div>

        {/* アラートメッセージ設定 */}
        <div className="border-t pt-6">
          <h4 className="text-sm font-semibold text-gray-800 mb-3">アラートメッセージのカスタマイズ</h4>
          <div className="space-y-4">
            {/* 警告アラート */}
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <h5 className="text-sm font-semibold text-red-800 mb-3">警告アラート（限度時間超過時）</h5>
              <div className="space-y-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    タイトル
                  </label>
                  <input
                    type="text"
                    value={alertMessages.warningTitle}
                    onChange={(e) => handleMessageChange('warningTitle', e.target.value)}
                    className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                    placeholder="例: 【警告】残業時間超過"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    メッセージ
                  </label>
                  <textarea
                    value={alertMessages.warningMessage}
                    onChange={(e) => handleMessageChange('warningMessage', e.target.value)}
                    className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                    rows={3}
                    placeholder="例: 今月の残業時間が{hours}時間に達しています。"
                  />
                  <p className="text-xs text-gray-500 mt-1">
                    ※ {'{hours}'} は残業時間に自動置換されます。
                  </p>
                </div>
              </div>
            </div>

            {/* 注意アラート */}
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <h5 className="text-sm font-semibold text-yellow-800 mb-3">注意アラート（通知時間超過時）</h5>
              <div className="space-y-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    タイトル
                  </label>
                  <input
                    type="text"
                    value={alertMessages.cautionTitle}
                    onChange={(e) => handleMessageChange('cautionTitle', e.target.value)}
                    className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                    placeholder="例: 【注意】残業時間が多くなっています"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    メッセージ
                  </label>
                  <textarea
                    value={alertMessages.cautionMessage}
                    onChange={(e) => handleMessageChange('cautionMessage', e.target.value)}
                    className="w-full border border-gray-300 rounded-md p-2 text-gray-900"
                    rows={3}
                    placeholder="例: 今月の残業時間が{hours}時間に達しています。"
                  />
                  <p className="text-xs text-gray-500 mt-1">
                    ※ {'{hours}'} は残業時間に自動置換されます。
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </SettingsCard>
  );
}
