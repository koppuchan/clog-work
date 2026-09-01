import { useState } from 'react';
import { Settings, Copy, Check } from 'lucide-react';
import SettingsCard from './SettingsCard';

interface CompanySettingsCardProps {
  companyCode: string;
  publicStampUrl: string;
  companyName: string;
  isStampHidden: boolean;
  onCompanyNameChange: (name: string) => void;
  onStampHiddenChange: (checked: boolean) => void;
}

export default function CompanySettingsCard({
  companyCode,
  publicStampUrl,
  companyName,
  isStampHidden,
  onCompanyNameChange,
  onStampHiddenChange,
}: CompanySettingsCardProps) {
  const [copied, setCopied] = useState(false);

  const handleCopyUrl = async () => {
    try {
      await navigator.clipboard.writeText(publicStampUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // フォールバック: 古いブラウザ向け
      const textArea = document.createElement('textarea');
      textArea.value = publicStampUrl;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <SettingsCard title="会社設定" icon={Settings}>
      <div className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              会社コード
            </label>
            <input
              type="text"
              value={companyCode}
              disabled
              className="w-full border border-gray-300 rounded-md p-2 bg-gray-100 text-gray-600"
            />
            <p className="text-xs text-gray-500 mt-1">
              会社コードは変更できません
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              会社名
            </label>
            <input
              type="text"
              value={companyName}
              onChange={(e) => onCompanyNameChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md p-2"
            />
          </div>
        </div>

        {/* 打刻専用画面URL */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            打刻専用画面URL
          </label>
          <div className="flex items-center gap-2">
            <input
              type="text"
              value={publicStampUrl}
              readOnly
              className="flex-1 border border-gray-300 rounded-md p-2 bg-gray-50 text-gray-600 text-sm"
            />
            <button
              type="button"
              onClick={handleCopyUrl}
              className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                copied
                  ? 'bg-green-100 text-green-700 border border-green-300'
                  : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
              }`}
            >
              {copied ? (
                <>
                  <Check className="h-4 w-4" />
                  コピー済み
                </>
              ) : (
                <>
                  <Copy className="h-4 w-4" />
                  コピー
                </>
              )}
            </button>
          </div>
          <p className="text-xs text-gray-500 mt-1">
            このURLを共有することで、従業員がログインなしで打刻できます。
          </p>
        </div>

        {/* 打刻画面非表示設定 */}
        <div className="pt-4 border-t">
          <label className="flex items-start cursor-pointer">
            <input
              type="checkbox"
              checked={isStampHidden}
              onChange={(e) => onStampHiddenChange(e.target.checked)}
              className="mt-1 mr-3 h-4 w-4"
            />
            <div>
              <span className="text-sm font-medium text-gray-700">スタッフ打刻画面を非表示にする</span>
              <p className="text-xs text-gray-500 mt-1">
                チェックを入れると、全スタッフの打刻画面が非表示になります。部署・スタッフ単位での設定も可能です。
              </p>
            </div>
          </label>
        </div>
      </div>
    </SettingsCard>
  );
}
