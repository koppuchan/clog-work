import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
  const { data, setData, post, processing, errors } = useForm({
    company_code: '',
    employee_code: '',
    password: '',
    remember: false,
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/admin/login');
  };

  return (
    <>
      <Head title="管理者ログイン" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">管理者ログイン</p>
          </div>

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
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                パスワード
              </label>
              <input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                autoComplete="current-password"
                required
              />
              {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
            </div>

            <div className="flex items-center">
              <input
                id="remember"
                type="checkbox"
                checked={data.remember}
                onChange={(e) => setData('remember', e.target.checked)}
                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label htmlFor="remember" className="ml-2 block text-sm text-gray-900">
                ログイン状態を保持する
              </label>
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? 'ログイン中...' : 'ログイン'}
              </button>
            </div>
          </form>

          <div className="mt-6 text-center space-y-2">
            <p className="text-sm">
              <Link
                href="/admin/forgot-password"
                className="text-blue-600 hover:text-blue-500"
              >
                パスワードを忘れた場合
              </Link>
              <span className="mx-2 text-gray-400">|</span>
              <Link
                href="/admin/forgot-code"
                className="text-blue-600 hover:text-blue-500"
              >
                コードを忘れた場合
              </Link>
            </p>
            <p className="text-sm text-gray-600">
              アカウントをお持ちでない方は
              <Link
                href="/register"
                className="ml-1 text-blue-600 hover:text-blue-500"
              >
                新規会員登録
              </Link>
            </p>
          </div>
        </div>
      </div>
    </>
  );
}
