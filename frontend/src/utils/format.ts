export function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount
    return new Intl.NumberFormat('cs-CZ', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num) + ' Kč'
}

export function formatDate(date: string): string {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(date))
}

export function formatPercent(value: number, total: number): string {
    if (total === 0) return '0%'
    return Math.round((value / total) * 100) + '%'
}
