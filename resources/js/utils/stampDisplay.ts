/**
 * 打刻専用画面の表示の設定（表示する事業所・文字の大きさ）
 *
 * 設定はPCごと（ブラウザごと）に保存する。
 */

export type StampTextSize = 'normal' | 'large' | 'xlarge';

export interface StampDisplaySettings {
  /** 表示する事業所（部署）のID。nullなら全事業所 */
  departmentId: number | null;
  textSize: StampTextSize;
}

export const DEFAULT_STAMP_DISPLAY_SETTINGS: StampDisplaySettings = { departmentId: null, textSize: 'normal' };

export const TEXT_SIZE_OPTIONS: { value: StampTextSize; label: string }[] = [
  { value: 'normal', label: '標準' },
  { value: 'large', label: '大' },
  { value: 'xlarge', label: '特大' },
];

/**
 * 打刻結果の表示の文字の大きさ。main は名前と時刻の行、sub は「出勤を記録しました。」などの行
 */
export function resultFontSizes(size: StampTextSize): { main: string; sub: string } {
  switch (size) {
    case 'large':
      return { main: '28px', sub: '24px' };
    case 'xlarge':
      return { main: '40px', sub: '32px' };
    default:
      return { main: '16px', sub: '14px' };
  }
}

const STORAGE_KEY = 'stampDisplaySettings';

export function loadStampDisplaySettings(): StampDisplaySettings {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return DEFAULT_STAMP_DISPLAY_SETTINGS;

    const parsed = JSON.parse(raw) as Partial<StampDisplaySettings>;
    return {
      departmentId: typeof parsed.departmentId === 'number' ? parsed.departmentId : null,
      textSize: TEXT_SIZE_OPTIONS.some((o) => o.value === parsed.textSize)
        ? (parsed.textSize as StampTextSize)
        : DEFAULT_STAMP_DISPLAY_SETTINGS.textSize,
    };
  } catch {
    return DEFAULT_STAMP_DISPLAY_SETTINGS;
  }
}

export function saveStampDisplaySettings(settings: StampDisplaySettings): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
  } catch {
    // 保存できなくても、その画面を開いている間は設定が効く
  }
}
