import { Head, Link } from '@inertiajs/react';

export default function PasswordResetSent() {
  return (
    <>
      <Head title="パスワードリセットメール送信完了" />

      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="max-w-md w-full bg-white rounded-lg shadow-md p-8">
          <div className="mb-8">
            <h1 className="text-2xl font-bold text-center text-gray-900">勤怠管理システム</h1>
            <p className="mt-2 text-center text-sm text-gray-600">パスワードリセットメール送信完了</p>
          </div>

          <div className="text-center">
            <div className="mb-6">
              <svg
                className="mx-auto h-16 w-16 text-green-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                />
              </svg>
            </div>

            <p className="text-gray-600 mb-6">
              パスワードリセットリンクをメールに送信しました。<br />
              メール内のリンクをクリックして、パスワードを再設定してください。
            </p>

            <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
              <p className="text-sm text-yellow-800">
                メールが届かない場合は、迷惑メールフォルダをご確認ください。
                リンクの有効期限は60分です。
              </p>
            </div>
          </div>

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
