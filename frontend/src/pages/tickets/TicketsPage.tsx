import {useCallback, useEffect, useState} from 'react'
import {useNavigate} from 'react-router-dom'
import {ticketsApi} from '../../api/tickets'
import type {PaginatedResponse, Ticket} from '../../types'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import Pagination from '../../components/ui/Pagination'
import EmptyState from '../../components/ui/EmptyState'
import CreateTicketModal from './CreateTicketModal'
import {leasesApi} from '../../api/leases'

const statusVariant = (status: string) => {
    switch (status) {
        case 'new':
            return 'pink'
        case 'in_progress':
            return 'yellow'
        case 'resolved':
            return 'green'
        case 'rejected':
            return 'red'
        default:
            return 'gray' as const
    }
}

const statusLabel = (status: string) => {
    switch (status) {
        case 'new':
            return 'New'
        case 'in_progress':
            return 'In Progress'
        case 'resolved':
            return 'Done'
        case 'rejected':
            return 'Rejected'
        default:
            return status
    }
}

const statusOptions = [
    {value: 'new', label: 'New'},
    {value: 'in_progress', label: 'In Progress'},
    {value: 'resolved', label: 'Resolved'},
    {value: 'rejected', label: 'Rejected'}
];

const priorityOptions = [
    {value: 'low', label: 'Low'},
    {value: 'medium', label: 'Medium'},
    {value: 'high', label: 'High'},
    {value: 'urgent', label: 'Urgent'}
];

const priorityLabel = (priority: string) => {
    return priority.charAt(0).toUpperCase() + priority.slice(1)
}

function daysSinceCreated(date: string): number {
    const created = new Date(date)
    const now = new Date()
    return Math.floor((now.getTime() - created.getTime()) / (1000 * 60 * 60 * 24))
}

export default function TicketsPage() {
    const {isLandlord, isTenant, isManager} = useAuth()
    const navigate = useNavigate()
    const [tickets, setTickets] = useState<Ticket[]>([])
    const [meta, setMeta] = useState<PaginatedResponse<Ticket>['meta'] | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [showCreateModal, setShowCreateModal] = useState(false)
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const [priorityFilter, setPriorityFilter] = useState('')
    const [page, setPage] = useState(1)
    const [hasActiveLease, setHasActiveLease] = useState(false)

    useEffect(() => {
        if (isTenant) {
            leasesApi.getAll({status: 'active'}).then((res) => {
                setHasActiveLease((res.data.data || []).length > 0)
            }).catch(() => setHasActiveLease(false))
        }
    }, [isTenant])

    const loadTickets = useCallback(async () => {
        setIsLoading(true)
        try {
            const res = await ticketsApi.getAll({
                search: search || undefined,
                status: statusFilter || undefined,
                priority: priorityFilter || undefined,
                page,
                sort: '-created_at',
            })
            setTickets(res.data.data)
            setMeta(res.data.meta)
        } catch (error) {
            console.error('Failed to load tickets:', error)
        } finally {
            setIsLoading(false)
        }
    }, [search, statusFilter, priorityFilter, page])

    useEffect(() => {
        void loadTickets()
    }, [loadTickets])

    useEffect(() => {
        setPage(1)
    }, [search, statusFilter, priorityFilter])

    return (
        <div>
            {/* Header */}
            <div className="flex items-center justify-between">
                <h1 className="text-2xl sm:text-4xl font-bold text-black">Helpdesk</h1>
                {(isLandlord || isManager) && (
                    <Button onClick={() => setShowCreateModal(true)}>
                        + Add Ticket
                    </Button>
                )}
                {isTenant && hasActiveLease && (
                    <Button onClick={() => setShowCreateModal(true)}>
                        + Add Ticket
                    </Button>
                )}
            </div>

            {/* Table */}
            <div className="bg-white rounded-2xl shadow-sm mt-6">
                <div
                    className="p-4 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <span className="font-semibold text-black">Tickets</span>
                        <span>Total {meta?.total || 0}</span>
                    </div>
                    <div className="flex-1"/>
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search tickets..."
                            className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-full sm:w-48"
                        />
                        <Select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            options={statusOptions}
                            placeholder="All Status"
                            className="w-auto"
                        />
                        <Select
                            value={priorityFilter}
                            onChange={(e) => setPriorityFilter(e.target.value)}
                            options={priorityOptions}
                            placeholder="All Priority"
                            className="w-auto"
                        />
                    </div>
                </div>

                {isLoading ? (
                    <Spinner/>
                ) : tickets.length === 0 ? (
                    <EmptyState
                        title="No tickets found"
                        description={search || statusFilter || priorityFilter ? 'Try adjusting your filters.' : 'No tickets have been created yet.'}
                    />
                ) : (
                    <>
                        {/* ── Mobile Cards ── */}
                        <div className="lg:hidden divide-y divide-gray-50">
                            {tickets.map((ticket) => (
                                <div
                                    key={ticket.id}
                                    className="p-4 hover:bg-gray-50/50 transition"
                                >
                                    <div className="flex items-start justify-between gap-2 mb-2">
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold text-black">{ticket.title}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">{ticket.property?.address}</p>
                                        </div>
                                        <Badge variant={statusVariant(ticket.status)}>
                                            {statusLabel(ticket.status)}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-3 text-xs text-gray-500 mb-3">
                                        <span>{daysSinceCreated(ticket.created_at)}d ago</span>
                                        <span>·</span>
                                        <span>{priorityLabel(ticket.priority)}</span>
                                        <span>·</span>
                                        <div className="flex items-center gap-1">
                                            <div
                                                className="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-semibold text-gray-600">
                                                {ticket.tenant?.name?.charAt(0)?.toUpperCase() || '?'}
                                            </div>
                                            <span>{ticket.tenant?.name || '—'}</span>
                                        </div>
                                    </div>
                                    <Button
                                        variant="success"
                                        size="sm"
                                        onClick={() => navigate('/tickets/' + ticket.id)}
                                    >
                                        View
                                    </Button>
                                </div>
                            ))}
                        </div>

                        {/* ── Desktop Table ── */}
                        <div className="hidden lg:block">
                            <table className="w-full">
                                <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Ticket</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Days</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Priority</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Reporter</th>
                                    <th className="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                {tickets.map((ticket) => (
                                    <tr key={ticket.id}
                                        className="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                        <td className="px-6 py-4">
                                            <div>
                                                <p className="text-sm font-semibold text-black">{ticket.title}</p>
                                                <p className="text-xs text-gray-500">{ticket.property?.address}</p>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-600">
                                            {daysSinceCreated(ticket.created_at)}
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-600">
                                            {priorityLabel(ticket.priority)}
                                        </td>
                                        <td className="px-4 py-4">
                                            <Badge variant={statusVariant(ticket.status)}>
                                                {statusLabel(ticket.status)}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600">
                                                    {ticket.tenant?.name?.charAt(0)?.toUpperCase() || '?'}
                                                </div>
                                                <span
                                                    className="text-sm text-gray-600">{ticket.tenant?.name || '—'}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    variant="success"
                                                    size="sm"
                                                    onClick={() => navigate('/tickets/' + ticket.id)}
                                                >
                                                    View
                                                </Button>
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

            <CreateTicketModal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={() => {
                    setShowCreateModal(false)
                    void loadTickets()
                }}
            />
        </div>
    )
}
