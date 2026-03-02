import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {leasesApi} from '../../api/leases'
import type {Lease} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'

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

export default function LeaseDetailPage() {
    const {id} = useParams<{ id: string }>()
    const navigate = useNavigate()
    const {isLandlord} = useAuth()
    const [lease, setLease] = useState<Lease | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [isDownloading, setIsDownloading] = useState(false)
    const [isUpdating, setIsUpdating] = useState(false)

    const loadLease = async () => {
        try {
            const res = await leasesApi.getOne(Number(id))
            setLease(res.data)
        } catch {
            navigate('/leases')
        } finally {
            setIsLoading(false)
        }
    }

    useEffect(() => {
        void loadLease()
    }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

    const handleDownloadPdf = async () => {
        setIsDownloading(true)
        try {
            const res = await leasesApi.downloadPdf(Number(id))
            const blob = new Blob([res.data], {type: 'application/pdf'})
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = `lease-${id}.pdf`
            link.click()
            window.URL.revokeObjectURL(url)
        } catch (error) {
            console.error('Failed to download PDF:', error)
        } finally {
            setIsDownloading(false)
        }
    }

    const handleStatusChange = async (newStatus: string) => {
        if (!confirm(`Are you sure you want to mark this lease as "${newStatus}"?`)) return
        setIsUpdating(true)
        try {
            await leasesApi.update(Number(id), {status: newStatus})
            void loadLease()
        } catch (error) {
            console.error('Failed to update lease:', error)
        } finally {
            setIsUpdating(false)
        }
    }

    const handleDelete = async () => {
        if (!confirm('Are you sure you want to delete this lease?')) return
        try {
            await leasesApi.delete(Number(id))
            navigate('/leases')
        } catch (error) {
            console.error('Failed to delete lease:', error)
        }
    }

    if (isLoading) return <Spinner/>
    if (!lease) return null

    const daysLeft = lease.end_date
        ? Math.ceil((new Date(lease.end_date).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
        : null

    return (
        <div>
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => navigate('/leases')}
                        className="text-gray-400 hover:text-black transition text-lg"
                    >
                        ←
                    </button>
                    <h1 className="text-4xl font-bold text-black">Lease Detail</h1>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" onClick={handleDownloadPdf} disabled={isDownloading}>
                        {isDownloading ? 'Generating...' : '📄 Download PDF'}
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Overview */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-2xl font-bold text-black">{formatCurrency(lease.rent_amount)}</p>
                                <p className="text-sm text-gray-500 mt-1">Monthly rent</p>
                            </div>
                            <Badge variant={statusVariant(lease.status)}>{lease.status}</Badge>
                        </div>
                    </div>

                    {/* Details grid */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-4">Contract Details</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs text-gray-400">Property</p>
                                <p className="text-sm text-gray-700">{lease.property?.address || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Tenant</p>
                                <p className="text-sm text-gray-700">{lease.tenant?.name || '—'}</p>
                                <p className="text-xs text-gray-500">{lease.tenant?.email}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Start Date</p>
                                <p className="text-sm text-gray-700">{formatDate(lease.start_date)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">End Date</p>
                                <p className="text-sm text-gray-700">{lease.end_date ? formatDate(lease.end_date) : 'Indefinite'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Deposit</p>
                                <p className="text-sm text-gray-700">{lease.deposit_amount ? formatCurrency(lease.deposit_amount) : '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Utility Advances</p>
                                <p className="text-sm text-gray-700">{lease.utility_advances ? formatCurrency(lease.utility_advances) : '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Variable Symbol</p>
                                <p className="text-sm text-gray-700">{lease.variable_symbol || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Created</p>
                                <p className="text-sm text-gray-700">{formatDate(lease.created_at)}</p>
                            </div>
                        </div>
                    </div>

                    {/* Payments summary */}
                    {lease.payments && lease.payments.length > 0 && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-4">
                                Payments ({lease.payments.length})
                            </h2>
                            <table className="w-full">
                                <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Due
                                        Date
                                    </th>
                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Amount</th>
                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Status</th>
                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Paid
                                        Date
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                {lease.payments.map((payment) => (
                                    <tr key={payment.id} className="border-b border-gray-50">
                                        <td className="py-3 text-sm text-gray-600">{formatDate(payment.due_date)}</td>
                                        <td className="py-3 text-sm text-gray-600">{formatCurrency(payment.amount)}</td>
                                        <td className="py-3">
                                            <Badge
                                                variant={payment.status === 'paid' ? 'green' : payment.status === 'overdue' ? 'red' : 'yellow'}>
                                                {payment.status}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-sm text-gray-600">
                                            {payment.paid_date ? formatDate(payment.paid_date) : '—'}
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Quick info */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-3">Quick Info</h2>
                        <div className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Status</span>
                                <span className="text-sm font-semibold capitalize">{lease.status}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Monthly total</span>
                                <span className="text-sm font-semibold">
                  {formatCurrency(
                      Number(lease.rent_amount) + Number(lease.utility_advances || 0)
                  )}
                </span>
                            </div>
                            {daysLeft !== null && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Expires in</span>
                                    <span
                                        className={`text-sm font-semibold ${daysLeft <= 30 ? 'text-yellow-600' : daysLeft <= 0 ? 'text-red-600' : 'text-gray-700'}`}>
                    {daysLeft <= 0 ? 'Expired' : `${daysLeft} days`}
                  </span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Payments</span>
                                <span className="text-sm font-semibold">{lease.payments?.length || 0}</span>
                            </div>
                        </div>
                    </div>

                    {/* Tenant card */}
                    {lease.tenant && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Tenant</h2>
                            <div className="flex items-center gap-3">
                                <div
                                    className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600">
                                    {lease.tenant.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-black">{lease.tenant.name}</p>
                                    <p className="text-xs text-gray-500">{lease.tenant.email}</p>
                                </div>
                            </div>
                            {lease.tenant.trust_score !== undefined && (
                                <div className="mt-4">
                                    <div className="flex justify-between text-sm mb-1">
                                        <span className="text-gray-500">Trust Score</span>
                                        <span className="font-semibold">{lease.tenant.trust_score}/100</span>
                                    </div>
                                    <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            className={`h-full rounded-full transition-all ${
                                                lease.tenant.trust_score >= 70 ? 'bg-green-500' :
                                                    lease.tenant.trust_score >= 40 ? 'bg-yellow-500' : 'bg-red-500'
                                            }`}
                                            style={{width: `${lease.tenant.trust_score}%`}}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Actions — landlord only */}
                    {isLandlord && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Actions</h2>
                            <div className="space-y-2">
                                {lease.status === 'active' && (
                                    <>
                                        <Button
                                            variant="secondary"
                                            className="w-full"
                                            onClick={() => handleStatusChange('ended')}
                                            disabled={isUpdating}
                                        >
                                            End Lease
                                        </Button>
                                        <Button
                                            variant="danger"
                                            className="w-full"
                                            onClick={() => handleStatusChange('terminated')}
                                            disabled={isUpdating}
                                        >
                                            Terminate Lease
                                        </Button>
                                    </>
                                )}
                                <Button
                                    variant="danger"
                                    className="w-full"
                                    onClick={handleDelete}
                                >
                                    Delete Lease
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    )
}
