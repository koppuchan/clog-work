
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Users, Plus, Edit, Trash2, Shield, User, Mail, Building, Search } from 'lucide-react';
import { mockUsers } from '@/lib/mockData';

export default function UsersPage() {
  const navigate = useNavigate();
  const [selectedRole, setSelectedRole] = useState<'all' | 'admin' | 'manager' | 'employee'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [showRetired, setShowRetired] = useState<'active' | 'retired' | 'all'>('active');

  const filteredUsers = mockUsers.filter(user => {
    const matchesRole = selectedRole === 'all' || user.role === selectedRole;
    const matchesSearch = searchQuery === '' ||
      user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (user.nameKana && user.nameKana.toLowerCase().includes(searchQuery.toLowerCase())) ||
      (user.employeeCode && user.employeeCode.includes(searchQuery)) ||
      user.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.department.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesRetirementStatus =
      showRetired === 'all' ||
      (showRetired === 'active' && !user.isRetired) ||
      (showRetired === 'retired' && user.isRetired);

    return matchesRole && matchesSearch && matchesRetirementStatus;
  });

  const getRoleText = (role: string) => {
    switch (role) {
      case 'admin': return '管理者';
      case 'manager': return '責任者';
      case 'employee': return '一般';
      default: return role;
    }
  };

  const getRoleColor = (role: string) => {
    switch (role) {
      case 'admin': return 'bg-red-100 text-red-800';
      case 'manager': return 'bg-blue-100 text-blue-800';
      case 'employee': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const handleDelete = (userId: string) => {
    if (confirm('このユーザーを削除しますか？')) {
      console.log('ユーザー削除:', userId);
      // TODO: API呼び出しを実装
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">ユーザー管理</h1>
        <button
          onClick={() => navigate('/admin/users/new')}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors"
        >
          <Plus className="h-4 w-4" />
          <span>新規ユーザー</span>
        </button>
      </div>

      {/* 統計情報 */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">在籍中</p>
              <p className="text-2xl font-bold text-green-600">
                {mockUsers.filter(u => !u.isRetired).length}
              </p>
            </div>
            <Users className="h-8 w-8 text-green-600" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">管理者</p>
              <p className="text-2xl font-bold text-red-600">
                {mockUsers.filter(u => u.role === 'admin' && !u.isRetired).length}
              </p>
            </div>
            <Shield className="h-8 w-8 text-red-600" />
          </div>
        </div>
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">責任者</p>
              <p className="text-2xl font-bold text-blue-600">
                {mockUsers.filter(u => u.role === 'manager' && !u.isRetired).length}
              </p>
            </div>
            <Shield className="h-8 w-8 text-blue-600" />
          </div>
        </div>
      </div>

      {/* 検索とフィルター */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="space-y-4">
          {/* 検索欄 */}
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <Search className="h-5 w-5 text-gray-400" />
            </div>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900"
              placeholder="名前、フリガナ、個人コード、メールアドレス、部署で検索..."
            />
          </div>

          {/* フィルターボタン */}
          <div className="space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">役割</label>
              <div className="flex space-x-4">
                {[
                  { key: 'all', label: 'すべて' },
                  { key: 'admin', label: '管理者' },
                  { key: 'manager', label: '責任者' },
                  { key: 'employee', label: '一般' }
                ].map(item => (
                  <button
                    key={item.key}
                    onClick={() => setSelectedRole(item.key as 'all' | 'admin' | 'manager' | 'employee')}
                    className={`px-4 py-2 rounded-lg font-medium ${
                      selectedRole === item.key
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">雇用状態</label>
              <div className="flex space-x-4">
                {[
                  { key: 'active', label: '在籍中' },
                  { key: 'retired', label: '退職者' },
                  { key: 'all', label: 'すべて' }
                ].map(item => (
                  <button
                    key={item.key}
                    onClick={() => setShowRetired(item.key as 'active' | 'retired' | 'all')}
                    className={`px-4 py-2 rounded-lg font-medium ${
                      showRetired === item.key
                        ? 'bg-green-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ユーザー一覧 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">ユーザー一覧</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ユーザー
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  フリガナ
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  個人コード
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  メールアドレス
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  権限
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  部署
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  操作
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredUsers.map(user => (
                <tr key={user.id} className={user.isRetired ? 'bg-gray-50 opacity-60' : ''}>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <div className={`${user.isRetired ? 'bg-gray-300' : 'bg-blue-100'} p-2 rounded-full mr-3`}>
                        <User className={`h-5 w-5 ${user.isRetired ? 'text-gray-600' : 'text-blue-600'}`} />
                      </div>
                      <div>
                        <div className="text-sm font-medium text-gray-900">{user.name}</div>
                        {user.isRetired && (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500 text-white mt-1">
                            退職済み
                          </span>
                        )}
                        {user.retirementDate && !user.isRetired && (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                            退職日: {user.retirementDate}
                          </span>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{user.nameKana || '-'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900 font-mono">{user.employeeCode || '-'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center text-sm text-gray-900">
                      <Mail className="h-4 w-4 mr-2 text-gray-400" />
                      {user.email}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getRoleColor(user.role)}`}>
                      <Shield className="h-3 w-3 mr-1" />
                      {getRoleText(user.role)}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center text-sm text-gray-900">
                      <Building className="h-4 w-4 mr-2 text-gray-400" />
                      {user.department}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div className="flex space-x-2">
                      <button
                        onClick={() => navigate(`/admin/users/${user.id}/edit`)}
                        className="text-blue-600 hover:text-blue-900 p-1 transition-colors"
                        title="編集"
                      >
                        <Edit className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => handleDelete(user.id)}
                        className="text-red-600 hover:text-red-900 p-1 transition-colors"
                        title="削除"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
}