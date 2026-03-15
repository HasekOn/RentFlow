import * as React from 'react'
import { useState } from 'react'
import { expensesApi } from '../../api/expenses'
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

export default function CreateExpenseModal({ isOpen, onClose, propertyId, onSuccess }: Props) {
    const [formData, setFormData] = useState({
        type: 'maintenance',
        amount: '',
        expense_date: new Date().toISOString().split('T')[0],
        description: '',
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
            await expensesApi.create({
                property_id: propertyId,
                type: formData.type,
                amount: Number(formData.amount),
                expense_date: formData.expense_date,
                description: formData.description || undefined,
            })
            setFormData({
                type: 'maintenance',
                amount: '',
                expense_date: new Date().toISOString().split('T')[0],
                description: '',
            })
            onSuccess()
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Add Expense" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <Select
                        label="Type"
                        name="type"
                        value={formData.type}
                        onChange={handleChange}
                        options={[
                            { value: 'maintenance', label: 'Maintenance' },
                            { value: 'repair', label: 'Repair' },
                            { value: 'insurance', label: 'Insurance' },
                            { value: 'tax', label: 'Tax' },
                            { value: 'utility', label: 'Utility' },
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
                        placeholder="5 000"
                        error={errors.amount?.[0]}
                        required
                    />
                </div>
                <Input
                    label="Date"
                    name="expense_date"
                    type="date"
                    value={formData.expense_date}
                    onChange={handleChange}
                    error={errors.expense_date?.[0]}
                    required
                />
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                        rows={2}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"
                        placeholder="What was this expense for?"
                    />
                    {errors.description?.[0] && <p className="mt-1 text-xs text-red-600">{errors.description[0]}</p>}
                </div>
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Creating...' : 'Add Expense'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
