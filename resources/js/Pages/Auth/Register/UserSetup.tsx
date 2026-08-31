import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

interface Props {
  token: string;
  email: string;
}

export default function UserSetup({ token, email }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    token: token,
    name: '',
    name_kana: '',
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/register/user');
  };

  return (
    <>
      <Head title="ユーザー情報の設定" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">ユーザー情報の設定</p>
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
              <div className="w-12 h-0.5 bg-gray-300 mx-2"></div>
              <div className="flex items-center">
                <div className="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm">
                  2
                </div>
                <span className="ml-2 text-sm font-medium text-gray-900">ユーザー情報</span>
              </div>
              <div className="w-12 h-0.5 bg-gray-300 mx-2"></div>
              <div className="flex items-center">
                <div className="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-white text-sm">
                  3
                </div>
                <span className="ml-2 text-sm text-gray-500">会社情報</span>
              </div>
            </div>
          </div>

          <div className="mb-6 p-3 bg-gray-50 rounded-md">
            <p className="text-sm text-gray-600">
              <span className="font-medium">メールアドレス:</span> {email}
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                お名前 <span className="text-red-500">*</span>
              </label>
              <input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="山田 太郎"
                required
                autoFocus
              />
              {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
            </div>

            <div>
              <label htmlFor="name_kana" className="block text-sm font-medium text-gray-700">
                フリガナ
              </label>
              <input
                id="name_kana"
                type="text"
                value={data.name_kana}
                onChange={(e) => setData('name_kana', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="ヤマダ タロウ"
              />
              {errors.name_kana && <p className="mt-1 text-sm text-red-600">{errors.name_kana}</p>}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                パスワード <span className="text-red-500">*</span>
              </label>
              <input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                autoComplete="new-password"
                required
              />
              <p className="mt-1 text-xs text-gray-500">8文字以上で入力してください</p>
              {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
            </div>

            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">
                パスワード（確認） <span className="text-red-500">*</span>
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
            </div>

            <div>
              <button
                type="submit"
                disabled={processing}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {processing ? '処理中...' : '次へ'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
