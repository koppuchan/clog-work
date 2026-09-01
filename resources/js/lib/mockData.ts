import { User, AttendanceRecord, Shift, Application, Department, ShiftTypeInfo } from '@/types';

export const mockUsers: User[] = [
  {
    id: '1',
    name: '田中太郎',
    nameKana: 'タナカタロウ',
    employeeCode: '000001',
    email: 'tanaka@company.com',
    role: 'admin',
    department: '管理部',
    shiftPatterns: {
      sunday: undefined,      // 定休日
      monday: 'day',
      tuesday: 'day',
      wednesday: 'day',
      thursday: 'day',
      friday: 'day',
      saturday: undefined,     // 追加休み
    }
  },
  {
    id: '2',
    name: '佐藤花子',
    nameKana: 'サトウハナコ',
    employeeCode: '000002',
    email: 'sato@company.com',
    role: 'manager',
    department: '営業部',
    shiftPatterns: {
      sunday: undefined,       // 定休日
      monday: 'day',
      tuesday: undefined,      // 追加休み
      wednesday: 'late',
      thursday: 'day',
      friday: 'late',
      saturday: 'day',
    }
  },
  {
    id: '3',
    name: '山田次郎',
    nameKana: 'ヤマダジロウ',
    employeeCode: '000003',
    email: 'yamada@company.com',
    role: 'employee',
    department: '営業部',
    shiftPatterns: {
      sunday: undefined,       // 定休日
      monday: 'early',
      tuesday: 'early',
      wednesday: undefined,    // 追加休み
      thursday: 'early',
      friday: 'early',
      saturday: 'early',
    }
  },
  {
    id: '4',
    name: '鈴木一郎',
    nameKana: 'スズキイチロウ',
    employeeCode: '000004',
    email: 'suzuki@company.com',
    role: 'employee',
    department: '開発部',
    shiftPatterns: {
      sunday: undefined,       // 定休日
      monday: undefined,       // 追加休み
      tuesday: 'night',
      wednesday: 'night',
      thursday: 'night',
      friday: 'night',
      saturday: 'night',
    }
  },
  {
    id: '5',
    name: '高橋美咲',
    nameKana: 'タカハシミサキ',
    employeeCode: '000005',
    email: 'takahashi@company.com',
    role: 'employee',
    department: '営業部',
    shiftPatterns: {
      sunday: undefined,
      monday: 'day',
      tuesday: 'day',
      wednesday: 'day',
      thursday: 'day',
      friday: 'day',
      saturday: undefined,
    },
    isRetired: true,
    retirementDate: '2024-12-31'
  }
];

export const mockDepartments: Department[] = [
  { id: '1', name: '管理部', managerId: '1' },
  { id: '2', name: '営業部', managerId: '2' },
  { id: '3', name: '開発部', managerId: '1' }
];

// 勤務実績（固定データ）
export const mockAttendanceRecords: AttendanceRecord[] = [
  // 田中太郎（userId: '1'）- 9月分（限度時間80時間超過で警告アラート）
  { id: '1', userId: '1', date: '2025-09-01', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '2', userId: '1', date: '2025-09-02', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '3', userId: '1', date: '2025-09-03', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '4', userId: '1', date: '2025-09-04', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '5', userId: '1', date: '2025-09-05', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '6', userId: '1', date: '2025-09-08', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '7', userId: '1', date: '2025-09-09', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '8', userId: '1', date: '2025-09-10', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '9', userId: '1', date: '2025-09-11', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '10', userId: '1', date: '2025-09-12', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '11', userId: '1', date: '2025-09-15', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '12', userId: '1', date: '2025-09-16', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '13', userId: '1', date: '2025-09-17', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '14', userId: '1', date: '2025-09-18', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '15', userId: '1', date: '2025-09-19', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '16', userId: '1', date: '2025-09-22', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '16b', userId: '1', date: '2025-09-23', status: 'paid-leave', totalHours: 0 },
  { id: '17', userId: '1', date: '2025-09-24', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '18', userId: '1', date: '2025-09-25', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '19', userId: '1', date: '2025-09-26', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '20', userId: '1', date: '2025-09-29', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '21', userId: '1', date: '2025-09-30', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },

  // 佐藤花子（userId: '2'）- 9月分（限度時間80時間超過で警告アラート）
  { id: '103', userId: '2', date: '2025-09-03', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '104', userId: '2', date: '2025-09-04', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '105', userId: '2', date: '2025-09-05', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '106', userId: '2', date: '2025-09-06', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '107', userId: '2', date: '2025-09-07', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '108', userId: '2', date: '2025-09-10', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '109', userId: '2', date: '2025-09-11', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '110', userId: '2', date: '2025-09-12', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '111', userId: '2', date: '2025-09-13', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '112', userId: '2', date: '2025-09-14', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '113', userId: '2', date: '2025-09-17', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '114', userId: '2', date: '2025-09-18', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '115', userId: '2', date: '2025-09-19', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '116', userId: '2', date: '2025-09-20', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '117', userId: '2', date: '2025-09-21', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '118', userId: '2', date: '2025-09-24', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '119', userId: '2', date: '2025-09-25', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '120', userId: '2', date: '2025-09-26', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '121', userId: '2', date: '2025-09-27', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '122', userId: '2', date: '2025-09-28', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },

  // 山田次郎（userId: '3'）- 9月分（限度時間80時間超過で警告アラート）
  { id: '201', userId: '3', date: '2025-09-01', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '202', userId: '3', date: '2025-09-03', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '203', userId: '3', date: '2025-09-04', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '204', userId: '3', date: '2025-09-05', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '205', userId: '3', date: '2025-09-06', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '206', userId: '3', date: '2025-09-08', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '207', userId: '3', date: '2025-09-10', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '208', userId: '3', date: '2025-09-11', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '209', userId: '3', date: '2025-09-12', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '210', userId: '3', date: '2025-09-13', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '211', userId: '3', date: '2025-09-15', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '212', userId: '3', date: '2025-09-17', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '213', userId: '3', date: '2025-09-18', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '214', userId: '3', date: '2025-09-19', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '215', userId: '3', date: '2025-09-20', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '216', userId: '3', date: '2025-09-22', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '217', userId: '3', date: '2025-09-24', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '218', userId: '3', date: '2025-09-25', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '219', userId: '3', date: '2025-09-26', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '220', userId: '3', date: '2025-09-27', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '221', userId: '3', date: '2025-09-29', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },

  // 鈴木一郎（userId: '4'）- 9月分（限度時間80時間超過で警告アラート）
  { id: '301', userId: '4', date: '2025-09-02', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '302', userId: '4', date: '2025-09-04', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '303', userId: '4', date: '2025-09-05', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '304', userId: '4', date: '2025-09-06', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '305', userId: '4', date: '2025-09-07', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '306', userId: '4', date: '2025-09-09', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '307', userId: '4', date: '2025-09-11', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '308', userId: '4', date: '2025-09-12', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '309', userId: '4', date: '2025-09-13', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '310', userId: '4', date: '2025-09-14', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '311', userId: '4', date: '2025-09-16', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '312', userId: '4', date: '2025-09-18', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '313', userId: '4', date: '2025-09-19', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '314', userId: '4', date: '2025-09-20', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '315', userId: '4', date: '2025-09-21', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '316', userId: '4', date: '2025-09-23', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '317', userId: '4', date: '2025-09-25', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '318', userId: '4', date: '2025-09-26', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '319', userId: '4', date: '2025-09-27', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '320', userId: '4', date: '2025-09-28', clockIn: '09:00', clockOut: '23:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 13 },
  { id: '321', userId: '4', date: '2025-09-30', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },

  // 田中太郎 - 10月分（限度時間80時間超過で警告アラート）
  { id: '401', userId: '1', date: '2025-10-01', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '402', userId: '1', date: '2025-10-02', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '403', userId: '1', date: '2025-10-03', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '404', userId: '1', date: '2025-10-04', status: 'paid-leave', totalHours: 0 },
  { id: '404b', userId: '1', date: '2025-10-05', status: 'paid-leave', totalHours: 0 },
  { id: '405', userId: '1', date: '2025-10-06', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '406', userId: '1', date: '2025-10-07', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '407', userId: '1', date: '2025-10-08', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '408', userId: '1', date: '2025-10-09', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '409', userId: '1', date: '2025-10-10', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '410', userId: '1', date: '2025-10-14', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '411', userId: '1', date: '2025-10-15', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '412', userId: '1', date: '2025-10-16', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '413', userId: '1', date: '2025-10-17', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '414', userId: '1', date: '2025-10-21', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '415', userId: '1', date: '2025-10-22', clockIn: '09:00', clockOut: '20:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10 },
  { id: '416', userId: '1', date: '2025-10-23', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '417', userId: '1', date: '2025-10-24', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '418', userId: '1', date: '2025-10-28', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '419', userId: '1', date: '2025-10-29', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '420', userId: '1', date: '2025-10-30', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '421', userId: '1', date: '2025-10-31', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },

  // 佐藤花子 - 10月分（限度時間80時間超過で警告アラート）
  { id: '501', userId: '2', date: '2025-10-01', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '502', userId: '2', date: '2025-10-02', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '503', userId: '2', date: '2025-10-03', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '504', userId: '2', date: '2025-10-04', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '505', userId: '2', date: '2025-10-06', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '506', userId: '2', date: '2025-10-08', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '507', userId: '2', date: '2025-10-09', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '508', userId: '2', date: '2025-10-10', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '509', userId: '2', date: '2025-10-11', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '510', userId: '2', date: '2025-10-14', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '511', userId: '2', date: '2025-10-15', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '512', userId: '2', date: '2025-10-16', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '513', userId: '2', date: '2025-10-17', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '514', userId: '2', date: '2025-10-18', clockIn: '09:00', clockOut: '20:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10 },
  { id: '515', userId: '2', date: '2025-10-20', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '516', userId: '2', date: '2025-10-22', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '517', userId: '2', date: '2025-10-23', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '518', userId: '2', date: '2025-10-24', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '519', userId: '2', date: '2025-10-25', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '520', userId: '2', date: '2025-10-27', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '521', userId: '2', date: '2025-10-29', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '522', userId: '2', date: '2025-10-30', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '523', userId: '2', date: '2025-10-31', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },

  // 山田次郎 - 10月分（通知時間45時間超過で注意アラート）
  { id: '601', userId: '3', date: '2025-10-02', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '601b', userId: '3', date: '2025-10-03', status: 'special-leave', totalHours: 0 },
  { id: '602', userId: '3', date: '2025-10-04', clockIn: '06:00', clockOut: '17:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 10 },
  { id: '603', userId: '3', date: '2025-10-06', clockIn: '06:00', clockOut: '16:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9 },
  { id: '604', userId: '3', date: '2025-10-07', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '605', userId: '3', date: '2025-10-09', clockIn: '06:00', clockOut: '17:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 10 },
  { id: '606', userId: '3', date: '2025-10-10', clockIn: '06:00', clockOut: '16:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9 },
  { id: '607', userId: '3', date: '2025-10-11', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '608', userId: '3', date: '2025-10-14', clockIn: '06:00', clockOut: '17:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 10 },
  { id: '609', userId: '3', date: '2025-10-16', clockIn: '06:00', clockOut: '16:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9 },
  { id: '610', userId: '3', date: '2025-10-17', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '611', userId: '3', date: '2025-10-18', clockIn: '06:00', clockOut: '17:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 10 },
  { id: '612', userId: '3', date: '2025-10-20', clockIn: '06:00', clockOut: '16:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9 },
  { id: '613', userId: '3', date: '2025-10-21', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '614', userId: '3', date: '2025-10-23', clockIn: '06:00', clockOut: '17:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 10 },
  { id: '615', userId: '3', date: '2025-10-24', clockIn: '06:00', clockOut: '16:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9 },
  { id: '616', userId: '3', date: '2025-10-25', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '617', userId: '3', date: '2025-10-27', clockIn: '06:00', clockOut: '17:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 10 },
  { id: '618', userId: '3', date: '2025-10-28', clockIn: '06:00', clockOut: '16:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9 },
  { id: '619', userId: '3', date: '2025-10-30', clockIn: '06:00', clockOut: '16:30', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 9.5 },
  { id: '620', userId: '3', date: '2025-10-31', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },

  // 鈴木一郎 - 10月分（限度時間80時間超過で警告アラート）
  { id: '701', userId: '4', date: '2025-10-02', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '702', userId: '4', date: '2025-10-03', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '703', userId: '4', date: '2025-10-04', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '704', userId: '4', date: '2025-10-07', clockIn: '09:00', clockOut: '22:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12.5 },
  { id: '705', userId: '4', date: '2025-10-08', clockIn: '09:00', clockOut: '20:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10 },
  { id: '706', userId: '4', date: '2025-10-09', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '707', userId: '4', date: '2025-10-10', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '708', userId: '4', date: '2025-10-11', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '709', userId: '4', date: '2025-10-14', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '710', userId: '4', date: '2025-10-15', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '711', userId: '4', date: '2025-10-16', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '712', userId: '4', date: '2025-10-17', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '713', userId: '4', date: '2025-10-18', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '714', userId: '4', date: '2025-10-21', clockIn: '09:00', clockOut: '20:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10 },
  { id: '715', userId: '4', date: '2025-10-22', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '716', userId: '4', date: '2025-10-23', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '717', userId: '4', date: '2025-10-24', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '718', userId: '4', date: '2025-10-25', clockIn: '09:00', clockOut: '20:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10.5 },
  { id: '719', userId: '4', date: '2025-10-28', clockIn: '09:00', clockOut: '21:30', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11.5 },
  { id: '720', userId: '4', date: '2025-10-29', clockIn: '09:00', clockOut: '22:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 12 },
  { id: '721', userId: '4', date: '2025-10-30', clockIn: '09:00', clockOut: '21:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 11 },
  { id: '722', userId: '4', date: '2025-10-31', clockIn: '09:00', clockOut: '20:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 10 },

  // 田中太郎 - 11月分（日勤: 09:00-18:00）
  { id: '801', userId: '1', date: '2025-11-04', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '802', userId: '1', date: '2025-11-05', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '803', userId: '1', date: '2025-11-06', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '804', userId: '1', date: '2025-11-07', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '805', userId: '1', date: '2025-11-10', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '806', userId: '1', date: '2025-11-11', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '807', userId: '1', date: '2025-11-12', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '808', userId: '1', date: '2025-11-13', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '809', userId: '1', date: '2025-11-14', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '810', userId: '1', date: '2025-11-17', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '811', userId: '1', date: '2025-11-18', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '812', userId: '1', date: '2025-11-19', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '813', userId: '1', date: '2025-11-20', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '814', userId: '1', date: '2025-11-21', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '815', userId: '1', date: '2025-11-25', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '816', userId: '1', date: '2025-11-26', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '817', userId: '1', date: '2025-11-27', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '818', userId: '1', date: '2025-11-28', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },

  // 佐藤花子 - 11月分（日勤: 09:00-18:00、遅番: 13:00-22:00）
  { id: '901', userId: '2', date: '2025-11-01', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '902', userId: '2', date: '2025-11-05', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '903', userId: '2', date: '2025-11-06', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '904', userId: '2', date: '2025-11-07', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '905', userId: '2', date: '2025-11-08', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '906', userId: '2', date: '2025-11-10', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '907', userId: '2', date: '2025-11-12', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '908', userId: '2', date: '2025-11-13', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '909', userId: '2', date: '2025-11-14', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '910', userId: '2', date: '2025-11-15', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '911', userId: '2', date: '2025-11-17', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '912', userId: '2', date: '2025-11-19', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '913', userId: '2', date: '2025-11-20', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '914', userId: '2', date: '2025-11-21', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '915', userId: '2', date: '2025-11-22', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '916', userId: '2', date: '2025-11-25', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '917', userId: '2', date: '2025-11-26', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '918', userId: '2', date: '2025-11-27', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },
  { id: '919', userId: '2', date: '2025-11-28', clockIn: '13:00', clockOut: '22:00', breakStart: '17:00', breakEnd: '18:00', status: 'present', totalHours: 8 },
  { id: '920', userId: '2', date: '2025-11-29', clockIn: '09:00', clockOut: '18:00', breakStart: '12:00', breakEnd: '13:00', status: 'present', totalHours: 8 },

  // 山田次郎 - 11月分（早番: 06:00-15:00）
  { id: '1001', userId: '3', date: '2025-11-01', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1002', userId: '3', date: '2025-11-04', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1003', userId: '3', date: '2025-11-06', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1004', userId: '3', date: '2025-11-07', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1005', userId: '3', date: '2025-11-08', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1006', userId: '3', date: '2025-11-10', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1007', userId: '3', date: '2025-11-11', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1008', userId: '3', date: '2025-11-13', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1009', userId: '3', date: '2025-11-14', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1010', userId: '3', date: '2025-11-15', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1011', userId: '3', date: '2025-11-17', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1012', userId: '3', date: '2025-11-18', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1013', userId: '3', date: '2025-11-20', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1014', userId: '3', date: '2025-11-21', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1015', userId: '3', date: '2025-11-22', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1016', userId: '3', date: '2025-11-25', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1017', userId: '3', date: '2025-11-27', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1018', userId: '3', date: '2025-11-28', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },
  { id: '1019', userId: '3', date: '2025-11-29', clockIn: '06:00', clockOut: '15:00', breakStart: '10:00', breakEnd: '11:00', status: 'present', totalHours: 8 },

  // 鈴木一郎 - 11月分（夜勤: 22:00-06:00）
  { id: '1101', userId: '4', date: '2025-11-01', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1102', userId: '4', date: '2025-11-04', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1103', userId: '4', date: '2025-11-05', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1104', userId: '4', date: '2025-11-06', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1105', userId: '4', date: '2025-11-07', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1106', userId: '4', date: '2025-11-08', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1107', userId: '4', date: '2025-11-11', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1108', userId: '4', date: '2025-11-12', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1109', userId: '4', date: '2025-11-13', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1110', userId: '4', date: '2025-11-14', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1111', userId: '4', date: '2025-11-15', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1112', userId: '4', date: '2025-11-18', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1113', userId: '4', date: '2025-11-19', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1114', userId: '4', date: '2025-11-20', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1115', userId: '4', date: '2025-11-21', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1116', userId: '4', date: '2025-11-22', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1117', userId: '4', date: '2025-11-25', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1118', userId: '4', date: '2025-11-26', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1119', userId: '4', date: '2025-11-27', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1120', userId: '4', date: '2025-11-28', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
  { id: '1121', userId: '4', date: '2025-11-29', clockIn: '22:00', clockOut: '06:00', breakStart: '01:00', breakEnd: '02:00', status: 'present', totalHours: 7 },
];

export const shiftTypeInfo: ShiftTypeInfo[] = [
  {
    type: 'early',
    name: '早番',
    timeRange: '06:00-15:00',
    color: 'bg-blue-100',
    textColor: 'text-blue-800'
  },
  {
    type: 'day',
    name: '日勤',
    timeRange: '09:00-18:00',
    color: 'bg-green-100',
    textColor: 'text-green-800'
  },
  {
    type: 'late',
    name: '遅番',
    timeRange: '13:00-22:00',
    color: 'bg-orange-100',
    textColor: 'text-orange-800'
  },
  {
    type: 'night',
    name: '夜勤',
    timeRange: '22:00-06:00',
    color: 'bg-purple-100',
    textColor: 'text-purple-800'
  },
  {
    type: 'off-duty',
    name: '明け',
    timeRange: '翌朝まで休み',
    color: 'bg-yellow-100',
    textColor: 'text-yellow-800'
  }
];

// シフト選択時に使用する休みの情報（shiftTypeInfoには含めない）
export const restShiftInfo: ShiftTypeInfo = {
  type: 'rest',
  name: '休み',
  timeRange: '終日休み',
  color: 'bg-gray-100',
  textColor: 'text-gray-800'
};

// シフトデータ（9月、10月の平日）
export const mockShifts: Shift[] = [
  // 9月分（土日祝以外）
  { id: '1', userId: '1', date: '2025-09-01', shiftType: 'day' },
  { id: '2', userId: '1', date: '2025-09-02', shiftType: 'day' },
  { id: '3', userId: '1', date: '2025-09-03', shiftType: 'day' },
  { id: '4', userId: '1', date: '2025-09-04', shiftType: 'day' },
  { id: '5', userId: '1', date: '2025-09-05', shiftType: 'day' },
  { id: '6', userId: '1', date: '2025-09-08', shiftType: 'day' },
  { id: '7', userId: '1', date: '2025-09-09', shiftType: 'day' },
  { id: '9', userId: '1', date: '2025-09-11', shiftType: 'day' },
  { id: '10', userId: '1', date: '2025-09-12', shiftType: 'day' },
  { id: '11', userId: '1', date: '2025-09-16', shiftType: 'day' },
  { id: '12', userId: '1', date: '2025-09-17', shiftType: 'day' },
  { id: '13', userId: '1', date: '2025-09-18', shiftType: 'day' },
  { id: '14', userId: '1', date: '2025-09-19', shiftType: 'day' },
  { id: '15', userId: '1', date: '2025-09-22', shiftType: 'day' },
  { id: '16', userId: '1', date: '2025-09-24', shiftType: 'day' },
  { id: '17', userId: '1', date: '2025-09-25', shiftType: 'day' },
  { id: '18', userId: '1', date: '2025-09-26', shiftType: 'day' },
  { id: '19', userId: '1', date: '2025-09-29', shiftType: 'day' },
  { id: '20', userId: '1', date: '2025-09-30', shiftType: 'day' },

  // 10月分 - 各スタッフのshiftPatternsに基づいて生成
  // 会社定休日: 日曜日（10/5, 12, 19, 26）
  // 祝日: 10/13（スポーツの日・月）

  // 田中太郎（userId: '1'）- 月～金は日勤、土日は休み
  { id: '21', userId: '1', date: '2025-10-01', shiftType: 'day' },   // 水
  { id: '22', userId: '1', date: '2025-10-02', shiftType: 'day' },   // 木
  { id: '23', userId: '1', date: '2025-10-03', shiftType: 'day' },   // 金
  { id: '24', userId: '1', date: '2025-10-06', shiftType: 'day' },   // 月
  { id: '25', userId: '1', date: '2025-10-07', shiftType: 'day' },   // 火
  { id: '26', userId: '1', date: '2025-10-08', shiftType: 'day' },   // 水
  { id: '27', userId: '1', date: '2025-10-09', shiftType: 'day' },   // 木
  { id: '28', userId: '1', date: '2025-10-10', shiftType: 'day' },   // 金
  { id: '29', userId: '1', date: '2025-10-14', shiftType: 'day' },   // 火
  { id: '30', userId: '1', date: '2025-10-15', shiftType: 'day' },   // 水
  { id: '31', userId: '1', date: '2025-10-16', shiftType: 'day' },   // 木
  { id: '32', userId: '1', date: '2025-10-17', shiftType: 'day' },   // 金
  { id: '33', userId: '1', date: '2025-10-20', shiftType: 'day' },   // 月
  { id: '34', userId: '1', date: '2025-10-21', shiftType: 'day' },   // 火
  { id: '35', userId: '1', date: '2025-10-22', shiftType: 'day' },   // 水
  { id: '36', userId: '1', date: '2025-10-23', shiftType: 'day' },   // 木
  { id: '37', userId: '1', date: '2025-10-24', shiftType: 'day' },   // 金
  { id: '38', userId: '1', date: '2025-10-27', shiftType: 'day' },   // 月
  { id: '39', userId: '1', date: '2025-10-28', shiftType: 'day' },   // 火
  { id: '40', userId: '1', date: '2025-10-29', shiftType: 'day' },   // 水
  { id: '41', userId: '1', date: '2025-10-30', shiftType: 'day' },   // 木
  { id: '42', userId: '1', date: '2025-10-31', shiftType: 'day' },   // 金

  // 佐藤花子（userId: '2'）- 9月分（勤務: 水〜日、休み: 月・火）
  { id: '101', userId: '2', date: '2025-09-01', shiftType: 'rest' },
  { id: '102', userId: '2', date: '2025-09-02', shiftType: 'rest' },
  { id: '103', userId: '2', date: '2025-09-03', shiftType: 'day' },
  { id: '104', userId: '2', date: '2025-09-04', shiftType: 'day' },
  { id: '105', userId: '2', date: '2025-09-05', shiftType: 'late' },
  { id: '106', userId: '2', date: '2025-09-06', shiftType: 'day' },
  { id: '107', userId: '2', date: '2025-09-07', shiftType: 'late' },
  { id: '108', userId: '2', date: '2025-09-08', shiftType: 'rest' },
  { id: '109', userId: '2', date: '2025-09-09', shiftType: 'rest' },
  { id: '110', userId: '2', date: '2025-09-10', shiftType: 'day' },
  { id: '111', userId: '2', date: '2025-09-11', shiftType: 'late' },
  { id: '112', userId: '2', date: '2025-09-12', shiftType: 'day' },
  { id: '113', userId: '2', date: '2025-09-13', shiftType: 'late' },
  { id: '114', userId: '2', date: '2025-09-14', shiftType: 'day' },
  { id: '115', userId: '2', date: '2025-09-15', shiftType: 'rest' },
  { id: '116', userId: '2', date: '2025-09-16', shiftType: 'rest' },
  { id: '117', userId: '2', date: '2025-09-17', shiftType: 'late' },
  { id: '118', userId: '2', date: '2025-09-18', shiftType: 'day' },
  { id: '119', userId: '2', date: '2025-09-19', shiftType: 'day' },
  { id: '120', userId: '2', date: '2025-09-20', shiftType: 'late' },
  { id: '121', userId: '2', date: '2025-09-21', shiftType: 'day' },
  { id: '122', userId: '2', date: '2025-09-22', shiftType: 'rest' },
  { id: '123', userId: '2', date: '2025-09-23', shiftType: 'rest' },
  { id: '124', userId: '2', date: '2025-09-24', shiftType: 'day' },
  { id: '125', userId: '2', date: '2025-09-25', shiftType: 'late' },
  { id: '126', userId: '2', date: '2025-09-26', shiftType: 'day' },
  { id: '127', userId: '2', date: '2025-09-27', shiftType: 'day' },
  { id: '128', userId: '2', date: '2025-09-28', shiftType: 'late' },
  { id: '129', userId: '2', date: '2025-09-29', shiftType: 'rest' },
  { id: '130', userId: '2', date: '2025-09-30', shiftType: 'rest' },

  // 佐藤花子（userId: '2'）- 10月分（月水木金土勤務、日火休み）
  { id: '131', userId: '2', date: '2025-10-01', shiftType: 'late' }, // 水
  { id: '132', userId: '2', date: '2025-10-02', shiftType: 'day' },  // 木
  { id: '133', userId: '2', date: '2025-10-03', shiftType: 'late' }, // 金
  { id: '134', userId: '2', date: '2025-10-04', shiftType: 'day' },  // 土
  { id: '135', userId: '2', date: '2025-10-06', shiftType: 'day' },  // 月
  { id: '136', userId: '2', date: '2025-10-08', shiftType: 'late' }, // 水
  { id: '137', userId: '2', date: '2025-10-09', shiftType: 'day' },  // 木
  { id: '138', userId: '2', date: '2025-10-10', shiftType: 'late' }, // 金
  { id: '139', userId: '2', date: '2025-10-11', shiftType: 'day' },  // 土
  { id: '140', userId: '2', date: '2025-10-14', shiftType: 'day' },  // 火（祝日振替で勤務）
  { id: '141', userId: '2', date: '2025-10-15', shiftType: 'late' }, // 水
  { id: '142', userId: '2', date: '2025-10-16', shiftType: 'day' },  // 木
  { id: '143', userId: '2', date: '2025-10-17', shiftType: 'late' }, // 金
  { id: '144', userId: '2', date: '2025-10-18', shiftType: 'day' },  // 土
  { id: '145', userId: '2', date: '2025-10-20', shiftType: 'day' },  // 月
  { id: '146', userId: '2', date: '2025-10-22', shiftType: 'late' }, // 水
  { id: '147', userId: '2', date: '2025-10-23', shiftType: 'day' },  // 木
  { id: '148', userId: '2', date: '2025-10-24', shiftType: 'late' }, // 金
  { id: '149', userId: '2', date: '2025-10-25', shiftType: 'day' },  // 土
  { id: '150', userId: '2', date: '2025-10-27', shiftType: 'day' },  // 月
  { id: '151', userId: '2', date: '2025-10-29', shiftType: 'late' }, // 水
  { id: '152', userId: '2', date: '2025-10-30', shiftType: 'day' },  // 木
  { id: '153', userId: '2', date: '2025-10-31', shiftType: 'late' }, // 金

  // 山田次郎（userId: '3'）- 9月分（勤務: 月・水・木・金・土、休み: 火・日）
  { id: '201', userId: '3', date: '2025-09-01', shiftType: 'early' },
  { id: '202', userId: '3', date: '2025-09-02', shiftType: 'rest' },
  { id: '203', userId: '3', date: '2025-09-03', shiftType: 'early' },
  { id: '204', userId: '3', date: '2025-09-04', shiftType: 'early' },
  { id: '205', userId: '3', date: '2025-09-05', shiftType: 'early' },
  { id: '206', userId: '3', date: '2025-09-06', shiftType: 'early' },
  { id: '207', userId: '3', date: '2025-09-07', shiftType: 'rest' },
  { id: '208', userId: '3', date: '2025-09-08', shiftType: 'early' },
  { id: '209', userId: '3', date: '2025-09-09', shiftType: 'rest' },
  { id: '210', userId: '3', date: '2025-09-10', shiftType: 'early' },
  { id: '211', userId: '3', date: '2025-09-11', shiftType: 'early' },
  { id: '212', userId: '3', date: '2025-09-12', shiftType: 'early' },
  { id: '213', userId: '3', date: '2025-09-13', shiftType: 'early' },
  { id: '214', userId: '3', date: '2025-09-14', shiftType: 'rest' },
  { id: '215', userId: '3', date: '2025-09-15', shiftType: 'early' },
  { id: '216', userId: '3', date: '2025-09-16', shiftType: 'rest' },
  { id: '217', userId: '3', date: '2025-09-17', shiftType: 'early' },
  { id: '218', userId: '3', date: '2025-09-18', shiftType: 'early' },
  { id: '219', userId: '3', date: '2025-09-19', shiftType: 'early' },
  { id: '220', userId: '3', date: '2025-09-20', shiftType: 'early' },
  { id: '221', userId: '3', date: '2025-09-21', shiftType: 'rest' },
  { id: '222', userId: '3', date: '2025-09-22', shiftType: 'early' },
  { id: '223', userId: '3', date: '2025-09-23', shiftType: 'rest' },
  { id: '224', userId: '3', date: '2025-09-24', shiftType: 'early' },
  { id: '225', userId: '3', date: '2025-09-25', shiftType: 'early' },
  { id: '226', userId: '3', date: '2025-09-26', shiftType: 'early' },
  { id: '227', userId: '3', date: '2025-09-27', shiftType: 'early' },
  { id: '228', userId: '3', date: '2025-09-28', shiftType: 'rest' },
  { id: '229', userId: '3', date: '2025-09-29', shiftType: 'early' },
  { id: '230', userId: '3', date: '2025-09-30', shiftType: 'rest' },

  // 山田次郎（userId: '3'）- 10月分（月火木金土早番、日水休み）
  { id: '231', userId: '3', date: '2025-10-01', shiftType: 'early' }, // 水→火（新パターン）
  { id: '232', userId: '3', date: '2025-10-02', shiftType: 'early' }, // 木
  { id: '233', userId: '3', date: '2025-10-03', shiftType: 'early' }, // 金
  { id: '234', userId: '3', date: '2025-10-04', shiftType: 'early' }, // 土
  { id: '235', userId: '3', date: '2025-10-06', shiftType: 'early' }, // 月
  { id: '236', userId: '3', date: '2025-10-07', shiftType: 'early' }, // 火
  { id: '237', userId: '3', date: '2025-10-09', shiftType: 'early' }, // 木
  { id: '238', userId: '3', date: '2025-10-10', shiftType: 'early' }, // 金
  { id: '239', userId: '3', date: '2025-10-11', shiftType: 'early' }, // 土
  { id: '240', userId: '3', date: '2025-10-14', shiftType: 'early' }, // 火
  { id: '241', userId: '3', date: '2025-10-16', shiftType: 'early' }, // 木
  { id: '242', userId: '3', date: '2025-10-17', shiftType: 'early' }, // 金
  { id: '243', userId: '3', date: '2025-10-18', shiftType: 'early' }, // 土
  { id: '244', userId: '3', date: '2025-10-20', shiftType: 'early' }, // 月
  { id: '245', userId: '3', date: '2025-10-21', shiftType: 'early' }, // 火
  { id: '246', userId: '3', date: '2025-10-23', shiftType: 'early' }, // 木
  { id: '247', userId: '3', date: '2025-10-24', shiftType: 'early' }, // 金
  { id: '248', userId: '3', date: '2025-10-25', shiftType: 'early' }, // 土
  { id: '249', userId: '3', date: '2025-10-27', shiftType: 'early' }, // 月
  { id: '250', userId: '3', date: '2025-10-28', shiftType: 'early' }, // 火
  { id: '251', userId: '3', date: '2025-10-30', shiftType: 'early' }, // 木
  { id: '252', userId: '3', date: '2025-10-31', shiftType: 'early' }, // 金

  // 鈴木一郎（userId: '4'）- 9月分（勤務: 火・木・金・土・日、休み: 月・水）
  { id: '301', userId: '4', date: '2025-09-01', shiftType: 'rest' },
  { id: '302', userId: '4', date: '2025-09-02', shiftType: 'night' },
  { id: '303', userId: '4', date: '2025-09-03', shiftType: 'rest' },
  { id: '304', userId: '4', date: '2025-09-04', shiftType: 'off-duty' },
  { id: '305', userId: '4', date: '2025-09-05', shiftType: 'night' },
  { id: '306', userId: '4', date: '2025-09-06', shiftType: 'off-duty' },
  { id: '307', userId: '4', date: '2025-09-07', shiftType: 'night' },
  { id: '308', userId: '4', date: '2025-09-08', shiftType: 'rest' },
  { id: '309', userId: '4', date: '2025-09-09', shiftType: 'off-duty' },
  { id: '310', userId: '4', date: '2025-09-10', shiftType: 'rest' },
  { id: '311', userId: '4', date: '2025-09-11', shiftType: 'night' },
  { id: '312', userId: '4', date: '2025-09-12', shiftType: 'off-duty' },
  { id: '313', userId: '4', date: '2025-09-13', shiftType: 'night' },
  { id: '314', userId: '4', date: '2025-09-14', shiftType: 'off-duty' },
  { id: '315', userId: '4', date: '2025-09-15', shiftType: 'rest' },
  { id: '316', userId: '4', date: '2025-09-16', shiftType: 'night' },
  { id: '317', userId: '4', date: '2025-09-17', shiftType: 'rest' },
  { id: '318', userId: '4', date: '2025-09-18', shiftType: 'off-duty' },
  { id: '319', userId: '4', date: '2025-09-19', shiftType: 'night' },
  { id: '320', userId: '4', date: '2025-09-20', shiftType: 'off-duty' },
  { id: '321', userId: '4', date: '2025-09-21', shiftType: 'night' },
  { id: '322', userId: '4', date: '2025-09-22', shiftType: 'rest' },
  { id: '323', userId: '4', date: '2025-09-23', shiftType: 'off-duty' },
  { id: '324', userId: '4', date: '2025-09-24', shiftType: 'rest' },
  { id: '325', userId: '4', date: '2025-09-25', shiftType: 'night' },
  { id: '326', userId: '4', date: '2025-09-26', shiftType: 'off-duty' },
  { id: '327', userId: '4', date: '2025-09-27', shiftType: 'night' },
  { id: '328', userId: '4', date: '2025-09-28', shiftType: 'off-duty' },
  { id: '329', userId: '4', date: '2025-09-29', shiftType: 'rest' },
  { id: '330', userId: '4', date: '2025-09-30', shiftType: 'night' },

  // 鈴木一郎（userId: '4'）- 10月分（火水木金土夜勤、日月休み）
  { id: '331', userId: '4', date: '2025-10-01', shiftType: 'rest' },      // 水→火（新パターン）
  { id: '332', userId: '4', date: '2025-10-02', shiftType: 'night' },     // 木→水
  { id: '333', userId: '4', date: '2025-10-03', shiftType: 'night' },     // 金→木
  { id: '334', userId: '4', date: '2025-10-04', shiftType: 'night' },     // 土→金
  { id: '335', userId: '4', date: '2025-10-07', shiftType: 'night' },     // 火
  { id: '336', userId: '4', date: '2025-10-08', shiftType: 'night' },     // 水
  { id: '337', userId: '4', date: '2025-10-09', shiftType: 'night' },     // 木
  { id: '338', userId: '4', date: '2025-10-10', shiftType: 'night' },     // 金
  { id: '339', userId: '4', date: '2025-10-11', shiftType: 'night' },     // 土
  { id: '340', userId: '4', date: '2025-10-14', shiftType: 'night' },     // 火
  { id: '341', userId: '4', date: '2025-10-15', shiftType: 'night' },     // 水
  { id: '342', userId: '4', date: '2025-10-16', shiftType: 'night' },     // 木
  { id: '343', userId: '4', date: '2025-10-17', shiftType: 'night' },     // 金
  { id: '344', userId: '4', date: '2025-10-18', shiftType: 'night' },     // 土
  { id: '345', userId: '4', date: '2025-10-21', shiftType: 'night' },     // 火
  { id: '346', userId: '4', date: '2025-10-22', shiftType: 'night' },     // 水
  { id: '347', userId: '4', date: '2025-10-23', shiftType: 'night' },     // 木
  { id: '348', userId: '4', date: '2025-10-24', shiftType: 'night' },     // 金
  { id: '349', userId: '4', date: '2025-10-25', shiftType: 'night' },     // 土
  { id: '350', userId: '4', date: '2025-10-28', shiftType: 'night' },     // 火
  { id: '351', userId: '4', date: '2025-10-29', shiftType: 'night' },     // 水
  { id: '352', userId: '4', date: '2025-10-30', shiftType: 'night' },     // 木
  { id: '353', userId: '4', date: '2025-10-31', shiftType: 'night' },     // 金
];

export const mockApplications: Application[] = [
  {
    id: '1',
    userId: '3',
    type: 'vacation',
    startDate: '2025-10-15',
    endDate: '2025-10-16',
    reason: '私用のため',
    status: 'pending',
    submittedAt: '2025-10-01T10:00:00Z'
  },
  {
    id: '2',
    userId: '4',
    type: 'overtime',
    startDate: '2025-10-05',
    endDate: '2025-10-05',
    reason: 'プロジェクト納期対応',
    status: 'approved',
    approvedBy: '2',
    submittedAt: '2025-09-30T15:30:00Z'
  },
  {
    id: '3',
    userId: '1',
    type: 'vacation',
    startDate: '2025-09-23',
    endDate: '2025-09-23',
    reason: '家族旅行のため',
    status: 'approved',
    approvedBy: '2',
    submittedAt: '2025-09-10T09:00:00Z'
  },
  {
    id: '4',
    userId: '2',
    type: 'sick-leave',
    startDate: '2025-10-07',
    endDate: '2025-10-08',
    reason: '体調不良',
    status: 'approved',
    approvedBy: '1',
    submittedAt: '2025-10-07T08:00:00Z'
  },
  {
    id: '5',
    userId: '1',
    type: 'overtime',
    startDate: '2025-09-18',
    endDate: '2025-09-18',
    reason: '月次決算業務',
    status: 'approved',
    approvedBy: '2',
    submittedAt: '2025-09-15T14:00:00Z'
  },
  {
    id: '7',
    userId: '1',
    type: 'overtime',
    startDate: '2025-10-15',
    endDate: '2025-10-15',
    reason: 'システムメンテナンス対応',
    status: 'approved',
    approvedBy: '2',
    submittedAt: '2025-10-14T16:00:00Z'
  },
  {
    id: '8',
    userId: '2',
    type: 'vacation',
    startDate: '2025-10-20',
    endDate: '2025-10-21',
    reason: 'リフレッシュ休暇',
    status: 'approved',
    approvedBy: '1',
    submittedAt: '2025-10-10T09:00:00Z'
  },
  {
    id: '9',
    userId: '1',
    type: 'vacation',
    startDate: '2025-10-04',
    endDate: '2025-10-05',
    reason: '私用のため',
    status: 'approved',
    approvedBy: '2',
    submittedAt: '2025-09-25T10:00:00Z'
  }
];