import { useState, useEffect, useMemo, useRef, FormEvent } from 'react';
import { Head } from '@inertiajs/react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { AlertCircle, CheckCircle, User, ChevronLeft, Lock, Coffee, X } from 'lucide-react';
import axios from 'axios';
import { useCurrentTime } from '@/hooks/useCurrentTime';

/**
 * FeliCaカードによる打刻の試行結果（サーバー側でログ化されたもの）
 *
 * 常駐アプリは /stamp/{uuid}/felica にサーバーへ直接POSTするため、この画面は
 * ポーリングでしか結果を知れない。取得した結果はトーストとして表示する。
 */
interface FelicaEvent {
  id: number;
  status: 'success' | 'cooldown' | 'unregistered' | 'retired' | 'error';
  message: string;
  detail: string | null;
  userName: string | null;
  time: string;
  maskedIdm: string;
}

const FELICA_EVENTS_POLL_INTERVAL_MS = 3000;
const FELICA_TOAST_DURATION_MS = { success: 8000, warning: 12000 } as const;

interface UserItem {
  id: number;
  name: string;
  employee_code: string | null;
}

interface Company {
  id: number;
  name: string;
  uuid: string;
}

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

interface Props {
  company: Company;
  users: UserItem[];
}

export default function PublicStampPage({ company, users }: Props) {
  const currentTime = useCurrentTime();
  const [selectedUser, setSelectedUser] = useState<UserItem | null>(null);
  // 一覧は初期表示では畳んでおく。検索すると自動で開く
  const [nameQuery, setNameQuery] = useState('');
  const [isListOpen, setIsListOpen] = useState(false);
  // 休憩開始モード。onにすると、この後の打刻が休憩開始になる
  const [isBreakMode, setIsBreakMode] = useState(false);

  // 名前・フリガナ・個人コードで絞り込む
  const filteredUsers = useMemo(() => {
    const query = nameQuery.trim().toLowerCase();

    if (query === '') {
      return users;
    }

    return users.filter((user) =>
      [user.name, user.employee_code]
        .some((value) => (value ?? '').toLowerCase().includes(query))
    );
  }, [users, nameQuery]);

  // 休憩開始モードは Esc でも解除できるようにする
  useEffect(() => {
    if (! isBreakMode) {
      return;
    }

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setIsBreakMode(false);
      }
    };

    window.addEventListener('keydown', onKeyDown);

    return () => window.removeEventListener('keydown', onKeyDown);
  }, [isBreakMode]);

  // 検索を始めたら一覧を開き、消したら畳む
  useEffect(() => {
    if (nameQuery.trim() !== '') {
      setIsListOpen(true);
    }
  }, [nameQuery]);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [password, setPassword] = useState('');
  const [verifiedPassword, setVerifiedPassword] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [currentStatus, setCurrentStatus] = useState<CurrentStatus | null>(null);
  const [todayRecords, setTodayRecords] = useState<StampRecord[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  // 名前選択の画面に戻っても直近の打刻結果が分かるようにする
  const [lastStamp, setLastStamp] = useState<{ name: string; time: string; label: string } | null>(null);

  // メッセージを3秒後に消す
  useEffect(() => {
    if (message) {
      const timer = setTimeout(() => setMessage(null), 3000);
      return () => clearTimeout(timer);
    }
  }, [message]);

  // 直近の打刻は確認できる程度に残し、しばらくして消す
  useEffect(() => {
    if (lastStamp) {
      const timer = setTimeout(() => setLastStamp(null), 30000);
      return () => clearTimeout(timer);
    }
  }, [lastStamp]);

  // FeliCaカードの打刻結果（常駐アプリからサーバーへ直接届く）をトースト表示する
  const [felicaEvents, setFelicaEvents] = useState<FelicaEvent[]>([]);
  const felicaSinceIdRef = useRef<number | null>(null);

  // 数秒おきに新しいFeliCa打刻結果をポーリングする
  useEffect(() => {
    let cancelled = false;

    const poll = async () => {
      try {
        const params = felicaSinceIdRef.current === null
          ? {}
          : { since_id: felicaSinceIdRef.current };
        const response = await axios.get(`/stamp/${company.uuid}/felica-events`, { params });
        if (cancelled) return;

        felicaSinceIdRef.current = response.data.lastId;

        const newEvents: FelicaEvent[] = response.data.events;
        if (newEvents.length > 0) {
          setFelicaEvents((prev) => [...prev, ...newEvents]);
        }
      } catch {
        // ポーリング1回分の失敗は無視し、次の周期で再試行する
      }
    };

    poll();
    const interval = setInterval(poll, FELICA_EVENTS_POLL_INTERVAL_MS);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [company.uuid]);

  // トーストは種類ごとの時間で自動的に消す
  useEffect(() => {
    if (felicaEvents.length === 0) return;

    const timers = felicaEvents.map((event) =>
      setTimeout(
        () => setFelicaEvents((prev) => prev.filter((e) => e.id !== event.id)),
        event.status === 'success' ? FELICA_TOAST_DURATION_MS.success : FELICA_TOAST_DURATION_MS.warning
      )
    );

    return () => timers.forEach(clearTimeout);
  }, [felicaEvents]);

  const dismissFelicaEvent = (id: number) => {
    setFelicaEvents((prev) => prev.filter((e) => e.id !== id));
  };

  const handleVerifyPassword = async (e: FormEvent) => {
    e.preventDefault();
    if (!selectedUser || !password) return;

    setIsVerifying(true);
    setMessage(null);
    try {
      const response = await axios.post(`/stamp/${company.uuid}/verify-password`, {
        user_id: selectedUser.id,
        password,
      });
      if (response.data.success) {
        setIsAuthenticated(true);
        setVerifiedPassword(password);
        setCurrentStatus(response.data.currentStatus);
        setTodayRecords(response.data.todayRecords);

        // 休憩開始モードなら打刻種別を選ばせずそのまま記録する
        if (isBreakMode) {
          await stampWith('break-start', password);
          setIsBreakMode(false);
        }

        setPassword('');
      }
    } catch (error: unknown) {
      if (axios.isAxiosError(error) && error.response?.data?.message) {
        setMessage({ type: 'error', text: error.response.data.message });
      } else {
        setMessage({ type: 'error', text: 'パスワードの検証に失敗しました。' });
      }
    } finally {
      setIsVerifying(false);
    }
  };

  /**
   * 打刻を記録する
   *
   * 認証直後にそのまま打刻する場合、verifiedPassword の反映を待てないため
   * パスワードを引数で受け取れるようにしている。
   */
  const stampWith = async (
    action: 'clock-in' | 'clock-out' | 'break-start' | 'break-end',
    passwordToUse?: string
  ) => {
    if (!selectedUser) return;

    setIsLoading(true);
    try {
      const response = await axios.post(`/stamp/${company.uuid}/${action}`, {
        user_id: selectedUser.id,
        password: passwordToUse ?? verifiedPassword,
      });
      if (response.data.success) {
        setMessage({ type: 'success', text: response.data.message });
        setCurrentStatus(response.data.currentStatus);
        if (response.data.record) {
          setTodayRecords((prev) => [...prev, response.data.record]);
          setLastStamp({
            name: selectedUser.name,
            time: response.data.record.time,
            // 「出勤を記録しました。」のような打刻結果の文言をそのまま使う
            label: response.data.message,
          });
        }

        // 次の人がすぐ打刻できるよう、記録できたら名前選択へ戻す
        setTimeout(() => {
          setSelectedUser(null);
          setIsAuthenticated(false);
          setPassword('');
          setVerifiedPassword('');
          setCurrentStatus(null);
          setTodayRecords([]);
          setNameQuery('');
          setIsListOpen(false);
        }, 1500);
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

  const handleStamp = (action: 'clock-in' | 'clock-out' | 'break-start' | 'break-end') =>
    stampWith(action);

  const handleBack = () => {
    setSelectedUser(null);
    setIsAuthenticated(false);
    setPassword('');
    setVerifiedPassword('');
    setCurrentStatus(null);
    setTodayRecords([]);
    setMessage(null);
  };

  const handleBackToPassword = () => {
    setIsAuthenticated(false);
    setPassword('');
    setVerifiedPassword('');
    setCurrentStatus(null);
    setTodayRecords([]);
    setMessage(null);
  };

  const getStatusText = () => {
    if (!currentStatus) return '';
    if (currentStatus.hasClockOutToday) return '退勤済み';
    if (!currentStatus.isWorking) return '未出勤';
    if (currentStatus.isOnBreak) return '休憩中';
    return '勤務中';
  };

  const getStatusColor = () => {
    if (!currentStatus) return 'text-gray-500';
    if (!currentStatus.isWorking) return 'text-gray-500';
    if (currentStatus.isOnBreak) return 'text-yellow-600';
    return 'text-blue-600';
  };

  return (
    <>
      <Head title={`打刻 - ${company.name}`} />
      <div
        className="min-h-screen"
        style={{ backgroundColor: isBreakMode ? '#fef9c3' : '#f3f4f6' }}
      >
        {/* ヘッダー */}
        <header className="bg-white shadow">
          <div className="max-w-4xl mx-auto px-4 py-4">
            <h1 className="text-xl font-bold text-gray-900">{company.name}</h1>
          </div>
        </header>

        {/* FeliCaカード打刻の結果トースト（成功・重複防止など。常駐アプリからの打刻は
            この画面上の操作を経由しないため、ここでポーリング結果を通知する） */}
        {felicaEvents.length > 0 && (
          <div className="space-y-1">
            {felicaEvents.map((event) => (
              <div
                key={event.id}
                className={`flex items-start justify-between gap-3 px-4 py-3 border-2 ${
                  event.status === 'success'
                    ? 'bg-green-50 border-green-500 text-green-800'
                    : 'bg-yellow-100 border-yellow-600 text-yellow-900'
                }`}
              >
                <div className="flex items-start gap-2">
                  {event.status === 'success' ? (
                    <CheckCircle className="h-5 w-5 mt-0.5 flex-shrink-0" />
                  ) : (
                    <AlertCircle className="h-5 w-5 mt-0.5 flex-shrink-0" />
                  )}
                  <div>
                    <p className="font-semibold">
                      {event.userName ? `${event.userName}さん　${event.time}` : event.message}
                    </p>
                    <p className={event.userName ? 'text-sm' : 'text-sm font-normal'}>
                      {event.userName ? event.message : event.detail}
                    </p>
                    {event.userName && event.detail && (
                      <p className="text-sm">{event.detail}</p>
                    )}
                    {event.status !== 'success' && (
                      <p className="text-xs opacity-75 mt-1">（カード: {event.maskedIdm}）</p>
                    )}
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => dismissFelicaEvent(event.id)}
                  className="opacity-60 hover:opacity-100 flex-shrink-0"
                  aria-label="閉じる"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>
        )}

        <main className="max-w-4xl mx-auto px-4 py-6">
          {/* メッセージ表示 */}
          {message && (
            <div
              className={`mb-6 p-4 rounded-lg flex items-center gap-2 ${
                message.type === 'success'
                  ? 'bg-green-50 text-green-800'
                  : 'bg-yellow-200 text-yellow-900 border-2 border-yellow-600 font-semibold'
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

          {!selectedUser ? (
            // スタッフ選択画面
            <div className="bg-white shadow rounded-lg p-6">
              <div className="text-center mb-6">
                {/* 時計の右側に休憩開始モードの切り替えを置く */}
                <div className="flex items-center justify-center gap-6 flex-wrap">
                  <div>
                    <div className="text-6xl font-bold text-gray-900 font-mono mb-2">
                      {format(currentTime, 'HH:mm:ss')}
                    </div>
                    <div className="text-xl text-gray-600">
                      {format(currentTime, 'yyyy年MM月dd日 (E)', { locale: ja })}
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() => setIsBreakMode((on) => !on)}
                    className="inline-flex flex-col items-center justify-center gap-1 px-6 py-4 rounded-lg font-semibold text-white"
                    style={{ backgroundColor: isBreakMode ? '#eab308' : '#f59e0b' }}
                  >
                    <Coffee className="w-6 h-6" />
                    <span>休憩開始</span>
                    {isBreakMode && <span className="text-xs font-normal opacity-90">タップで解除</span>}
                  </button>
                </div>

                {isBreakMode && (
                  <p className="mt-2 text-sm text-yellow-800">
                    このまま名前とパスワードを入力すると休憩開始として記録されます。Escキーで解除できます。
                  </p>
                )}
              </div>

              {/* 打刻したことが画面に残るようにする */}
              {lastStamp && (
                <div className="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-center">
                  <p className="text-green-800 font-medium">
                    {lastStamp.name} さん　{lastStamp.time}　{lastStamp.label}
                  </p>
                </div>
              )}

              <h2 className="text-lg font-semibold text-gray-900 mb-4 text-center">
                名前を選択してください
              </h2>

              {/* 人数が多いと一覧が長くなるため、初期は畳んでおき検索で開く */}
              <div className="mb-4 flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                <input
                  type="text"
                  value={nameQuery}
                  onChange={(e) => setNameQuery(e.target.value)}
                  placeholder="名前・個人コードで検索"
                  className="flex-1 border border-gray-300 rounded-lg px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <button
                  type="button"
                  onClick={() => setIsListOpen((open) => !open)}
                  className="px-4 py-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-sm font-medium text-gray-700 whitespace-nowrap"
                >
                  {isListOpen ? '一覧を閉じる' : '一覧を開く'}
                </button>
              </div>

              {nameQuery.trim() !== '' && (
                <p className="text-sm text-gray-600 mb-3 text-center">
                  該当 {filteredUsers.length} 名
                </p>
              )}

              {users.length === 0 ? (
                <div className="text-center text-gray-500 py-8">
                  登録されているスタッフがいません。
                </div>
              ) : ! isListOpen ? (
                <div className="text-center text-gray-500 py-8 text-sm">
                  名前を検索するか、「一覧を開く」を押してください。
                </div>
              ) : filteredUsers.length === 0 ? (
                <div className="text-center text-gray-500 py-8 text-sm">
                  該当するスタッフがいません。
                </div>
              ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                  {filteredUsers.map((user) => (
                    <button
                      key={user.id}
                      onClick={() => setSelectedUser(user)}
                      className="flex flex-col items-center p-4 bg-gray-50 hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-lg transition-colors"
                    >
                      <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-2">
                        <User className="w-6 h-6 text-blue-600" />
                      </div>
                      <span className="text-sm font-medium text-gray-900 text-center">
                        {user.name}
                      </span>
                      {user.employee_code && (
                        <span className="text-xs text-gray-500 mt-1">{user.employee_code}</span>
                      )}
                    </button>
                  ))}
                </div>
              )}
            </div>
          ) : !isAuthenticated ? (
            // パスワード入力画面
            <div className="space-y-6">
              <button
                onClick={handleBack}
                className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
              >
                <ChevronLeft className="w-5 h-5" />
                <span>スタッフ選択に戻る</span>
              </button>

              <div className="bg-white shadow rounded-lg p-8">
                <div className="text-center space-y-6">
                  <div className="text-6xl font-bold text-gray-900 font-mono">
                    {format(currentTime, 'HH:mm:ss')}
                  </div>
                  <div className="text-xl text-gray-600">
                    {format(currentTime, 'yyyy年MM月dd日 (E)', { locale: ja })}
                  </div>

                  {/* 選択中のスタッフ */}
                  <div className="flex items-center justify-center gap-3 py-4 bg-blue-50 rounded-lg">
                    <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                      <User className="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                      <div className="text-lg font-semibold text-gray-900">{selectedUser.name}</div>
                      {selectedUser.employee_code && (
                        <div className="text-sm text-gray-500">{selectedUser.employee_code}</div>
                      )}
                    </div>
                  </div>

                  {/* パスワード入力フォーム */}
                  <form onSubmit={handleVerifyPassword} className="max-w-sm mx-auto space-y-4">
                    <div className="flex items-center justify-center gap-2 text-gray-700">
                      <Lock className="w-5 h-5" />
                      <span className="font-medium">パスワードを入力してください</span>
                    </div>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg p-4 text-lg text-center focus:border-blue-500 focus:ring-blue-500"
                      placeholder="パスワード"
                      autoComplete="off"
                      autoFocus
                      required
                    />
                    <button
                      type="submit"
                      disabled={isVerifying || !password}
                      className="w-full font-semibold py-4 px-4 rounded-lg text-lg transition-colors bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {isVerifying ? '確認中...' : '確認'}
                    </button>
                  </form>
                </div>
              </div>
            </div>
          ) : (
            // 打刻画面
            <div className="space-y-6">
              {/* 戻るボタン */}
              <button
                onClick={handleBack}
                className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
              >
                <ChevronLeft className="w-5 h-5" />
                <span>スタッフ選択に戻る</span>
              </button>

              <div className="bg-white shadow rounded-lg p-8">
                <div className="text-center space-y-6">
                  <div className="text-6xl font-bold text-gray-900 font-mono">
                    {format(currentTime, 'HH:mm:ss')}
                  </div>
                  <div className="text-xl text-gray-600">
                    {format(currentTime, 'yyyy年MM月dd日 (E)', { locale: ja })}
                  </div>

                  {/* 選択中のスタッフ */}
                  <div className="flex items-center justify-center gap-3 py-4 bg-blue-50 rounded-lg">
                    <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                      <User className="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                      <div className="text-lg font-semibold text-gray-900">{selectedUser.name}</div>
                      {selectedUser.employee_code && (
                        <div className="text-sm text-gray-500">{selectedUser.employee_code}</div>
                      )}
                    </div>
                  </div>

                  {/* 現在の状態 */}
                  <div className={`text-lg font-semibold ${getStatusColor()}`}>
                    現在の状態: {getStatusText()}
                    {currentStatus?.clockInTime && ` (出勤: ${currentStatus.clockInTime})`}
                  </div>

                  <div className="grid grid-cols-2 gap-4 max-w-md mx-auto mt-4">
                    <button
                      onClick={() => handleStamp('clock-in')}
                      disabled={isLoading || (currentStatus?.isWorking ?? false) || (currentStatus?.hasClockInToday ?? false)}
                      className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                        currentStatus?.isWorking || currentStatus?.hasClockInToday
                          ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                          : 'bg-blue-600 hover:bg-blue-700 text-white'
                      }`}
                    >
                      出勤
                    </button>
                    <button
                      onClick={() => handleStamp('clock-out')}
                      disabled={
                        isLoading ||
                        !(currentStatus?.isWorking ?? false) ||
                        (currentStatus?.isOnBreak ?? false) ||
                        (currentStatus?.hasClockOutToday ?? false)
                      }
                      className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                        !currentStatus?.isWorking || currentStatus?.isOnBreak || currentStatus?.hasClockOutToday
                          ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                          : 'bg-red-600 hover:bg-red-700 text-white'
                      }`}
                    >
                      退勤
                    </button>
                    <button
                      onClick={() => handleStamp('break-start')}
                      disabled={
                        isLoading ||
                        !(currentStatus?.isWorking ?? false) ||
                        (currentStatus?.isOnBreak ?? false) ||
                        (currentStatus?.breakCount ?? 0) >= 2
                      }
                      className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                        !currentStatus?.isWorking || currentStatus?.isOnBreak || (currentStatus?.breakCount ?? 0) >= 2
                          ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                          : 'bg-yellow-600 hover:bg-yellow-700 text-white'
                      }`}
                    >
                      休憩開始
                    </button>
                    <button
                      onClick={() => handleStamp('break-end')}
                      disabled={isLoading || !(currentStatus?.isOnBreak ?? false)}
                      className={`font-semibold py-6 px-4 rounded-lg text-lg transition-colors ${
                        !currentStatus?.isOnBreak
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
                      <div
                        key={record.id}
                        className="flex items-center justify-between py-2 border-b border-gray-100"
                      >
                        <span className="text-gray-700">{record.typeLabel}</span>
                        <span className="font-mono text-gray-900">{record.time}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </main>
      </div>
    </>
  );
}
