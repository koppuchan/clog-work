import { format, addMonths } from 'date-fns';
import { ja } from 'date-fns/locale';

interface MonthSelectorProps {
  value: string;
  onChange: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  className?: string;
  formatLabel?: (date: Date) => string;
  placeholder?: string;
}

const defaultClassName =
  'border border-gray-300 rounded-md px-3 py-2 pr-8 text-sm appearance-none bg-[url(\'data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2024%2024%22%20fill%3d%22none%22%20stroke%3d%22%23666%22%20stroke-width%3d%222%22%20stroke-linecap%3d%22round%22%20stroke-linejoin%3d%22round%22%3e%3cpolyline%20points%3d%226%209%2012%2015%2018%209%22%3e%3c%2fpolyline%3e%3c%2fsvg%3e\')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat bg-white';

const defaultFormatLabel = (date: Date) => format(date, 'yyyy年M月', { locale: ja });

export default function MonthSelector({
  value,
  onChange,
  className = defaultClassName,
  formatLabel = defaultFormatLabel,
  placeholder,
}: MonthSelectorProps) {
  return (
    <select value={value} onChange={onChange} className={className}>
      {placeholder && <option value="">{placeholder}</option>}
      {Array.from({ length: 24 }, (_, i) => {
        const date = addMonths(new Date(), i - 12);
        const optionValue = format(date, 'yyyy-MM');
        return (
          <option key={optionValue} value={optionValue}>
            {formatLabel(date)}
          </option>
        );
      })}
    </select>
  );
}
