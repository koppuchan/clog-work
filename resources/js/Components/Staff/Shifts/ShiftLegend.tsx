import React from 'react';
import type { ShiftPattern } from '@/types/staff/shifts';

interface ShiftLegendProps {
  shiftPatterns: ShiftPattern[];
}

export function ShiftLegend({ shiftPatterns }: ShiftLegendProps) {
  if (shiftPatterns.length === 0) {
    return null;
  }

  // 重複を除去
  const uniquePatterns = shiftPatterns.filter(
    (pattern, index, self) => index === self.findIndex((p) => p.id === pattern.id)
  );

  return (
    <div className="bg-white shadow rounded-lg p-4">
      <h3 className="text-sm font-semibold text-gray-900 mb-3">シフト凡例</h3>
      <div className="flex flex-wrap gap-4">
        {uniquePatterns.map((pattern) => (
          <div key={pattern.id} className="flex items-center gap-2">
            <span className={`px-3 py-1 text-xs font-medium rounded ${pattern.background_color} ${pattern.text_color}`}>
              {pattern.name}
            </span>
            <span className="text-sm text-gray-600">
              {pattern.start_time}-{pattern.end_time}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
