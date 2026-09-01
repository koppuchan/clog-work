import { format } from 'date-fns';
import { User } from '@/types';

interface ReportFilterPanelProps {
  selectedMonth: Date;
  onMonthChange: (month: Date) => void;
  selectedUser: string;
  onUserChange: (userId: string) => void;
  users: Pick<User, 'id' | 'name'>[];
}

/**
 * 集計対象の月・スタッフを選択するフィルターパネル
 */
export default function ReportFilterPanel({
  selectedMonth,
  onMonthChange,
  selectedUser,
  onUserChange,
  users,
}: ReportFilterPanelProps) {
  return (
    <div className="bg-white shadow rounded-lg p-6">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            対象月
          </label>
          <input
            type="month"
            value={format(selectedMonth, 'yyyy-MM')}
            onChange={(e) => onMonthChange(new Date(e.target.value))}
            className="w-full border border-gray-300 rounded-md p-2"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            従業員
          </label>
          <select
            value={selectedUser}
            onChange={(e) => onUserChange(e.target.value)}
            className="w-full border border-gray-300 rounded-md p-2"
          >
            {users.map(user => (
              <option key={user.id} value={user.id}>{user.name}</option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
}
