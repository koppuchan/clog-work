import React from 'react';

interface SimpleAdminLayoutProps {
  children: React.ReactNode;
}

export default function SimpleAdminLayout({ children }: SimpleAdminLayoutProps) {
  console.log('SimpleAdminLayout rendering, children:', children);

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* サイドバー */}
      <div className="w-64 bg-white border-r border-gray-200 p-4 flex-shrink-0">
        <h1 className="text-xl font-bold text-gray-900">管理画面</h1>
        <div className="mt-4 text-sm text-gray-600">サイドバー</div>
      </div>

      {/* メインコンテンツ */}
      <div className="flex-1 overflow-auto">
        <main className="p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
