import { useEffect, useRef, useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import { TEXT_SIZE_OPTIONS, type StampDisplaySettings, type StampTextSize } from '@/utils/stampDisplay';
import type { StampDepartment } from '@/hooks/useStampDisplay';

interface StampDisplayControlProps {
  departments: StampDepartment[];
  settings: StampDisplaySettings;
  onDepartmentsChange: (departmentIds: number[]) => void;
  onTextSizeChange: (size: StampTextSize) => void;
}

/**
 * 打刻専用画面の表示の設定（表示する事業所・文字の大きさ）
 *
 * ボタンを押したときだけ、設定のパネルを開く。設定はこのPC（ブラウザ）だけに保存される。
 */
export default function StampDisplayControl({
  departments,
  settings,
  onDepartmentsChange,
  onTextSizeChange,
}: StampDisplayControlProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isOpen) return;

    const onPointerDown = (event: PointerEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setIsOpen(false);
    };
    window.addEventListener('pointerdown', onPointerDown);
    window.addEventListener('keydown', onKeyDown);

    return () => {
      window.removeEventListener('pointerdown', onPointerDown);
      window.removeEventListener('keydown', onKeyDown);
    };
  }, [isOpen]);

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        className="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-800 hover:bg-gray-50"
        aria-label="表示の設定"
        aria-expanded={isOpen}
        title="表示の設定"
      >
        <SlidersHorizontal className="h-5 w-5" />
      </button>

      {isOpen && (
        <div className="absolute right-0 top-full z-20 mt-2 w-72 max-w-[90vw] rounded-lg border border-gray-200 bg-white p-4 shadow-lg space-y-4">
          {departments.length > 0 && (
            <fieldset>
              <legend className="text-sm font-medium text-gray-800 mb-1">表示する事業所（複数選べます）</legend>
              <div className="max-h-48 overflow-y-auto rounded-md border border-gray-300 divide-y divide-gray-100">
                <label className="flex items-center gap-2 px-2 py-1.5 text-sm bg-gray-50 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={settings.departmentIds.length === 0}
                    onChange={() => onDepartmentsChange([])}
                    className="h-4 w-4"
                  />
                  <span className="font-medium">すべての事業所</span>
                </label>
                {departments.map((department) => (
                  <label key={department.id} className="flex items-center gap-2 px-2 py-1.5 text-sm cursor-pointer">
                    <input
                      type="checkbox"
                      checked={settings.departmentIds.includes(department.id)}
                      onChange={(e) =>
                        onDepartmentsChange(
                          e.target.checked
                            ? [...settings.departmentIds, department.id]
                            : settings.departmentIds.filter((id) => id !== department.id)
                        )
                      }
                      className="h-4 w-4"
                    />
                    <span>{department.name}</span>
                  </label>
                ))}
              </div>
              <p className="mt-1 text-xs text-gray-500">
                選んだ事業所のスタッフがカードで打刻したときだけ、この画面に表示し、音を鳴らします。
                本社の中の部門を、まとめて選ぶこともできます。
              </p>
            </fieldset>
          )}

          <div>
            <p className="text-sm font-medium text-gray-800 mb-1">文字の大きさ（打刻の表示）</p>
            <div className="flex gap-2">
              {TEXT_SIZE_OPTIONS.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => onTextSizeChange(option.value)}
                  aria-pressed={settings.textSize === option.value}
                  className={`flex-1 rounded-md border px-2 py-1 text-sm ${
                    settings.textSize === option.value
                      ? 'border-blue-600 bg-blue-50 text-blue-700'
                      : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  {option.label}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
