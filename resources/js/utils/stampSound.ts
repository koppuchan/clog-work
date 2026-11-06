/**
 * 打刻画面の効果音（ピッ）
 *
 * 音声ファイルは使わず、ブラウザの Web Audio API で鳴らす。リーダー本体やPCのドライバー、
 * 音量設定に関係なく、どのPCの打刻画面でも同じ音が鳴るようにするため。
 * 音の入切と大きさは、PCごと（ブラウザごと）に保存する。
 */

export interface StampSoundSettings {
  enabled: boolean;
  /** 0（無音）〜1（最大） */
  volume: number;
}

export type StampSoundKind = 'success' | 'warning';

export const DEFAULT_STAMP_SOUND_SETTINGS: StampSoundSettings = { enabled: true, volume: 0.6 };

const STORAGE_KEY = 'stampSoundSettings';

export function loadStampSoundSettings(): StampSoundSettings {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return DEFAULT_STAMP_SOUND_SETTINGS;

    const parsed = JSON.parse(raw) as Partial<StampSoundSettings>;
    return {
      enabled: typeof parsed.enabled === 'boolean' ? parsed.enabled : DEFAULT_STAMP_SOUND_SETTINGS.enabled,
      volume:
        typeof parsed.volume === 'number' && parsed.volume >= 0 && parsed.volume <= 1
          ? parsed.volume
          : DEFAULT_STAMP_SOUND_SETTINGS.volume,
    };
  } catch {
    return DEFAULT_STAMP_SOUND_SETTINGS;
  }
}

export function saveStampSoundSettings(settings: StampSoundSettings): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
  } catch {
    // 保存できなくても、その画面を開いている間は設定が効く
  }
}

let audioContext: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
  if (typeof window === 'undefined') return null;

  const Constructor =
    window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
  if (!Constructor) return null;

  if (!audioContext) {
    audioContext = new Constructor();
  }
  return audioContext;
}

/**
 * 音を鳴らせる状態にする
 *
 * ブラウザは、画面を操作する（クリック・キー入力）までは音を出せない決まりになっている。
 * 操作されたときに呼んで、鳴らせる状態にしておく。
 */
export function unlockStampAudio(): Promise<boolean> {
  const context = getAudioContext();
  if (!context) return Promise.resolve(false);
  if (context.state === 'running') return Promise.resolve(true);

  return context.resume().then(
    () => context.state === 'running',
    () => false
  );
}

export function isStampAudioRunning(): boolean {
  return audioContext?.state === 'running';
}

function beep(context: AudioContext, frequency: number, startAt: number, duration: number, peak: number): void {
  const oscillator = context.createOscillator();
  const gain = context.createGain();

  oscillator.type = 'sine';
  oscillator.frequency.value = frequency;
  gain.gain.setValueAtTime(0.0001, startAt);
  gain.gain.linearRampToValueAtTime(peak, startAt + 0.008);
  gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

  oscillator.connect(gain);
  gain.connect(context.destination);
  oscillator.start(startAt);
  oscillator.stop(startAt + duration + 0.02);
}

/**
 * 効果音を鳴らす。鳴らせなかった場合（まだ操作されていない等）はfalse
 *
 * - success: 高い「ピッ」（打刻できた）
 * - warning: 低い「ピッピッ」（打刻できなかった・要確認）
 */
export function playStampSound(kind: StampSoundKind, volume: number): boolean {
  const context = getAudioContext();
  if (!context || context.state !== 'running') return false;
  if (volume <= 0) return true;

  const peak = Math.min(1, Math.max(0.0002, Math.pow(volume, 1.6) * 0.9));
  const now = context.currentTime + 0.01;

  if (kind === 'success') {
    beep(context, 1800, now, 0.14, peak);
  } else {
    beep(context, 660, now, 0.16, peak);
    beep(context, 660, now + 0.24, 0.16, peak);
  }
  return true;
}
