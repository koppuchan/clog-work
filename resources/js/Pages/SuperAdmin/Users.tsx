import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';

interface UserSummary {
  id: number;
  name: string;
  employee_code: string | null;
  email: string | null;
  company_name: string | null;
  company_code: string | null;
  is_owner: boolean;
  is_retired: boolean;
}

interface Props {
  users: UserSummary[];
}

/**
 * スーパー管理画面の全事業所横断スタッフ一覧
 */
export default function SuperAdminUsers({ users }: Props) {
  const [keyword, setKeyword] = useState('');

  const filtered = useMemo(() => {
    const q = keyword.trim().toLowerCase();
    if (!q) return users;

    return users.filter((user) =>
      [user.name, user.employee_code, user.email, user.company_name, user.company_code]
        .some((value) => value?.toLowerCase().includes(q))
    );
  }, [users, keyword]);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">スタッフ一覧</h1>

      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          type="text"
          value={keyword}
          onChange={(e) => setKeyword(e.target.value)}
          placeholder="氏名・個人コード・事業所名で絞り込み"
          className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm"
        />
      </div>

      <div className="bg-white rounded-lg shadow overflow-auto max-h-[70vh]">
        {filtered.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-gray-500">
            該当するスタッフがいません。
          </p>
        ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600 sticky top-0 z-10 shadow-[0_1px_0_0_rgb(229,231,235)]">
              <tr>
                <th className="px-5 py-3 font-medium">事業所</th>
                <th className="px-5 py-3 font-medium">個人コード</th>
                <th className="px-5 py-3 font-medium">氏名</th>
                <th className="px-5 py-3 font-medium">メールアドレス</th>
                <th className="px-5 py-3 font-medium">区分</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {filtered.map((user) => (
                <tr key={user.id} className="hover:bg-gray-50">
                  <td className="px-5 py-3 text-gray-700">
                    {user.company_name ?? '—'}
                    {user.company_code && (
                      <span className="ml-2 font-mono text-xs text-gray-400">{user.company_code}</span>
                    )}
                  </td>
                  <td className="px-5 py-3 font-mono text-gray-700">{user.employee_code ?? '—'}</td>
                  <td className="px-5 py-3 font-medium text-gray-900">{user.name}</td>
                  <td className="px-5 py-3 text-gray-700">{user.email ?? '—'}</td>
                  <td className="px-5 py-3">
                    <div className="flex gap-1">
                      {user.is_owner && (
                        <span className="px-2 py-0.5 text-xs rounded bg-blue-100 text-blue-700">管理者</span>
                      )}
                      {user.is_retired && (
                        <span className="px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-600">退職</span>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <p className="text-xs text-gray-500">{filtered.length} 件を表示中（全 {users.length} 件）</p>
    </div>
  );
}

SuperAdminUsers.layout = (page: React.ReactNode) => <SuperAdminLayout>{page}</SuperAdminLayout>;
