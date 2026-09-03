import { ShiftType, ShiftTypeInfo, User, ShiftPattern } from '@/types';
import { BackendUser, BackendDepartment, BackendShift } from '@/types/shift';
import { Shift } from '@/types';

/**
 * バックエンドのスタッフデータをフロントエンド形式に変換
 */
export function convertBackendUsers(
  backendUsers: BackendUser[],
  backendDepartments: BackendDepartment[]
): User[] {
  return backendUsers.map(user => {
    const department = backendDepartments.find(d => d.id === user.department_id);
    return {
      id: user.id.toString(),
      name: user.name,
      employeeCode: user.employee_code,
      email: '',
      role: 'employee' as const,
      department: department?.name || '',
      departmentId: user.department_id ?? undefined,
    };
  });
}

/**
 * バックエンドのシフトデータをフロントエンド形式に変換
 * shiftPatternIdMapを使用してシフトパターンIDからShiftTypeを決定
 */
export function convertBackendShifts(backendShifts: BackendShift[], shiftPatternIdMap: Map<number, ShiftType>): Shift[] {
  return backendShifts.map(shift => {
    const shiftType: ShiftType = shift.shift_pattern_id
      ? shiftPatternIdMap.get(shift.shift_pattern_id) ?? 'rest'
      : 'rest';

    return {
      id: shift.id.toString(),
      userId: shift.user_id.toString(),
      date: shift.shift_date,
      shiftType,
      shiftPatternId: shift.shift_pattern_id,
    };
  });
}

/**
 * バックエンドのshiftPatternsからShiftTypeInfo配列を生成
 */
export function convertShiftPatterns(shiftPatterns: ShiftPattern[]): ShiftTypeInfo[] {
  return shiftPatterns.map((pattern) => ({
    id: pattern.id,
    type: `shift-${pattern.id}`,
    name: pattern.name,
    timeRange: `${pattern.start_time.slice(0, 5)}-${pattern.end_time.slice(0, 5)}`,
    color: pattern.background_color,
    textColor: pattern.text_color,
  }));
}

/**
 * シフト配列に1件を反映する（同一ユーザー・同一日付の既存分はすべて置き換える）
 *
 * 呼び出し側ごとに findIndex で1件だけ探して置換/追加する実装が重複しており、
 * 同一ユーザー・同一日付のシフトが複数件残っていると1件しか置き換わらず、
 * 集計（人数・日数）が古いシフトを数え続けてしまう。常にこの関数を通すことで
 * 「ユーザー×日付」につき1件になることを保証する。
 */
export function upsertShift(shifts: Shift[], newShift: Shift): Shift[] {
  const withoutTarget = shifts.filter(
    (shift) => !(shift.userId === newShift.userId && shift.date === newShift.date)
  );
  return [...withoutTarget, newShift];
}

/**
 * シフト配列から指定ユーザー・日付のシフトを取り除く（重複していてもすべて取り除く）
 */
export function removeShift(shifts: Shift[], userId: string, date: string): Shift[] {
  return shifts.filter((shift) => !(shift.userId === userId && shift.date === date));
}

/**
 * 休みのShiftTypeInfo定義
 */
export const restShiftInfo: ShiftTypeInfo = {
  id: 0,
  type: 'rest',
  name: '休み',
  timeRange: '',
  color: 'bg-gray-200',
  textColor: 'text-gray-600',
};

/**
 * ShiftTypeに対応するShiftTypeInfoを取得
 */
export function getShiftTypeInfo(
  shiftType: ShiftType,
  shiftTypeInfoList: ShiftTypeInfo[]
): ShiftTypeInfo {
  if (shiftType === 'rest') {
    return restShiftInfo;
  }
  return shiftTypeInfoList.find(info => info.type === shiftType) || shiftTypeInfoList[0] || restShiftInfo;
}
