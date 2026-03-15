import * as React from 'react'
import { useEffect, useState } from 'react'
import { propertiesApi } from '../../api/properties'
import type { ApiError, Property } from '../../types'
import { AxiosError } from 'axios'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    onSuccess: () => void
    property?: Property | null
}

export default function PropertyFormModal({ isOpen, onClose, onSuccess, property }: Props) {
    const [formData, setFormData] = useState({
        address: '',
        city: '',
        zip_code: '',
        size: '',
        disposition: '',
        floor: '',
        status: 'available',
        purchase_price: '',
        description: '',
    })

    useEffect(() => {
        if (property) {
            setFormData({
                address: property.address || '',
                city: property.city || '',
                zip_code: property.zip_code || '',
                floor: property.floor?.toString() || '',
                disposition: property.disposition || '',
                size: property.size?.toString() || '',
                status: property.status || 'available',
                description: property.description || '',
                purchase_price: property.purchase_price?.toString() || '',
            })
        } else {
            setFormData({
                address: '',
                city: '',
                zip_code: '',
                floor: '',
                disposition: '',
                size: '',
                status: 'available',
                description: '',
                purchase_price: '',
            })
        }
    }, [property, isOpen])

    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    }

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            const submitData = {
                ...formData,
                size: formData.size ? Number(formData.size) : undefined,
                floor: formData.floor ? Number(formData.floor) : undefined,
                purchase_price: formData.purchase_price ? Number(formData.purchase_price) : undefined,
            }

            if (property) {
                await propertiesApi.update(property.id, submitData)
            } else {
                await propertiesApi.create(submitData)
            }
            setFormData({
                address: '',
                city: '',
                zip_code: '',
                size: '',
                disposition: '',
                floor: '',
                status: 'available',
                purchase_price: '',
                description: '',
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
        <Modal isOpen={isOpen} onClose={onClose} title={property ? 'Edit Property' : 'Add Property'} size="lg">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Address"
                    name="address"
                    value={formData.address}
                    onChange={handleChange}
                    placeholder="Street and number"
                    error={errors.address?.[0]}
                    required
                />

                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="City"
                        name="city"
                        value={formData.city}
                        onChange={handleChange}
                        placeholder="City"
                        error={errors.city?.[0]}
                    />
                    <Input
                        label="ZIP Code"
                        name="zip_code"
                        value={formData.zip_code}
                        onChange={handleChange}
                        placeholder="000 00"
                        error={errors.zip_code?.[0]}
                    />
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <Input
                        label="Size (m²)"
                        name="size"
                        type="number"
                        value={formData.size}
                        onChange={handleChange}
                        placeholder="65"
                        error={errors.size?.[0]}
                    />
                    <Input
                        label="Disposition"
                        name="disposition"
                        value={formData.disposition}
                        onChange={handleChange}
                        placeholder="2+kk"
                        error={errors.disposition?.[0]}
                    />
                    <Input
                        label="Floor"
                        name="floor"
                        type="number"
                        value={formData.floor}
                        onChange={handleChange}
                        placeholder="3"
                        error={errors.floor?.[0]}
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Select
                        label="Status"
                        name="status"
                        value={formData.status}
                        onChange={handleChange}
                        options={[
                            { value: 'available', label: 'Available' },
                            { value: 'occupied', label: 'Occupied' },
                            { value: 'renovation', label: 'Renovation' },
                        ]}
                        error={errors.status?.[0]}
                    />
                    <Input
                        label="Purchase Price (Kč)"
                        name="purchase_price"
                        type="number"
                        value={formData.purchase_price}
                        onChange={handleChange}
                        placeholder="3 500 000"
                        error={errors.purchase_price?.[0]}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                        rows={3}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition resize-none"
                        placeholder="Property description..."
                    />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Saving...' : property ? 'Save Changes' : 'Create Property'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
