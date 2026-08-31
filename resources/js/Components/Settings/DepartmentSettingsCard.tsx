import { Building, Plus, Edit, Trash2, EyeOff } from 'lucide-react';
import SettingsCard from './SettingsCard';
import { SettingsDepartment } from '@/types/settings';

interface DepartmentSettingsCardProps {
  departments: SettingsDepartment[];
  onAdd: () => void;
  onEdit: (id: number) => void;
  onDelete: (id: number) => void;
}

export default function DepartmentSettingsCard({
  departments,
  onAdd,
  onEdit,
  onDelete,
}: DepartmentSettingsCardProps) {
  return (
    <SettingsCard
      title="部署設定"
      icon={Building}
      headerAction={
        <button
          type="button"
          onClick={onAdd}
          className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg flex items-center space-x-1 text-sm"
        >
          <Plus className="h-3 w-3" />
          <span>追加</span>
        </button>
      }
    >
      <div className="space-y-3">
        {departments.map((dept) => (
          <div key={dept.id} className="border rounded-lg p-4 flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <span className="px-3 py-1 rounded font-medium text-sm bg-gray-100 text-gray-800">
                {dept.name}
              </span>
              {dept.isStampHidden && (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">
                  <EyeOff className="h-3 w-3" />
                  打刻非表示
                </span>
              )}
            </div>
            <div className="flex space-x-2">
              <button
                type="button"
                onClick={() => onEdit(dept.id)}
                className="text-blue-600 hover:text-blue-900 p-1"
              >
                <Edit className="h-4 w-4" />
              </button>
              <button
                type="button"
                onClick={() => onDelete(dept.id)}
                className="text-red-600 hover:text-red-900 p-1"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          </div>
        ))}
      </div>
    </SettingsCard>
  );
}
