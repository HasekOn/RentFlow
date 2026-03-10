import {useCallback, useEffect, useState} from 'react'
import {useNavigate} from 'react-router-dom'
import {leasesApi} from '../../api/leases'
import type {Lease, PaginatedResponse} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import Pagination from '../../components/ui/Pagination'
import EmptyState from '../../components/ui/EmptyState'
import CreateLeaseModal from './CreateLeaseModal'

const statusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'green'
        case 'ended':
            return 'gray'
        case 'terminated':
            return 'red'
        default:
            return 'gray' as const
    }
}

const statusOptions = [
    {value: 'active', label: 'Active'},
    {value: 'ended', label: 'Ended'},
    {value: 'terminated', label: 'Terminated'}
];

function daysUntilEnd(endDate: string | null): number | null {
    if (!endDate) return null
    const end = new Date(endDate)
    const now = new Date()
    return Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
}

function expiresLabel(days: number | null) {
    if (days === null) return {text: '—', color: 'text-gray-400'}
    if (days <= 0) return {text: 'Expired', color: 'text-red-600 font-semibold'}
    if (days <= 30) return {text: `${days}d left`, color: 'text-yellow-600 font-semibold'}
    return {text: `${days}d left`, color: 'text-gray-600'}
}

export default function LeasesPage() {
    const {isLandlord} = useAuth()
    const navigate = useNavigate()
    const [leases, setLeases] = useState<Lease[]>([])
    const [meta, setMeta] = useState<PaginatedResponse<Lease>['meta'] | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [showCreateModal, setShowCreateModal] = useState(false)
    const [statusFilter, setStatusFilter] = useState('')
    const [page, setPage] = useState(1)

    const loadLeases = useCallback(async () => {
        setIsLoading(true)
        try {
            const res = await leasesApi.getAll({
                status: statusFilter || undefined,
                page,
                sort: '-created_at',
            })
            setLeases(res.data.data)
            setMeta(res.data.meta)
        } catch (error) {
            console.error('Failed to load leases:', error)
        } finally {
            setIsLoading(false)
        }
    }, [statusFilter, page])

    useEffect(() => {
        void loadLeases()
    }, [loadLeases])

    useEffect(() => {
        setPage(1)
    }, [statusFilter])

    return (
        <div>
            {/* Header */}
            <div className="flex items-center justify-between">
                <h1 className="text-2xl sm:text-4xl font-bold text-black">Leases</h1>
                {isLandlord && (
                    <Button onClick={() => setShowCreateModal(true)}>
                        + New Lease
                    </Button>
                )}
            </div>

            {/* Table */}
            <div className="bg-white rounded-2xl shadow-sm mt-6">
                <div
                    className="p-4 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <span className="font-semibold text-black">Leases</span>
                        <span>Total {meta?.total || 0}</span>
                    </div>
                    <div className="flex-1"/>
                    <Select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        options={statusOptions}
                        placeholder="All Status"
                        className="sm:w-auto"
                    />
                </div>

                {isLoading ? (
                    <Spinner/>
                ) : leases.length === 0 ? (
                    <EmptyState
                        title="No leases found"
                        description={statusFilter ? 'Try adjusting your filter.' : 'Create your first lease to get started.'}
                        action={isLandlord && !statusFilter ? (
                            <Button onClick={() => setShowCreateModal(true)}>+ New Lease</Button>
                        ) : undefined}
                    />
                ) : (
                    <>
                        {/* ── Mobile Cards ── */}
                        <div className="lg:hidden divide-y divide-gray-50">
                            {leases.map((lease) => {
                                const days = daysUntilEnd(lease.end_date)
                                const expires = expiresLabel(days)

                                return (
                                    <div key={lease.id} className="p-4 hover:bg-gray-50/50 transition">
                                        <div className="flex items-start justify-between gap-2 mb-2">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-semibold text-black">{lease.property?.address || '—'}</p>
                                                <p className="text-xs text-gray-500">{lease.property?.city}</p>
                                            </div>
                                            <Badge variant={statusVariant(lease.status)}>{lease.status}</Badge>
                                        </div>
                                        <div className="flex items-center gap-2 mb-2">
                                            <div
                                                className="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-semibold text-gray-600">
                                                {lease.tenant?.name?.charAt(0)?.toUpperCase() || '?'}
                                            </div>
                                            <span className="text-sm text-gray-600">{lease.tenant?.name || '—'}</span>
                                        </div>
                                        <div className="flex items-center gap-3 text-xs text-gray-500 mb-3">
                                            <span>{formatDate(lease.start_date)} → {lease.end_date ? formatDate(lease.end_date) : 'Indefinite'}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <span
                                                    className="text-sm font-semibold text-black">{formatCurrency(lease.rent_amount)}</span>
                                                <span className={`text-xs ${expires.color}`}>{expires.text}</span>
                                            </div>
                                            <Button
                                                variant="success"
                                                size="sm"
                                                onClick={() => navigate('/leases/' + lease.id)}
                                            >
                                                View
                                            </Button>
                                        </div>
                                    </div>
                                )
                            })}
                        </div>

                        {/* ── Desktop Table ── */}
                        <div className="hidden lg:block">
                            <table className="w-full">
                                <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Property</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Tenant</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Period</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Rent</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Expires</th>
                                    <th className="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                {leases.map((lease) => {
                                    const days = daysUntilEnd(lease.end_date)
                                    const isExpiring = days !== null && days > 0 && days <= 30

                                    return (
                                        <tr key={lease.id}
                                            className="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                            <td className="px-6 py-4">
                                                <p className="text-sm font-semibold text-black">{lease.property?.address || '—'}</p>
                                                <p className="text-xs text-gray-500">{lease.property?.city}</p>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600">
                                                        {lease.tenant?.name?.charAt(0)?.toUpperCase() || '?'}
                                                    </div>
                                                    <span
                                                        className="text-sm text-gray-600">{lease.tenant?.name || '—'}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <p className="text-sm text-gray-600">{formatDate(lease.start_date)}</p>
                                                <p className="text-xs text-gray-400">{lease.end_date ? formatDate(lease.end_date) : 'Indefinite'}</p>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-600">
                                                {formatCurrency(lease.rent_amount)}
                                            </td>
                                            <td className="px-4 py-4">
                                                <Badge variant={statusVariant(lease.status)}>{lease.status}</Badge>
                                            </td>
                                            <td className="px-4 py-4">
                                                {days === null ? (
                                                    <span className="text-sm text-gray-400">—</span>
                                                ) : days <= 0 ? (
                                                    <span className="text-sm text-red-600 font-semibold">Expired</span>
                                                ) : (
                                                    <span
                                                        className={`text-sm font-semibold ${isExpiring ? 'text-yellow-600' : 'text-gray-600'}`}>
                              {days} days
                            </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        variant="success"
                                                        size="sm"
                                                        onClick={() => navigate('/leases/' + lease.id)}
                                                    >
                                                        View
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    )
                                })}
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

            <CreateLeaseModal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={() => {
                    setShowCreateModal(false)
                    void loadLeases()
                }}
            />
        </div>
    )
}
