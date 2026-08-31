import React from 'react';
import SimpleAdminLayout from '@/Layouts/SimpleAdminLayout';

export default function TestPage() {
  console.log('TestPage rendering');

  return (
    <SimpleAdminLayout>
      <div className="p-8 bg-gray-100">
        <h1 className="text-3xl font-bold text-red-600">テストページ</h1>
        <p className="mt-4 text-lg">このページが表示されていれば、Inertiaは正しく動作しています。</p>
        <div className="mt-4 p-4 bg-white rounded shadow">
          <p className="text-green-600 font-bold">SimpleAdminLayout で表示テスト</p>
        </div>
      </div>
    </SimpleAdminLayout>
  );
}
