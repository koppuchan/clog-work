import { ShiftLegendProps } from '@/types/shift';

/**
 * シフト凡例コンポーネント
 *
 * シフトカレンダーで使用されているシフトタイプの凡例を表示。
 *
 * 表示内容：
 * - 各シフトタイプの色付きボックス
 * - シフトタイプ名（早番、日勤、遅番、夜勤、勤務なしなど）
 * - 各シフトタイプの時間帯
 *
 * レイアウト：
 * - 3列グリッドで表示
 * - シフトタイプごとに色分け
 *
 * 用途：
 * - ユーザーがシフトの色と意味を理解するためのガイド
 * - シフトカレンダーの下部に配置
 */
export default function ShiftLegend({ shiftTypeInfo }: ShiftLegendProps) {
  return (
    <div className="mt-4 bg-gray-50 p-3 rounded-lg">
      <h4 className="text-xs font-semibold text-gray-700 mb-2">勤務形態一覧</h4>
      <div className="grid grid-cols-3 gap-2 text-xs">
        {shiftTypeInfo.map((info, index) => (
          <div key={`${info.type}-${index}`} className="flex items-center space-x-2">
            <div className={`w-4 h-3 rounded ${info.color}`}></div>
            <span className="text-gray-600">{info.name}: {info.timeRange}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
