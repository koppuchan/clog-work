import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Email() {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/register/email');
  };

  return (
    <>
      <Head title="新規会員登録" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">新規会員登録</p>
          </div>

          <div className="mb-6">
            <p className="text-sm text-gray-600">
              メールアドレスを入力してください。確認メールをお送りします。
            </p>
          </div>

          {errors.token && (
            <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
              <p className="text-sm text-red-600">{errors.token}</p>
            </div>
          )}

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
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="example@example.com"
                required
                autoFocus
              />
              {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? '送信中...' : '確認メールを送信'}
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
