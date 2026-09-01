import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import { CreditCard, CheckCircle, Users, Download } from 'lucide-react';
import InlineFlashMessage from '@/Components/InlineFlashMessage';

function BillingPage() {
  const [selectedPlan, setSelectedPlan] = useState<'free' | 'starter' | 'business' | 'enterprise'>('business');
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'yearly'>('monthly');
  const [showPaymentForm, setShowPaymentForm] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [paymentDetails, setPaymentDetails] = useState({
    cardNumber: '',
    expiryDate: '',
    cvv: '',
    cardholderName: '',
    billingAddress: '',
    postalCode: ''
  });

  const plans = {
    free: {
      name: '無料トライアル',
      monthlyPrice: 0,
      yearlyPrice: 0,
      userLimit: null,
      trialDuration: '1ヶ月間無料',
      features: [
        '1ヶ月間無料でお試し',
        'すべての機能が利用可能',
        'ユーザー数制限なし',
        '期間終了後は有料プランへ移行が必要'
      ]
    },
    starter: {
      name: 'スターター',
      monthlyPrice: 1980,
      yearlyPrice: 19800,
      userLimit: 10,
      features: [
        '基本的な勤怠打刻',
        'シフト管理',
        '月次レポート',
        'メールサポート'
      ]
    },
    business: {
      name: 'ビジネス',
      monthlyPrice: 4980,
      yearlyPrice: 49800,
      userLimit: 50,
      features: [
        'すべてのスターター機能',
        '申請・承認ワークフロー',
        'PDF出力機能',
        '詳細レポート・分析',
        'API連携',
        '電話サポート'
      ]
    },
    enterprise: {
      name: 'エンタープライズ',
      monthlyPrice: 9980,
      yearlyPrice: 99800,
      userLimit: null,
      features: [
        'すべてのビジネス機能',
        '無制限ユーザー',
        'カスタム機能開発',
        '専任サポート',
        'オンプレミス対応',
        'SSO連携'
      ]
    }
  };

  const currentPlan = plans[selectedPlan];
  const currentPrice = billingCycle === 'monthly' ? currentPlan.monthlyPrice : currentPlan.yearlyPrice;
  const yearlyDiscount = billingCycle === 'yearly' ? Math.round((1 - currentPlan.yearlyPrice / (currentPlan.monthlyPrice * 12)) * 100) : 0;

  const handlePayment = (e: React.FormEvent) => {
    e.preventDefault();
    console.log('決済処理:', {
      plan: selectedPlan,
      cycle: billingCycle,
      amount: currentPrice,
      paymentDetails
    });
    setSuccessMessage('決済処理が完了しました（デモ）');
    setTimeout(() => setSuccessMessage(null), 5000);
    setShowPaymentForm(false);
  };

  const mockInvoices = [
    {
      id: 'INV-2024-001',
      date: '2024-09-01',
      amount: 4980,
      status: 'paid',
      plan: 'ビジネス'
    },
    {
      id: 'INV-2024-002',
      date: '2024-08-01',
      amount: 4980,
      status: 'paid',
      plan: 'ビジネス'
    },
    {
      id: 'INV-2024-003',
      date: '2024-07-01',
      amount: 4980,
      status: 'paid',
      plan: 'ビジネス'
    }
  ];

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">料金・決済</h1>
      </div>

      {/* 現在のプラン */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-lg font-semibold text-blue-900">現在のプラン: ビジネス</h3>
            <p className="text-blue-700">月額 ¥4,980 / 次回請求日: 2024年11月01日</p>
          </div>
          <div className="flex items-center space-x-2 text-blue-600">
            <CheckCircle className="h-5 w-5" />
            <span className="font-medium">アクティブ</span>
          </div>
        </div>
      </div>

      {/* 請求サイクル選択 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">請求サイクル</h3>
        <div className="flex space-x-4">
          <button
            onClick={() => setBillingCycle('monthly')}
            className={`px-4 py-2 rounded-lg font-medium ${
              billingCycle === 'monthly'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            月額
          </button>
          <button
            onClick={() => setBillingCycle('yearly')}
            className={`px-4 py-2 rounded-lg font-medium relative ${
              billingCycle === 'yearly'
                ? 'bg-blue-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            年額
            <span className="absolute -top-2 -right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full">
              お得
            </span>
          </button>
        </div>
      </div>

      {/* プラン選択 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-6">プラン選択</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {Object.entries(plans).map(([planKey, plan]) => (
            <div
              key={planKey}
              className={`border rounded-lg p-6 cursor-pointer transition-all ${
                selectedPlan === planKey
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              } ${planKey === 'free' ? 'border-green-300 bg-green-50' : ''}`}
              onClick={() => setSelectedPlan(planKey as 'free' | 'starter' | 'business' | 'enterprise')}
            >
              <div className="text-center mb-4">
                <h4 className="text-xl font-bold text-gray-900">{plan.name}</h4>
                {planKey === 'free' ? (
                  <div className="mt-2">
                    <span className="text-3xl font-bold text-green-600">無料</span>
                    <div className="text-sm text-gray-600 mt-1">1ヶ月間</div>
                  </div>
                ) : (
                  <>
                    <div className="mt-2">
                      <span className="text-3xl font-bold text-blue-600">
                        ¥{(billingCycle === 'monthly' ? plan.monthlyPrice : plan.yearlyPrice).toLocaleString()}
                      </span>
                      <span className="text-gray-600">
                        /{billingCycle === 'monthly' ? '月' : '年'}
                      </span>
                    </div>
                    {billingCycle === 'yearly' && (
                      <div className="text-sm text-green-600 font-medium">
                        {yearlyDiscount}% お得！
                      </div>
                    )}
                  </>
                )}
              </div>

              {planKey !== 'free' && (
                <div className="space-y-3 mb-6">
                  <div className="flex items-center space-x-2">
                    <Users className="h-4 w-4 text-gray-500" />
                    <span className="text-sm text-gray-700">
                      {plan.userLimit ? `${plan.userLimit}ユーザーまで` : '無制限ユーザー'}
                    </span>
                  </div>
                </div>
              )}

              <ul className="space-y-2 mb-6">
                {plan.features.map((feature, index) => (
                  <li key={index} className="flex items-center space-x-2">
                    <CheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-sm text-gray-700">{feature}</span>
                  </li>
                ))}
              </ul>

              {selectedPlan === planKey && (
                <div className="flex items-center justify-center text-blue-600">
                  <CheckCircle className="h-5 w-5 mr-2" />
                  <span className="font-medium">選択中</span>
                </div>
              )}
            </div>
          ))}
        </div>

        <div className="mt-6 text-center">
          <button
            onClick={() => setShowPaymentForm(true)}
            className="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold"
          >
            プランを変更する
          </button>
        </div>
      </div>

      {/* 請求履歴 */}
      <div className="bg-white shadow rounded-lg p-6">
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-lg font-semibold text-gray-900">請求履歴</h3>
          <button className="text-blue-600 hover:text-blue-700 flex items-center space-x-1">
            <Download className="h-4 w-4" />
            <span>すべてダウンロード</span>
          </button>
        </div>

        <div className="overflow-auto max-h-[70vh]">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50 sticky top-0 z-10 shadow-[0_1px_0_0_rgb(229,231,235)]">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  請求書番号
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  日付
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  プラン
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  金額
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  ステータス
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  操作
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {mockInvoices.map(invoice => (
                <tr key={invoice.id}>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {invoice.id}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {invoice.date}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {invoice.plan}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ¥{invoice.amount.toLocaleString()}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      <CheckCircle className="h-3 w-3 mr-1" />
                      支払済み
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                    <button className="hover:text-blue-700">ダウンロード</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* 支払い方法 */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">支払い方法</h3>
        <div className="flex items-center space-x-4 p-4 border rounded-lg">
          <CreditCard className="h-8 w-8 text-gray-400" />
          <div>
            <div className="font-medium text-gray-900">Visa **** 1234</div>
            <div className="text-sm text-gray-600">有効期限: 12/26</div>
          </div>
          <div className="ml-auto">
            <button className="text-blue-600 hover:text-blue-700 text-sm">編集</button>
          </div>
        </div>
      </div>

      {/* 決済フォーム */}
      {showPaymentForm && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-lg">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">決済情報</h3>
            <div className="mb-4 p-4 bg-blue-50 rounded-lg">
              <div className="flex justify-between items-center">
                <span className="font-medium">選択プラン: {currentPlan.name}</span>
                <span className="font-bold text-blue-600">
                  ¥{currentPrice.toLocaleString()}/{billingCycle === 'monthly' ? '月' : '年'}
                </span>
              </div>
            </div>

            <form onSubmit={handlePayment} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  カード番号
                </label>
                <input
                  type="text"
                  value={paymentDetails.cardNumber}
                  onChange={(e) => setPaymentDetails({ ...paymentDetails, cardNumber: e.target.value })}
                  placeholder="1234 5678 9012 3456"
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    有効期限
                  </label>
                  <input
                    type="text"
                    value={paymentDetails.expiryDate}
                    onChange={(e) => setPaymentDetails({ ...paymentDetails, expiryDate: e.target.value })}
                    placeholder="MM/YY"
                    className="w-full border border-gray-300 rounded-md p-2"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    CVV
                  </label>
                  <input
                    type="text"
                    value={paymentDetails.cvv}
                    onChange={(e) => setPaymentDetails({ ...paymentDetails, cvv: e.target.value })}
                    placeholder="123"
                    className="w-full border border-gray-300 rounded-md p-2"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  カード名義人
                </label>
                <input
                  type="text"
                  value={paymentDetails.cardholderName}
                  onChange={(e) => setPaymentDetails({ ...paymentDetails, cardholderName: e.target.value })}
                  placeholder="山田太郎"
                  className="w-full border border-gray-300 rounded-md p-2"
                  required
                />
              </div>

              <div className="flex space-x-3 pt-4">
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-md font-medium"
                >
                  ¥{currentPrice.toLocaleString()} を支払う
                </button>
                <button
                  type="button"
                  onClick={() => setShowPaymentForm(false)}
                  className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-3 px-4 rounded-md font-medium"
                >
                  キャンセル
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
BillingPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default BillingPage;
