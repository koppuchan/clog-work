import { AlertTriangle, Info } from 'lucide-react';

export interface LaborAlertItem {
  user_id: number;
  user_name?: string;
  alert_level?: { value: number; label: string };
  message?: string;
  total_overtime_minutes?: number;
  threshold_minutes?: number;
}

interface Props {
  alerts: LaborAlertItem[];
}

/**
 * スタッフ本人の労務アラート
 *
 * 管理者だけが見ていた残業時間の警告を、本人にも表示する。
 * 上限に近づいていることを本人が把握できるようにするため。
 */
export default function LaborAlertPanel({ alerts }: Props) {
  if (alerts.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2">
      {alerts.map((alert, index) => {
        // 警告レベル（value=1）は赤、それ以外（注意喚起）は黄色で示す
        const isWarning = alert.alert_level?.value === 1;
        const Icon = isWarning ? AlertTriangle : Info;

        return (
          <div
            key={`${alert.user_id}-${alert.alert_level?.value ?? index}`}
            className={`flex items-start gap-3 rounded-lg border p-4 ${
              isWarning
                ? 'border-red-200 bg-red-50 text-red-800'
                : 'border-amber-200 bg-amber-50 text-amber-800'
            }`}
          >
            <Icon className="mt-0.5 h-5 w-5 shrink-0" />
            <div className="text-sm">
              {alert.alert_level?.label && (
                <p className="font-semibold">{alert.alert_level.label}</p>
              )}
              {alert.message && <p className="mt-0.5 whitespace-pre-line">{alert.message}</p>}
            </div>
          </div>
        );
      })}
    </div>
  );
}
