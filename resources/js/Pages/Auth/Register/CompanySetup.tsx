import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

interface Props {
  token: string;
  email: string;
  userName: string;
}

export default function CompanySetup({ token, email, userName }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    token: token,
    company_name: '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/register/company');
  };

  return (
    <>
      <Head title="会社情報の設定" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">会社情報の設定</p>
          </div>

          {/* ステップ表示 */}
          <div className="mb-8">
            <div className="flex items-center justify-center">
              <div className="flex items-center">
                <div className="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm">
                  1
                </div>
                <span className="ml-2 text-sm text-gray-500">メール確認</span>
              </div>
              <div className="w-12 h-0.5 bg-green-500 mx-2"></div>
              <div className="flex items-center">
                <div className="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm">
                  2
                </div>
                <span className="ml-2 text-sm text-gray-500">ユーザー情報</span>
              </div>
              <div className="w-12 h-0.5 bg-gray-300 mx-2"></div>
              <div className="flex items-center">
                <div className="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm">
                  3
                </div>
                <span className="ml-2 text-sm font-medium text-gray-900">会社情報</span>
              </div>
            </div>
          </div>

          <div className="mb-6 p-3 bg-gray-50 rounded-md space-y-1">
            <p className="text-sm text-gray-600">
              <span className="font-medium">メールアドレス:</span> {email}
            </p>
            <p className="text-sm text-gray-600">
              <span className="font-medium">お名前:</span> {userName}
            </p>
          </div>

          {errors.error && (
            <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
              <p className="text-sm text-red-600">{errors.error}</p>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="company_name" className="block text-sm font-medium text-gray-700">
                会社名 <span className="text-red-500">*</span>
              </label>
              <input
                id="company_name"
                type="text"
                value={data.company_name}
                onChange={(e) => setData('company_name', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="株式会社サンプル"
                required
                autoFocus
              />
              {errors.company_name && <p className="mt-1 text-sm text-red-600">{errors.company_name}</p>}
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
              <h3 className="text-sm font-medium text-blue-800 mb-2">登録完了後について</h3>
              <ul className="text-sm text-blue-700 list-disc list-inside space-y-1">
                <li>管理者としてログインできます</li>
                <li>会社コードが自動発行されます</li>
                <li>スタッフの追加は管理画面から行えます</li>
              </ul>
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? '登録中...' : '登録を完了する'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
