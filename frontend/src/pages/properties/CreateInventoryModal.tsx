import * as React from 'react'
import { useState } from 'react'
import { inventoryApi } from '../../api/inventory'
import Modal from '../../components/ui/Modal'
import Select from '../../components/ui/Select'
import Input from '../../components/ui/Input'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    propertyId: number
    onSuccess: () => void
}

export default function CreateInventoryModal({ isOpen, onClose, propertyId, onSuccess }: Props) {
    const [formData, setFormData] = useState({
        name: '',
        category: '',
        condition: 'good',
        purchase_date: '',
        purchase_price: '',
        note: '',
    })
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
            await inventoryApi.create(propertyId, {
                name: formData.name,
                category: formData.category || undefined,
                condition: formData.condition,
                purchase_date: formData.purchase_date || undefined,
                purchase_price: formData.purchase_price ? Number(formData.purchase_price) : undefined,
                note: formData.note || undefined,
            })
            setFormData({ name: '', category: '', condition: 'good', purchase_date: '', purchase_price: '', note: '' })
            onSuccess()
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Add Inventory Item" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Item Name"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    placeholder="e.g. Fridge, Washing machine"
                    error={errors.name?.[0]}
                    required
                />
                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Category"
                        name="category"
                        value={formData.category}
                        onChange={handleChange}
                        placeholder="e.g. Appliance, Furniture"
                        error={errors.category?.[0]}
                    />
                    <Select
                        label="Condition"
                        name="condition"
                        value={formData.condition}
                        onChange={handleChange}
                        options={[
                            { value: 'new', label: 'New' },
                            { value: 'good', label: 'Good' },
                            { value: 'fair', label: 'Fair' },
                            { value: 'poor', label: 'Poor' },
                            { value: 'broken', label: 'Broken' },
                        ]}
                        error={errors.condition?.[0]}
                    />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <Input
                        label="Purchase Date"
                        name="purchase_date"
                        type="date"
                        value={formData.purchase_date}
                        onChange={handleChange}
                        error={errors.purchase_date?.[0]}
                    />
                    <Input
                        label="Purchase Price (Kč)"
                        name="purchase_price"
                        type="number"
                        value={formData.purchase_price}
                        onChange={handleChange}
                        placeholder="12 000"
                        error={errors.purchase_price?.[0]}
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Note</label>
                    <textarea
                        name="note"
                        value={formData.note}
                        onChange={handleChange}
                        rows={2}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"
                        placeholder="Optional note..."
                    />
                </div>
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Creating...' : 'Add Item'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
