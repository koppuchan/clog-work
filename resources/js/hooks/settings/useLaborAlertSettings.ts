import { useState, useCallback, useRef, useMemo } from 'react';
import { LaborAlertSettings, AlertMessagesForm } from '@/types/settings';
import { laborAlertToFormMessages } from '@/utils/settingsHelpers';

export function useLaborAlertSettings(initialSettings?: LaborAlertSettings) {
  // 初期値を共通変数で定義し、useStateとuseRefの不一致を防止
  const overtimeNotificationInit = String(initialSettings?.overtimeNotification ?? 45);
  const overtimeLimitInit = String(initialSettings?.overtimeLimit ?? 80);
  const alertMessagesInit = laborAlertToFormMessages(initialSettings);

  const [overtimeNotification, setOvertimeNotification] = useState(overtimeNotificationInit);
  const [overtimeLimit, setOvertimeLimit] = useState(overtimeLimitInit);
  const [alertMessages, setAlertMessages] = useState<AlertMessagesForm>(alertMessagesInit);

  const handleMessageChange = useCallback((field: keyof AlertMessagesForm, value: string) => {
    setAlertMessages((prev) => ({ ...prev, [field]: value }));
  }, []);

  const initialValues = useRef({
    overtimeNotification: overtimeNotificationInit,
    overtimeLimit: overtimeLimitInit,
    alertMessages: alertMessagesInit,
  });

  const [dirtyResetVersion, setDirtyResetVersion] = useState(0);

  const isDirty = useMemo(() => {
    const init = initialValues.current;
    return (
      overtimeNotification !== init.overtimeNotification ||
      overtimeLimit !== init.overtimeLimit ||
      JSON.stringify(alertMessages) !== JSON.stringify(init.alertMessages)
    );
  }, [overtimeNotification, overtimeLimit, alertMessages, dirtyResetVersion]);

  return {
    overtimeNotification,
    setOvertimeNotification,
    overtimeLimit,
    setOvertimeLimit,
    alertMessages,
    setAlertMessages,
    handleMessageChange,
    isDirty,
    resetDirty: useCallback(() => {
      initialValues.current = {
        overtimeNotification,
        overtimeLimit,
        alertMessages,
      };
      setDirtyResetVersion(v => v + 1);
    }, [overtimeNotification, overtimeLimit, alertMessages]),
  };
}
