import React from 'react';
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Users, Plus, Edit, Trash2, Shield, User, Mail, Building, Search, Upload, KeyRound } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import InlineFlashMessage from '@/Components/InlineFlashMessage';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';

/**
 * ユーザーデータの型定義
 */
interface UserData {
  id: number;
  name: string;
  name_kana?: string;
  employee_code?: string;
  email: string;
  department: string;
  role: 'admin' | 'manager' | 'employee';
  is_retired?: boolean;
  retirement_date?: string;
  roles?: Array<{ id: number; name: string }>;
  isLastAdmin?: boolean;
  isOwner?: boolean;
  isSelf?: boolean;
}

/**
 * 部署データの型定義
 */
interface DepartmentData {
  id: number;
  name: string;
}

/**
 * ユーザー一覧ページのプロップス
 */
interface UsersPageProps {
  users: UserData[];
  departments: DepartmentData[];
  canManageUsers: boolean;
  canResetPassword: boolean;
}

/**
 * ユーザー一覧ページコンポーネント
 *
 * 管理者用のユーザー管理画面。以下の機能を提供：
 * - ユーザーの一覧表示
 * - 役割（管理者/責任者/一般）によるフィルタリング
 * - 雇用状態（在籍中/退職者/すべて）によるフィルタリング
 * - 名前、フリガナ、個人コード、メールアドレス、部署での検索
 * - 統計情報の表示（在籍者数、管理者数、責任者数）
 * - ユーザーの編集・削除機能
 */
export default function UsersPage({ users, departments, canManageUsers, canResetPassword }: UsersPageProps) {
  // 役割フィルター
  const [selectedRole, setSelectedRole] = useState<'all' | 'admin' | 'manager' | 'employee'>('all');
  // 検索入力値（入力中の値）
  const [searchInput, setSearchInput] = useState('');
  // 検索クエリ（実際にフィルタリングに使用）
  const [searchQuery, setSearchQuery] = useState('');
  // 雇用状態フィルター
  const [showRetired, setShowRetired] = useState<'active' | 'retired' | 'all'>('active');
  // フラッシュメッセージ
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  // 確認ダイアログ
  const { dialogProps, openDialog } = useConfirmDialog();
  const [dialogProcessing, setDialogProcessing] = useState(false);

  /**
   * ユーザーリストをフィルタリング
   * 役割、検索クエリ、雇用状態の3つの条件でフィルタリングする
   */
  const filteredUsers = users.filter(user => {
    const matchesRole = selectedRole === 'all' || user.role === selectedRole;
    const matchesSearch = searchQuery === '' ||
      user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (user.name_kana && user.name_kana.toLowerCase().includes(searchQuery.toLowerCase())) ||
      (user.employee_code && user.employee_code.includes(searchQuery)) ||
      user.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.department.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesRetirementStatus =
      showRetired === 'all' ||
      (showRetired === 'active' && !user.is_retired) ||
      (showRetired === 'retired' && user.is_retired);

    return matchesRole && matchesSearch && matchesRetirementStatus;
  });

  /**
   * 役割の表示テキストを取得
   */
  const getRoleText = (role: string) => {
    switch (role) {
      case 'admin': return '管理者';
      case 'manager': return '責任者';
      case 'employee': return '一般';
      default: return role;
    }
  };

  /**
   * 役割バッジの色を取得
   */
  const getRoleColor = (role: string) => {
    switch (role) {
      case 'admin': return 'bg-red-100 text-red-800';
      case 'manager': return 'bg-blue-100 text-blue-800';
      case 'employee': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  /**
   * ユーザー削除処理
   * 確認ダイアログを表示してから削除リクエストを送信
   */
  const handleDelete = (user: UserData) => {
    if (user.isSelf) {
      setErrorMessage('自分自身を削除することはできません。');
      setTimeout(() => setErrorMessage(null), 5000);
      return;
    }
    if (user.isOwner) {
      setErrorMessage('最初に作成されたユーザーは削除できません。');
      setTimeout(() => setErrorMessage(null), 5000);
      return;
    }
    if (user.isLastAdmin) {
      setErrorMessage('会社に管理者が1人しかいないため、削除できません。');
      setTimeout(() => setErrorMessage(null), 5000);
      return;
    }
    openDialog({
      title: 'ユーザー削除',
      message: `${user.name} さんを削除しますか？`,
      icon: <Trash2 className="h-6 w-6 text-red-600" />,
      iconBgClass: 'bg-red-100',
      confirmLabel: '削除する',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: () => {
        router.delete(`/admin/users/${user.id}`, {
          onError: () => {
            setErrorMessage('ユーザーの削除に失敗しました');
            setTimeout(() => setErrorMessage(null), 5000);
          }
        });
      },
    });
  };

  /**
   * パスワード初期化確認ダイアログを開く
   */
  const handleResetPassword = (userId: number, userName: string) => {
    openDialog({
      title: 'パスワード初期化',
      message: `${userName} さんのパスワードを初期化しますか？`,
      description: '新しいパスワードが生成され、次回ログイン時にパスワード変更が必要になります。',
      icon: <KeyRound className="h-6 w-6 text-orange-600" />,
      iconBgClass: 'bg-orange-100',
      confirmLabel: '初期化する',
      confirmButtonClass: 'bg-orange-600 hover:bg-orange-700',
      onConfirm: () => {
        setDialogProcessing(true);
        router.post(`/admin/users/${userId}/reset-password`, {}, {
          onSuccess: () => {
            setDialogProcessing(false);
          },
          onError: () => {
            setDialogProcessing(false);
            setErrorMessage('パスワードの初期化に失敗しました');
            setTimeout(() => setErrorMessage(null), 5000);
          }
        });
      },
    });
  };

  return (
    <div className="space-y-6">
      {/* フラッシュメッセージ */}
      <InlineFlashMessage type="error" message={errorMessage} />

      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">ユーザー管理</h1>
        {canManageUsers && (
          <div className="flex space-x-2">
            <Link
              href="/admin/users/csv-import"
              className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors"
            >
              <Upload className="h-4 w-4" />
              <span>CSV登録</span>
            </Link>
            <Link
              href="/admin/users/new"
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors"
            >
              <Plus className="h-4 w-4" />
              <span>新規ユーザー</span>
            </Link>
          </div>
        )}
      </div>

      {/* 統計情報 */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-600">在籍中</p>
              <p className="text-2xl font-bold text-green-600">
                {users.filter(u => !u.is_retired).length}
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
                {users.filter(u => u.role === 'admin' && !u.is_retired).length}
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
                {users.filter(u => u.role === 'manager' && !u.is_retired).length}
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
          <div className="flex gap-2">
            <div className="relative flex-1">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <Search className="h-5 w-5 text-gray-400" />
              </div>
              <input
                type="text"
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    setSearchQuery(searchInput);
                  }
                }}
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900"
                placeholder="名前、フリガナ、個人コード、メールアドレス、部署で検索..."
              />
            </div>
            <button
              type="button"
              onClick={() => setSearchQuery(searchInput)}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              検索
            </button>
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
        <div className="overflow-auto max-h-[70vh]">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50 sticky top-0 z-10 shadow-[0_1px_0_0_rgb(229,231,235)]">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  個人コード
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ユーザー
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
                {canManageUsers && (
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    操作
                  </th>
                )}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredUsers.map(user => (
                <tr key={user.id} className={user.is_retired ? 'bg-gray-50 opacity-60' : ''}>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900 font-mono">{user.employee_code || '-'}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <div className={`${user.is_retired ? 'bg-gray-300' : 'bg-blue-100'} p-2 rounded-full mr-3`}>
                        <User className={`h-5 w-5 ${user.is_retired ? 'text-gray-600' : 'text-blue-600'}`} />
                      </div>
                      <div>
                        <div className="text-sm font-medium text-gray-900">{user.name}</div>
                        {!!user.is_retired && (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500 text-white mt-1">
                            退職済み
                          </span>
                        )}
                        {user.retirement_date && !user.is_retired && (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 mt-1">
                            退職日: {user.retirement_date}
                          </span>
                        )}
                      </div>
                    </div>
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
                  {canManageUsers && (
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <div className="flex space-x-2">
                        <Link
                          href={`/admin/users/${user.id}/edit`}
                          className="text-blue-600 hover:text-blue-900 p-1 transition-colors inline-block"
                          title="編集"
                        >
                          <Edit className="h-4 w-4" />
                        </Link>
                        {canResetPassword && (
                          <button
                            onClick={() => handleResetPassword(user.id, user.name)}
                            className="text-orange-600 hover:text-orange-900 p-1 transition-colors"
                            title="パスワード初期化"
                          >
                            <KeyRound className="h-4 w-4" />
                          </button>
                        )}
                        <button
                          onClick={() => handleDelete(user)}
                          className={`p-1 transition-colors ${
                            user.isSelf || user.isOwner || user.isLastAdmin
                              ? 'text-gray-300 cursor-not-allowed'
                              : 'text-red-600 hover:text-red-900'
                          }`}
                          title={
                            user.isSelf
                              ? '自分自身は削除できません'
                              : user.isOwner
                              ? '最初に作成されたユーザーは削除できません'
                              : user.isLastAdmin
                              ? '会社に管理者が1人しかいないため削除できません'
                              : '削除'
                          }
                          disabled={user.isSelf || user.isOwner || user.isLastAdmin}
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <ConfirmDialog {...dialogProps} processing={dialogProcessing} />
    </div>
  );
}

UsersPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
