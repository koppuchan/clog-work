import { format, addMonths } from 'date-fns';
import { ja } from 'date-fns/locale';

interface MonthSelectorProps {
  value: string;
  onChange: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  className?: string;
  formatLabel?: (date: Date) => string;
  placeholder?: string;
  /** 遡って選択できる月数（既定66ヶ月＝5年6ヶ月） */
  monthsBack?: number;
  /** 先の月を選択できる月数（シフトの事前登録に使用） */
  monthsForward?: number;
}

/**
 * 過去に遡って選択できる期間の既定値。
 *
 * 労働基準法上、賃金台帳や出勤簿には保存義務があるため、
 * 過去の勤務実績をさかのぼって参照できる必要がある。
 */
const DEFAULT_MONTHS_BACK = 66;

/**
 * 先の月を選択できる期間の既定値。シフトの事前登録に使用する。
 */
const DEFAULT_MONTHS_FORWARD = 11;

const defaultClassName =
  'border border-gray-300 rounded-md px-3 py-2 pr-8 text-sm appearance-none bg-[url(\'data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2024%2024%22%20fill%3d%22none%22%20stroke%3d%22%23666%22%20stroke-width%3d%222%22%20stroke-linecap%3d%22round%22%20stroke-linejoin%3d%22round%22%3e%3cpolyline%20points%3d%226%209%2012%2015%2018%209%22%3e%3c%2fpolyline%3e%3c%2fsvg%3e\')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat bg-white';

const defaultFormatLabel = (date: Date) => format(date, 'yyyy年M月', { locale: ja });

export default function MonthSelector({
  value,
  onChange,
  className = defaultClassName,
  formatLabel = defaultFormatLabel,
  placeholder,
  monthsBack = DEFAULT_MONTHS_BACK,
  monthsForward = DEFAULT_MONTHS_FORWARD,
}: MonthSelectorProps) {
  // 新しい月が上に来るよう、先の月から過去へ向かって並べる
  const options = Array.from({ length: monthsBack + monthsForward + 1 }, (_, i) => {
    const date = addMonths(new Date(), monthsForward - i);
    return { value: format(date, 'yyyy-MM'), date };
  });

  return (
    <select value={value} onChange={onChange} className={className}>
      {placeholder && <option value="">{placeholder}</option>}
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {formatLabel(option.date)}
        </option>
      ))}
    </select>
  );
}
