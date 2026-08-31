
import { useState } from 'react';
import { FileText, Plus, Check, X, Calendar, Clock, User } from 'lucide-react';
import { format } from 'date-fns';
import { mockApplications, mockUsers } from '@/lib/mockData';
import { Application } from '@/types';

export default function ApplicationsPage() {
  const [applications, setApplications] = useState<Application[]>(mockApplications);
  const [filter, setFilter] = useState<'all' | 'pending' | 'approved' | 'rejected'>('all');
  const [showForm, setShowForm] = useState(false);
  const [newApplication, setNewApplication] = useState({
    userId: '',
    type: 'vacation' as 'vacation' | 'sick-leave' | 'overtime' | 'shift-change',
    startDate: '',
    endDate: '',
    reason: ''
  });

  const filteredApplications = applications.filter(app =>
    filter === 'all' || app.status === filter
  );

  const getUserName = (userId: string) => {
    const user = mockUsers.find(u => u.id === userId);
    return user?.name || '不明';
  };

  const getApplicationTypeText = (type: string) => {
    const types = {
      vacation: '有給休暇',
      'sick-leave': '病気休暇',
      overtime: '残業申請',
      'shift-change': 'シフト変更'
    };
    return types[type as keyof typeof types] || type;
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'approved': return 'bg-green-100 text-green-800';
      case 'rejected': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'pending': return '承認待ち';
      case 'approved': return '承認済み';
      case 'rejected': return '却下';
      default: return status;
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    console.log('新しい申請:', newApplication);
    setShowForm(false);
    setNewApplication({
      userId: '',
      type: 'vacation',
      startDate: '',
      endDate: '',
      reason: ''
    });
  };

  const handleApproval = (applicationId: string, approved: boolean) => {
    setApplications(prevApplications =>
      prevApplications.map(app =>
        app.id === applicationId
          ? {
              ...app,
              status: approved ? 'approved' : 'rejected',
              approvedBy: approved ? 'admin-1' : undefined
            }
          : app
      )
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">申請管理</h1>
        <button
          onClick={() => setShowForm(true)}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2"
        >
          <Plus className="h-4 w-4" />
          <span>新規申請</span>
        </button>
      </div>

      {/* 統計情報 */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">総申請数</p>
              <p className="text-2xl font-bold text-gray-900">{applications.length}</p>
            </div>
            <FileText className="h-8 w-8 text-blue-600" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">承認待ち</p>
              <p className="text-2xl font-bold text-yellow-600">
                {applications.filter(app => app.status === 'pending').length}
              </p>
            </div>
            <Clock className="h-8 w-8 text-yellow-600" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">承認済み</p>
              <p className="text-2xl font-bold text-green-600">
                {applications.filter(app => app.status === 'approved').length}
              </p>
            </div>
            <Check className="h-8 w-8 text-green-600" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">却下</p>
              <p className="text-2xl font-bold text-red-600">
                {applications.filter(app => app.status === 'rejected').length}
              </p>
            </div>
            <X className="h-8 w-8 text-red-600" />
          </div>
        </div>
      </div>

      {/* フィルター */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex space-x-4">
          {[
            { key: 'all', label: 'すべて' },
            { key: 'pending', label: '承認待ち' },
            { key: 'approved', label: '承認済み' },
            { key: 'rejected', label: '却下' }
          ].map(item => (
            <button
              key={item.key}
              onClick={() => setFilter(item.key as 'all' | 'pending' | 'approved' | 'rejected')}
              className={`px-4 py-2 rounded-lg font-medium ${
                filter === item.key
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {/* 申請一覧 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">申請一覧</h3>
        <div className="space-y-4">
          {filteredApplications.map(application => (
            <div key={application.id} className="border rounded-lg p-4">
              <div className="flex items-start justify-between">
                <div className="flex items-start space-x-4">
                  <div className="bg-blue-100 p-2 rounded-full">
                    <FileText className="h-5 w-5 text-blue-600" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center space-x-3 mb-2">
                      <h4 className="font-semibold text-gray-900">
                        {getApplicationTypeText(application.type)}
                      </h4>
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(application.status)}`}>
                        {getStatusText(application.status)}
                      </span>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                      <div className="flex items-center space-x-2">
                        <User className="h-4 w-4" />
                        <span>{getUserName(application.userId)}</span>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Calendar className="h-4 w-4" />
                        <span>
                          {application.startDate}
                          {application.startDate !== application.endDate && ` - ${application.endDate}`}
                        </span>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Clock className="h-4 w-4" />
                        <span>申請日: {format(new Date(application.submittedAt), 'yyyy/MM/dd HH:mm')}</span>
                      </div>
                      {application.approvedBy && (
                        <div className="flex items-center space-x-2">
                          <Check className="h-4 w-4" />
                          <span>承認者: {getUserName(application.approvedBy)}</span>
                        </div>
                      )}
                    </div>
                    <div className="mt-3">
                      <p className="text-sm text-gray-700">
                        <span className="font-medium">理由: </span>
                        {application.reason}
                      </p>
                    </div>
                  </div>
                </div>
                {application.status === 'pending' && (
                  <div className="flex space-x-2 ml-4">
                    <button
                      onClick={() => handleApproval(application.id, true)}
                      className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm flex items-center space-x-1"
                    >
                      <Check className="h-3 w-3" />
                      <span>承認</span>
                    </button>
                    <button
                      onClick={() => handleApproval(application.id, false)}
                      className="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm flex items-center space-x-1"
                    >
                      <X className="h-3 w-3" />
                      <span>却下</span>
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 新規申請フォーム */}
      {showForm && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">新規申請作成</h3>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  対象者
                </label>
                <select
                  value={newApplication.userId}
                  onChange={(e) => setNewApplication({ ...newApplication, userId: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                >
                  <option value="">選択してください</option>
                  {mockUsers.map(user => (
                    <option key={user.id} value={user.id}>
                      {user.name} ({user.department})
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  申請種類
                </label>
                <select
                  value={newApplication.type}
                  onChange={(e) => setNewApplication({ ...newApplication, type: e.target.value as 'vacation' | 'sick-leave' | 'overtime' | 'shift-change' })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                >
                  <option value="vacation">有給休暇</option>
                  <option value="sick-leave">病気休暇</option>
                  <option value="overtime">残業申請</option>
                  <option value="shift-change">シフト変更</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  開始日
                </label>
                <input
                  type="date"
                  value={newApplication.startDate}
                  onChange={(e) => setNewApplication({ ...newApplication, startDate: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  終了日
                </label>
                <input
                  type="date"
                  value={newApplication.endDate}
                  onChange={(e) => setNewApplication({ ...newApplication, endDate: e.target.value })}
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  理由
                </label>
                <textarea
                  value={newApplication.reason}
                  onChange={(e) => setNewApplication({ ...newApplication, reason: e.target.value })}
                  rows={3}
                  className="w-full border border-gray-300 rounded-md p-2"
                  placeholder="申請理由を入力してください"
                  required
                />
              </div>
              <div className="flex space-x-3 pt-4">
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md"
                >
                  申請
                </button>
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-md"
                >
                  キャンセル
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}