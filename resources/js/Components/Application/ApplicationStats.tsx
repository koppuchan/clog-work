import { FileText, Clock, Check, X } from 'lucide-react';
import { RequestStats } from './types';

interface ApplicationStatsProps {
    stats: RequestStats;
}

export default function ApplicationStats({ stats }: ApplicationStatsProps) {
    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6">
            <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-600">総申請数</p>
                        <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
                    </div>
                    <FileText className="h-8 w-8 text-blue-600" />
                </div>
            </div>
            <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-600">承認待ち</p>
                        <p className="text-2xl font-bold text-yellow-600">{stats.pending}</p>
                    </div>
                    <Clock className="h-8 w-8 text-yellow-600" />
                </div>
            </div>
            <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-600">承認済み</p>
                        <p className="text-2xl font-bold text-green-600">{stats.approved}</p>
                    </div>
                    <Check className="h-8 w-8 text-green-600" />
                </div>
            </div>
            <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-600">却下</p>
                        <p className="text-2xl font-bold text-red-600">{stats.rejected}</p>
                    </div>
                    <X className="h-8 w-8 text-red-600" />
                </div>
            </div>
        </div>
    );
}
