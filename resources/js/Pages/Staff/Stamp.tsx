import React, { useState, useEffect } from 'react';
import StaffLayout from '@/Layouts/StaffLayout';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { AlertTriangle, AlertCircle, CheckCircle } from 'lucide-react';
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

interface StampPageProps {
  currentStatus: CurrentStatus;
  todayRecords: StampRecord[];
}

function StampPage({ currentStatus: initialStatus, todayRecords: initialRecords }: StampPageProps) {
  const currentTime = useCurrentTime();
  const [currentStatus, setCurrentStatus] = useState<CurrentStatus>(initialStatus);
  const [todayRecords, setTodayRecords] = useState<StampRecord[]>(initialRecords);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // 別スタッフへの切り替え（ログアウト→別アカウントログイン）後、
  // Inertiaのpartial visit等で前スタッフのstateが残るのを防ぐため、
  // propsが更新されたらstateを同期し直す。
  useEffect(() => {
    setCurrentStatus(initialStatus);
    setTodayRecords(initialRecords);
  }, [initialStatus, initialRecords]);

  // メッセージを3秒後に消す
  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  // 労務アラート用の仮データ（後でバックエンドから取得）
  const overtimeHours = 0; // TODO: バックエンドから取得
  const notificationThreshold = 45;
  const limitThreshold = 80;

  const handleStamp = async (action: 'clock-in' | 'clock-out' | 'break-start' | 'break-end') => {
    setIsLoading(true);
    try {
      const response = await axios.post(`/staff/stamp/${action}`);
      if (response.data.success) {
        setMessage({ type: 'success', text: response.data.message });
        setCurrentStatus(response.data.currentStatus);
        if (response.data.record) {
          setTodayRecords((prev) => [...prev, response.data.record]);
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
            message.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
          }`}
        >
          {message.type === 'success' ? (
            <CheckCircle className="h-5 w-5" />
          ) : (
            <AlertCircle className="h-5 w-5" />
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
    </div>
  );
}

StampPage.layout = (page: React.ReactNode) => <StaffLayout>{page}</StaffLayout>;

export default StampPage;
