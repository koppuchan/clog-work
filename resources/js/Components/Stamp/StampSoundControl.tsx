import { useEffect, useRef, useState } from 'react';
import { Volume2, VolumeX } from 'lucide-react';
import type { StampSoundSettings } from '@/utils/stampSound';

interface StampSoundControlProps {
  settings: StampSoundSettings;
  /** 音を出したいのに、ブラウザがまだ音を許していない */
  isBlocked: boolean;
  onEnabledChange: (enabled: boolean) => void;
  onVolumeChange: (volume: number) => void;
  onTest: () => void;
}

/**
 * 打刻画面の効果音の入切と音量
 *
 * 普段はスピーカーのボタンだけを出し、押すと入切・音量・試し聴きのパネルを開く。
 * 設定はこのPC（ブラウザ）だけに保存される。
 */
export default function StampSoundControl({
  settings,
  isBlocked,
  onEnabledChange,
  onVolumeChange,
  onTest,
}: StampSoundControlProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  // パネルの外を押すか、Escキーを押すと閉じる
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

  const showsBlockedMark = settings.enabled && isBlocked;

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        className={`relative flex h-10 w-10 items-center justify-center rounded-full border ${
          settings.enabled ? 'border-gray-300 text-gray-800' : 'border-gray-300 text-gray-400'
        } bg-white hover:bg-gray-50`}
        aria-label="打刻音の設定"
        aria-expanded={isOpen}
        title="打刻音の設定"
      >
        {settings.enabled ? <Volume2 className="h-5 w-5" /> : <VolumeX className="h-5 w-5" />}
        {showsBlockedMark && (
          <span className="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-white bg-yellow-500" />
        )}
      </button>

      {isOpen && (
        <div className="absolute right-0 top-full z-20 mt-2 w-max max-w-[90vw] rounded-lg border border-gray-200 bg-white p-3 shadow-lg">
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => onEnabledChange(!settings.enabled)}
              className={`flex items-center gap-1 rounded-md border px-2 py-1 text-sm ${
                settings.enabled
                  ? 'border-blue-600 text-blue-700 bg-blue-50'
                  : 'border-gray-300 text-gray-500 bg-white'
              }`}
              aria-pressed={settings.enabled}
              aria-label={settings.enabled ? '打刻音を切る' : '打刻音を入れる'}
            >
              {settings.enabled ? <Volume2 className="h-4 w-4" /> : <VolumeX className="h-4 w-4" />}
              <span>{settings.enabled ? '音あり' : '音なし'}</span>
            </button>
            <input
              type="range"
              min={0}
              max={100}
              step={5}
              value={Math.round(settings.volume * 100)}
              onChange={(e) => onVolumeChange(Number(e.target.value) / 100)}
              disabled={!settings.enabled}
              className="w-24 disabled:opacity-40"
              aria-label="打刻音の大きさ"
            />
            <button
              type="button"
              onClick={onTest}
              className="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-700 hover:bg-gray-50"
            >
              試し聴き
            </button>
          </div>
          {showsBlockedMark && (
            <p className="mt-2 text-xs text-yellow-800 bg-yellow-100 border border-yellow-300 rounded px-2 py-1">
              音を鳴らすには、この画面を一度クリックしてください。
            </p>
          )}
        </div>
      )}
    </div>
  );
}
