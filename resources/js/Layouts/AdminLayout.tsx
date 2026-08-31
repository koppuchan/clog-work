import { useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import {
  Calendar,
  FileText,
  BarChart3,
  Users,
  Settings,
  AlertTriangle,
  LogOut,
  ChevronLeft,
  ChevronRight,
  Menu,
  X
} from 'lucide-react';
import PageFlashMessage from '@/Components/PageFlashMessage';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const { url, props } = usePage();
  const auth = props.auth as { user: { name: string; email: string } | null } | undefined;
  const user = auth?.user;
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [isCollapsed, setIsCollapsed] = useState(() => {
    if (typeof window !== 'undefined') {
      const saved = localStorage.getItem('adminSidebarCollapsed');
      return saved === 'true';
    }
    return false;
  });

  useEffect(() => {
    localStorage.setItem('adminSidebarCollapsed', isCollapsed.toString());
  }, [isCollapsed]);

  const navigation = [
    { name: 'シフト管理', href: '/admin/shifts', icon: Calendar },
    { name: '申請管理', href: '/admin/applications', icon: FileText },
    { name: '勤務実績', href: '/admin/reports', icon: BarChart3 },
    { name: '労務アラート', href: '/admin/alerts', icon: AlertTriangle },
    { name: 'ユーザー管理', href: '/admin/users', icon: Users },
    { name: '設定', href: '/admin/settings', icon: Settings },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* モバイルサイドバーオーバーレイ */}
      {sidebarOpen && (
        <div className="fixed inset-0 flex z-40 md:hidden">
          <div
            className="fixed inset-0 bg-gray-600 bg-opacity-75"
            onClick={() => setSidebarOpen(false)}
          />
          <div className="relative flex-1 flex flex-col max-w-xs w-full bg-white">
            <div className="absolute top-0 right-0 -mr-12 pt-2">
              <button
                className="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                onClick={() => setSidebarOpen(false)}
              >
                <X className="h-6 w-6 text-white" />
              </button>
            </div>
            <div className="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
              <div className="px-4 py-3 mx-2">
                <h1 className="text-xl font-bold text-gray-900">管理画面</h1>
              </div>
              <nav className="mt-5 px-2 space-y-1">
                {navigation.map((item) => {
                  const isActive = url === item.href;
                  const Icon = item.icon;
                  return (
                    <Link
                      key={item.name}
                      href={item.href}
                      className={`group flex items-center px-2 py-2 text-base font-medium rounded-md ${
                        isActive
                          ? 'bg-blue-100 text-blue-900'
                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                      }`}
                      onClick={() => setSidebarOpen(false)}
                    >
                      <Icon className="mr-4 h-6 w-6" />
                      {item.name}
                    </Link>
                  );
                })}
              </nav>
            </div>
            <div className="flex-shrink-0 border-t border-gray-200 p-4">
              <div className="flex items-center mb-3">
                <div className="bg-blue-100 p-2 rounded-full">
                  <span className="text-blue-600 font-semibold text-sm">{user?.name?.charAt(0) ?? '管'}</span>
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-900">{user?.name ?? '管理者'}</p>
                  <p className="text-xs text-gray-500">{user?.email ?? ''}</p>
                </div>
              </div>
              <button
                onClick={() => router.post('/logout')}
                className="flex items-center text-sm text-gray-600 hover:text-gray-900 transition-colors"
              >
                <LogOut className="mr-2 h-4 w-4" />
                ログアウト
              </button>
            </div>
          </div>
        </div>
      )}

      {/* デスクトップサイドバー */}
      <div className={`hidden md:flex md:flex-col md:fixed md:inset-y-0 transition-all duration-300 ${isCollapsed ? 'md:w-20' : 'md:w-64'}`}>
        <div className="flex-1 flex flex-col min-h-0 bg-white border-r border-gray-200">
          {/* ヘッダー */}
          <div className="p-4 border-b border-gray-200 flex items-center justify-between">
            {!isCollapsed && <h1 className="text-xl font-bold text-gray-900">管理画面</h1>}
            <button
              onClick={() => setIsCollapsed(!isCollapsed)}
              className="p-2 rounded-md hover:bg-gray-100 transition-colors"
              title={isCollapsed ? 'サイドバーを開く' : 'サイドバーを閉じる'}
            >
              {isCollapsed ? (
                <ChevronRight className="h-5 w-5 text-gray-600" />
              ) : (
                <ChevronLeft className="h-5 w-5 text-gray-600" />
              )}
            </button>
          </div>

          {/* ナビゲーション */}
          <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
            {navigation.map((item) => {
              const isActive = url === item.href;
              const Icon = item.icon;

              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={`flex items-center ${isCollapsed ? 'justify-center px-2' : 'px-3'} py-2 text-sm font-medium rounded-md transition-colors ${
                    isActive
                      ? 'bg-blue-100 text-blue-900'
                      : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                  }`}
                  title={isCollapsed ? item.name : undefined}
                >
                  <Icon className={`h-5 w-5 ${isCollapsed ? '' : 'mr-3'}`} />
                  {!isCollapsed && item.name}
                </Link>
              );
            })}
          </nav>

          {/* フッター - ユーザー情報 */}
          <div className="p-4 border-t border-gray-200">
            {!isCollapsed ? (
              <>
                <div className="flex items-center mb-3">
                  <div className="bg-blue-100 p-2 rounded-full">
                    <span className="text-blue-600 font-semibold text-sm">{user?.name?.charAt(0) ?? '管'}</span>
                  </div>
                  <div className="ml-3">
                    <p className="text-sm font-medium text-gray-900">{user?.name ?? '管理者'}</p>
                    <p className="text-xs text-gray-500">{user?.email ?? ''}</p>
                  </div>
                </div>
                <button
                  onClick={() => router.post('/logout')}
                  className="flex items-center text-sm text-gray-600 hover:text-gray-900 transition-colors"
                >
                  <LogOut className="mr-2 h-4 w-4" />
                  ログアウト
                </button>
              </>
            ) : (
              <div className="flex flex-col items-center space-y-3">
                <div className="bg-blue-100 p-2 rounded-full" title={user?.name ?? '管理者'}>
                  <span className="text-blue-600 font-semibold text-sm">{user?.name?.charAt(0) ?? '管'}</span>
                </div>
                <button
                  onClick={() => router.post('/logout')}
                  className="p-2 rounded-md hover:bg-gray-100 transition-colors"
                  title="ログアウト"
                >
                  <LogOut className="h-4 w-4 text-gray-600" />
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* メインコンテンツ */}
      <div className={`flex flex-col flex-1 transition-all duration-300 ${isCollapsed ? 'md:ml-20' : 'md:ml-64'}`}>
        {/* モバイルヘッダー */}
        <div className="sticky top-0 z-10 md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-gray-50">
          <button
            className="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-blue-600 hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
            onClick={() => setSidebarOpen(true)}
          >
            <Menu className="h-6 w-6" />
          </button>
        </div>
        <main className="flex-1 p-4 sm:p-6 md:p-8">
          <PageFlashMessage />
          {children}
        </main>
      </div>
    </div>
  );
}
