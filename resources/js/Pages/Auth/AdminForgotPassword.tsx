import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

interface AdminForgotPasswordProps {
  status?: string;
}

export default function AdminForgotPassword({ status }: AdminForgotPasswordProps) {
  const { data, setData, post, processing, errors } = useForm({
    company_code: '',
    employee_code: '',
    email: '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/admin/forgot-password');
  };

  return (
    <>
      <Head title="パスワードリセット" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">パスワードリセット</p>
          </div>

          <p className="mb-6 text-sm text-gray-600">
            会社コード、個人コード、登録済みのメールアドレスを入力してください。パスワードリセットリンクをメールに送信します。
          </p>

          {status && (
            <div className="mb-4 p-3 rounded-md bg-green-50 border border-green-200">
              <p className="text-sm font-medium text-green-700">{status}</p>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="company_code" className="block text-sm font-medium text-gray-700">
                会社コード
              </label>
              <input
                id="company_code"
                type="text"
                value={data.company_code}
                onChange={(e) => setData('company_code', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                required
                autoFocus
              />
              {errors.company_code && <p className="mt-1 text-sm text-red-600">{errors.company_code}</p>}
            </div>

            <div>
              <label htmlFor="employee_code" className="block text-sm font-medium text-gray-700">
                個人コード
              </label>
              <input
                id="employee_code"
                type="text"
                value={data.employee_code}
                onChange={(e) => setData('employee_code', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                required
              />
              {errors.employee_code && <p className="mt-1 text-sm text-red-600">{errors.employee_code}</p>}
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                メールアドレス
              </label>
              <input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                required
              />
              {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? '送信中...' : 'リセットリンクを送信'}
              </button>
            </div>
          </form>

          <div className="mt-6 text-center">
            <Link
              href="/admin/login"
              className="text-sm text-blue-600 hover:text-blue-500"
            >
              ログイン画面に戻る
            </Link>
          </div>
        </div>
      </div>
    </>
  );
}
