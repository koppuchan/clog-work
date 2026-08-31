'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('felicaApi', {
  getConfig: () => ipcRenderer.invoke('config:get'),
  setConfig: (partial) => ipcRenderer.invoke('config:set', partial),
  simulateCard: (idm) => ipcRenderer.invoke('felica:simulate', idm),

  // 登録モード（カードをかざすと IDm を画面に表示するだけのモード）
  setRegistrationMode: (enabled) => ipcRenderer.invoke('registration:set-mode', enabled),
  getRegistrationMode: () => ipcRenderer.invoke('registration:get-mode'),

  // 休憩開始モード（次の1回のカードタップを休憩開始として送信する）
  setBreakMode: (enabled) => ipcRenderer.invoke('break-mode:set', enabled),
  toggleBreakMode: () => ipcRenderer.invoke('break-mode:toggle'),
  getBreakMode: () => ipcRenderer.invoke('break-mode:get'),
  onBreakModeChanged: (cb) => {
    const listener = (_e, payload) => cb(payload);
    ipcRenderer.on('break-mode:changed', listener);
    return () => ipcRenderer.off('break-mode:changed', listener);
  },

  onReaderEvent: (cb) => {
    const listener = (_e, payload) => cb(payload);
    ipcRenderer.on('reader:event', listener);
    return () => ipcRenderer.off('reader:event', listener);
  },
  onCardDetected: (cb) => {
    const listener = (_e, payload) => cb(payload);
    ipcRenderer.on('felica:card-detected', listener);
    return () => ipcRenderer.off('felica:card-detected', listener);
  },
  onStampResult: (cb) => {
    const listener = (_e, payload) => cb(payload);
    ipcRenderer.on('stamp:result', listener);
    return () => ipcRenderer.off('stamp:result', listener);
  },
  onRegistrationCapture: (cb) => {
    const listener = (_e, payload) => cb(payload);
    ipcRenderer.on('registration:idm-captured', listener);
    return () => ipcRenderer.off('registration:idm-captured', listener);
  },
});
