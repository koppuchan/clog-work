import SettingsCard from './SettingsCard';

interface AccountInfoCardProps {
  userName: string;
  email: string | null;
  companyCode: string;
  companyName: string;
}

/**
 * ログイン中の管理者と所属事業所の情報を表示する
 *
 * 会社コードと個人コードはログインに使うため、忘れたときに
 * 確認できる場所が必要になる。
 */
export default function AccountInfoCard({
  userName,
  email,
  companyCode,
  companyName,
}: AccountInfoCardProps) {
  return (
    <SettingsCard title="アカウント情報">
      <div className="space-y-3 text-sm">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <p className="text-gray-500">ログインユーザー</p>
            <p className="font-medium text-gray-900">{userName}</p>
          </div>
          <div>
            <p className="text-gray-500">メールアドレス</p>
            <p className="font-medium text-gray-900">{email ?? '未設定'}</p>
          </div>
          <div>
            <p className="text-gray-500">会社コード</p>
            <p className="font-mono text-gray-900">{companyCode}</p>
          </div>
          <div>
            <p className="text-gray-500">会社名</p>
            <p className="font-medium text-gray-900">{companyName}</p>
          </div>
        </div>

        <div className="pt-2 border-t border-gray-100 flex flex-col sm:flex-row gap-2">
          <a
            href="/admin/forgot-password"
            className="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium"
          >
            パスワード再設定（メールで案内）
          </a>
        </div>

        <p className="text-xs text-gray-500">
          パスワードを忘れた場合や、他の管理者から強制リセットを依頼する場合に使用してください。
        </p>
      </div>
    </SettingsCard>
  );
}
