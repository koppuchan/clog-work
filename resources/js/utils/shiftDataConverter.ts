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
