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

  // 保存してある事業所のうち、いまの会社に無いもの（部署が削除された等）は除く。1つも残らなければ全事業所として扱う
  const settings = useMemo<StampDisplaySettings>(
    () => ({
      ...stored,
      departmentIds: stored.departmentIds.filter((id) => departments.some((d) => d.id === id)),
    }),
    [stored, departments]
  );

  const update = useCallback((next: StampDisplaySettings) => {
    setStored(next);
    saveStampDisplaySettings(next);
  }, []);

  const setDepartmentIds = useCallback((departmentIds: number[]) => update({ ...settings, departmentIds }), [settings, update]);
  const setTextSize = useCallback((textSize: StampTextSize) => update({ ...settings, textSize }), [settings, update]);

  return { settings, setDepartmentIds, setTextSize };
}
