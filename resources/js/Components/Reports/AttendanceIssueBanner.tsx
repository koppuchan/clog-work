import { AlertTriangle } from 'lucide-react';

export type AttendanceIssueMap = Record<string, string[]>;

export const ISSUE_LABELS: Record<string, string> = {
  missing_clock_out: '退勤忘れ',
  not_calculated: '未計算',
  missing_break_end: '休憩打刻漏れ',
};

interface Props {
  issues: AttendanceIssueMap;
}

/**
 * 要対応の勤務実績をまとめて知らせるバナー
 *
 * トーストではなくバナーにしている。退勤忘れや未計算は直すまで残る状態のため、
 * 数秒で消える通知だと気づいた時点で対応できなかった場合に見失う。
 */
export default function AttendanceIssueBanner({ issues }: Props) {
  const dates = Object.keys(issues).sort();

  if (dates.length === 0) {
    return null;
  }

  const counts = dates.reduce<Record<string, number>>((acc, date) => {
    issues[date].forEach((kind) => {
      acc[kind] = (acc[kind] ?? 0) + 1;
    });
    return acc;
  }, {});

  return (
    <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
      <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
      <div className="text-sm">
        <p className="font-semibold">
          確認が必要な日が {dates.length} 日あります
        </p>
        <p className="mt-1">
          {Object.entries(counts)
            .map(([kind, count]) => `${ISSUE_LABELS[kind] ?? kind} ${count}日`)
            .join('　/　')}
        </p>
        <p className="mt-1 text-xs text-amber-700">
          対象日: {dates.join('、')}
        </p>
      </div>
    </div>
  );
}
