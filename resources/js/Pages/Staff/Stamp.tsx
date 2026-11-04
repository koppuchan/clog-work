import React, { useState, useEffect } from 'react';
import StaffLayout from '@/Layouts/StaffLayout';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { AlertTriangle, AlertCircle, CheckCircle, MapPin } from 'lucide-react';
import axios from 'axios';
import { useCurrentTime } from '@/hooks/useCurrentTime';

interface StampRecord {
  id: number;
  type: string;
  typeLabel: string;
  time: string;
}

interface CurrentStatus {
  isWorking: boolean;
  isOnBreak: boolean;
  clockInTime: string | null;
  breakCount: number;
  hasClockInToday: boolean;
  hasClockOutToday: boolean;
}

interface LocationRecording {
  enabled: boolean;
  consented: boolean;
}

interface StampPageProps {
  currentStatus: CurrentStatus;
  todayRecords: StampRecord[];
  locationRecording?: LocationRecording;
}

type StampAction = 'clock-in' | 'clock-out' | 'break-start' | 'break-end';

interface StampPosition {
  latitude: number;
  longitude: number;
  accuracy: number;
}

// 同意しないを選んだ印。ブラウザを閉じるまで、打刻のたびに同意を聞き直さない
const LOCATION_DECLINED_KEY = 'stampLocationDeclined';

function readDeclined(): boolean {
  try {
    return sessionStorage.getItem(LOCATION_DECLINED_KEY) === '1';
  } catch {
    return false;
  }
}

function writeDeclined(): void {
  try {
    sessionStorage.setItem(LOCATION_DECLINED_KEY, '1');
  } catch {
    // 保存できなくても打刻には影響しない
  }
}

/**
 * 端末の現在位置を取得する。取得できない場合は、その理由（画面に出す文言）を返す
 */
function getCurrentPosition(): Promise<StampPosition | { error: string }> {
  return new Promise((resolve) => {
    if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
      resolve({ error: 'この端末では位置情報を利用できません。' });
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) =>
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
        }),
      (error) => {
        if (error.code === error.PERMISSION_DENIED) {
          resolve({ error: '位置情報の利用が許可されていません。端末やブラウザの設定を確認してください。' });
        } else if (error.code === error.TIMEOUT) {
          resolve({ error: '位置情報の取得に時間がかかりました。' });
        } else {
          resolve({ error: '位置情報を取得できませんでした。' });
        }
      },
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 15000 }
    );
  });
}

function StampPage({ currentStatus: initialStatus, todayRecords: initialRecords, locationRecording }: StampPageProps) {
  const currentTime = useCurrentTime();
  const [currentStatus, setCurrentStatus] = useState<CurrentStatus>(initialStatus);
  const [todayRecords, setTodayRecords] = useState<StampRecord[]>(initialRecords);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'warning' | 'error'; text: string } | null>(null);
  const [locationConsented, setLocationConsented] = useState(locationRecording?.consented ?? false);
  const [pendingAction, setPendingAction] = useState<StampAction | null>(null);
  const locationEnabled = locationRecording?.enabled ?? false;

  // 別スタッフへの切り替え（ログアウト→別アカウントログイン）後、
  // Inertiaのpartial visit等で前スタッフのstateが残るのを防ぐため、
  // propsが更新されたらstateを同期し直す。
  useEffect(() => {
    setCurrentStatus(initialStatus);
    setTodayRecords(initialRecords);
  }, [initialStatus, initialRecords]);

  useEffect(() => {
    setLocationConsented(locationRecording?.consented ?? false);
  }, [locationRecording?.consented]);

  // メッセージを3秒後に消す（位置情報の警告は読み切れるよう長めに出す）
  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), message.type === 'warning' ? 8000 : 3000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  // 労務アラート用の仮データ（後でバックエンドから取得）
  const overtimeHours = 0; // TODO: バックエンドから取得
  const notificationThreshold = 45;
  const limitThreshold = 80;

  // 打刻する。withLocationがtrueのときは、端末の位置情報も付ける
  // （位置情報が取得できなくても、打刻はそのまま行い、取得できなかったことを知らせる）
  const performStamp = async (action: StampAction, withLocation: boolean) => {
    setIsLoading(true);
    try {
      let position: StampPosition | null = null;
      let locationError: string | null = null;
      if (withLocation) {
        const result = await getCurrentPosition();
        if ('error' in result) {
          locationError = result.error;
        } else {
          position = result;
        }
      }

      const response = await axios.post(`/staff/stamp/${action}`, position ?? {});
      if (response.data.success) {
        setCurrentStatus(response.data.currentStatus);
        if (response.data.record) {
          setTodayRecords((prev) => [...prev, response.data.record]);
        }
        if (locationError) {
          setMessage({ type: 'warning', text: `${response.data.message} ${locationError}位置情報なしで記録しました。` });
        } else {
          setMessage({ type: 'success', text: response.data.message });
        }
      }
    } catch (error: unknown) {
      if (axios.isAxiosError(error) && error.response?.data?.message) {
        setMessage({ type: 'error', text: error.response.data.message });
      } else {
        setMessage({ type: 'error', text: '打刻に失敗しました。' });
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleStamp = async (action: StampAction) => {
    // 位置情報を記録する会社で、まだ同意していない人には、最初に案内と同意の確認を出す
    // （同意しないを選んだ場合は、その間は聞き直さず、位置情報なしで打刻する）
    if (locationEnabled && !locationConsented && !readDeclined()) {
      setPendingAction(action);
      return;
    }
    await performStamp(action, locationEnabled && locationConsented);
  };

  const handleLocationConsent = async () => {
    const action = pendingAction;
    setPendingAction(null);
    if (!action) return;

    try {
      await axios.post('/staff/stamp/location-consent');
      setLocationConsented(true);
      await performStamp(action, true);
    } catch {
      // 同意を記録できなかったときは、位置情報なしで打刻する
      setMessage({ type: 'warning', text: '同意を記録できませんでした。位置情報なしで打刻します。' });
      await performStamp(action, false);
    }
  };

  const handleLocationDecline = async () => {
    const action = pendingAction;
    setPendingAction(null);
    writeDeclined();
    if (action) {
      await performStamp(action, false);
    }
  };

  const getStatusText = () => {
    if (currentStatus.hasClockOutToday) return '退勤済み';
    if (!currentStatus.isWorking) return '未出勤';
    if (currentStatus.isOnBreak) return '休憩中';
    return '勤務中';
  };

  const getStatusColor = () => {
    if (!currentStatus.isWorking) return 'text-gray-500';
    if (currentStatus.isOnBreak) return 'text-yellow-600';
    return 'text-green-600';
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">打刻</h1>

      {/* メッセージ表示 */}
      {message && (
        <div
          className={`p-4 rounded-lg flex items-center gap-2 ${
            message.type === 'success'
              ? 'bg-green-50 text-green-800'
              : message.type === 'warning'
                ? 'bg-yellow-50 text-yellow-800'
                : 'bg-red-50 text-red-800'
          }`}
        >
          {message.type === 'success' ? (
            <CheckCircle className="h-5 w-5 flex-shrink-0" />
          ) : message.type === 'warning' ? (
            <AlertTriangle className="h-5 w-5 flex-shrink-0" />
          ) : (
            <AlertCircle className="h-5 w-5 flex-shrink-0" />
          )}
          {message.text}
        </div>
      )}

      {/* 労務アラート */}
      {overtimeHours >= limitThreshold && (
        <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow">
          <div className="flex items-start">
            <AlertCircle className="h-6 w-6 text-red-600 mt-0.5 flex-shrink-0" />
            <div className="ml-3">
              <h3 className="text-sm font-bold text-red-800">【警告】残業時間超過</h3>
              <p className="mt-1 text-sm text-red-700">
                今月の残業時間が<strong>{overtimeHours.toFixed(1)}時間</strong>に達しています。
                残業時間が非常に多くなっています。健康管理に十分注意してください。
              </p>
            </div>
          </div>
        </div>
      )}

      {overtimeHours >= notificationThreshold && overtimeHours < limitThreshold && (
        <div className="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg shadow">
          <div className="flex items-start">
            <AlertTriangle className="h-6 w-6 text-yellow-600 mt-0.5 flex-shrink-0" />
            <div className="ml-3">
              <h3 className="text-sm font-bold text-yellow-800">【注意】残業時間が多くなっています</h3>
              <p className="mt-1 text-sm text-yellow-700">
                今月の残業時間が<strong>{overtimeHours.toFixed(1)}時間</strong>に達しています。
                残業時間が多くなっています。体調管理に注意してください。
              </p>
            </div>
          </div>
        </div>
      )}

      {/* 打刻画面 */}
      <div className="bg-white shadow rounded-lg p-8">
        <div className="text-center space-y-6">
          <div className="text-6xl font-bold text-gray-900 font-mono">
            {format(currentTime, 'HH:mm:ss')}
          </div>
          <div className="text-xl text-gray-600">
            {format(currentTime, 'yyyy年MM月dd日 (E)', { locale: ja })}
          </div>

          {/* 現在の状態 */}
          <div className={`text-lg font-semibold ${getStatusColor()}`}>
            現在の状態: {getStatusText()}
            {currentStatus.clockInTime && ` (出勤: ${currentStatus.clockInTime})`}
          </div>

          <div className="grid grid-cols-2 gap-4 max-w-md mx-auto mt-4">
            <button
              onClick={() => handleStamp('clock-in')}
              disabled={isLoading || currentStatus.isWorking || currentStatus.hasClockInToday}
              className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                currentStatus.isWorking || currentStatus.hasClockInToday
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-blue-600 hover:bg-blue-700 text-white'
              }`}
            >
              出勤
            </button>
            <button
              onClick={() => handleStamp('clock-out')}
              disabled={isLoading || !currentStatus.isWorking || currentStatus.isOnBreak || currentStatus.hasClockOutToday}
              className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                !currentStatus.isWorking || currentStatus.isOnBreak || currentStatus.hasClockOutToday
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-red-600 hover:bg-red-700 text-white'
              }`}
            >
              退勤
            </button>
            <button
              onClick={() => handleStamp('break-start')}
              disabled={isLoading || !currentStatus.isWorking || currentStatus.isOnBreak || currentStatus.breakCount >= 2}
              className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                !currentStatus.isWorking || currentStatus.isOnBreak || currentStatus.breakCount >= 2
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-yellow-600 hover:bg-yellow-700 text-white'
              }`}
            >
              休憩開始
            </button>
            <button
              onClick={() => handleStamp('break-end')}
              disabled={isLoading || !currentStatus.isOnBreak}
              className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                !currentStatus.isOnBreak
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-green-600 hover:bg-green-700 text-white'
              }`}
            >
              休憩終了
            </button>
          </div>

          {/* 注意書き */}
          <p className="text-xs text-gray-600 bg-gray-50 rounded px-3 py-2 max-w-md mx-auto mt-4">
            ※ 出勤・退勤は1日1回までです。打刻時間を変更する場合は申請が必要です。
          </p>
          {locationEnabled && (
            <p className="text-xs text-gray-600 bg-gray-50 rounded px-3 py-2 max-w-md mx-auto flex items-center justify-center gap-1">
              <MapPin className="h-3.5 w-3.5" />
              {locationConsented ? '打刻のときに位置情報を記録します。' : '打刻のときの位置情報は記録しません（未同意）。'}
            </p>
          )}
        </div>
      </div>

      {/* 本日の打刻履歴 */}
      {todayRecords.length > 0 && (
        <div className="bg-white shadow rounded-lg p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">本日の打刻履歴</h2>
          <div className="space-y-2">
            {todayRecords.map((record) => (
              <div key={record.id} className="flex items-center justify-between py-2 border-b border-gray-100">
                <span className="text-gray-700">{record.typeLabel}</span>
                <span className="font-mono text-gray-900">{record.time}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* 位置情報の取得についての案内と同意（初回のみ） */}
      {pendingAction && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-600 bg-opacity-50 p-4">
          <div className="bg-white rounded-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
            <h3 className="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
              <MapPin className="h-5 w-5 text-blue-600" />
              位置情報の取得について
            </h3>
            <ul className="text-sm text-gray-700 space-y-2 mb-4 list-disc pl-5">
              <li>この会社では、打刻した場所を確認するため、打刻したときの位置情報（緯度・経度・精度）を記録します。</li>
              <li>位置情報を取得するのは、出勤・退勤・休憩の打刻ボタンを押したときだけです。常に位置を追跡することはありません。</li>
              <li>記録した位置情報は、会社の管理者が勤務実績の出力（CSV）で確認できます。</li>
              <li>同意しない場合や、位置情報を取得できない場合も、打刻はできます。その場合、位置情報は記録されません。</li>
            </ul>
            <div className="grid grid-cols-1 gap-2">
              <button
                type="button"
                onClick={handleLocationConsent}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-md font-medium"
              >
                同意して打刻する
              </button>
              <button
                type="button"
                onClick={handleLocationDecline}
                className="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-4 rounded-md font-medium"
              >
                同意しない（位置情報なしで打刻する）
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

StampPage.layout = (page: React.ReactNode) => <StaffLayout>{page}</StaffLayout>;

export default StampPage;
