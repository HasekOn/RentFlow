interface Props {
    currentPage: number
    lastPage: number
    total: number
    perPage: number
    onPageChange: (page: number) => void
}

export default function Pagination({currentPage, lastPage, total, perPage, onPageChange}: Props) {
    if (lastPage <= 1) return null

    const from = (currentPage - 1) * perPage + 1
    const to = Math.min(currentPage * perPage, total)

    return (
        <div className="flex items-center justify-between px-2 py-4">
            <p className="text-sm text-gray-500">
                Showing {from}–{to} of {total}
            </p>
            <div className="flex items-center gap-2">
                <button
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                    className="px-3 py-1.5 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:hover:bg-white transition cursor-pointer"
                >
                    Previous
                </button>
                {Array.from({length: lastPage}, (_, i) => i + 1)
                    .filter((p) => p === 1 || p === lastPage || Math.abs(p - currentPage) <= 1)
                    .map((page, idx, arr) => {
                        const prev = arr[idx - 1]
                        const showDots = prev !== undefined && page - prev > 1
                        return (
                            <span key={page}>
                {showDots && <span className="px-1 text-gray-400">...</span>}
                                <button
                                    onClick={() => onPageChange(page)}
                                    className={`w-8 h-8 text-sm rounded-lg transition cursor-pointer ${
                                        page === currentPage
                                            ? 'bg-black text-white'
                                            : 'hover:bg-gray-50'
                                    }`}
                                >
                  {page}
                </button>
              </span>
                        )
                    })}
                <button
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= lastPage}
                    className="px-3 py-1.5 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:hover:bg-white transition cursor-pointer"
                >
                    Next
                </button>
            </div>
        </div>
    )
}
