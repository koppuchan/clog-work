
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLocation } from 'react-router-dom';
import {
  Calendar,
  FileText,
  BarChart3,
  Users,
  Settings,
  Menu,
  X,
  LogOut,
  ChevronLeft,
  ChevronRight,
  User,
  AlertTriangle
} from 'lucide-react';

interface AdminLayoutProps {
  children: React.ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const location = useLocation();

  // 仮のログインユーザー情報
  const currentUser = {
    name: '山田太郎',
    email: 'yamada@company.com',
    role: '管理者'
  };

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
      {/* Mobile sidebar */}
      <div className={`fixed inset-0 flex z-40 md:hidden ${sidebarOpen ? '' : 'pointer-events-none'}`}>
        <div className={`fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity ease-linear duration-300 ${sidebarOpen ? 'opacity-100' : 'opacity-0'}`} onClick={() => setSidebarOpen(false)} />
        <div className={`relative flex-1 flex flex-col max-w-xs w-full bg-white transform transition ease-in-out duration-300 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
          <div className="absolute top-0 right-0 -mr-12 pt-2">
            <button
              className="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
              onClick={() => setSidebarOpen(false)}
            >
              <X className="h-6 w-6 text-white" />
            </button>
          </div>
          <div className="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
            <div className="px-4">
              <h1 className="text-xl font-bold text-gray-900">管理画面</h1>
            </div>
            <nav className="mt-5 px-2 space-y-1">
              {navigation.map((item) => {
                const isActive = location.pathname === item.href;
                return (
                  <Link
                    key={item.name}
                    to={item.href}
                    className={`group flex items-center px-2 py-2 text-base font-medium rounded-md ${
                      isActive
                        ? 'bg-blue-100 text-blue-900'
                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                    }`}
                    onClick={() => setSidebarOpen(false)}
                  >
                    <item.icon className="mr-4 h-6 w-6" />
                    {item.name}
                  </Link>
                );
              })}
            </nav>
          </div>
          <div className="flex-shrink-0 border-t border-gray-200">
            <div className="p-4 border-b border-gray-200">
              <div className="flex items-center">
                <div className="bg-blue-100 p-2 rounded-full">
                  <User className="h-5 w-5 text-blue-600" />
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-900">{currentUser.name}</p>
                  <p className="text-xs text-gray-500">{currentUser.role}</p>
                </div>
              </div>
            </div>
            <div className="p-4">
              <button className="flex items-center text-gray-600 hover:text-gray-900">
                <LogOut className="mr-3 h-5 w-5" />
                ログアウト
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Static sidebar for desktop */}
      <div className={`hidden md:flex md:flex-col md:fixed md:inset-y-0 transition-all duration-300 ${
        sidebarCollapsed ? 'md:w-16' : 'md:w-64'
      }`}>
        <div className="flex-1 flex flex-col min-h-0 border-r border-gray-200 bg-white">
          <div className="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
            <div className="px-4 flex items-center justify-between">
              {!sidebarCollapsed && (
                <h1 className="text-xl font-bold text-gray-900">管理画面</h1>
              )}
              <button
                onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                className="p-1.5 rounded-md hover:bg-gray-100 transition-colors"
                title={sidebarCollapsed ? 'サイドバーを展開' : 'サイドバーを折りたたむ'}
              >
                {sidebarCollapsed ? (
                  <ChevronRight className="h-4 w-4 text-gray-600" />
                ) : (
                  <ChevronLeft className="h-4 w-4 text-gray-600" />
                )}
              </button>
            </div>
            <nav className="mt-5 flex-1 px-2 bg-white space-y-1">
              {navigation.map((item) => {
                const isActive = location.pathname === item.href;
                return (
                  <Link
                    key={item.name}
                    to={item.href}
                    className={`group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-all ${
                      isActive
                        ? 'bg-blue-100 text-blue-900'
                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                    } ${sidebarCollapsed ? 'justify-center' : ''}`}
                    title={sidebarCollapsed ? item.name : ''}
                  >
                    <item.icon className={`h-5 w-5 ${sidebarCollapsed ? '' : 'mr-3'}`} />
                    {!sidebarCollapsed && (
                      <span className="truncate">{item.name}</span>
                    )}
                  </Link>
                );
              })}
            </nav>
          </div>
          <div className="flex-shrink-0 border-t border-gray-200">
            {!sidebarCollapsed ? (
              <>
                <div className="p-4 border-b border-gray-200">
                  <div className="flex items-center">
                    <div className="bg-blue-100 p-2 rounded-full">
                      <User className="h-5 w-5 text-blue-600" />
                    </div>
                    <div className="ml-3 overflow-hidden">
                      <p className="text-sm font-medium text-gray-900 truncate">{currentUser.name}</p>
                      <p className="text-xs text-gray-500 truncate">{currentUser.role}</p>
                    </div>
                  </div>
                </div>
                <div className="p-4">
                  <button className="flex items-center text-gray-600 hover:text-gray-900">
                    <LogOut className="h-5 w-5 mr-3" />
                    ログアウト
                  </button>
                </div>
              </>
            ) : (
              <div className="p-4">
                <button
                  className="flex items-center justify-center w-full text-gray-600 hover:text-gray-900"
                  title="ログアウト"
                >
                  <LogOut className="h-5 w-5" />
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className={`flex flex-col flex-1 transition-all duration-300 ${
        sidebarCollapsed ? 'md:pl-16' : 'md:pl-64'
      }`}>
        <div className="sticky top-0 z-10 md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-gray-50">
          <button
            className="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
            onClick={() => setSidebarOpen(true)}
          >
            <Menu className="h-6 w-6" />
          </button>
        </div>
        <main className="flex-1">
          <div className="py-6">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
              {children}
            </div>
          </div>
        </main>
      </div>
    </div>
  );
}
