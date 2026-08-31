import { ChevronLeft, ChevronRight } from 'lucide-react';

interface MonthSelectorProps {
  currentDate: Date;
  onPreviousMonth: () => void;
  onNextMonth: () => void;
  onYearChange: (year: number) => void;
  onMonthChange: (month: number) => void;
}

export function MonthSelector({
  currentDate,
  onPreviousMonth,
  onNextMonth,
  onYearChange,
  onMonthChange,
}: MonthSelectorProps) {
  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);
  const months = Array.from({ length: 12 }, (_, i) => i);

  return (
    <div className="flex items-center gap-4">
      <button onClick={onPreviousMonth} className="p-2 hover:bg-gray-100 rounded-lg transition-colors">
        <ChevronLeft className="h-5 w-5 text-gray-600" />
      </button>

      <div className="flex items-center gap-2 relative z-10">
        <select
          value={currentDate.getFullYear()}
          onChange={(e) => onYearChange(parseInt(e.target.value))}
          className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm font-semibold text-gray-900 bg-white"
        >
          {years.map((year) => (
            <option key={year} value={year}>
              {year}年
            </option>
          ))}
        </select>

        <select
          value={currentDate.getMonth()}
          onChange={(e) => onMonthChange(parseInt(e.target.value))}
          className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm font-semibold text-gray-900 bg-white"
        >
          {months.map((month) => (
            <option key={month} value={month}>
              {month + 1}月
            </option>
          ))}
        </select>
      </div>

      <button onClick={onNextMonth} className="p-2 hover:bg-gray-100 rounded-lg transition-colors">
        <ChevronRight className="h-5 w-5 text-gray-600" />
      </button>
    </div>
  );
}
