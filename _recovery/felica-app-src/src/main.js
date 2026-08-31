'use strict';

const { app, BrowserWindow, ipcMain, Tray, Menu, nativeImage } = require('electron');
const path = require('node:path');

const { startFelicaReader } = require('./felica');
const { sendFelicaStamp } = require('./stamp-api');
const { getConfig, setConfig } = require('./config');

const isDev = process.argv.includes('--dev');

/** @type {BrowserWindow | null} */
let mainWindow = null;
/** @type {Tray | null} */
let tray = null;
/**
 * 登録モード時は読み取った IDm を打刻 API へ送信せず、画面に表示するだけにする。
 * 管理者が IDm をコピーして勤怠Webの「FeliCaカード登録」画面に貼り付けて使う。
 */
let registrationMode = false;
/**
 * 「次の打刻を休憩開始にする」フラグ。
 * UI からボタン or ショートカット押下で true になり、次のカード読み取り後に false へ戻る。
 */
let breakStartArmed = false;

function createWindow() {
  // 自動起動（--hidden）時はトレイ常駐のみでウィンドウを隠して開く。
  // 通常起動時は従来通りウィンドウを表示する。
  const startHidden = process.argv.includes('--hidden')
    || app.getLoginItemSettings().wasOpenedAsHidden;

  mainWindow = new BrowserWindow({
    width: 520,
    height: 640,
    resizable: false,
    title: 'FeliCa打刻',
    show: !startHidden,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
  });

  mainWindow.loadFile(path.join(__dirname, 'renderer', 'index.html'));
  if (isDev) mainWindow.webContents.openDevTools({ mode: 'detach' });

  mainWindow.on('close', (event) => {
    if (!app.isQuiting) {
      event.preventDefault();
      mainWindow?.hide();
    }
  });
}

function createTray() {
  const icon = nativeImage.createEmpty();
  tray = new Tray(icon);
  const menu = Menu.buildFromTemplate([
    { label: '画面を表示', click: () => mainWindow?.show() },
    { type: 'separator' },
    {
      label: '終了',
      click: () => {
        app.isQuiting = true;
        app.quit();
      },
    },
  ]);
  tray.setToolTip('FeliCa打刻');
  tray.setContextMenu(menu);
  tray.on('click', () => mainWindow?.show());
}

function broadcastEvent(channel, payload) {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send(channel, payload);
  }
}

// 同一 IDm の連続検出を抑止するためのクライアント側デバウンス。
// nfc-pcsc ライブラリは一度のカードタッチで card イベントを複数回
// 発火することがあり、その重複リクエストがサーバの 30 秒スロットルに
// 弾かれて「30秒後にもう一度かざしてください」エラーとなる事象を防ぐ。
const lastReadByIdm = new Map();
// NFC リーダー（特に RC-S300）は 1 回の物理タップでも card イベントを
// 数秒間にわたり複数回発火することがある。2 秒では取りこぼすケースがあり、
// サーバ側のレート制限で 2 回目以降が拒否され、Web 打刻画面に
// 「重複打刻防止」トーストが毎回表示される事象を引き起こす。
// 5 秒に延長して大半の多重発火を吸収する。
const CARD_READ_DEBOUNCE_MS = 5000;

// 登録モード切替直後（onに切り替えた瞬間）に、別スレッドで先行して
// 検出されたカード読取りが「まだ registrationMode=false」と判定されて
// 打刻送信されてしまうレースコンディションを防ぐためのガード。
// 切替後 1.5 秒間は全カード読取りを一旦無視する。
let registrationModeChangedAt = 0;
const REGISTRATION_TRANSITION_GUARD_MS = 1500;

async function handleCardRead(idm) {
  const now = Date.now();
  const lastReadAt = lastReadByIdm.get(idm) || 0;
  if (now - lastReadAt < CARD_READ_DEBOUNCE_MS) {
    return; // 同一 IDm の連続検出を無視
  }

  // 登録モード切替直後のガード（モードON/OFF いずれの切替でも適用）
  // 「切替前に発火していた card イベント」がここまで届いて誤打刻するのを防ぐ。
  if (now - registrationModeChangedAt < REGISTRATION_TRANSITION_GUARD_MS) {
    broadcastEvent('felica:card-detected', { idm, detectedAt: new Date().toISOString(), suppressed: true });
    return;
  }

  lastReadByIdm.set(idm, now);

  broadcastEvent('felica:card-detected', { idm, detectedAt: new Date().toISOString() });

  // 登録モード: API へ送信せず IDm 表示のみ
  if (registrationMode) {
    broadcastEvent('registration:idm-captured', { idm });
    return;
  }

  const config = getConfig();
  if (!config.serverUrl || !config.companyUuid) {
    broadcastEvent('stamp:result', {
      ok: false,
      message: 'サーバURLと会社UUIDを設定してください。',
    });
    return;
  }

  // 設定値の簡易バリデーション（誤入力対策）
  const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  if (!uuidPattern.test(String(config.companyUuid).trim())) {
    broadcastEvent('stamp:result', {
      ok: false,
      message: '会社UUID の形式が不正です。設定画面で正しいUUID（例: d1b86286-64ca-4f37-a94c-e8a078972efa）を保存してください。',
    });
    return;
  }
  try {
    const u = new URL(String(config.serverUrl).trim());
    if (u.pathname && u.pathname !== '/' && u.pathname !== '') {
      broadcastEvent('stamp:result', {
        ok: false,
        message: 'サーバURL にパス（/admin/login 等）が含まれています。設定画面でドメイン部分のみを保存してください。例: https://clog-work.jp',
      });
      return;
    }
  } catch (_) {
    broadcastEvent('stamp:result', {
      ok: false,
      message: 'サーバURL の形式が不正です。例: https://clog-work.jp',
    });
    return;
  }

  // 休憩開始モードが ON ならこの回は intent='break-start' を送る
  const intent = breakStartArmed ? 'break-start' : null;
  const wasBreakArmed = breakStartArmed;

  try {
    const result = await sendFelicaStamp({
      serverUrl: config.serverUrl,
      companyUuid: config.companyUuid,
      idm,
      intent,
    });

    // 仕様変更（2026-06-24）— 複数ユーザーが連続して休憩入りできるよう、
    // BREAK_START 成功時に breakStartArmed を自動リセットしない。
    // タイムアウト（startBreakArmTimeout で設定された 60 秒）または手動で Esc 押下時に解除。

    // 404 など、サーバ側のルーティングで見つからなかった場合は分かりやすいメッセージに置換
    let displayMessage = result.body?.message ?? '打刻処理を完了しました。';
    if (result.status === 404 || (typeof displayMessage === 'string' && displayMessage.startsWith('The route '))) {
      displayMessage = 'サーバURL または 会社UUID が正しくありません。設定画面で見直してください。';
    }

    broadcastEvent('stamp:result', {
      ok: result.ok,
      status: result.status,
      message: displayMessage,
      data: result.body,
      sentAsBreakStart: wasBreakArmed,
    });
  } catch (err) {
    broadcastEvent('stamp:result', {
      ok: false,
      message: '通信エラーが発生しました: ' + (err && err.message ? err.message : String(err)),
    });
  }
}

/**
 * Windows ログイン時に本アプリを自動起動する設定を有効化する。
 * 既にユーザがオフにしている場合は尊重したいので、初回起動だけ自動 ON する
 * （config に `loginAutoStartInitialized` フラグを保存）。
 * 以降はユーザが設定画面（あれば）または OS のスタートアップアプリ管理から
 * 自由にオン／オフできる。
 * - openAtLogin: ログイン時に起動
 * - openAsHidden: 起動時はウィンドウを非表示（タスクトレイ常駐のため）
 * - args: '--hidden' を渡し、createWindow で起動直後は hide する
 */
function ensureAutoLaunchEnabled() {
  try {
    // dev 実行（electron .）では Squirrel/NSIS の Update.exe パスが存在せず、
    // setLoginItemSettings がレジストリに不正な値を書く可能性があるためスキップ。
    if (!app.isPackaged) return;

    const cfg = getConfig();
    if (cfg.loginAutoStartInitialized) return;

    app.setLoginItemSettings({
      openAtLogin: true,
      openAsHidden: true,
      args: ['--hidden'],
    });

    setConfig({ loginAutoStartInitialized: true });
  } catch (err) {
    // 失敗してもアプリ本体は起動させる
    console.warn('auto-launch setup failed:', err && err.message ? err.message : err);
  }
}

app.whenReady().then(() => {
  ensureAutoLaunchEnabled();
  createWindow();
  createTray();

  // IPC: 設定の読み書き
  ipcMain.handle('config:get', () => getConfig());
  ipcMain.handle('config:set', (_event, partial) => setConfig(partial));

  // IPC: 自動起動の取得・切替（タスクトレイメニューや設定画面から利用可能）
  ipcMain.handle('auto-launch:get', () => {
    if (!app.isPackaged) return false;
    return app.getLoginItemSettings().openAtLogin;
  });
  ipcMain.handle('auto-launch:set', (_event, enabled) => {
    if (!app.isPackaged) return false;
    app.setLoginItemSettings({
      openAtLogin: !!enabled,
      openAsHidden: true,
      args: ['--hidden'],
    });
    return app.getLoginItemSettings().openAtLogin;
  });

  // IPC: 手動でモックIDmを送るデバッグ用
  ipcMain.handle('felica:simulate', async (_event, idm) => {
    await handleCardRead(String(idm).toLowerCase());
    return true;
  });

  // IPC: 登録モード ON/OFF
  ipcMain.handle('registration:set-mode', (_event, enabled) => {
    const next = !!enabled;
    if (next !== registrationMode) {
      // モード切替時刻を記録し、ガード期間中は card 読取りを無視する
      registrationModeChangedAt = Date.now();
      // 同 IDm デバウンスもクリアして、ガード明け後の最初のタップを確実に拾う
      lastReadByIdm.clear();
    }
    registrationMode = next;
    return registrationMode;
  });
  ipcMain.handle('registration:get-mode', () => registrationMode);

  // 休憩開始モードの自動失効タイマー（60秒）
  // サーバ側 arm（"stamp:arm-break-start" cache）と同じ TTL に合わせる
  const BREAK_ARM_TIMEOUT_MS = 60 * 1000;
  let breakArmTimer = null;
  const startBreakArmTimeout = () => {
    if (breakArmTimer) clearTimeout(breakArmTimer);
    breakArmTimer = setTimeout(() => {
      if (breakStartArmed) {
        breakStartArmed = false;
        broadcastEvent('break-mode:changed', { armed: false });
      }
    }, BREAK_ARM_TIMEOUT_MS);
  };
  const cancelBreakArmTimeout = () => {
    if (breakArmTimer) {
      clearTimeout(breakArmTimer);
      breakArmTimer = null;
    }
  };

  // IPC: 休憩開始モード ON/OFF/Toggle
  ipcMain.handle('break-mode:set', (_event, enabled) => {
    breakStartArmed = !!enabled;
    if (breakStartArmed) startBreakArmTimeout();
    else cancelBreakArmTimeout();
    broadcastEvent('break-mode:changed', { armed: breakStartArmed });
    return breakStartArmed;
  });
  ipcMain.handle('break-mode:toggle', () => {
    breakStartArmed = !breakStartArmed;
    if (breakStartArmed) startBreakArmTimeout();
    else cancelBreakArmTimeout();
    broadcastEvent('break-mode:changed', { armed: breakStartArmed });
    return breakStartArmed;
  });
  ipcMain.handle('break-mode:get', () => breakStartArmed);

  // 実機のリーダーを起動
  startFelicaReader({
    onCard: (idm) => handleCardRead(idm),
    onReaderEvent: (evt) => broadcastEvent('reader:event', evt),
  });

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  // タスクトレイ常駐のため終了しない
});
