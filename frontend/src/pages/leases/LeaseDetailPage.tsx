import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {leasesApi} from '../../api/leases'
import type {Lease} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import RateTenantModal from './RateTenantModal'
import {useConfirm} from "../../hooks/useConfirm.tsx";
import {paymentsApi} from "../../api/payments.ts";

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
    const [showEditModal, setShowEditModal] = useState(false)
    const [showRatingModal, setShowRatingModal] = useState(false)
    const {confirm: showConfirm, dialog} = useConfirm()

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
        const ok = await showConfirm({
            title: `${newStatus === 'ended' ? 'End' : 'Terminate'} Lease`,
            message: `Are you sure you want to mark this lease as "${newStatus}"? This action cannot be undone.`,
            confirmLabel: newStatus === 'ended' ? 'End Lease' : 'Terminate',
            variant: 'danger',
        })

        if (!ok) {
            return
        }

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

    const handleMarkPaid = async (paymentId: number) => {
        const ok = await showConfirm({
            title: 'Mark as Paid',
            message: 'Mark this payment as paid with today\'s date?',
            confirmLabel: 'Mark Paid',
            variant: 'primary',
        })
        if (!ok) return
        try {
            await paymentsApi.markPaid(paymentId)
            void loadLease()
        } catch (error) {
            console.error('Failed to mark payment as paid:', error)
        }
    }

    const handleDelete = async () => {
        const ok = await showConfirm({
            title: 'Delete Lease',
            message: 'Are you sure you want to delete this lease? All associated payments will be affected.',
            confirmLabel: 'Delete',
            variant: 'danger',
        })

        if (!ok) {
            return
        }

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
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-4">
                    <button onClick={() => navigate('/leases')}
                            className="text-gray-400 hover:text-black transition text-lg cursor-pointer">←
                    </button>
                    <h1 className="text-2xl sm:text-4xl font-bold text-black">Lease Detail</h1>
                </div>
                <div className="flex items-center gap-2">
                    {isLandlord && lease.status === 'active' && (
                        <Button variant="secondary" onClick={() => setShowEditModal(true)}>
                            ✏️ Edit
                        </Button>
                    )}
                    <Button variant="secondary" onClick={handleDownloadPdf} disabled={isDownloading}>
                        {isDownloading ? 'Generating...' : '📄 PDF'}
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

                    {/* Payments */}
                    {lease.payments && lease.payments.length > 0 && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-4">Payments ({lease.payments.length})</h2>
                            {/* Desktop */}
                            <div className="hidden sm:block">
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
                                                {payment.paid_date ? formatDate(payment.paid_date) : (
                                                    isLandlord && payment.status !== 'paid' ? (
                                                        <button
                                                            onClick={() => handleMarkPaid(payment.id)}
                                                            className="text-xs font-semibold text-green-600 bg-green-50 border border-green-200 rounded-lg px-2.5 py-1 hover:bg-green-100 cursor-pointer transition"
                                                        >
                                                            ✓ Mark Paid
                                                        </button>
                                                    ) : '—'
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    </tbody>
                                </table>
                            </div>
                            {/* Mobile */}
                            <div className="sm:hidden space-y-2">
                                {lease.payments.map((payment) => (
                                    <div key={payment.id} className="p-3 border border-gray-100 rounded-xl">
                                        <div className="flex items-center justify-between">
                                            <span
                                                className="text-sm font-semibold text-black">{formatCurrency(payment.amount)}</span>
                                            <Badge
                                                variant={payment.status === 'paid' ? 'green' : payment.status === 'overdue' ? 'red' : 'yellow'}>
                                                {payment.status}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                            <span>Due {formatDate(payment.due_date)}</span>
                                            {payment.paid_date ? (
                                                <span>· Paid {formatDate(payment.paid_date)}</span>
                                            ) : isLandlord && payment.status !== 'paid' ? (
                                                <button
                                                    onClick={() => handleMarkPaid(payment.id)}
                                                    className="text-xs font-semibold text-green-600 bg-green-50 border border-green-200 rounded-lg px-2.5 py-1 hover:bg-green-100 cursor-pointer transition"
                                                >
                                                    ✓ Mark Paid
                                                </button>
                                            ) : null}
                                        </div>
                                    </div>
                                ))}
                            </div>
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
                  {formatCurrency(Number(lease.rent_amount) + Number(lease.utility_advances || 0))}
                </span>
                            </div>
                            {daysLeft !== null && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Expires in</span>
                                    <span
                                        className={`text-sm font-semibold ${daysLeft <= 0 ? 'text-red-600' : daysLeft <= 30 ? 'text-yellow-600' : 'text-gray-700'}`}>
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

                    {/* Actions */}
                    {isLandlord && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Actions</h2>
                            <div className="space-y-2">
                                {lease.status === 'active' && (
                                    <>
                                        <Button variant="secondary" className="w-full"
                                                onClick={() => handleStatusChange('ended')} disabled={isUpdating}>
                                            End Lease
                                        </Button>
                                        <Button variant="danger" className="w-full"
                                                onClick={() => handleStatusChange('terminated')} disabled={isUpdating}>
                                            Terminate Lease
                                        </Button>
                                    </>
                                )}
                                {(lease.status === 'ended' || lease.status === 'terminated') && (
                                    <Button
                                        variant="primary"
                                        className="w-full"
                                        onClick={() => setShowRatingModal(true)}
                                    >
                                        ⭐ Rate Tenant
                                    </Button>
                                )}
                                <Button variant="danger" className="w-full" onClick={handleDelete}>
                                    Delete Lease
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Edit Modal */}
            {lease && (
                <EditLeaseModal
                    isOpen={showEditModal}
                    onClose={() => setShowEditModal(false)}
                    lease={lease}
                    onSuccess={() => {
                        setShowEditModal(false)
                        void loadLease()
                    }}
                />
            )}
            <RateTenantModal
                isOpen={showRatingModal}
                onClose={() => setShowRatingModal(false)}
                leaseId={lease.id}
                existingCategories={(lease.ratings || []).map((r) => r.category)}
                onSuccess={() => {
                    setShowRatingModal(false)
                    void loadLease()
                }}
            />
            {dialog}
        </div>
    )
}

// ─── Edit Lease Modal ──────────────────────────
interface EditLeaseModalProps {
    isOpen: boolean
    onClose: () => void
    lease: Lease
    onSuccess: () => void
}

function EditLeaseModal({isOpen, onClose, lease, onSuccess}: EditLeaseModalProps) {
    const [formData, setFormData] = useState({
        rent_amount: '',
        deposit_amount: '',
        utility_advances: '',
        variable_symbol: '',
        end_date: '',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    useEffect(() => {
        if (lease && isOpen) {
            setFormData({
                rent_amount: lease.rent_amount?.toString() || '',
                deposit_amount: lease.deposit_amount?.toString() || '',
                utility_advances: lease.utility_advances?.toString() || '',
                variable_symbol: lease.variable_symbol || '',
                end_date: lease.end_date || '',
            })
        }
    }, [lease, isOpen])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData((prev) => ({...prev, [e.target.name]: e.target.value}))
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            await leasesApi.update(lease.id, {
                rent_amount: Number(formData.rent_amount),
                deposit_amount: formData.deposit_amount ? Number(formData.deposit_amount) : undefined,
                utility_advances: formData.utility_advances ? Number(formData.utility_advances) : undefined,
                variable_symbol: formData.variable_symbol || undefined,
                end_date: formData.end_date || undefined,
            })
            onSuccess()
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Edit Lease" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Monthly Rent (Kč)"
                    name="rent_amount"
                    type="number"
                    value={formData.rent_amount}
                    onChange={handleChange}
                    error={errors.rent_amount?.[0]}
                    required
                />
                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Deposit (Kč)"
                        name="deposit_amount"
                        type="number"
                        value={formData.deposit_amount}
                        onChange={handleChange}
                        error={errors.deposit_amount?.[0]}
                    />
                    <Input
                        label="Utility Advances (Kč)"
                        name="utility_advances"
                        type="number"
                        value={formData.utility_advances}
                        onChange={handleChange}
                        error={errors.utility_advances?.[0]}
                    />
                </div>
                <Input
                    label="Variable Symbol"
                    name="variable_symbol"
                    value={formData.variable_symbol}
                    onChange={handleChange}
                    error={errors.variable_symbol?.[0]}
                />
                <Input
                    label="End Date"
                    name="end_date"
                    type="date"
                    value={formData.end_date}
                    onChange={handleChange}
                    error={errors.end_date?.[0]}
                />
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Saving...' : 'Save Changes'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
