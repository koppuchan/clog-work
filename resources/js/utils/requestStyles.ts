/**
 * 申請種別コードに応じたバッジのTailwindクラスを返す
 */
const badgeStyleMap: Record<string, string> = {
  'paid-leave': 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
  'half-day-leave': 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
  'hourly-leave': 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
  'clock-error': 'bg-blue-100 text-blue-800 hover:bg-blue-200',
  'late': 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200',
  'early-leave': 'bg-amber-100 text-amber-800 hover:bg-amber-200',
  'special-leave': 'bg-teal-100 text-teal-800 hover:bg-teal-200',
  'absence': 'bg-red-100 text-red-800 hover:bg-red-200',
  'overtime': 'bg-purple-100 text-purple-800 hover:bg-purple-200',
  'other': 'bg-gray-100 text-gray-800 hover:bg-gray-200',
};

const defaultStyle = 'bg-gray-100 text-gray-800 hover:bg-gray-200';

export function getRequestBadgeStyle(code: string): string {
  return badgeStyleMap[code] ?? defaultStyle;
}
