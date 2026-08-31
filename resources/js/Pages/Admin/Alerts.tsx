import React, { useMemo, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { AlertTriangle, Clock, Filter, ChevronDown, ChevronUp } from 'lucide-react';
import { format } from 'date-fns';
import { router, usePage } from '@inertiajs/react';

type AlertLevel = 'warning' | 'caution';

interface Alert {
  id: number;
  userId: number;
  userName: string;
  alertLevel: AlertLevel;
  alertLevelLabel: string;
  title: string;
  message: string;
  value: number;
  threshold: number;
  isRead: boolean;
  date: string;
}

interface AlertSummary {
  total: number;
  warning: number;
  caution: number;
  unread: number;
}

interface AlertsPageProps {
  alerts: Alert[];
  summary: AlertSummary;
  selectedYear: number;
  selectedMonth: number;
}

function AlertsPage() {
  const { alerts, summary, selectedYear, selectedMonth } = usePage<{ props: AlertsPageProps }>().props as unknown as AlertsPageProps;

  const [filterLevel, setFilterLevel] = useState<AlertLevel | 'all'>('all');
  const [expandedAlert, setExpandedAlert] = useState<string | null>(null);

  const selectedValue = `${selectedYear}-${String(selectedMonth).padStart(2, '0')}`;

  const handleMonthChange = (value: string) => {
    const [year, month] = value.split('-');
    router.get('/admin/alerts', { year, month }, { preserveState: true });
  };

  const filteredAlerts = useMemo(() => {
    if (filterLevel === 'all') return alerts;
    return alerts.filter(alert => alert.alertLevel === filterLevel);
  }, [alerts, filterLevel]);

  const getSeverityColor = (level: AlertLevel) => {
    switch (level) {
      case 'warning':
        return 'bg-red-100 text-red-800 border-red-300';
      case 'caution':
        return 'bg-yellow-100 text-yellow-800 border-yellow-300';
    }
  };

  const getSeverityBadgeColor = (level: AlertLevel) => {
    switch (level) {
      case 'warning':
        return 'bg-red-500 text-white';
      case 'caution':
        return 'bg-yellow-500 text-white';
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">労務アラート</h1>
        <select
          value={selectedValue}
          onChange={(e) => handleMonthChange(e.target.value)}
          className="border border-gray-300 rounded-md px-3 py-2 pr-8 text-sm appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3csvg%20xmlns%3d%22http%3a%2f%2fwww.w3.org%2f2000%2fsvg%22%20viewBox%3d%220%200%2024%2024%22%20fill%3d%22none%22%20stroke%3d%22%23666%22%20stroke-width%3d%222%22%20stroke-linecap%3d%22round%22%20stroke-linejoin%3d%22round%22%3e%3cpolyline%20points%3d%226%209%2012%2015%2018%209%22%3e%3c%2fpolyline%3e%3c%2fsvg%3e')] bg-[length:1rem] bg-[right_0.5rem_center] bg-no-repeat bg-white"
        >
          {Array.from({ length: 12 }, (_, i) => {
            const date = new Date();
            date.setMonth(date.getMonth() - i);
            return (
              <option key={i} value={format(date, 'yyyy-MM')}>
                {format(date, 'yyyy年MM月')}
              </option>
            );
          })}
        </select>
      </div>

      {/* サマリーカード */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">総アラート数</p>
              <p className="text-3xl font-bold text-gray-900">{summary.total}</p>
            </div>
            <AlertTriangle className="h-10 w-10 text-gray-400" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">警告</p>
              <p className="text-3xl font-bold text-red-600">{summary.warning}</p>
            </div>
            <div className="h-10 w-10 bg-red-100 rounded-full flex items-center justify-center">
              <AlertTriangle className="h-6 w-6 text-red-600" />
            </div>
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">注意</p>
              <p className="text-3xl font-bold text-yellow-600">{summary.caution}</p>
            </div>
            <div className="h-10 w-10 bg-yellow-100 rounded-full flex items-center justify-center">
              <AlertTriangle className="h-6 w-6 text-yellow-600" />
            </div>
          </div>
        </div>
      </div>

      {/* フィルター */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex items-center space-x-3">
          <Filter className="h-5 w-5 text-gray-600" />
          <span className="text-sm font-semibold text-gray-700">フィルター:</span>
          <div className="flex items-center space-x-2">
            <button
              onClick={() => setFilterLevel('all')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filterLevel === 'all'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              すべて
            </button>
            <button
              onClick={() => setFilterLevel('warning')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filterLevel === 'warning'
                  ? 'bg-red-600 text-white'
                  : 'bg-red-100 text-red-700 hover:bg-red-200'
              }`}
            >
              警告
            </button>
            <button
              onClick={() => setFilterLevel('caution')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filterLevel === 'caution'
                  ? 'bg-yellow-600 text-white'
                  : 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'
              }`}
            >
              注意
            </button>
          </div>
        </div>
      </div>

      {/* アラート一覧 */}
      <div className="bg-white shadow rounded-lg">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">
            アラート一覧 ({filteredAlerts.length}件)
          </h2>
        </div>
        <div className="divide-y divide-gray-200">
          {filteredAlerts.length === 0 ? (
            <div className="px-6 py-12 text-center">
              <AlertTriangle className="mx-auto h-12 w-12 text-gray-400" />
              <p className="mt-2 text-sm text-gray-500">該当するアラートはありません</p>
            </div>
          ) : (
            filteredAlerts.map((alert) => (
              <div
                key={alert.id}
                className={`px-6 py-4 hover:bg-gray-50 transition-colors cursor-pointer ${
                  expandedAlert === String(alert.id) ? 'bg-gray-50' : ''
                }`}
                onClick={() => setExpandedAlert(expandedAlert === String(alert.id) ? null : String(alert.id))}
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-start space-x-4 flex-1">
                    <div className={`p-2 rounded-lg ${getSeverityColor(alert.alertLevel)}`}>
                      <Clock className="h-5 w-5" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center space-x-2 mb-1">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${getSeverityBadgeColor(alert.alertLevel)}`}>
                          {alert.alertLevelLabel}
                        </span>
                      </div>
                      <p className="text-sm font-semibold text-gray-900">{alert.userName}</p>
                      <p className="text-sm font-medium text-gray-800 mt-1">{alert.title}</p>
                      {expandedAlert === String(alert.id) && (
                        <p className="text-sm text-gray-600 mt-2">{alert.message}</p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center space-x-4 ml-4">
                    <span className="text-lg font-bold text-gray-900">{alert.value}時間</span>
                    {expandedAlert === String(alert.id) ? (
                      <ChevronUp className="h-5 w-5 text-gray-400" />
                    ) : (
                      <ChevronDown className="h-5 w-5 text-gray-400" />
                    )}
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}

AlertsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default AlertsPage;
