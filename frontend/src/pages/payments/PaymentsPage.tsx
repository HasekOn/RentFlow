import * as React from 'react'
import {useCallback, useEffect, useRef, useState} from 'react'
import {paymentsApi} from '../../api/payments'
import type {PaginatedResponse, Payment} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import Pagination from '../../components/ui/Pagination'
import EmptyState from '../../components/ui/EmptyState'
import CreatePaymentModal from './CreatePaymentModal'
import {useConfirm} from "../../hooks/useConfirm.tsx";

const statusVariant = (status: string) => {
    switch (status) {
        case 'paid':
            return 'green'
        case 'unpaid':
            return 'yellow'
        case 'overdue':
            return 'red'
        default:
            return 'gray' as const
    }
}

export default function PaymentsPage() {
    const {isLandlord} = useAuth()
    const [payments, setPayments] = useState<Payment[]>([])
    const [meta, setMeta] = useState<PaginatedResponse<Payment>['meta'] | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [showCreateModal, setShowCreateModal] = useState(false)
    const [statusFilter, setStatusFilter] = useState('')
    const [typeFilter, setTypeFilter] = useState('')
    const [page, setPage] = useState(1)
    const [isImporting, setIsImporting] = useState(false)
    const [importResult, setImportResult] = useState<{ matched: number; unmatched: number } | null>(null)
    const fileInputRef = useRef<HTMLInputElement>(null)
    const {confirm, dialog} = useConfirm()

    const loadPayments = useCallback(async () => {
        setIsLoading(true)
        try {
            const res = await paymentsApi.getAll({
                status: statusFilter || undefined,
                type: typeFilter || undefined,
                page,
                sort: '-due_date',
            })
            setPayments(res.data.data)
            setMeta(res.data.meta)
        } catch (error) {
            console.error('Failed to load payments:', error)
        } finally {
            setIsLoading(false)
        }
    }, [statusFilter, typeFilter, page])

    useEffect(() => {
        void loadPayments()
    }, [loadPayments])

    useEffect(() => {
        setPage(1)
    }, [statusFilter, typeFilter])

    const handleMarkPaid = async (id: number) => {
        const ok = await confirm({
            title: 'Mark as Paid',
            message: 'Mark this payment as paid with today\'s date?',
            confirmLabel: 'Mark Paid',
            variant: 'primary',
        })

        if (!ok) {
            return
        }

        try {
            await paymentsApi.markPaid(id)
            void loadPayments()
        } catch (error) {
            console.error('Failed to mark payment as paid:', error)
        }
    }

    const handleDelete = async (id: number) => {
        const ok = await confirm({
            title: 'Delete Payment',
            message: 'Are you sure you want to delete this payment?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })

        if (!ok) {
            return
        }
        try {
            await paymentsApi.delete(id)
            void loadPayments()
        } catch (error) {
            console.error('Failed to delete payment:', error)
        }
    }

    const handleImportCsv = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (!file) return

        setIsImporting(true)
        setImportResult(null)

        try {
            const res = await paymentsApi.importCsv(file)
            setImportResult(res.data)
            void loadPayments()
        } catch (error) {
            console.error('CSV import failed:', error)
        } finally {
            setIsImporting(false)
            if (fileInputRef.current) fileInputRef.current.value = ''
        }
    }

    const totalUnpaid = payments.filter((p) => p.status === 'unpaid').length
    const totalOverdue = payments.filter((p) => p.status === 'overdue').length

    return (
        <div>
            {/* Header */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <h1 className="text-2xl sm:text-4xl font-bold text-black">Payments</h1>
                {isLandlord && (
                    <div className="flex items-center gap-2">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv"
                            onChange={handleImportCsv}
                            className="hidden"
                        />
                        <Button
                            variant="secondary"
                            onClick={() => {
                                const BOM = '\uFEFF'
                                const csv = BOM +
                                    'variable_symbol;amount;date;note\r\n' +
                                    '1234567890;15000;2025-03-01;Rent March\r\n' +
                                    '1234567890;2500;2025-03-01;Utilities March\r\n'
                                const blob = new Blob([csv], {type: 'text/csv;charset=utf-8'})
                                const url = window.URL.createObjectURL(blob)
                                const link = document.createElement('a')
                                link.href = url
                                link.download = 'payment_import_template.csv'
                                link.click()
                                window.URL.revokeObjectURL(url)
                            }}
                        >
                            📋 Template
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={isImporting}
                        >
                            {isImporting ? 'Importing...' : '📥 CSV'}
                        </Button>
                        <Button onClick={() => setShowCreateModal(true)}>
                            + Add Payment
                        </Button>
                    </div>
                )}
            </div>

            {/* Import result */}
            {importResult && (
                <div className="mt-4 bg-white rounded-2xl p-4 shadow-sm flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Badge variant="green">{importResult.matched} matched</Badge>
                        {importResult.unmatched > 0 && (
                            <Badge variant="yellow">{importResult.unmatched} unmatched</Badge>
                        )}
                    </div>
                    <button onClick={() => setImportResult(null)} className="text-gray-400 hover:text-black text-sm">
                        Dismiss
                    </button>
                </div>
            )}

            {/* Quick stats */}
            {(totalUnpaid > 0 || totalOverdue > 0) && (
                <div className="flex gap-3 mt-4 flex-wrap">
                    {totalUnpaid > 0 && (
                        <div
                            className="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-2 text-sm text-yellow-700">
                            <span className="font-semibold">{totalUnpaid}</span> unpaid
                        </div>
                    )}
                    {totalOverdue > 0 && (
                        <div className="bg-red-50 border border-red-200 rounded-xl px-4 py-2 text-sm text-red-700">
                            <span className="font-semibold">{totalOverdue}</span> overdue
                        </div>
                    )}
                </div>
            )}

            {/* Table */}
            <div className="bg-white rounded-2xl shadow-sm mt-6">
                <div
                    className="p-4 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <span className="font-semibold text-black">Payments</span>
                        <span>Total {meta?.total || 0}</span>
                    </div>
                    <div className="flex-1"/>
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="px-4 py-2 border border-gray-200 rounded-lg text-sm bg-white"
                        >
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="px-4 py-2 border border-gray-200 rounded-lg text-sm bg-white"
                        >
                            <option value="">All Types</option>
                            <option value="rent">Rent</option>
                            <option value="utilities">Utilities</option>
                            <option value="deposit">Deposit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                {isLoading ? (
                    <Spinner/>
                ) : payments.length === 0 ? (
                    <EmptyState
                        title="No payments found"
                        description={statusFilter || typeFilter ? 'Try adjusting your filters.' : 'No payments have been recorded yet.'}
                    />
                ) : (
                    <>
                        {/* ── Mobile Cards ── */}
                        <div className="lg:hidden divide-y divide-gray-50">
                            {payments.map((payment) => (
                                <div key={payment.id} className="p-4 hover:bg-gray-50/50 transition">
                                    <div className="flex items-start justify-between gap-2 mb-2">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold text-black">
                                                {payment.lease?.tenant?.name || '—'}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                {payment.lease?.property?.address || '—'}
                                            </p>
                                        </div>
                                        <Badge variant={statusVariant(payment.status)}>{payment.status}</Badge>
                                    </div>
                                    <div className="flex items-center gap-3 text-xs text-gray-500 mb-3">
                                        <span className="capitalize">{payment.type}</span>
                                        <span>·</span>
                                        <span>Due {formatDate(payment.due_date)}</span>
                                        {payment.paid_date && (
                                            <>
                                                <span>·</span>
                                                <span>Paid {formatDate(payment.paid_date)}</span>
                                            </>
                                        )}
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span
                                            className="text-sm font-bold text-black">{formatCurrency(payment.amount)}</span>
                                        <div className="flex items-center gap-2">
                                            {isLandlord && payment.status !== 'paid' && (
                                                <Button
                                                    variant="success"
                                                    size="sm"
                                                    onClick={() => handleMarkPaid(payment.id)}
                                                >
                                                    Mark Paid
                                                </Button>
                                            )}
                                            {isLandlord && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => handleDelete(payment.id)}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* ── Desktop Table ── */}
                        <div className="hidden lg:block">
                            <table className="w-full">
                                <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Tenant
                                        / Property
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Type</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Amount</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Due
                                        Date
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Paid
                                        Date
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                                    <th className="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                {payments.map((payment) => (
                                    <tr key={payment.id}
                                        className="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                        <td className="px-6 py-4">
                                            <p className="text-sm font-semibold text-black">
                                                {payment.lease?.tenant?.name || '—'}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                {payment.lease?.property?.address || '—'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <span className="text-sm text-gray-600 capitalize">{payment.type}</span>
                                        </td>
                                        <td className="px-4 py-4">
                                            <span
                                                className="text-sm font-semibold text-black">{formatCurrency(payment.amount)}</span>
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-600">
                                            {formatDate(payment.due_date)}
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-600">
                                            {payment.paid_date ? formatDate(payment.paid_date) : '—'}
                                        </td>
                                        <td className="px-4 py-4">
                                            <Badge variant={statusVariant(payment.status)}>{payment.status}</Badge>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                {isLandlord && payment.status !== 'paid' && (
                                                    <Button
                                                        variant="success"
                                                        size="sm"
                                                        onClick={() => handleMarkPaid(payment.id)}
                                                    >
                                                        Mark Paid
                                                    </Button>
                                                )}
                                                {isLandlord && (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => handleDelete(payment.id)}
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>

                        {meta && (
                            <Pagination
                                currentPage={meta.current_page}
                                lastPage={meta.last_page}
                                total={meta.total}
                                perPage={meta.per_page}
                                onPageChange={setPage}
                            />
                        )}
                    </>
                )}
            </div>

            <CreatePaymentModal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={() => {
                    setShowCreateModal(false)
                    void loadPayments()
                }}
            />
            {dialog}
        </div>
    )
}
