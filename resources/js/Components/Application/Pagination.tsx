import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface PaginationProps {
    pagination: PaginationData;
    onPageChange: (page: number) => void;
}

export default function Pagination({ pagination, onPageChange }: PaginationProps) {
    const { current_page, last_page, total, from, to } = pagination;

    if (total === 0) {
        return null;
    }

    const getPageNumbers = () => {
        const pages: (number | string)[] = [];
        const maxVisible = 5;

        if (last_page <= maxVisible) {
            for (let i = 1; i <= last_page; i++) {
                pages.push(i);
            }
        } else {
            if (current_page <= 3) {
                for (let i = 1; i <= 4; i++) {
                    pages.push(i);
                }
                pages.push('...');
                pages.push(last_page);
            } else if (current_page >= last_page - 2) {
                pages.push(1);
                pages.push('...');
                for (let i = last_page - 3; i <= last_page; i++) {
                    pages.push(i);
                }
            } else {
                pages.push(1);
                pages.push('...');
                for (let i = current_page - 1; i <= current_page + 1; i++) {
                    pages.push(i);
                }
                pages.push('...');
                pages.push(last_page);
            }
        }

        return pages;
    };

    return (
        <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
            <div className="flex flex-1 justify-between sm:hidden">
                <button
                    onClick={() => onPageChange(current_page - 1)}
                    disabled={current_page === 1}
                    className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    前へ
                </button>
                <button
                    onClick={() => onPageChange(current_page + 1)}
                    disabled={current_page === last_page}
                    className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    次へ
                </button>
            </div>
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-gray-700">
                        <span className="font-medium">{total}</span> 件中{' '}
                        <span className="font-medium">{from}</span> -{' '}
                        <span className="font-medium">{to}</span> 件を表示
                    </p>
                </div>
                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm">
                        <button
                            onClick={() => onPageChange(current_page - 1)}
                            disabled={current_page === 1}
                            className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <ChevronLeft className="h-5 w-5" />
                        </button>
                        {getPageNumbers().map((page, index) =>
                            typeof page === 'number' ? (
                                <button
                                    key={index}
                                    onClick={() => onPageChange(page)}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 ${
                                        page === current_page
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-gray-900 hover:bg-gray-50'
                                    }`}
                                >
                                    {page}
                                </button>
                            ) : (
                                <span
                                    key={index}
                                    className="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300"
                                >
                                    ...
                                </span>
                            )
                        )}
                        <button
                            onClick={() => onPageChange(current_page + 1)}
                            disabled={current_page === last_page}
                            className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <ChevronRight className="h-5 w-5" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    );
}
