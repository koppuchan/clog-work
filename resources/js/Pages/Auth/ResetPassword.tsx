import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

interface ResetPasswordProps {
  token: string;
  email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
  const { data, setData, post, processing, errors } = useForm({
    token: token,
    email: email,
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/admin/reset-password', {
      onFinish: () => {
        setData('password', '');
        setData('password_confirmation', '');
      },
    });
  };

  return (
    <>
      <Head title="パスワード再設定" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">パスワード再設定</p>
          </div>

          <p className="mb-6 text-sm text-gray-600">
            新しいパスワードを入力してください。
          </p>

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                メールアドレス
              </label>
              <input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50"
                required
                readOnly
              />
              {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                新しいパスワード
              </label>
              <input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                autoComplete="new-password"
                required
                autoFocus
              />
              {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
            </div>

            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">
                新しいパスワード（確認）
              </label>
              <input
                id="password_confirmation"
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                autoComplete="new-password"
                required
              />
              {errors.password_confirmation && <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>}
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? '設定中...' : 'パスワードを再設定する'}
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
