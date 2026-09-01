import { useForm, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';

/**
 * スーパー管理画面の事業所新規作成
 *
 * 事業所と、その管理者アカウントを同時に作成する。
 * 管理者の個人コードは全事業所共通の固定値が自動で割り当てられる。
 */
export default function SuperAdminNewCompany() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    owner_name: '',
    owner_email: '',
    owner_password: '',
  });

  const fields = [
    { key: 'name' as const, label: '事業所名', type: 'text', placeholder: '株式会社サンプル' },
    { key: 'owner_name' as const, label: '管理者名', type: 'text', placeholder: '山田 太郎' },
    { key: 'owner_email' as const, label: '管理者のメールアドレス', type: 'email', placeholder: 'admin@example.com' },
    { key: 'owner_password' as const, label: '初期パスワード', type: 'text', placeholder: '8文字以上' },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link href="/super-admin/companies" className="p-2 hover:bg-gray-200 rounded-lg">
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </Link>
        <h1 className="text-2xl font-bold text-gray-900">新規事業所の作成</h1>
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          post('/super-admin/companies');
        }}
        className="bg-white rounded-lg shadow p-6 space-y-5 max-w-2xl"
      >
        {fields.map(({ key, label, type, placeholder }) => (
          <div key={key}>
            <label htmlFor={key} className="block text-sm font-medium text-gray-700 mb-2">
              {label}
            </label>
            <input
              id={key}
              type={type}
              value={data[key]}
              onChange={(e) => setData(key, e.target.value)}
              placeholder={placeholder}
              className="w-full border border-gray-300 rounded-lg px-3 py-2"
              disabled={processing}
            />
            {errors[key] && <p className="text-xs text-red-600 mt-1">{errors[key]}</p>}
          </div>
        ))}

        <p className="text-xs text-gray-500">
          会社コードと管理者の個人コードは自動で割り当てられます。管理者は初回ログイン時にパスワードの変更を求められます。
        </p>

        <div className="flex justify-end gap-3">
          <Link
            href="/super-admin/companies"
            className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            キャンセル
          </Link>
          <button
            type="submit"
            disabled={processing}
            className="px-4 py-2 text-sm bg-slate-800 text-white rounded-lg hover:bg-slate-700 disabled:opacity-50"
          >
            {processing ? '作成中...' : '作成する'}
          </button>
        </div>
      </form>
    </div>
  );
}

SuperAdminNewCompany.layout = (page: React.ReactNode) => <SuperAdminLayout>{page}</SuperAdminLayout>;
