import {useEffect, useState} from 'react'
import {useAuth} from '../../contexts/AuthContext'
import {dashboardApi} from '../../api/dashboard'
import type {DashboardStats, FinanceChartData, OccupancyChartData} from '../../types'
import {formatCurrency} from '../../utils/format'
import StatCard from '../../components/ui/StatCard'
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

export default function DashboardPage() {
    const {user, isLandlord} = useAuth()
    const [stats, setStats] = useState<DashboardStats | null>(null)
    const [financeData, setFinanceData] = useState<FinanceChartData[]>([])
    const [occupancyData, setOccupancyData] = useState<OccupancyChartData[]>([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        if (!isLandlord) return

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
    }, [isLandlord])

    if (!isLandlord) {
        return (
            <div>
                <h1 className="text-4xl font-bold text-black">Overview</h1>
                <p className="mt-2 text-gray-500">Welcome back, {user?.name}</p>
                <div className="mt-8 bg-white rounded-2xl p-8 shadow-sm">
                    <p className="text-gray-600">Your properties and leases will appear here.</p>
                </div>
            </div>
        )
    }

    if (isLoading) return <Spinner/>

    if (!stats) return null

    return (
        <div>
            <h1 className="text-4xl font-bold text-black">Overview</h1>
            <p className="mt-2 text-gray-500">Welcome back, {user?.name}</p>

            {/* ─── Stats Cards ──────────────────────────── */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                <StatCard
                    label="Total Properties"
                    value={stats.properties.total}
                    subtitle={`${stats.properties.occupied} occupied · ${stats.properties.available} available`}
                />
                <StatCard
                    label="Monthly Income"
                    value={formatCurrency(stats.finance.monthly_income)}
                    accent="green"
                />
                <StatCard
                    label="Monthly Expenses"
                    value={formatCurrency(stats.finance.monthly_expenses)}
                    accent="red"
                />
                <StatCard
                    label="Cashflow"
                    value={formatCurrency(stats.finance.cashflow)}
                    accent={stats.finance.cashflow >= 0 ? 'green' : 'red'}
                />
            </div>

            {/* ─── Second Row ───────────────────────────── */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <StatCard
                    label="Active Leases"
                    value={stats.leases.active}
                    subtitle={`${stats.leases.expiring_soon} expiring soon`}
                    accent={stats.leases.expiring_soon > 0 ? 'yellow' : 'default'}
                />
                <StatCard
                    label="Open Tickets"
                    value={stats.tickets.open}
                    accent={stats.tickets.open > 0 ? 'red' : 'green'}
                />
                <StatCard
                    label="Overdue Payments"
                    value={stats.finance.overdue_payments}
                    accent={stats.finance.overdue_payments > 0 ? 'red' : 'green'}
                />
                <StatCard
                    label="Properties in Renovation"
                    value={stats.properties.renovation}
                />
            </div>

            {/* ─── Charts ───────────────────────────────── */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
                {/* Finance Chart */}
                <div className="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm">
                    <h2 className="text-lg font-bold text-black mb-4">Finance Overview</h2>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={financeData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0"/>
                            <XAxis
                                dataKey="label"
                                tick={{fontSize: 12, fill: '#9ca3af'}}
                                axisLine={false}
                                tickLine={false}
                            />
                            <YAxis
                                tick={{fontSize: 12, fill: '#9ca3af'}}
                                axisLine={false}
                                tickLine={false}
                                tickFormatter={(v) => `${(v / 1000).toFixed(0)}k`}
                            />
                            <Tooltip
                                formatter={(value?: number | string) => formatCurrency(Number(value ?? 0))}
                                contentStyle={{
                                    borderRadius: '12px',
                                    border: 'none',
                                    boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                                    fontSize: '12px',
                                }}
                            />
                            <Legend
                                wrapperStyle={{fontSize: '12px'}}
                            />
                            <Bar dataKey="income" name="Income" fill="#22c55e" radius={[4, 4, 0, 0]}/>
                            <Bar dataKey="expenses" name="Expenses" fill="#ef4444" radius={[4, 4, 0, 0]}/>
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                {/* Occupancy Chart */}
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
                            <Tooltip
                                contentStyle={{
                                    borderRadius: '12px',
                                    border: 'none',
                                    boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                                    fontSize: '12px',
                                }}
                            />
                            <Legend
                                wrapperStyle={{fontSize: '12px'}}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    )
}
