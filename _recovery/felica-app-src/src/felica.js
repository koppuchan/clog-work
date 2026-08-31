'use strict';

/**
 * Sony RC-S300 など PC/SC 互換 FeliCa リーダーから IDm を取得する。
 *
 * nfc-pcsc を使用し、カード接触時に APDU (FFCA000000) で IDm を読み取る。
 * RC-S300 は内部的に USB CCID として動作し、Windows の Smart Card Service 経由で
 * アクセスできるため、特別なドライバ追加は不要なケースが多い。
 *
 * 開発・検証時にリーダーが手元にない環境では、nfc-pcsc の require が失敗するため
 * try/catch で握りつぶし、UI 側からはモックIDm送信機能で代替する。
 */
function startFelicaReader({ onCard, onReaderEvent }) {
  let NFC;
  try {
    NFC = require('nfc-pcsc').NFC;
  } catch (err) {
    onReaderEvent?.({
      type: 'init-error',
      message:
        'nfc-pcsc を読み込めませんでした。リーダー検出機能は無効です。\n' +
        '原因: ' + (err && err.message ? err.message : String(err)),
    });
    return { stop: () => {} };
  }

  const nfc = new NFC();
  const readers = new Map();

  nfc.on('reader', (reader) => {
    readers.set(reader.name, reader);
    onReaderEvent?.({ type: 'reader-attached', readerName: reader.name });

    reader.on('card', (card) => {
      // RC-S300 の場合、card.uid に IDm が ASCII hex で入る
      const idm = String(card.uid || '').toLowerCase();
      if (/^[0-9a-f]{16}$/.test(idm)) {
        onCard?.(idm);
      } else {
        onReaderEvent?.({
          type: 'unexpected-uid',
          readerName: reader.name,
          uid: idm,
        });
      }
    });

    reader.on('error', (err) => {
      onReaderEvent?.({
        type: 'reader-error',
        readerName: reader.name,
        message: err && err.message ? err.message : String(err),
      });
    });

    reader.on('end', () => {
      readers.delete(reader.name);
      onReaderEvent?.({ type: 'reader-detached', readerName: reader.name });
    });
  });

  nfc.on('error', (err) => {
    onReaderEvent?.({
      type: 'nfc-error',
      message: err && err.message ? err.message : String(err),
    });
  });

  return {
    stop: () => {
      for (const reader of readers.values()) {
        try {
          reader.close();
        } catch (_) {}
      }
      readers.clear();
    },
  };
}

module.exports = { startFelicaReader };
