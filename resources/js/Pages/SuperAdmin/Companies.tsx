import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';

interface CompanySummary {
  id: number;
  company_code: string;
  name: string;
  user_count: number;
  owner_name: string | null;
  owner_email: string | null;
  created_at: string | null;
}

interface Props {
  companies: CompanySummary[];
}

/**
 * スーパー管理画面の事業所一覧
 */
export default function SuperAdminCompanies({ companies }: Props) {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">事業所管理</h1>
        <Link
          href="/super-admin/companies/new"
          className="flex items-center gap-2 bg-slate-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-slate-700"
        >
          <Plus className="h-4 w-4" />
          新規事業所
        </Link>
      </div>

      <div className="bg-white rounded-lg shadow overflow-x-auto">
        {companies.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-gray-500">
            事業所がまだ登録されていません。
          </p>
        ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600">
              <tr>
                <th className="px-5 py-3 font-medium">会社コード</th>
                <th className="px-5 py-3 font-medium">事業所名</th>
                <th className="px-5 py-3 font-medium">管理者</th>
                <th className="px-5 py-3 font-medium">メールアドレス</th>
                <th className="px-5 py-3 font-medium text-right">人数</th>
                <th className="px-5 py-3 font-medium">作成日</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {companies.map((company) => (
                <tr key={company.id} className="hover:bg-gray-50">
                  <td className="px-5 py-3 font-mono text-gray-700">{company.company_code}</td>
                  <td className="px-5 py-3 font-medium text-gray-900">{company.name}</td>
                  <td className="px-5 py-3 text-gray-700">{company.owner_name ?? '—'}</td>
                  <td className="px-5 py-3 text-gray-700">{company.owner_email ?? '—'}</td>
                  <td className="px-5 py-3 text-right text-gray-700">{company.user_count}</td>
                  <td className="px-5 py-3 text-gray-500">{company.created_at ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

SuperAdminCompanies.layout = (page: React.ReactNode) => <SuperAdminLayout>{page}</SuperAdminLayout>;
