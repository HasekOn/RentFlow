import {useEffect, useState} from 'react'
import {useNavigate} from 'react-router-dom'
import {useAuth} from '../../contexts/AuthContext'
import {dashboardApi} from '../../api/dashboard'
import {leasesApi} from '../../api/leases'
import {paymentsApi} from '../../api/payments'
import {ticketsApi} from '../../api/tickets'
import type {DashboardStats, FinanceChartData, Lease, OccupancyChartData, Payment, Property, Ticket} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import StatCard from '../../components/ui/StatCard'
import Badge from '../../components/ui/Badge'
import Spinner from '../../components/ui/Spinner'
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'
import {propertiesApi} from "../../api/properties.ts";

export default function DashboardPage() {
    const {isTenant, isManager} = useAuth()

    if (isTenant) return <TenantDashboard/>
    if (isManager) return <ManagerDashboard/>
    return <LandlordDashboard/>
}

// ─── Tenant Dashboard ──────────────────────────
function TenantDashboard() {
    const {user} = useAuth()
    const navigate = useNavigate()
    const [lease, setLease] = useState<Lease | null>(null)
    const [payments, setPayments] = useState<Payment[]>([])
    const [tickets, setTickets] = useState<Ticket[]>([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        const load = async () => {
            try {
                const [leasesRes, paymentsRes, ticketsRes] = await Promise.all([
                    leasesApi.getAll({status: 'active'}),
                    paymentsApi.getAll({sort: '-due_date'}),
                    ticketsApi.getAll({sort: '-created_at'}),
                ])
                const leases = leasesRes.data.data || []
                setLease(leases[0] || null)
                setPayments((paymentsRes.data.data || []).slice(0, 5))
                setTickets((ticketsRes.data.data || []).slice(0, 5))
            } catch (error) {
                console.error('Failed to load tenant dashboard:', error)
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [])

    if (isLoading) return <Spinner/>

    const unpaidCount = payments.filter((p) => p.status === 'unpaid' || p.status === 'overdue').length
    const openTickets = tickets.filter((t) => t.status === 'new' || t.status === 'in_progress').length

    return (
        <div>
            <h1 className="text-2xl sm:text-4xl font-bold text-black">Overview</h1>
            <p className="mt-2 text-gray-500">Welcome back, {user?.name}</p>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                <StatCard
                    label="My Property"
                    value={lease ? '1' : '0'}
                    subtitle={lease?.property?.address?.split(',')[0] || 'No active lease'}
                />
                <StatCard
                    label="Monthly Rent"
                    value={lease ? formatCurrency(lease.rent_amount) : '—'}
                />
                <StatCard
                    label="Unpaid Payments"
                    value={unpaidCount}
                    accent={unpaidCount > 0 ? 'red' : 'green'}
                />
                <StatCard
                    label="Open Tickets"
                    value={openTickets}
                    accent={openTickets > 0 ? 'yellow' : 'green'}
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                {/* Recent Payments */}
                <div className="bg-white rounded-2xl p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-bold text-black">Recent Payments</h2>
                        <button onClick={() => navigate('/payments')}
                                className="text-sm text-gray-500 hover:text-black transition">
                            View all →
                        </button>
                    </div>
                    {payments.length === 0 ? (
                        <p className="text-sm text-gray-400 text-center py-4">No payments yet</p>
                    ) : (
                        <div className="space-y-2">
                            {payments.map((payment) => (
                                <div key={payment.id}
                                     className="flex items-center justify-between p-3 border border-gray-100 rounded-xl">
                                    <div>
                                        <p className="text-sm font-semibold text-black">{formatCurrency(payment.amount)}</p>
                                        <p className="text-xs text-gray-500">Due {formatDate(payment.due_date)}</p>
                                    </div>
                                    <Badge
                                        variant={payment.status === 'paid' ? 'green' : payment.status === 'overdue' ? 'red' : 'yellow'}>
                                        {payment.status}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Recent Tickets */}
                <div className="bg-white rounded-2xl p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-bold text-black">My Tickets</h2>
                        <button onClick={() => navigate('/tickets')}
                                className="text-sm text-gray-500 hover:text-black transition">
                            View all →
                        </button>
                    </div>
                    {tickets.length === 0 ? (
                        <p className="text-sm text-gray-400 text-center py-4">No tickets yet</p>
                    ) : (
                        <div className="space-y-2">
                            {tickets.map((ticket) => (
                                <div
                                    key={ticket.id}
                                    className="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition"
                                    onClick={() => navigate('/tickets/' + ticket.id)}
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-black truncate">{ticket.title}</p>
                                        <p className="text-xs text-gray-500">{formatDate(ticket.created_at)}</p>
                                    </div>
                                    <Badge variant={
                                        ticket.status === 'new' ? 'pink' :
                                            ticket.status === 'in_progress' ? 'yellow' :
                                                ticket.status === 'resolved' ? 'green' : 'red'
                                    }>
                                        {ticket.status === 'in_progress' ? 'In Progress' : ticket.status}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Lease info */}
            {lease && (
                <div className="bg-white rounded-2xl p-6 shadow-sm mt-6">
                    <h2 className="text-lg font-bold text-black mb-4">My Lease</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <p className="text-xs text-gray-400">Property</p>
                            <p className="text-sm text-gray-700 font-semibold">{lease.property?.address || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-400">Start Date</p>
                            <p className="text-sm text-gray-700 font-semibold">{formatDate(lease.start_date)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-400">End Date</p>
                            <p className="text-sm text-gray-700 font-semibold">{lease.end_date ? formatDate(lease.end_date) : 'Indefinite'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-400">Variable Symbol</p>
                            <p className="text-sm text-gray-700 font-semibold">{lease.variable_symbol || '—'}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Trust Score */}
            {user?.trust_score !== undefined && (
                <div className="bg-white rounded-2xl p-6 shadow-sm mt-6">
                    <h2 className="text-lg font-bold text-black mb-3">My Trust Score</h2>
                    <div className="flex items-center gap-4">
                        <div
                            className="w-16 h-16 rounded-full border-4 border-green-200 flex items-center justify-center">
                            <span className="text-xl font-bold text-black">{user.trust_score}</span>
                        </div>
                        <div className="flex-1">
                            <div className="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all ${
                                        user.trust_score >= 70 ? 'bg-green-500' :
                                            user.trust_score >= 40 ? 'bg-yellow-500' : 'bg-red-500'
                                    }`}
                                    style={{width: `${user.trust_score}%`}}
                                />
                            </div>
                            <p className="text-xs text-gray-500 mt-1">Based on payment history and landlord ratings</p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}

// ─── Manager Dashboard ─────────────────────────
function ManagerDashboard() {
    const {user} = useAuth()
    const [activeTab, setActiveTab] = useState<'manager' | 'tenant'>('manager')

    return (
        <div>
            <h1 className="text-2xl sm:text-4xl font-bold text-black">Overview</h1>
            <p className="mt-2 text-gray-500">Welcome back, {user?.name}</p>

            {/* Tab switch */}
            <div className="flex gap-2 mt-6 mb-6">
                <button
                    onClick={() => setActiveTab('manager')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition ${
                        activeTab === 'manager' ? 'bg-black text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >Manager View
                </button>
                <button
                    onClick={() => setActiveTab('tenant')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition ${
                        activeTab === 'tenant' ? 'bg-black text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >My Tenancy
                </button>
            </div>

            {activeTab === 'manager' ? <ManagerView/> : <TenantView/>}
        </div>
    )
}

function ManagerView() {
    const {user} = useAuth()
    const navigate = useNavigate()
    const [properties, setProperties] = useState<Property[]>([])
    const [tickets, setTickets] = useState<Ticket[]>([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        const load = async () => {
            try {
                const [propsRes, ticketsRes] = await Promise.all([
                    propertiesApi.getAll(),
                    ticketsApi.getAll({sort: '-created_at'}),
                ])
                const allProps = propsRes.data.data || []
                // Filter out properties where user is tenant (has active lease)
                const managed = allProps.filter((p) =>
                    !p.leases?.some((l) => l.status === 'active' && l.tenant?.id === user?.id)
                )
                setProperties(managed)
                setTickets(ticketsRes.data.data || [])
            } catch (error) {
                console.error('Failed to load manager dashboard:', error)
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [user?.id])

    if (isLoading) return <Spinner/>

    const openTickets = tickets.filter((t) => t.status === 'new' || t.status === 'in_progress')
    const occupiedCount = properties.filter((p) => p.status === 'occupied').length

    if (properties.length === 0) return (
        <div className="bg-white rounded-2xl p-8 shadow-sm text-center">
            <span className="text-3xl">📋</span>
            <p className="text-sm text-gray-500 mt-2">No managed properties yet. Ask your landlord to assign you.</p>
        </div>
    )

    return (
        <>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="Managed Properties" value={properties.length}
                          subtitle={`${occupiedCount} occupied`}/>
                <StatCard label="Open Tickets" value={openTickets.length}
                          accent={openTickets.length > 0 ? 'red' : 'green'}/>
                <StatCard label="New Tickets" value={tickets.filter((t) => t.status === 'new').length}
                          accent={tickets.filter((t) => t.status === 'new').length > 0 ? 'yellow' : 'green'}/>
                <StatCard label="Resolved" value={tickets.filter((t) => t.status === 'resolved').length}/>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                {/* Properties overview */}
                <div className="bg-white rounded-2xl p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-bold text-black">Managed Properties</h2>
                        <button onClick={() => navigate('/properties')}
                                className="text-sm text-gray-500 hover:text-black transition">View all →
                        </button>
                    </div>
                    <div className="space-y-3">
                        {properties.slice(0, 5).map((prop) => (
                            <div
                                key={prop.id}
                                onClick={() => navigate('/properties/' + prop.id)}
                                className="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition"
                            >
                                <div className="flex items-center gap-3">
                                    {prop.images && prop.images.length > 0 ? (
                                        <img src={prop.images[0].image_url} alt=""
                                             className="w-10 h-10 rounded-lg object-cover"/>
                                    ) : (
                                        <div
                                            className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-lg">🏠
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-sm font-semibold text-black">{prop.address}</p>
                                        <p className="text-xs text-gray-500">{prop.city || '—'}</p>
                                    </div>
                                </div>
                                <Badge
                                    variant={prop.status === 'occupied' ? 'green' : prop.status === 'available' ? 'gray' : 'yellow'}>
                                    {prop.status}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Recent tickets */}
                <div className="bg-white rounded-2xl p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-bold text-black">Recent Tickets</h2>
                        <button onClick={() => navigate('/tickets')}
                                className="text-sm text-gray-500 hover:text-black transition">View all →
                        </button>
                    </div>
                    {tickets.length === 0 ? (
                        <p className="text-sm text-gray-400 text-center py-6">No tickets yet</p>
                    ) : (
                        <div className="space-y-3">
                            {tickets.slice(0, 6).map((ticket) => (
                                <div
                                    key={ticket.id}
                                    onClick={() => navigate('/tickets/' + ticket.id)}
                                    className="p-3 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-black truncate">{ticket.title}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">
                                                {ticket.property?.address?.split(',')[0] || '—'} · {formatDate(ticket.created_at)}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={ticket.status === 'new' ? 'pink' : ticket.status === 'in_progress' ? 'yellow' : ticket.status === 'resolved' ? 'green' : 'red'}>
                                            {ticket.status === 'in_progress' ? 'In Progress' : ticket.status}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    )
}

function TenantView() {
    const {user} = useAuth()
    const navigate = useNavigate()
    const [lease, setLease] = useState<Lease | null>(null)
    const [payments, setPayments] = useState<Payment[]>([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        const load = async () => {
            try {
                const [leasesRes, paymentsRes] = await Promise.all([
                    leasesApi.getAll({status: 'active'}),
                    paymentsApi.getAll({sort: '-due_date'}),
                ])
                const leases = leasesRes.data.data || []
                setLease(leases[0] || null)
                setPayments((paymentsRes.data.data || []).slice(0, 5))
            } catch (error) {
                console.error('Failed to load tenant view:', error)
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [])

    if (isLoading) return <Spinner/>

    if (!lease) return (
        <div className="bg-white rounded-2xl p-8 shadow-sm text-center">
            <span className="text-3xl">🔑</span>
            <p className="text-sm text-gray-500 mt-2">You don't have an active lease.</p>
        </div>
    )

    const unpaidCount = payments.filter((p) => p.status === 'unpaid' || p.status === 'overdue').length

    return (
        <>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="My Property" value="1" subtitle={lease.property?.address?.split(',')[0] || ''}/>
                <StatCard label="Monthly Rent" value={formatCurrency(lease.rent_amount)}/>
                <StatCard label="Unpaid Payments" value={unpaidCount} accent={unpaidCount > 0 ? 'red' : 'green'}/>
                <StatCard label="Trust Score" value={user?.trust_score ?? '—'}/>
            </div>

            <div className="bg-white rounded-2xl p-6 shadow-sm mt-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-bold text-black">Recent Payments</h2>
                    <button onClick={() => navigate('/payments')}
                            className="text-sm text-gray-500 hover:text-black transition">View all →
                    </button>
                </div>
                {payments.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-4">No payments yet</p>
                ) : (
                    <div className="space-y-2">
                        {payments.map((payment) => (
                            <div key={payment.id}
                                 className="flex items-center justify-between p-3 border border-gray-100 rounded-xl">
                                <div>
                                    <p className="text-sm font-semibold text-black">{formatCurrency(payment.amount)}</p>
                                    <p className="text-xs text-gray-500">Due {formatDate(payment.due_date)}</p>
                                </div>
                                <Badge
                                    variant={payment.status === 'paid' ? 'green' : payment.status === 'overdue' ? 'red' : 'yellow'}>
                                    {payment.status}
                                </Badge>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    )
}

// ─── Landlord Dashboard ────────────────────────
function LandlordDashboard() {
    const {user} = useAuth()
    const [stats, setStats] = useState<DashboardStats | null>(null)
    const [financeData, setFinanceData] = useState<FinanceChartData[]>([])
    const [occupancyData, setOccupancyData] = useState<OccupancyChartData[]>([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        const loadData = async () => {
            try {
                const [statsRes, financeRes, occupancyRes] = await Promise.all([
                    dashboardApi.getStats(),
                    dashboardApi.getFinanceChart(),
                    dashboardApi.getOccupancyChart(),
                ])
                setStats(statsRes.data)
                setFinanceData(financeRes.data)
                setOccupancyData(occupancyRes.data)
            } catch (error) {
                console.error('Failed to load dashboard:', error)
            } finally {
                setIsLoading(false)
            }
        }
        void loadData()
    }, [])

    if (isLoading) return <Spinner/>
    if (!stats) return null

    return (
        <div>
            <h1 className="text-2xl sm:text-4xl font-bold text-black">Overview</h1>
            <p className="mt-2 text-gray-500">Welcome back, {user?.name}</p>

            {/* Stats Cards */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                <StatCard
                    label="Total Properties"
                    value={stats.properties.total}
                    subtitle={`${stats.properties.occupied} occupied · ${stats.properties.available} available`}
                />
                <StatCard label="Monthly Income" value={formatCurrency(stats.finance.monthly_income)} accent="green"/>
                <StatCard label="Monthly Expenses" value={formatCurrency(stats.finance.monthly_expenses)} accent="red"/>
                <StatCard label="Cashflow" value={formatCurrency(stats.finance.cashflow)}
                          accent={stats.finance.cashflow >= 0 ? 'green' : 'red'}/>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <StatCard label="Active Leases" value={stats.leases.active}
                          subtitle={`${stats.leases.expiring_soon} expiring soon`}
                          accent={stats.leases.expiring_soon > 0 ? 'yellow' : 'default'}/>
                <StatCard label="Open Tickets" value={stats.tickets.open}
                          accent={stats.tickets.open > 0 ? 'red' : 'green'}/>
                <StatCard label="Overdue Payments" value={stats.finance.overdue_payments}
                          accent={stats.finance.overdue_payments > 0 ? 'red' : 'green'}/>
                <StatCard label="Properties in Renovation" value={stats.properties.renovation}/>
            </div>

            {/* Charts */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
                <div className="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm">
                    <h2 className="text-lg font-bold text-black mb-4">Finance Overview</h2>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={financeData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0"/>
                            <XAxis dataKey="label" tick={{fontSize: 12, fill: '#9ca3af'}} axisLine={false}
                                   tickLine={false}/>
                            <YAxis tick={{fontSize: 12, fill: '#9ca3af'}} axisLine={false} tickLine={false}
                                   tickFormatter={(v) => `${(v / 1000).toFixed(0)}k`}/>
                            <Tooltip formatter={(value?: number | string) => formatCurrency(Number(value ?? 0))}
                                     contentStyle={{
                                         borderRadius: '12px',
                                         border: 'none',
                                         boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                                         fontSize: '12px'
                                     }}/>
                            <Legend wrapperStyle={{fontSize: '12px'}}/>
                            <Bar dataKey="income" name="Income" fill="#22c55e" radius={[4, 4, 0, 0]}/>
                            <Bar dataKey="expenses" name="Expenses" fill="#ef4444" radius={[4, 4, 0, 0]}/>
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                <div className="bg-white rounded-2xl p-6 shadow-sm">
                    <h2 className="text-lg font-bold text-black mb-4">Occupancy</h2>
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie
                                data={occupancyData.map((entry, index) => ({
                                    ...entry,
                                    fill: ['#22c55e', '#e5e7eb', '#facc15'][index % 3],
                                }))}
                                dataKey="value"
                                nameKey="label"
                                cx="50%"
                                cy="50%"
                                innerRadius={60}
                                outerRadius={100}
                                paddingAngle={4}
                                strokeWidth={0}
                            />
                            <Tooltip contentStyle={{
                                borderRadius: '12px',
                                border: 'none',
                                boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                                fontSize: '12px'
                            }}/>
                            <Legend wrapperStyle={{fontSize: '12px'}}/>
                        </PieChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    )
}
