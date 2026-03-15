import * as React from 'react'
import { useEffect, useState } from 'react'
import { paymentsApi } from '../../api/payments'
import { leasesApi } from '../../api/leases'
import type { ApiError, Lease } from '../../types'
import { AxiosError } from 'axios'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    onSuccess: () => void
}

export default function CreatePaymentModal({ isOpen, onClose, onSuccess }: Props) {
    const [leases, setLeases] = useState<Lease[]>([])
    const [formData, setFormData] = useState({
        lease_id: '',
        type: 'rent',
        amount: '',
        due_date: '',
        paid_date: '',
        variable_symbol: '',
        note: '',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    useEffect(() => {
        if (isOpen) {
            leasesApi
                .getAll({ status: 'active' })
                .then((res) => {
                    setLeases(res.data.data)
                })
                .catch(console.error)
        }
    }, [isOpen])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        const { name, value } = e.target
        setFormData((prev) => {
            const updated = { ...prev, [name]: value }
            // Autofill amount and VS when lease selected
            if (name === 'lease_id') {
                const lease = leases.find((l) => l.id === Number(value))
                if (lease) {
                    updated.amount = String(lease.rent_amount)
                    updated.variable_symbol = lease.variable_symbol || ''
                }
            }
            return updated
        })
    }

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            await paymentsApi.create({
                lease_id: Number(formData.lease_id),
                type: formData.type,
                amount: Number(formData.amount),
                due_date: formData.due_date,
                paid_date: formData.paid_date || undefined,
                variable_symbol: formData.variable_symbol || undefined,
                note: formData.note || undefined,
            })
            setFormData({
                lease_id: '',
                type: 'rent',
                amount: '',
                due_date: '',
                paid_date: '',
                variable_symbol: '',
                note: '',
            })
            onSuccess()
        } catch (err) {
            const axiosError = err as AxiosError<ApiError>
            setErrors(axiosError.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Add Payment" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Select
                    label="Lease"
                    name="lease_id"
                    value={formData.lease_id}
                    onChange={handleChange}
                    placeholder="Select lease..."
                    options={leases.map((l) => ({
                        value: String(l.id),
                        label: `${l.tenant?.name || 'Tenant'} — ${l.property?.address || 'Property'}`,
                    }))}
                    error={errors.lease_id?.[0]}
                />

                <div className="grid grid-cols-2 gap-4">
                    <Select
                        label="Type"
                        name="type"
                        value={formData.type}
                        onChange={handleChange}
                        options={[
                            { value: 'rent', label: 'Rent' },
                            { value: 'utilities', label: 'Utilities' },
                            { value: 'deposit', label: 'Deposit' },
                            { value: 'other', label: 'Other' },
                        ]}
                        error={errors.type?.[0]}
                    />
                    <Input
                        label="Amount (Kč)"
                        name="amount"
                        type="number"
                        value={formData.amount}
                        onChange={handleChange}
                        placeholder="15 000"
                        error={errors.amount?.[0]}
                        required
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Due Date"
                        name="due_date"
                        type="date"
                        value={formData.due_date}
                        onChange={handleChange}
                        error={errors.due_date?.[0]}
                        required
                    />
                    <Input
                        label="Paid Date (optional)"
                        name="paid_date"
                        type="date"
                        value={formData.paid_date}
                        onChange={handleChange}
                        error={errors.paid_date?.[0]}
                    />
                </div>

                <Input
                    label="Variable Symbol"
                    name="variable_symbol"
                    value={formData.variable_symbol}
                    onChange={handleChange}
                    placeholder="Auto-filled from lease"
                    error={errors.variable_symbol?.[0]}
                />

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Note</label>
                    <textarea
                        name="note"
                        value={formData.note}
                        onChange={handleChange}
                        rows={2}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition resize-none"
                        placeholder="Optional note..."
                    />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Creating...' : 'Create Payment'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
