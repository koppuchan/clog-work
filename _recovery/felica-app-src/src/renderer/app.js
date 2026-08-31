'use strict';

const $ = (id) => document.getElementById(id);

const els = {
  clock: $('clock'),
  serverUrl: $('serverUrl'),
  companyUuid: $('companyUuid'),
  deviceName: $('deviceName'),
  saveConfig: $('saveConfig'),
  configMessage: $('configMessage'),
  readerStatus: $('readerStatus'),
  latestStamp: $('latestStamp'),
  latestTitle: $('latestTitle'),
  mockIdm: $('mockIdm'),
  simulate: $('simulate'),
  registrationMode: $('registrationMode'),
  copyIdm: $('copyIdm'),
  breakStartBtn: $('breakStartBtn'),
};

let lastCapturedIdm = null;

function pad(n) { return String(n).padStart(2, '0'); }
function tickClock() {
  const d = new Date();
  els.clock.textContent = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}
setInterval(tickClock, 1000);
tickClock();

(async function init() {
  const cfg = await window.felicaApi.getConfig();
  els.serverUrl.value = cfg.serverUrl ?? '';
  els.companyUuid.value = cfg.companyUuid ?? '';
  els.deviceName.value = cfg.deviceName ?? '';
})();

/**
 * サーバURL の正規化と検証
 * - スキーム必須 (https:// または http://)
 * - パス・クエリ・ハッシュは除去（誤って /admin/login 等を貼り付けたケース救済）
 */
function normalizeServerUrl(raw) {
  const v = String(raw || '').trim();
  if (!v) {
    return { ok: false, message: 'サーバURL を入力してください。例: https://clog-work.jp' };
  }
  let u;
  try {
    u = new URL(v);
  } catch (_) {
    return { ok: false, message: 'サーバURL の形式が正しくありません。例: https://clog-work.jp' };
  }
  if (u.protocol !== 'https:' && u.protocol !== 'http:') {
    return { ok: false, message: 'サーバURL は https:// または http:// で始める必要があります。' };
  }
  // origin（scheme + host + port）のみ採用
  const normalized = u.origin;
  return { ok: true, value: normalized };
}

/**
 * 会社UUID の検証 (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
 */
function validateCompanyUuid(raw) {
  const v = String(raw || '').trim().toLowerCase();
  if (!v) {
    return { ok: false, message: '会社UUID を入力してください。' };
  }
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(v)) {
    return {
      ok: false,
      message: '会社UUID の形式が正しくありません。例: d1b86286-64ca-4f37-a94c-e8a078972efa',
    };
  }
  return { ok: true, value: v };
}

els.saveConfig.addEventListener('click', async () => {
  const urlResult = normalizeServerUrl(els.serverUrl.value);
  if (!urlResult.ok) {
    els.configMessage.textContent = '❌ ' + urlResult.message;
    els.configMessage.style.color = '#b91c1c';
    return;
  }

  const uuidResult = validateCompanyUuid(els.companyUuid.value);
  if (!uuidResult.ok) {
    els.configMessage.textContent = '❌ ' + uuidResult.message;
    els.configMessage.style.color = '#b91c1c';
    return;
  }

  // 正規化された値を画面にも反映（パスを削った旨が分かるように）
  els.serverUrl.value = urlResult.value;
  els.companyUuid.value = uuidResult.value;

  await window.felicaApi.setConfig({
    serverUrl: urlResult.value,
    companyUuid: uuidResult.value,
    deviceName: els.deviceName.value.trim(),
  });
  els.configMessage.style.color = '#16a34a';
  els.configMessage.textContent = '✅ 保存しました。';
  setTimeout(() => {
    els.configMessage.textContent = '';
    els.configMessage.style.color = '';
  }, 2500);
});

els.simulate.addEventListener('click', () => {
  const idm = els.mockIdm.value.trim().toLowerCase();
  if (!/^[0-9a-f]{16}$/.test(idm)) {
    els.latestStamp.className = 'latest-card error';
    els.latestStamp.textContent = 'IDm は 16進数16桁で入力してください。';
    return;
  }
  window.felicaApi.simulateCard(idm);
});

window.felicaApi.onReaderEvent((evt) => {
  switch (evt.type) {
    case 'reader-attached':
      els.readerStatus.textContent = `接続: ${evt.readerName}`;
      break;
    case 'reader-detached':
      els.readerStatus.textContent = `切断: ${evt.readerName}`;
      break;
    case 'init-error':
      els.readerStatus.textContent = evt.message;
      break;
    case 'nfc-error':
    case 'reader-error':
      els.readerStatus.textContent = `エラー: ${evt.message}`;
      break;
    case 'unexpected-uid':
      els.readerStatus.textContent = `非対応のUIDを検出: ${evt.uid}`;
      break;
    default:
      break;
  }
});

window.felicaApi.onCardDetected((evt) => {
  els.latestStamp.className = 'latest-card';
  els.latestStamp.textContent = `カード検出: ${evt.idm} … 送信中`;
});

window.felicaApi.onStampResult((result) => {
  els.latestStamp.className = 'latest-card ' + (result.ok ? 'success' : 'error');
  const lines = [result.message ?? ''];
  if (result.data?.user?.name) {
    const time = result.data?.record?.time ?? '';
    lines.unshift(`${result.data.user.name} ${time}`);
  }
  els.latestStamp.textContent = lines.filter(Boolean).join('\n');
  els.copyIdm.style.display = 'none';
});

// --- 登録モード ---
els.registrationMode.addEventListener('change', async (e) => {
  const enabled = e.target.checked;
  await window.felicaApi.setRegistrationMode(enabled);
  if (enabled) {
    document.body.classList.add('registration-on');
    els.latestTitle.textContent = '登録用 IDm';
    els.latestStamp.className = 'latest-card';
    els.latestStamp.textContent = 'カードをかざすと IDm がここに表示されます。';
  } else {
    document.body.classList.remove('registration-on');
    els.latestTitle.textContent = '直近の打刻';
    els.latestStamp.className = 'latest-card';
    els.latestStamp.textContent = 'カードをかざしてください。';
    els.copyIdm.style.display = 'none';
  }
});

window.felicaApi.onRegistrationCapture((payload) => {
  lastCapturedIdm = payload.idm;
  els.latestStamp.className = 'latest-card registration';
  els.latestStamp.textContent = payload.idm;
  els.copyIdm.style.display = 'block';
});

els.copyIdm.addEventListener('click', async () => {
  if (!lastCapturedIdm) return;
  try {
    await navigator.clipboard.writeText(lastCapturedIdm);
    els.copyIdm.textContent = 'コピーしました！';
    setTimeout(() => { els.copyIdm.textContent = 'IDm をコピー'; }, 2000);
  } catch (_) {
    els.copyIdm.textContent = 'コピー失敗';
  }
});

// 初期状態をメインプロセスから取得
(async function initRegMode() {
  const enabled = await window.felicaApi.getRegistrationMode();
  els.registrationMode.checked = !!enabled;
  if (enabled) document.body.classList.add('registration-on');
})();

// --- 休憩開始モード ---
function applyBreakArmed(armed) {
  if (armed) {
    document.body.classList.add('break-armed');
    els.breakStartBtn.classList.add('armed');
    els.breakStartBtn.textContent = '休憩開始モード ON — カードをかざしてください（Esc でキャンセル）';
    els.latestStamp.textContent = '休憩開始モード ON — 次の打刻は「休憩入り」になります。';
    els.latestStamp.className = 'latest-card';
  } else {
    document.body.classList.remove('break-armed');
    els.breakStartBtn.classList.remove('armed');
    els.breakStartBtn.textContent = '休憩開始（次の打刻を休憩入りにする）';
  }
}

els.breakStartBtn.addEventListener('click', async () => {
  const armed = await window.felicaApi.toggleBreakMode();
  applyBreakArmed(armed);
});

window.felicaApi.onBreakModeChanged((evt) => {
  applyBreakArmed(!!evt.armed);
});

// キーボードショートカット: B で休憩開始モード ON、Esc でキャンセル
document.addEventListener('keydown', async (e) => {
  // 入力欄にフォーカスがあるときは無視
  const tag = (e.target && e.target.tagName) || '';
  if (tag === 'INPUT' || tag === 'TEXTAREA') return;

  if (e.key === 'b' || e.key === 'B') {
    const armed = await window.felicaApi.toggleBreakMode();
    applyBreakArmed(armed);
  } else if (e.key === 'Escape') {
    await window.felicaApi.setBreakMode(false);
    applyBreakArmed(false);
  }
});

// 初期状態
(async function initBreakMode() {
  const armed = await window.felicaApi.getBreakMode();
  applyBreakArmed(!!armed);
})();
