import { useCallback, useMemo, useState } from 'react';
import {
  loadStampDisplaySettings,
  saveStampDisplaySettings,
  type StampDisplaySettings,
  type StampTextSize,
} from '@/utils/stampDisplay';

export interface StampDepartment {
  id: number;
  name: string;
}

/**
 * 打刻専用画面の、表示する事業所と文字の大きさ
 */
export function useStampDisplay(departments: StampDepartment[]) {
  const [stored, setStored] = useState<StampDisplaySettings>(loadStampDisplaySettings);

  // 保存してある事業所が、いまの会社に無い（部署が削除された等）場合は、全事業所として扱う
  const settings = useMemo<StampDisplaySettings>(
    () => ({
      ...stored,
      departmentId: departments.some((d) => d.id === stored.departmentId) ? stored.departmentId : null,
    }),
    [stored, departments]
  );

  const update = useCallback((next: StampDisplaySettings) => {
    setStored(next);
    saveStampDisplaySettings(next);
  }, []);

  const setDepartmentId = useCallback((departmentId: number | null) => update({ ...settings, departmentId }), [settings, update]);
  const setTextSize = useCallback((textSize: StampTextSize) => update({ ...settings, textSize }), [settings, update]);

  return { settings, setDepartmentId, setTextSize };
}
