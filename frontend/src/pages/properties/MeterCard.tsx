import * as React from 'react'
import {useState} from 'react'
import {metersApi} from '../../api/meters'
import type {Meter} from '../../types'
import {formatDate} from '../../utils/format'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'

const meterIcons: Record<string, string> = {
    water: '💧',
    electricity: '⚡',
    gas: '🔥',
    heat: '🌡️',
}

const meterUnits: Record<string, string> = {
    water: 'm³',
    electricity: 'kWh',
    gas: 'm³',
    heat: 'GJ',
}

interface Props {
    meter: Meter
    canEdit: boolean
    onUpdate: () => void
}

export default function MeterCard({meter, canEdit, onUpdate}: Props) {
    const [showForm, setShowForm] = useState(false)
    const [readingValue, setReadingValue] = useState('')
    const [readingDate, setReadingDate] = useState(new Date().toISOString().split('T')[0])
    const [isSubmitting, setIsSubmitting] = useState(false)
    const [error, setError] = useState('')

    const handleSubmit = async (e: React.FormEvent) => {
        setError('')
        e.preventDefault()

        if (!readingValue) {
            return
        }

        setIsSubmitting(true)

        try {
            await metersApi.addReading(meter.id, {
                reading_value: Number(readingValue),
                reading_date: readingDate,
            })
            setReadingValue('')
            setShowForm(false)
            onUpdate()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to add reading')
        } finally {
            setIsSubmitting(false)
        }
    }

    const latestReading = meter.latest_reading
    const icon = meterIcons[meter.meter_type] || '📊'
    const unit = meterUnits[meter.meter_type] || ''

    return (
        <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div className="text-center mb-3">
                <span className="text-3xl">{icon}</span>
                <h3 className="text-sm font-bold text-black mt-1 capitalize">{meter.meter_type}</h3>
            </div>

            {latestReading ? (
                <div className="text-center mb-3">
                    <p className="text-2xl font-bold text-black">
                        {Number(latestReading.value).toLocaleString('cs-CZ')} {unit}
                    </p>
                    <p className="text-xs text-gray-400 mt-1">
                        Last reading: {formatDate(latestReading.date)}
                    </p>
                </div>
            ) : (
                <div className="text-center mb-3">
                    <p className="text-sm text-gray-400">No readings yet</p>
                </div>
            )}

            {meter.serial_number && (
                <p className="text-xs text-gray-400 text-center mb-3">
                    S/N: {meter.serial_number}
                </p>
            )}

            {showForm ? (
                <form onSubmit={handleSubmit} className="space-y-2 mt-3">
                    <Input
                        type="number"
                        value={readingValue}
                        onChange={(e) => setReadingValue(e.target.value)}
                        placeholder={`Value (${unit})`}
                        required
                    />
                    {error && <p className="text-xs text-red-600">{error}</p>}
                    <Input
                        type="date"
                        value={readingDate}
                        onChange={(e) => setReadingDate(e.target.value)}
                        required
                    />
                    <div className="flex gap-2">
                        <Button type="submit" size="sm" className="flex-1" disabled={isSubmitting}>
                            {isSubmitting ? 'Saving...' : 'Save'}
                        </Button>
                        <Button type="button" variant="secondary" size="sm" onClick={() => setShowForm(false)}>
                            Cancel
                        </Button>
                    </div>
                </form>
            ) : canEdit ? (
                <Button
                    variant="primary"
                    size="sm"
                    className="w-full mt-2"
                    onClick={() => setShowForm(true)}
                >
                    Log Reading
                </Button>
            ) : null}
        </div>
    )
}
