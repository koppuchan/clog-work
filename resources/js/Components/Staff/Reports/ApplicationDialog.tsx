import React, { useState, useMemo } from 'react';
import { format } from 'date-fns';
import { X } from 'lucide-react';

interface ClockErrorData {
  startTime?: string;
  endTime?: string;
  breakStartTime?: string;
  breakEndTime?: string;
}

interface LeaveSettings {
  half_day: boolean;
  hourly: boolean;
}

interface ApplicationDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (type: string, reason: string, clockErrorData?: ClockErrorData) => Promise<void>;
  date: Date | null;
  leaveSettings?: LeaveSettings;
  existingTypes?: string[];
}

const baseApplicationTypes = [
  { value: 'paid-leave', label: '有給' },
  { value: 'hourly-leave', label: '時間有給' },
  { value: 'clock-error', label: '打刻間違い' },
  { value: 'late', label: '遅刻' },
  { value: 'early-leave', label: '早退' },
  { value: 'special-leave', label: '特別休暇' },
  { value: 'absence', label: '欠勤' },
  { value: 'overtime', label: '残業申請' },
  { value: 'other', label: 'その他' },
];

const LEAVE_UNIT_OPTIONS = [
  { value: '1', label: '1日' },
  { value: '0.5', label: '0.5日' },
];

// 開始時刻〜終了時刻の入力が必要な申請タイプ
const TIME_RANGE_TYPES = ['hourly-leave', 'overtime'];

export function ApplicationDialog({ isOpen, onClose, onSubmit, date, leaveSettings, existingTypes = [] }: ApplicationDialogProps) {
  const [applicationType, setApplicationType] = useState('');
  const [reason, setReason] = useState('');
  const [correctClockIn, setCorrectClockIn] = useState('');
  const [correctClockOut, setCorrectClockOut] = useState('');
  const [correctBreakStart, setCorrectBreakStart] = useState('');
  const [correctBreakEnd, setCorrectBreakEnd] = useState('');
  const [leaveUnit, setLeaveUnit] = useState('1');
  const [leaveStartTime, setLeaveStartTime] = useState('');
  const [leaveEndTime, setLeaveEndTime] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const applicationTypes = useMemo(() => {
    return baseApplicationTypes.filter((type) => {
      if (type.value === 'hourly-leave') return leaveSettings?.hourly === true;
      if (existingTypes.includes(type.value)) return false;
      return true;
    });
  }, [leaveSettings, existingTypes]);

  const leaveUnitOptions = useMemo(() => {
    if (leaveSettings?.half_day) {
      return LEAVE_UNIT_OPTIONS;
    }
    return LEAVE_UNIT_OPTIONS.filter((opt) => opt.value === '1');
  }, [leaveSettings]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (applicationType && reason) {
      setIsSubmitting(true);
      setError(null);
      try {
        let clockErrorData: ClockErrorData | undefined;
        let submitType = applicationType;

        if (applicationType === 'clock-error') {
          clockErrorData = {
            startTime: correctClockIn || undefined,
            endTime: correctClockOut || undefined,
            breakStartTime: correctBreakStart || undefined,
            breakEndTime: correctBreakEnd || undefined,
          };
        } else if (applicationType === 'paid-leave' && leaveUnit === '0.5') {
          submitType = 'half-day-leave';
        } else if (TIME_RANGE_TYPES.includes(applicationType)) {
          if (!leaveStartTime || !leaveEndTime) {
            setError('開始時刻と終了時刻を入力してください。');
            setIsSubmitting(false);
            return;
          }
          clockErrorData = {
            startTime: leaveStartTime,
            endTime: leaveEndTime,
          };
        }

        await onSubmit(submitType, reason, clockErrorData);
        resetForm();
        onClose();
      } catch {
        setError('申請の送信に失敗しました。もう一度お試しください。');
      } finally {
        setIsSubmitting(false);
      }
    }
  };

  const resetForm = () => {
    setApplicationType('');
    setReason('');
    setCorrectClockIn('');
    setCorrectClockOut('');
    setCorrectBreakStart('');
    setCorrectBreakEnd('');
    setLeaveUnit('1');
    setLeaveStartTime('');
    setLeaveEndTime('');
  };

  const handleClose = () => {
    resetForm();
    onClose();
  };

  if (!isOpen || !date) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div className="fixed inset-0 bg-gray-500 bg-opacity-50 transition-opacity" onClick={handleClose}></div>

      <div className="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div
          className="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="bg-gradient-to-r from-green-600 to-green-500 px-6 py-4">
            <div className="flex items-center justify-between">
              <h3 className="text-xl font-semibold text-white" id="modal-title">
                {format(date, 'M月d日')}の申請
              </h3>
              <button
                type="button"
                onClick={handleClose}
                className="text-white hover:text-green-100 transition-colors"
              >
                <X className="h-6 w-6" />
              </button>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="bg-white px-6 py-6">
            <div className="space-y-5">
              <div>
                <label htmlFor="application-type" className="block text-sm font-semibold text-gray-900 mb-2">
                  申請種別
                </label>
                <select
                  id="application-type"
                  value={applicationType}
                  onChange={(e) => setApplicationType(e.target.value)}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                  required
                >
                  <option value="">選択してください</option>
                  {applicationTypes.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </div>

              {applicationType === 'paid-leave' && (
                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                  <label htmlFor="leave-unit" className="block text-sm font-semibold text-gray-900 mb-2">
                    取得日数
                  </label>
                  <select
                    id="leave-unit"
                    value={leaveUnit}
                    onChange={(e) => setLeaveUnit(e.target.value)}
                    className="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                  >
                    {leaveUnitOptions.map((opt) => (
                      <option key={opt.value} value={opt.value}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {applicationType === 'clock-error' && (
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-4">
                  <h4 className="text-sm font-semibold text-gray-900">正しい打刻時刻</h4>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="correct-clock-in" className="block text-sm font-medium text-gray-700 mb-1">
                        出勤時刻
                      </label>
                      <input
                        type="time"
                        id="correct-clock-in"
                        value={correctClockIn}
                        onChange={(e) => setCorrectClockIn(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                      />
                    </div>
                    <div>
                      <label htmlFor="correct-clock-out" className="block text-sm font-medium text-gray-700 mb-1">
                        退勤時刻
                      </label>
                      <input
                        type="time"
                        id="correct-clock-out"
                        value={correctClockOut}
                        onChange={(e) => setCorrectClockOut(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="correct-break-start" className="block text-sm font-medium text-gray-700 mb-1">
                        休憩開始時刻
                      </label>
                      <input
                        type="time"
                        id="correct-break-start"
                        value={correctBreakStart}
                        onChange={(e) => setCorrectBreakStart(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                      />
                    </div>
                    <div>
                      <label htmlFor="correct-break-end" className="block text-sm font-medium text-gray-700 mb-1">
                        休憩終了時刻
                      </label>
                      <input
                        type="time"
                        id="correct-break-end"
                        value={correctBreakEnd}
                        onChange={(e) => setCorrectBreakEnd(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                      />
                    </div>
                  </div>

                  <p className="text-xs text-gray-600">
                    ※ 間違えた項目のみ入力してください。変更のない項目は空欄で構いません。
                  </p>
                </div>
              )}

              {TIME_RANGE_TYPES.includes(applicationType) && (
                <div className="bg-green-50 border border-green-200 rounded-lg p-4 space-y-3">
                  <h4 className="text-sm font-semibold text-gray-900">
                    {applicationType === 'overtime' ? '残業時間' : '取得時間'}
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label htmlFor="leave-start" className="block text-sm font-medium text-gray-700 mb-1">
                        開始時刻
                      </label>
                      <input
                        type="time"
                        id="leave-start"
                        value={leaveStartTime}
                        onChange={(e) => setLeaveStartTime(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                        required
                      />
                    </div>
                    <div>
                      <label htmlFor="leave-end" className="block text-sm font-medium text-gray-700 mb-1">
                        終了時刻
                      </label>
                      <input
                        type="time"
                        id="leave-end"
                        value={leaveEndTime}
                        onChange={(e) => setLeaveEndTime(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-900"
                        required
                      />
                    </div>
                  </div>
                </div>
              )}

              <div>
                <label htmlFor="reason" className="block text-sm font-semibold text-gray-900 mb-2">
                  申請理由
                </label>
                <textarea
                  id="reason"
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  rows={4}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none text-gray-900"
                  placeholder="申請理由を入力してください"
                  required
                />
              </div>
            </div>

            {error && (
              <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                {error}
              </div>
            )}

            <div className="mt-6 flex gap-3 sm:flex-row-reverse">
              <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex w-full justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 transition-colors sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isSubmitting ? '送信中...' : '申請する'}
              </button>
              <button
                type="button"
                onClick={handleClose}
                disabled={isSubmitting}
                className="inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors sm:w-auto disabled:opacity-50"
              >
                キャンセル
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
