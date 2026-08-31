'use strict';

/**
 * 勤怠Web の FeliCa 打刻エンドポイントを呼び出す。
 *
 * エンドポイント: POST {serverUrl}/stamp/{companyUuid}/felica
 * Body         : { idm: "0123456789abcdef" }
 */
async function sendFelicaStamp({ serverUrl, companyUuid, idm, intent }) {
  const base = String(serverUrl).replace(/\/+$/, '');
  const url = `${base}/stamp/${encodeURIComponent(companyUuid)}/felica`;

  const requestBody = { idm };
  if (intent) {
    requestBody.intent = intent;
  }

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(requestBody),
  });

  let responseBody = null;
  try {
    responseBody = await res.json();
  } catch (_) {
    responseBody = { message: 'サーバから不正なレスポンスを受信しました。' };
  }

  return {
    ok: res.ok && responseBody && responseBody.success === true,
    status: res.status,
    body: responseBody,
  };
}

module.exports = { sendFelicaStamp };
