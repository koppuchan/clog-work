/**
 * 設定画面用のヘルパー関数
 */

import {
  SettingsShiftPattern,
  LaborAlertSettings,
  AlertMessagesForm,
  NewShiftPatternForm,
} from '@/types/settings';

/**
 * デフォルトのアラートメッセージ
 */
export const DEFAULT_ALERT_MESSAGES: AlertMessagesForm = {
  warningTitle: '【警告】残業時間超過',
  warningMessage: '今月の残業時間が{hours}時間に達しています。残業時間が非常に多くなっています。健康管理に十分注意してください。',
  cautionTitle: '【注意】残業時間が多くなっています',
  cautionMessage: '今月の残業時間が{hours}時間に達しています。残業時間が多くなっています。体調管理に注意してください。',
};

/**
 * デフォルトの労務アラート設定
 */
export const DEFAULT_LABOR_ALERT = {
  overtimeNotification: 45,
  overtimeLimit: 80,
  consecutiveWorkDays: 6,
};

/**
 * 新規シフトパターンフォームの初期値
 */
export const getInitialShiftPatternForm = (): NewShiftPatternForm => ({
  name: '',
  startTime: null,
  endTime: null,
  workMinutes: null,
  breakMode: null,
  breakMinutes: 60,
  breakStart: null,
  breakEnd: null,
  isInUse: false,
  colorId: null,
  backgroundColor: null,
  textColor: null,
});

/**
 * 休憩時間の初期設定
 */
export const DEFAULT_BREAK_SETTINGS = {
  start: '12:00',
  end: '13:00',
};

/**
 * LaborAlertSettingsからフォーム用のAlertMessagesFormに変換
 */
export function laborAlertToFormMessages(settings: LaborAlertSettings | undefined): AlertMessagesForm {
  return {
    warningTitle: settings?.messages?.warning?.title ?? DEFAULT_ALERT_MESSAGES.warningTitle,
    warningMessage: settings?.messages?.warning?.message ?? DEFAULT_ALERT_MESSAGES.warningMessage,
    cautionTitle: settings?.messages?.caution?.title ?? DEFAULT_ALERT_MESSAGES.cautionTitle,
    cautionMessage: settings?.messages?.caution?.message ?? DEFAULT_ALERT_MESSAGES.cautionMessage,
  };
}

/**
 * シフトパターンの時間表示を生成
 */
export function formatShiftTimeRange(pattern: SettingsShiftPattern): string {
  if (pattern.startTime && pattern.endTime) {
    return `${pattern.startTime}-${pattern.endTime}`;
  }
  return '時間未設定';
}

/**
 * シフトパターンの休憩時間表示を生成
 */
export function formatBreakTime(pattern: SettingsShiftPattern): string | null {
  if (pattern.breakMinutes) {
    return `休憩: ${pattern.breakMinutes}分`;
  }
  if (pattern.breakStart && pattern.breakEnd) {
    return `休憩: ${pattern.breakStart}-${pattern.breakEnd}`;
  }
  return null;
}

/**
 * 曜日ラベルの定義
 */
export const WEEKDAY_LABELS = [
  { key: 'sunday', label: '日', color: 'text-red-600' },
  { key: 'monday', label: '月', color: 'text-gray-700' },
  { key: 'tuesday', label: '火', color: 'text-gray-700' },
  { key: 'wednesday', label: '水', color: 'text-gray-700' },
  { key: 'thursday', label: '木', color: 'text-gray-700' },
  { key: 'friday', label: '金', color: 'text-gray-700' },
  { key: 'saturday', label: '土', color: 'text-blue-600' },
] as const;

/**
 * 丸め設定のオプション
 */
export const CLOCK_ROUNDING_OPTIONS = [
  { value: 'none', label: '丸めない' },
  { value: '5min', label: '5分単位' },
  { value: '10min', label: '10分単位' },
  { value: '15min', label: '15分単位' },
  { value: '30min', label: '30分単位' },
] as const;

/**
 * 丸め設定の例示を生成
 */
export function getRoundingExamples(rounding: string): string[][] {
  switch (rounding) {
    case '5min':
      return [
        ['9:00 〜 9:04', '9:00'],
        ['9:05 〜 9:09', '9:05'],
        ['9:10 〜 9:14', '9:10'],
        ['9:15 〜 9:19', '9:15'],
      ];
    case '10min':
      return [
        ['9:00 〜 9:09', '9:00'],
        ['9:10 〜 9:19', '9:10'],
        ['9:20 〜 9:29', '9:20'],
        ['9:30 〜 9:39', '9:30'],
      ];
    case '15min':
      return [
        ['9:00 〜 9:14', '9:00'],
        ['9:15 〜 9:29', '9:15'],
        ['9:30 〜 9:44', '9:30'],
        ['9:45 〜 9:59', '9:45'],
      ];
    case '30min':
      return [
        ['9:00 〜 9:29', '9:00'],
        ['9:30 〜 9:59', '9:30'],
        ['10:00 〜 10:29', '10:00'],
        ['10:30 〜 10:59', '10:30'],
      ];
    default:
      return [];
  }
}

/**
 * 丸め設定のラベルを取得
 */
export function getRoundingLabel(rounding: string): string {
  const option = CLOCK_ROUNDING_OPTIONS.find(o => o.value === rounding);
  return option?.label ?? '丸めない';
}

/**
 * 締切日の選択肢を生成（1-31）
 */
export function generateClosingDayOptions(): number[] {
  return Array.from({ length: 31 }, (_, i) => i + 1);
}
