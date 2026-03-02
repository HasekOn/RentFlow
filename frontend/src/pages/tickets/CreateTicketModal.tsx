import {useEffect, useState} from 'react'
import {ticketsApi} from '../../api/tickets'
import {propertiesApi} from '../../api/properties'
import type {ApiError, Property} from '../../types'
import {AxiosError} from 'axios'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    onSuccess: () => void
}

export default function CreateTicketModal({isOpen, onClose, onSuccess}: Props) {
    const [properties, setProperties] = useState<Property[]>([])
    const [formData, setFormData] = useState({
        property_id: '',
        title: '',
        description: '',
        category: '',
        priority: 'medium',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    useEffect(() => {
        if (isOpen) {
            propertiesApi.getAll().then((res) => {
                setProperties(res.data.data)
            }).catch(console.error)
        }
    }, [isOpen])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        setFormData((prev) => ({...prev, [e.target.name]: e.target.value}))
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            await ticketsApi.create({
                property_id: Number(formData.property_id),
                title: formData.title,
                description: formData.description,
                category: formData.category || undefined,
                priority: formData.priority || undefined,
            })
            setFormData({property_id: '', title: '', description: '', category: '', priority: 'medium'})
            onSuccess()
        } catch (err) {
            const axiosError = err as AxiosError<ApiError>
            setErrors(axiosError.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Create Ticket" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Select
                    label="Property"
                    name="property_id"
                    value={formData.property_id}
                    onChange={handleChange}
                    placeholder="Select property..."
                    options={properties.map((p) => ({value: String(p.id), label: p.address}))}
                    error={errors.property_id?.[0]}
                />

                <Input
                    label="Title"
                    name="title"
                    value={formData.title}
                    onChange={handleChange}
                    placeholder="Short summary of the issue"
                    error={errors.title?.[0]}
                    required
                />

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea
                        name="description"
                        value={formData.description}
                        onChange={handleChange}
                        rows={4}
                        className={`w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition resize-none ${errors.description ? 'border-red-300' : 'border-gray-200'}`}
                        placeholder="Describe the issue in detail (min 10 characters)..."
                        required
                    />
                    {errors.description?.[0] && (
                        <p className="mt-1 text-xs text-red-600">{errors.description[0]}</p>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Select
                        label="Category"
                        name="category"
                        value={formData.category}
                        onChange={handleChange}
                        placeholder="Select category..."
                        options={[
                            {value: 'plumbing', label: 'Plumbing'},
                            {value: 'electrical', label: 'Electrical'},
                            {value: 'heating', label: 'Heating'},
                            {value: 'structural', label: 'Structural'},
                            {value: 'appliance', label: 'Appliance'},
                            {value: 'other', label: 'Other'},
                        ]}
                        error={errors.category?.[0]}
                    />
                    <Select
                        label="Priority"
                        name="priority"
                        value={formData.priority}
                        onChange={handleChange}
                        options={[
                            {value: 'low', label: 'Low'},
                            {value: 'medium', label: 'Medium'},
                            {value: 'high', label: 'High'},
                            {value: 'urgent', label: 'Urgent'},
                        ]}
                        error={errors.priority?.[0]}
                    />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Creating...' : 'Create Ticket'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
