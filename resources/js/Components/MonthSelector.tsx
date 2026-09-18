import { useEffect, useRef, useState } from 'react';
import { format, addMonths, parse } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Calendar } from 'lucide-react';

interface MonthSelectorProps {
  value: string;
  onChange: (value: string) => void;
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

const defaultFormatLabel = (date: Date) => format(date, 'yyyy年M月', { locale: ja });

/**
 * 期間選択（シフト管理・勤務実績で共通使用）
 *
 * 以前は最大78個の選択肢を持つネイティブのプルダウン1つだけで、目的の
 * 期間を探すのにリストを縦に長くスクロールする必要があった
 * （クライアント報告: 縦に長く伸びる日付表示は非常に操作しづらい）。
 * 年単位のヘッダーと月を4列で並べたグリッドのポップオーバーに置き換え、
 * 年送り・月選択をクリック操作だけで完結できるようにした。
 */
export default function MonthSelector({
  value,
  onChange,
  className = '',
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

  const minValue = options[options.length - 1]?.value;
  const maxValue = options[0]?.value;
  const minYear = options[options.length - 1]?.date.getFullYear();
  const maxYear = options[0]?.date.getFullYear();

  const currentDate = value ? parse(value, 'yyyy-MM', new Date()) : new Date();

  const [isOpen, setIsOpen] = useState(false);
  const [displayedYear, setDisplayedYear] = useState(currentDate.getFullYear());
  const containerRef = useRef<HTMLDivElement>(null);

  // 開くたびに現在選択中の年を表示する
  useEffect(() => {
    if (isOpen) {
      setDisplayedYear(currentDate.getFullYear());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  // ポップオーバーの外側をクリックしたら閉じる
  useEffect(() => {
    if (!isOpen) return;

    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setIsOpen(false);
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen]);

  // 一覧を開かなくても前後の期間へすぐ移動できるよう矢印ボタンを設ける
  const goToAdjacentMonth = (offset: 1 | -1) => {
    onChange(format(addMonths(currentDate, offset), 'yyyy-MM'));
  };

  // グリッド内は自然な時系列（1月→12月）で読めるよう並べ替える
  // （optionsは新しい月を上に出すため月ごとの一覧では降順になっている）
  const monthsInDisplayedYear = options
    .filter((option) => option.date.getFullYear() === displayedYear)
    .sort((a, b) => a.date.getTime() - b.date.getTime());

  return (
    <div className="flex items-center gap-1">
      <button
        type="button"
        onClick={() => goToAdjacentMonth(-1)}
        disabled={value <= minValue}
        aria-label="前の期間"
        className="p-2 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white shrink-0"
      >
        <ChevronLeft className="w-4 h-4" />
      </button>

      <div className="relative flex-1 min-w-0" ref={containerRef}>
        <button
          type="button"
          onClick={() => setIsOpen((open) => !open)}
          className={`flex items-center justify-between gap-2 w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white hover:bg-gray-50 ${className}`}
        >
          <span className="truncate">{value ? formatLabel(currentDate) : placeholder}</span>
          <Calendar className="w-4 h-4 text-gray-500 shrink-0" />
        </button>

        {isOpen && (
          <div className="absolute z-20 mt-1 w-72 bg-white border border-gray-300 rounded-lg shadow-lg overflow-hidden">
            {/* 年送りヘッダー */}
            <div className="flex items-center justify-between px-3 py-2 border-b border-gray-200">
              <span className="text-sm font-semibold text-gray-700">{displayedYear}年</span>
              <div className="flex items-center rounded-md border border-gray-300 overflow-hidden">
                <button
                  type="button"
                  onClick={() => setDisplayedYear((y) => y - 1)}
                  disabled={displayedYear <= minYear}
                  aria-label="前の年"
                  className="p-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed border-r border-gray-300"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>
                <button
                  type="button"
                  onClick={() => setDisplayedYear(currentDate.getFullYear())}
                  aria-label="選択中の年に戻る"
                  className="px-2 py-1.5 text-gray-600 hover:bg-gray-50"
                >
                  <span className="block w-2 h-2 rounded-full bg-gray-500" />
                </button>
                <button
                  type="button"
                  onClick={() => setDisplayedYear((y) => y + 1)}
                  disabled={displayedYear >= maxYear}
                  aria-label="次の年"
                  className="p-1.5 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed border-l border-gray-300"
                >
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* 月グリッド */}
            <div className="grid grid-cols-4 gap-2 p-3">
              {monthsInDisplayedYear.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => {
                    onChange(option.value);
                    setIsOpen(false);
                  }}
                  className={`px-2 py-2 rounded-md text-sm text-center ${
                    option.value === value
                      ? 'bg-blue-600 text-white font-semibold'
                      : 'text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  {format(option.date, 'M月', { locale: ja })}
                </button>
              ))}
            </div>

            {placeholder && (
              <button
                type="button"
                onClick={() => {
                  onChange('');
                  setIsOpen(false);
                }}
                className={`w-full px-3 py-2 text-sm text-center border-t border-gray-200 ${
                  value === '' ? 'bg-blue-600 text-white font-semibold' : 'text-gray-600 hover:bg-gray-100'
                }`}
              >
                {placeholder}
              </button>
            )}
          </div>
        )}
      </div>

      <button
        type="button"
        onClick={() => goToAdjacentMonth(1)}
        disabled={value >= maxValue}
        aria-label="次の期間"
        className="p-2 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white shrink-0"
      >
        <ChevronRight className="w-4 h-4" />
      </button>
    </div>
  );
}
