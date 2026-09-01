import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useConfirmDialog } from '@/hooks/useConfirmDialog';

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
  const { dialogProps, openDialog } = useConfirmDialog();
  const [processing, setProcessing] = useState(false);

  /**
   * 事業所の削除確認ダイアログを開く
   *
   * 削除するとシフト・打刻・申請などの関連データがすべて失われ、
   * その事業所にしか所属していないスタッフも削除される。
   * 取り返しがつかないため、影響を明示したうえで確認する。
   */
  const handleDelete = (company: CompanySummary) => {
    openDialog({
      title: '事業所の削除',
      message: `${company.name} を削除しますか？`,
      description:
        `所属する ${company.user_count} 名のスタッフ、シフト、打刻、勤務実績、申請がすべて削除されます。` +
        'この操作は取り消せません。削除後、管理者のメールアドレスは再登録に使用できるようになります。',
      icon: <Trash2 className="h-6 w-6 text-red-600" />,
      iconBgClass: 'bg-red-100',
      confirmLabel: '削除する',
      confirmButtonClass: 'bg-red-600 hover:bg-red-700',
      onConfirm: () => {
        setProcessing(true);
        router.delete(`/super-admin/companies/${company.id}`, {
          onFinish: () => setProcessing(false),
        });
      },
    });
  };

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

      <div className="bg-white rounded-lg shadow overflow-auto max-h-[70vh]">
        {companies.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-gray-500">
            事業所がまだ登録されていません。
          </p>
        ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 text-left text-gray-600 sticky top-0 z-10 shadow-[0_1px_0_0_rgb(229,231,235)]">
              <tr>
                <th className="px-5 py-3 font-medium">会社コード</th>
                <th className="px-5 py-3 font-medium">事業所名</th>
                <th className="px-5 py-3 font-medium">管理者</th>
                <th className="px-5 py-3 font-medium">メールアドレス</th>
                <th className="px-5 py-3 font-medium text-right">人数</th>
                <th className="px-5 py-3 font-medium">作成日</th>
                <th className="px-5 py-3 font-medium text-right">操作</th>
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
                  <td className="px-5 py-3 text-right">
                    <button
                      type="button"
                      onClick={() => handleDelete(company)}
                      disabled={processing}
                      className="inline-flex items-center gap-1 px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50 disabled:opacity-50"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                      削除
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <ConfirmDialog {...dialogProps} processing={processing} />
    </div>
  );
}

SuperAdminCompanies.layout = (page: React.ReactNode) => <SuperAdminLayout>{page}</SuperAdminLayout>;
