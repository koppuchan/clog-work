import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

interface User {
  id: number;
  name: string;
}

interface Props {
  user: User;
}

export default function PasswordChange({ user }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/staff/password-change');
  };

  return (
    <>
      <Head title="パスワード変更" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">パスワード変更</h1>
            <p className="mt-2 text-center text-sm text-gray-600">
              {user.name} さん、パスワードの変更が必要です。
            </p>
          </div>

          <div className="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <p className="text-sm text-yellow-800">
              セキュリティのため、新しいパスワードを設定してください。
              パスワードは8文字以上で入力してください。
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                新しいパスワード
              </label>
              <input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
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
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                autoComplete="new-password"
                required
              />
              {errors.password_confirmation && (
                <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>
              )}
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? 'パスワード変更中...' : 'パスワードを変更する'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
