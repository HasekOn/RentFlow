import { useEffect, useState } from 'react'
import { leasesApi } from '../../api/leases'
import { propertiesApi } from '../../api/properties'
import { usersApi } from '../../api/users'
import type { ApiError, Property, User } from '../../types'
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

export default function CreateLeaseModal({ isOpen, onClose, onSuccess }: Props) {
    const [properties, setProperties] = useState<Property[]>([])
    const [tenants, setTenants] = useState<User[]>([])
    const [formData, setFormData] = useState({
        property_id: '',
        tenant_id: '',
        start_date: '',
        end_date: '',
        rent_amount: '',
        deposit_amount: '',
        utility_advances: '',
        variable_symbol: '',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    useEffect(() => {
        if (isOpen) {
            propertiesApi
                .getAll()
                .then((res) => setProperties(res.data.data))
                .catch(console.error)
            // Load tenants + managers (managers can also be tenants)
            Promise.all([usersApi.getTenants(), usersApi.getManagers()])
                .then(([tenantsRes, managersRes]) => {
                    const all = [...tenantsRes.data, ...managersRes.data]
                    // Deduplicate by id
                    const unique = all.filter((u, i, arr) => arr.findIndex((x) => x.id === u.id) === i)
                    setTenants(unique)
                })
                .catch(console.error)
        }
    }, [isOpen])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            await leasesApi.create({
                property_id: Number(formData.property_id),
                tenant_id: Number(formData.tenant_id),
                start_date: formData.start_date,
                end_date: formData.end_date || undefined,
                rent_amount: Number(formData.rent_amount),
                deposit_amount: formData.deposit_amount ? Number(formData.deposit_amount) : undefined,
                utility_advances: formData.utility_advances ? Number(formData.utility_advances) : undefined,
                variable_symbol: formData.variable_symbol || undefined,
            })
            setFormData({
                property_id: '',
                tenant_id: '',
                start_date: '',
                end_date: '',
                rent_amount: '',
                deposit_amount: '',
                utility_advances: '',
                variable_symbol: '',
            })
            onSuccess()
        } catch (err) {
            const axiosError = err as AxiosError<ApiError>
            if (axiosError.response?.data?.message) {
                setErrors({ _general: [axiosError.response.data.message] })
            } else {
                setErrors(axiosError.response?.data?.errors || {})
            }
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="New Lease" size="lg">
            <form onSubmit={handleSubmit} className="space-y-4">
                {errors._general?.[0] && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p className="text-sm text-red-600">{errors._general[0]}</p>
                    </div>
                )}
                <div className="grid grid-cols-2 gap-4">
                    <Select
                        label="Property"
                        name="property_id"
                        value={formData.property_id}
                        onChange={handleChange}
                        placeholder="Select property..."
                        options={properties.map((p) => ({ value: String(p.id), label: p.address }))}
                        error={errors.property_id?.[0]}
                    />
                    <Select
                        label="Tenant"
                        name="tenant_id"
                        value={formData.tenant_id}
                        onChange={handleChange}
                        placeholder="Select tenant..."
                        options={tenants.map((t) => ({ value: String(t.id), label: `${t.name} (${t.email})` }))}
                        error={errors.tenant_id?.[0]}
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Start Date"
                        name="start_date"
                        type="date"
                        value={formData.start_date}
                        onChange={handleChange}
                        error={errors.start_date?.[0]}
                        required
                    />
                    <Input
                        label="End Date"
                        name="end_date"
                        type="date"
                        value={formData.end_date}
                        onChange={handleChange}
                        error={errors.end_date?.[0]}
                    />
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <Input
                        label="Rent (Kč)"
                        name="rent_amount"
                        type="number"
                        value={formData.rent_amount}
                        onChange={handleChange}
                        placeholder="15 000"
                        error={errors.rent_amount?.[0]}
                        required
                    />
                    <Input
                        label="Deposit (Kč)"
                        name="deposit_amount"
                        type="number"
                        value={formData.deposit_amount}
                        onChange={handleChange}
                        placeholder="30 000"
                        error={errors.deposit_amount?.[0]}
                    />
                    <Input
                        label="Utilities (Kč)"
                        name="utility_advances"
                        type="number"
                        value={formData.utility_advances}
                        onChange={handleChange}
                        placeholder="3 000"
                        error={errors.utility_advances?.[0]}
                    />
                </div>

                <Input
                    label="Variable Symbol"
                    name="variable_symbol"
                    value={formData.variable_symbol}
                    onChange={handleChange}
                    placeholder="Unique payment identifier"
                    error={errors.variable_symbol?.[0]}
                />

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Creating...' : 'Create Lease'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
