'use strict';

let store;
try {
  // electron-store v10 は ESM のみ。CJS から動的 import で読み込む。
  // 同期APIを使いたいため、初回 import を await する初期化関数を提供する。
} catch (_) {}

const defaults = {
  serverUrl: 'http://localhost:8000',
  companyUuid: '',
  deviceName: '',
};

let cache = { ...defaults };
let initialized = false;
let initPromise = null;

async function ensureStore() {
  if (initialized) return store;
  if (!initPromise) {
    initPromise = import('electron-store').then(({ default: Store }) => {
      store = new Store({ name: 'felica-stamp-config', defaults });
      cache = { ...defaults, ...store.store };
      initialized = true;
      return store;
    });
  }
  return initPromise;
}

// 起動と同時に読み込みを開始
ensureStore().catch((e) => {
  // eslint-disable-next-line no-console
  console.error('Failed to load config store:', e);
});

function getConfig() {
  return { ...cache };
}

async function setConfig(partial) {
  await ensureStore();
  cache = { ...cache, ...partial };
  for (const [k, v] of Object.entries(partial)) {
    store.set(k, v);
  }
  return getConfig();
}

module.exports = { getConfig, setConfig };
