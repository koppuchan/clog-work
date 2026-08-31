
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLocation } from 'react-router-dom';
import {
  Clock,
  Calendar,
  BarChart3,
  Menu,
  X,
  LogOut,
  ChevronLeft,
  ChevronRight
} from 'lucide-react';
import { mockUsers } from '@/lib/mockData';

interface StaffLayoutProps {
  children: React.ReactNode;
}

export default function StaffLayout({ children }: StaffLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const location = useLocation();

  // 仮のログインユーザー（後で認証機能を追加する際に置き換え）
  const currentUser = mockUsers[0];

  const navigation = [
    { name: '打刻', href: '/staff', icon: Clock },
    { name: 'シフト確認', href: '/staff/shifts', icon: Calendar },
    { name: '勤務実績', href: '/staff/reports', icon: BarChart3 },
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
            <div className="px-4 py-3 mx-2">
              <h1 className="text-xl font-bold text-gray-900">{currentUser.name}さん</h1>
              <p className="text-sm text-gray-700 mt-1">{currentUser.role}</p>
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
                        ? 'bg-green-600 text-white'
                        : 'text-gray-800 hover:bg-green-50 hover:text-green-900'
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
          <div className="flex-shrink-0 flex border-t border-gray-200 p-4">
            <button className="flex items-center text-gray-700 hover:text-gray-900">
              <LogOut className="mr-3 h-5 w-5" />
              ログアウト
            </button>
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
                <div className="px-3 py-2">
                  <h1 className="text-xl font-bold text-gray-900">{currentUser.name}さん</h1>
                  <p className="text-sm text-gray-700 mt-1">{currentUser.role}</p>
                </div>
              )}
              <button
                onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                className="p-1.5 rounded-md hover:bg-green-50 transition-colors ml-2"
                title={sidebarCollapsed ? 'サイドバーを展開' : 'サイドバーを折りたたむ'}
              >
                {sidebarCollapsed ? (
                  <ChevronRight className="h-4 w-4 text-gray-700" />
                ) : (
                  <ChevronLeft className="h-4 w-4 text-gray-700" />
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
                        ? 'bg-green-600 text-white'
                        : 'text-gray-800 hover:bg-green-50 hover:text-green-900'
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
          <div className="flex-shrink-0 flex border-t border-gray-200 p-4">
            <button
              className={`flex items-center text-gray-700 hover:text-gray-900 transition-all ${
                sidebarCollapsed ? 'justify-center w-full' : ''
              }`}
              title={sidebarCollapsed ? 'ログアウト' : ''}
            >
              <LogOut className={`h-5 w-5 ${sidebarCollapsed ? '' : 'mr-3'}`} />
              {!sidebarCollapsed && 'ログアウト'}
            </button>
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className={`flex flex-col flex-1 transition-all duration-300 ${
        sidebarCollapsed ? 'md:pl-16' : 'md:pl-64'
      }`}>
        <div className="sticky top-0 z-10 md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-gray-50">
          <button
            className="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-green-600 hover:text-green-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-green-500"
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
