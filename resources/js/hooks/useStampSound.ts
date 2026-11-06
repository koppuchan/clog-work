import { useCallback, useEffect, useRef, useState } from 'react';
import {
  isStampAudioRunning,
  loadStampSoundSettings,
  playStampSound,
  saveStampSoundSettings,
  unlockStampAudio,
  type StampSoundKind,
  type StampSoundSettings,
} from '@/utils/stampSound';

/**
 * 打刻画面の効果音の入切・音量と、鳴らす処理
 */
export function useStampSound() {
  const [settings, setSettings] = useState<StampSoundSettings>(loadStampSoundSettings);
  // 設定が入っていて音を出したいのに、ブラウザがまだ音を許していない状態
  const [isBlocked, setIsBlocked] = useState(false);
  const settingsRef = useRef(settings);
  settingsRef.current = settings;

  // 画面が操作されたら、音を鳴らせる状態にする（ブラウザの決まりで、操作されるまで音は出せない）
  useEffect(() => {
    const unlock = () => {
      void unlockStampAudio().then((running) => setIsBlocked(!running));
    };
    const events = ['pointerdown', 'keydown', 'touchstart'] as const;
    events.forEach((name) => window.addEventListener(name, unlock, { passive: true }));
    setIsBlocked(!isStampAudioRunning());

    return () => events.forEach((name) => window.removeEventListener(name, unlock));
  }, []);

  // 鳴らす処理は、ポーリングなど古い設定を持ったままの場所から呼ばれるため、最新の設定を見る
  const play = useCallback((kind: StampSoundKind) => {
    const current = settingsRef.current;
    if (!current.enabled || current.volume <= 0) return;
    setIsBlocked(!playStampSound(kind, current.volume));
  }, []);

  const update = useCallback((next: StampSoundSettings) => {
    setSettings(next);
    saveStampSoundSettings(next);
  }, []);

  const setEnabled = useCallback((enabled: boolean) => update({ ...settingsRef.current, enabled }), [update]);
  const setVolume = useCallback((volume: number) => update({ ...settingsRef.current, volume }), [update]);

  // 音量を確かめるための試し鳴らし（入切に関係なく鳴らす）
  const playTest = useCallback(() => {
    void unlockStampAudio().then((running) => {
      setIsBlocked(!running);
      if (running) playStampSound('success', settingsRef.current.volume);
    });
  }, []);

  return { settings, isBlocked, play, setEnabled, setVolume, playTest };
}
