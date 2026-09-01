/**
 * 基本情報入力フィールドのプロップス
 */
interface BasicInfoFieldsProps {
  data: {
    name: string;
    name_kana: string;
    employee_code: string;
    email: string;
    stamp_password?: string;
  };
  setData: (key: string, value: any) => void;
  errors: Partial<Record<string, string>>;
  processing: boolean;
  isEditing?: boolean;
}

/**
 * 基本情報入力フィールドコンポーネント
 *
 * スタッフの基本情報を入力するフィールド群：
 * - 氏名（必須）
 * - フリガナ（任意）
 * - 個人コード（6桁、任意 - 未入力時は自動採番）
 * - メールアドレス（任意）
 *
 * バリデーション：
 * - 氏名は必須
 * - 個人コードは6桁の数字のみ（入力した場合）
 */
export default function BasicInfoFields({ data, setData, errors, processing, isEditing = false }: BasicInfoFieldsProps) {
  return (
    <>
      <div>
        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
          氏名 <span className="text-red-500">*</span>
        </label>
        <input
          type="text"
          id="name"
          name="name"
          value={data.name}
          onChange={(e) => setData('name', e.target.value)}
          className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="山田太郎"
          required
          disabled={processing}
        />
        {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
      </div>

      <div>
        <label htmlFor="name_kana" className="block text-sm font-medium text-gray-700 mb-2">
          フリガナ
        </label>
        <input
          type="text"
          id="name_kana"
          name="name_kana"
          value={data.name_kana}
          onChange={(e) => setData('name_kana', e.target.value)}
          className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="ヤマダタロウ"
          disabled={processing}
        />
        {errors.name_kana && <p className="text-red-500 text-sm mt-1">{errors.name_kana}</p>}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label htmlFor="employee_code" className="block text-sm font-medium text-gray-700 mb-2">
            個人コード（半角数字6桁）
          </label>
          <input
            type="text"
            id="employee_code"
            name="employee_code"
            value={data.employee_code}
            onChange={(e) => setData('employee_code', e.target.value)}
            className={`w-full border border-gray-300 rounded-md p-2 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 ${isEditing ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''}`}
            placeholder="自動採番"
            pattern="[0-9]{6}"
            maxLength={6}
            disabled={processing || isEditing}
            readOnly={isEditing}
          />
          <p className="text-xs text-gray-500 mt-1">
            {isEditing ? '個人コードは変更できません' : '未入力の場合は自動採番されます'}
          </p>
          {errors.employee_code && <p className="text-red-500 text-sm mt-1">{errors.employee_code}</p>}
        </div>

        <div>
          <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
            メールアドレス
          </label>
          <input
            type="email"
            id="email"
            name="email"
            value={data.email}
            onChange={(e) => setData('email', e.target.value)}
            className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="yamada@company.com"
            disabled={processing}
          />
          {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
        </div>
      </div>

      {isEditing && (
        <div>
          <label htmlFor="stamp_password" className="block text-sm font-medium text-gray-700 mb-2">
            打刻パスワード
          </label>
          <input
            type="text"
            id="stamp_password"
            name="stamp_password"
            value={data.stamp_password || ''}
            onChange={(e) => setData('stamp_password', e.target.value)}
            className="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="変更しない場合は空欄"
            disabled={processing}
          />
          <p className="text-xs text-gray-500 mt-1">
            打刻専用画面で使用するパスワード（初期パスワードは「1111」）です
          </p>
          {errors.stamp_password && <p className="text-red-500 text-sm mt-1">{errors.stamp_password}</p>}
        </div>
      )}
    </>
  );
}
