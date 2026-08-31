import React from 'react';
import { LucideIcon } from 'lucide-react';

interface SettingsCardProps {
  title: string;
  icon?: LucideIcon;
  iconColor?: string;
  headerAction?: React.ReactNode;
  children: React.ReactNode;
}

export default function SettingsCard({
  title,
  icon: Icon,
  iconColor = 'text-blue-600',
  headerAction,
  children,
}: SettingsCardProps) {
  return (
    <div className="bg-white shadow rounded-lg p-6">
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold text-gray-900 flex items-center">
          {Icon && <Icon className={`h-5 w-5 mr-2 ${iconColor}`} />}
          {title}
        </h3>
        {headerAction}
      </div>
      {children}
    </div>
  );
}
