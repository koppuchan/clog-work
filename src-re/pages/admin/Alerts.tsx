
import { useState } from 'react';
import { AlertTriangle, Clock, Calendar, TrendingUp, Filter, ChevronDown, ChevronUp } from 'lucide-react';
import { mockUsers, mockAttendanceRecords } from '@/lib/mockData';
import { format, startOfMonth, endOfMonth, differenceInDays } from 'date-fns';

type AlertType = 'overtime' | 'continuous-work' | 'no-break' | 'late-frequently';
type AlertSeverity = 'medium' | 'low';

interface Alert {
  id: string;
  userId: string;
  userName: string;
  type: AlertType;
  severity: AlertSeverity;
  title: string;
  description: string;
  date: string;
  value?: string;
}

export default function AlertsPage() {
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [filterSeverity, setFilterSeverity] = useState<AlertSeverity | 'all'>('all');
  const [expandedAlert, setExpandedAlert] = useState<string | null>(null);

  // アラート設定（本来は設定ページから取得）
  const NOTIFICATION_TIME = 45; // 通知時間（時間/月）
  const LIMIT_TIME = 80; // 限度時間（時間/月）

  // アラートを生成
  const generateAlerts = (): Alert[] => {
    const alerts: Alert[] = [];
    const monthStart = startOfMonth(selectedDate);
    const monthEnd = endOfMonth(selectedDate);
    const monthStartStr = format(monthStart, 'yyyy-MM-dd');
    const monthEndStr = format(monthEnd, 'yyyy-MM-dd');

    mockUsers.forEach(user => {
      // 月間の勤務実績を取得
      const userAttendances = mockAttendanceRecords.filter(record =>
        record.userId === user.id &&
        record.date >= monthStartStr &&
        record.date <= monthEndStr
      );

      // 残業時間チェック（通知時間と限度時間）
      const totalOvertime = userAttendances.reduce((sum, record) => {
        const overtime = Math.max(0, record.totalHours - 8);
        return sum + overtime;
      }, 0);

      // 限度時間を超えた場合（警告）
      if (totalOvertime >= LIMIT_TIME) {
        alerts.push({
          id: `${user.id}-overtime-limit`,
          userId: user.id,
          userName: user.name,
          type: 'overtime',
          severity: 'medium',
          title: '残業時間が限度時間を超過',
          description: `月間残業時間が${Math.round(totalOvertime)}時間となり、限度時間（${LIMIT_TIME}時間）を超えています。`,
          date: format(selectedDate, 'yyyy-MM'),
          value: `${Math.round(totalOvertime)}時間`
        });
      }
      // 通知時間を超えた場合（注意）
      else if (totalOvertime >= NOTIFICATION_TIME) {
        alerts.push({
          id: `${user.id}-overtime-notification`,
          userId: user.id,
          userName: user.name,
          type: 'overtime',
          severity: 'low',
          title: '残業時間が増加しています',
          description: `月間残業時間が${Math.round(totalOvertime)}時間となり、通知時間（${NOTIFICATION_TIME}時間）を超えています。`,
          date: format(selectedDate, 'yyyy-MM'),
          value: `${Math.round(totalOvertime)}時間`
        });
      }
    });

    return alerts;
  };

  const alerts = generateAlerts();

  // フィルタリング
  const filteredAlerts = alerts.filter(alert => {
    if (filterSeverity !== 'all' && alert.severity !== filterSeverity) return false;
    return true;
  });

  // 重要度ごとのカウント
  const severityCounts = {
    medium: alerts.filter(a => a.severity === 'medium').length,
    low: alerts.filter(a => a.severity === 'low').length,
  };

  const getSeverityColor = (severity: AlertSeverity) => {
    switch (severity) {
      case 'medium':
        return 'bg-red-100 text-red-800 border-red-300';
      case 'low':
        return 'bg-yellow-100 text-yellow-800 border-yellow-300';
    }
  };

  const getSeverityBadgeColor = (severity: AlertSeverity) => {
    switch (severity) {
      case 'medium':
        return 'bg-red-500 text-white';
      case 'low':
        return 'bg-yellow-500 text-white';
    }
  };

  const getSeverityLabel = (severity: AlertSeverity) => {
    switch (severity) {
      case 'medium':
        return '警告';
      case 'low':
        return '注意';
    }
  };

  const getTypeLabel = (type: AlertType) => {
    switch (type) {
      case 'overtime':
        return '長時間労働';
      case 'continuous-work':
        return '連続勤務';
      case 'no-break':
        return '休憩未記録';
      case 'late-frequently':
        return '遅刻多発';
    }
  };

  const getTypeIcon = (type: AlertType) => {
    switch (type) {
      case 'overtime':
        return <Clock className="h-5 w-5" />;
      case 'continuous-work':
        return <Calendar className="h-5 w-5" />;
      case 'no-break':
        return <TrendingUp className="h-5 w-5" />;
      case 'late-frequently':
        return <AlertTriangle className="h-5 w-5" />;
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">労務アラート</h1>
        <select
          value={format(selectedDate, 'yyyy-MM')}
          onChange={(e) => {
            const [year, month] = e.target.value.split('-');
            setSelectedDate(new Date(parseInt(year), parseInt(month) - 1, 1));
          }}
          className="border border-gray-300 rounded-md px-3 py-2 text-sm"
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
              <p className="text-3xl font-bold text-gray-900">{alerts.length}</p>
            </div>
            <AlertTriangle className="h-10 w-10 text-gray-400" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">警告</p>
              <p className="text-3xl font-bold text-red-600">{severityCounts.medium}</p>
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
              <p className="text-3xl font-bold text-yellow-600">{severityCounts.low}</p>
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
              onClick={() => setFilterSeverity('all')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filterSeverity === 'all'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              すべて
            </button>
            <button
              onClick={() => setFilterSeverity('medium')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filterSeverity === 'medium'
                  ? 'bg-red-600 text-white'
                  : 'bg-red-100 text-red-700 hover:bg-red-200'
              }`}
            >
              警告
            </button>
            <button
              onClick={() => setFilterSeverity('low')}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filterSeverity === 'low'
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
                  expandedAlert === alert.id ? 'bg-gray-50' : ''
                }`}
                onClick={() => setExpandedAlert(expandedAlert === alert.id ? null : alert.id)}
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-start space-x-4 flex-1">
                    <div className={`p-2 rounded-lg ${getSeverityColor(alert.severity)}`}>
                      {getTypeIcon(alert.type)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center space-x-2 mb-1">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${getSeverityBadgeColor(alert.severity)}`}>
                          {getSeverityLabel(alert.severity)}
                        </span>
                      </div>
                      <p className="text-sm font-semibold text-gray-900">{alert.userName}</p>
                      <p className="text-sm font-medium text-gray-800 mt-1">{alert.title}</p>
                      {expandedAlert === alert.id && (
                        <p className="text-sm text-gray-600 mt-2">{alert.description}</p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center space-x-4 ml-4">
                    {alert.value && (
                      <span className="text-lg font-bold text-gray-900">{alert.value}</span>
                    )}
                    {expandedAlert === alert.id ? (
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
