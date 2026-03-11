import * as React from 'react'
import { useState } from 'react'
import { metersApi } from '../../api/meters'
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

export default function CreateMeterModal({ isOpen, onClose, propertyId, onSuccess }: Props) {
    const [formData, setFormData] = useState({
        meter_type: 'water',
        serial_number: '',
        location: '',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            await metersApi.create(propertyId, formData)
            setFormData({ meter_type: 'water', serial_number: '', location: '' })
            onSuccess()
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Add Meter" size="sm">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Select
                    label="Meter Type"
                    name="meter_type"
                    value={formData.meter_type}
                    onChange={handleChange}
                    options={[
                        { value: 'water', label: '💧 Water' },
                        { value: 'electricity', label: '⚡ Electricity' },
                        { value: 'gas', label: '🔥 Gas' },
                        { value: 'heat', label: '🌡️ Heat' },
                    ]}
                    error={errors.meter_type?.[0]}
                />
                <Input
                    label="Serial Number"
                    name="serial_number"
                    value={formData.serial_number}
                    onChange={handleChange}
                    placeholder="e.g. 667835472"
                    error={errors.serial_number?.[0]}
                />
                <Input
                    label="Location"
                    name="location"
                    value={formData.location}
                    onChange={handleChange}
                    placeholder="e.g. Kitchen, Bathroom"
                    error={errors.location?.[0]}
                />
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading}>
                        {isLoading ? 'Creating...' : 'Add Meter'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
