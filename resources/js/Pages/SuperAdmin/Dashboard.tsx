import { Link } from '@inertiajs/react';
import { Building2, Users, UserMinus } from 'lucide-react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';

interface CompanySummary {
  id: number;
  company_code: string;
  name: string;
  user_count: number;
  owner_name: string | null;
  created_at: string | null;
}

interface Props {
  statistics: {
    company_count: number;
    user_count: number;
    retired_user_count: number;
  };
  companies: CompanySummary[];
}

/**
 * スーパー管理画面のダッシュボード
 *
 * 全事業所を横断した件数と、直近に作成された事業所を表示する。
 */
export default function SuperAdminDashboard({ statistics, companies }: Props) {
  const cards = [
    { label: '事業所数', value: statistics.company_count, icon: Building2 },
    { label: '在籍ユーザー数', value: statistics.user_count, icon: Users },
    { label: '退職済みユーザー数', value: statistics.retired_user_count, icon: UserMinus },
  ];

  const recent = [...companies].slice(-5).reverse();

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">ダッシュボード</h1>

      <div className="grid gap-4 sm:grid-cols-3">
        {cards.map(({ label, value, icon: Icon }) => (
          <div key={label} className="bg-white rounded-lg shadow p-5">
            <div className="flex items-center gap-3">
              <Icon className="h-5 w-5 text-slate-500" />
              <span className="text-sm text-gray-600">{label}</span>
            </div>
            <p className="mt-3 text-3xl font-bold text-gray-900">{value}</p>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-lg shadow">
        <div className="flex items-center justify-between px-5 py-4 border-b">
          <h2 className="font-medium text-gray-900">最近作成された事業所</h2>
          <Link href="/super-admin/companies" className="text-sm text-blue-600 hover:underline">
            すべて表示
          </Link>
        </div>

        {recent.length === 0 ? (
          <p className="px-5 py-8 text-center text-sm text-gray-500">
            事業所がまだ登録されていません。
          </p>
        ) : (
          <ul className="divide-y">
            {recent.map((company) => (
              <li key={company.id} className="px-5 py-3 flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-900">{company.name}</p>
                  <p className="text-xs text-gray-500">
                    会社コード {company.company_code}
                    {company.owner_name && ` ／ 管理者 ${company.owner_name}`}
                  </p>
                </div>
                <span className="text-xs text-gray-500">{company.user_count} 名</span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

SuperAdminDashboard.layout = (page: React.ReactNode) => <SuperAdminLayout>{page}</SuperAdminLayout>;
