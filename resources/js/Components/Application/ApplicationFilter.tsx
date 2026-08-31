import { useState } from 'react';
import { Search } from 'lucide-react';
import MonthSelector from '@/Components/MonthSelector';
import { Applicant, StatusOption, ApplicationTypeOption } from './types';

interface ApplicationFilterProps {
    statusOptions: StatusOption[];
    approvers: Applicant[];
    applicationTypes: ApplicationTypeOption[];
    currentStatus: number | null;
    currentName: string | null;
    currentTargetMonth: string | null;
    currentApprover: number | null;
    currentType: string | null;
    onFilterChange: (status: number | null) => void;
    onNameChange: (name: string | null) => void;
    onTargetMonthChange: (month: string | null) => void;
    onApproverChange: (approverId: number | null) => void;
    onTypeChange: (type: string | null) => void;
}

export default function ApplicationFilter({
    statusOptions,
    approvers,
    applicationTypes,
    currentStatus,
    currentName,
    currentTargetMonth,
    currentApprover,
    currentType,
    onFilterChange,
    onNameChange,
    onTargetMonthChange,
    onApproverChange,
    onTypeChange,
}: ApplicationFilterProps) {
    const [nameInput, setNameInput] = useState(currentName ?? '');

    const handleSearch = () => {
        onNameChange(nameInput.trim() || null);
    };

    const handleTargetMonthChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const value = e.target.value;
        onTargetMonthChange(value || null);
    };

    const handleApproverChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const value = e.target.value;
        onApproverChange(value === '' ? null : Number(value));
    };

    const handleTypeChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const value = e.target.value;
        onTypeChange(value === '' ? null : value);
    };

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <div className="space-y-4">
                {/* ステータスフィルター */}
                <div className="flex flex-wrap gap-2">
                    <button
                        onClick={() => onFilterChange(null)}
                        className={`px-4 py-2 rounded-lg font-medium ${
                            currentStatus === null
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        すべて
                    </button>
                    {statusOptions.map(option => (
                        <button
                            key={option.value}
                            onClick={() => onFilterChange(option.value)}
                            className={`px-4 py-2 rounded-lg font-medium ${
                                currentStatus === option.value
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>

                {/* 詳細フィルター */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label htmlFor="name-filter" className="block text-sm font-medium text-gray-700 mb-1">
                            申請者
                        </label>
                        <input
                            id="name-filter"
                            type="text"
                            value={nameInput}
                            onChange={(e) => setNameInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') handleSearch();
                            }}
                            placeholder="名前で検索"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label htmlFor="month-filter" className="block text-sm font-medium text-gray-700 mb-1">
                            対象月
                        </label>
                        <MonthSelector
                            value={currentTargetMonth ?? ''}
                            onChange={handleTargetMonthChange}
                            placeholder="すべて"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label htmlFor="type-filter" className="block text-sm font-medium text-gray-700 mb-1">
                            申請タイプ
                        </label>
                        <select
                            id="type-filter"
                            value={currentType ?? ''}
                            onChange={handleTypeChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">すべて</option>
                            {applicationTypes.map(type => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="approver-filter" className="block text-sm font-medium text-gray-700 mb-1">
                            承認者
                        </label>
                        <select
                            id="approver-filter"
                            value={currentApprover ?? ''}
                            onChange={handleApproverChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">すべて</option>
                            {approvers.map(approver => (
                                <option key={approver.id} value={approver.id}>
                                    {approver.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* 検索ボタン */}
                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={handleSearch}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <Search className="h-4 w-4" />
                        検索
                    </button>
                </div>
            </div>
        </div>
    );
}
