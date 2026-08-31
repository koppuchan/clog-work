import React, { useRef, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Upload, FileText, AlertCircle, CheckCircle, Info, Mail } from 'lucide-react';

/**
 * 部署データの型定義
 */
interface Department {
  id: number;
  name: string;
}

/**
 * 自動採番されたユーザー情報
 */
interface AutoAssignedUser {
  row: number;
  name: string;
  employee_code: string;
}

/**
 * CSVインポートページのプロップス
 */
interface Props {
  departments: Department[];
  importSuccess?: string | null;
  importErrors?: string[];
  importAutoAssigned?: AutoAssignedUser[];
}

/**
 * CSVインポートページコンポーネント
 *
 * CSVファイルからユーザーを一括登録するページ
 * - テンプレートダウンロード
 * - ファイル選択・プレビュー
 * - インポート実行
 * - エラー表示
 */
function UserCsvImportPage({ departments, importSuccess, importErrors, importAutoAssigned }: Props) {
  const { errors } = usePage<{ errors: Record<string, string> }>().props;
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [csvFile, setCsvFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  /**
   * ファイル選択処理
   */
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setCsvFile(file);
      setLocalError(null);
    }
  };

  /**
   * ファイルをクリア
   */
  const clearFile = () => {
    setCsvFile(null);
    setLocalError(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  /**
   * インポート実行
   */
  const handleImport = () => {
    if (!csvFile) return;

    setUploading(true);
    setLocalError(null);
    const formData = new FormData();
    formData.append('csv_file', csvFile);

    router.post('/admin/users/csv-import', formData, {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        setUploading(false);
        setCsvFile(null);
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
      },
    });
  };

  // 単一ファイルエラー（ImportUserCsvRequest由来）
  const fileError = localError || errors.csv_file;
  // 行ごとのエラー（importFromCsv 由来）
  const rowErrors = importErrors ?? [];
  // 自動採番されたユーザー一覧
  const autoAssigned = importAutoAssigned ?? [];

  return (
    <div className="space-y-6">
      {/* ヘッダー */}
      <div className="flex items-center space-x-4">
        <button
          onClick={() => router.visit('/admin/users')}
          className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </button>
        <h1 className="text-2xl font-bold text-gray-900">CSVからユーザーを登録</h1>
      </div>

      {/* 成功メッセージ */}
      {importSuccess && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-start">
            <CheckCircle className="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="text-sm font-medium text-green-800">インポート結果</h3>
              <p className="text-sm text-green-700 mt-1">{importSuccess}</p>
            </div>
          </div>
        </div>
      )}

      {/* ファイル全体のエラー（ファイル未選択・形式不正など） */}
      {fileError && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-start">
            <AlertCircle className="h-5 w-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="text-sm font-medium text-red-800">インポートエラー</h3>
              <p className="text-sm text-red-700 mt-1 whitespace-pre-wrap">{fileError}</p>
            </div>
          </div>
        </div>
      )}

      {/* 行ごとのエラー詳細 */}
      {rowErrors.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-start">
            <AlertCircle className="h-5 w-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h3 className="text-sm font-medium text-red-800">
                登録に失敗した行 ({rowErrors.length}件)
              </h3>
              <ul className="text-sm text-red-700 mt-2 space-y-1 list-disc list-inside">
                {rowErrors.map((msg, i) => (
                  <li key={i}>{msg}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* 自動採番された個人コードの通知 */}
      {autoAssigned.length > 0 && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div className="flex items-start">
            <Info className="h-5 w-5 text-blue-500 mr-3 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h3 className="text-sm font-medium text-blue-800">
                個人コードを自動採番しました ({autoAssigned.length}件)
              </h3>
              <p className="text-xs text-blue-700 mt-1">
                個人コードが空欄だったユーザーには、会社内で未使用の番号を自動的に割り当てました。
              </p>
              <ul className="text-sm text-blue-700 mt-2 space-y-1 list-disc list-inside">
                {autoAssigned.map((user) => (
                  <li key={`${user.row}-${user.employee_code}`}>
                    行{user.row}: {user.name} → <span className="font-mono font-semibold">{user.employee_code}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      <div className="bg-white shadow rounded-lg">
        {/* ステップ1: テンプレートダウンロード */}
        <div className="p-6 border-b">
          <div className="flex items-start space-x-4">
            <div className="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
              <span className="text-blue-600 font-semibold text-sm">1</span>
            </div>
            <div className="flex-1">
              <h2 className="text-lg font-medium text-gray-900 mb-2">テンプレートをダウンロード</h2>
              <p className="text-sm text-gray-600 mb-4">
                CSVテンプレートをダウンロードして、登録するユーザー情報を入力してください。
              </p>
              <a
                href="/admin/users/csv-template"
                className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              >
                <Download className="h-4 w-4 mr-2" />
                CSVテンプレートをダウンロード
              </a>
            </div>
          </div>
        </div>

        {/* ステップ2: ファイル選択 */}
        <div className="p-6 border-b">
          <div className="flex items-start space-x-4">
            <div className="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
              <span className="text-blue-600 font-semibold text-sm">2</span>
            </div>
            <div className="flex-1">
              <h2 className="text-lg font-medium text-gray-900 mb-2">CSVファイルを選択</h2>
              <p className="text-sm text-gray-600 mb-4">
                入力済みのCSVファイルを選択してください。
              </p>

              {/* ファイル選択エリア */}
              {!csvFile ? (
                <div
                  onClick={() => fileInputRef.current?.click()}
                  className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 hover:bg-blue-50 transition-colors cursor-pointer"
                >
                  <Upload className="h-10 w-10 text-gray-400 mx-auto mb-3" />
                  <p className="text-sm text-gray-600 mb-1">クリックしてファイルを選択</p>
                  <p className="text-xs text-gray-500">CSV形式、最大5MBまで</p>
                </div>
              ) : (
                <div className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <div className="p-2 bg-green-100 rounded-lg">
                        <FileText className="h-6 w-6 text-green-600" />
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-900">{csvFile.name}</p>
                        <p className="text-xs text-gray-500">
                          {(csvFile.size / 1024).toFixed(1)} KB
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={clearFile}
                      className="text-sm text-gray-500 hover:text-red-600 transition-colors"
                    >
                      削除
                    </button>
                  </div>
                </div>
              )}

              <input
                ref={fileInputRef}
                type="file"
                accept=".csv,.txt"
                onChange={handleFileChange}
                className="hidden"
              />
            </div>
          </div>
        </div>

        {/* CSVフォーマット説明 */}
        <div className="p-6 border-b bg-gray-50">
          <h3 className="text-sm font-medium text-gray-900 mb-4">CSVファイルの形式</h3>

          {/* 必須項目 */}
          <div className="mb-4">
            <div className="flex items-center mb-2">
              <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                必須
              </span>
              <span className="ml-2 text-sm font-medium text-gray-700">必須項目</span>
            </div>
            <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <tbody className="divide-y divide-gray-200">
                  <tr>
                    <td className="px-4 py-2 text-sm font-medium text-gray-900 w-32">氏名</td>
                    <td className="px-4 py-2 text-sm text-gray-500">ユーザーの氏名</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-2 text-sm font-medium text-gray-900">役割</td>
                    <td className="px-4 py-2 text-sm text-gray-500">
                      <span className="font-medium">1</span>=管理者、
                      <span className="font-medium">2</span>=責任者、
                      <span className="font-medium">3</span>=一般
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          {/* 任意項目 */}
          <div className="mb-4">
            <div className="flex items-center mb-2">
              <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                任意
              </span>
              <span className="ml-2 text-sm font-medium text-gray-700">任意項目</span>
            </div>
            <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <tbody className="divide-y divide-gray-200">
                  <tr>
                    <td className="px-4 py-2 text-sm font-medium text-gray-900 w-32">個人コード</td>
                    <td className="px-4 py-2 text-sm text-gray-500">
                      半角数字6桁。<span className="text-blue-700">空欄の場合は会社内で未使用の番号を自動採番</span>
                    </td>
                  </tr>
                  <tr>
                    <td className="px-4 py-2 text-sm font-medium text-gray-900">氏名（カナ）</td>
                    <td className="px-4 py-2 text-sm text-gray-500">カタカナで入力</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-2 text-sm font-medium text-gray-900">メールアドレス</td>
                    <td className="px-4 py-2 text-sm text-gray-500">有効なメールアドレス形式</td>
                  </tr>
                  <tr>
                    <td className="px-4 py-2 text-sm font-medium text-gray-900">部署</td>
                    <td className="px-4 py-2 text-sm text-gray-500">部署IDを入力（下記参照）</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          {/* 部署ID一覧 */}
          {departments.length > 0 && (
            <div className="mb-4">
              <p className="text-sm font-medium text-gray-700 mb-2">部署ID一覧:</p>
              <div className="flex flex-wrap gap-2">
                {departments.map(dept => (
                  <span key={dept.id} className="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    <span className="font-bold mr-1">{dept.id}</span> = {dept.name}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* 注意事項 */}
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            <p className="text-sm text-yellow-800">
              <span className="font-medium">注意:</span> 文字コードはUTF-8で保存してください。
              各項目は半角カンマ「,」で区切ってください。
            </p>
          </div>
        </div>

        {/* ステップ3: インポート実行 */}
        <div className="p-6">
          <div className="flex items-start space-x-4">
            <div className="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
              <span className="text-blue-600 font-semibold text-sm">3</span>
            </div>
            <div className="flex-1">
              <h2 className="text-lg font-medium text-gray-900 mb-2">インポートを実行</h2>
              <p className="text-sm text-gray-600 mb-4">
                ファイルを確認したら、インポートボタンをクリックしてください。
              </p>

              {/* メール送信に関する重要な注意 */}
              <div className="mb-4 bg-amber-50 border-2 border-amber-400 rounded-lg p-4 shadow-sm">
                <div className="flex items-start">
                  <Mail className="h-6 w-6 text-amber-600 mr-3 flex-shrink-0 mt-0.5" />
                  <div>
                    <h3 className="text-base font-bold text-amber-900">
                      メール送信に関するご注意
                    </h3>
                    <p className="text-sm text-amber-800 mt-1 font-medium">
                      メールアドレスを取り込んだユーザーには、初期パスワード等を記載した案内メールが自動送信されます。
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex space-x-3">
                <button
                  onClick={() => router.visit('/admin/users')}
                  className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors"
                >
                  キャンセル
                </button>
                <button
                  onClick={handleImport}
                  disabled={!csvFile || uploading}
                  className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {uploading ? (
                    <>
                      <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      インポート中...
                    </>
                  ) : (
                    <>
                      <Upload className="h-4 w-4 mr-2" />
                      インポート実行
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

UserCsvImportPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default UserCsvImportPage;
