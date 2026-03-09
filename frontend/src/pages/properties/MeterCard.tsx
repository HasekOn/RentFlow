import * as React from 'react'
import {useState} from 'react'
import {metersApi} from '../../api/meters'
import type {Meter} from '../../types'
import {formatDate} from '../../utils/format'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'
import {useConfirm} from '../../hooks/useConfirm'

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
    isLandlord: boolean
    onUpdate: () => void
}

export default function MeterCard({meter, canEdit, isLandlord, onUpdate}: Props) {
    const [showForm, setShowForm] = useState(false)
    const [showEditForm, setShowEditForm] = useState(false)
    const [readingValue, setReadingValue] = useState('')
    const [readingDate, setReadingDate] = useState(new Date().toISOString().split('T')[0])
    const [serialNumber, setSerialNumber] = useState(meter.serial_number || '')
    const [location, setLocation] = useState(meter.location || '')
    const [isSubmitting, setIsSubmitting] = useState(false)
    const [error, setError] = useState('')
    const {confirm: showConfirm, dialog} = useConfirm()

    const handleSubmitReading = async (e: React.FormEvent) => {
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

    const handleEditMeter = async (e: React.FormEvent) => {
        e.preventDefault()
        setIsSubmitting(true)
        setError('')

        try {
            await metersApi.update(meter.id, {
                serial_number: serialNumber || undefined,
                location: location || undefined,
            })
            setShowEditForm(false)
            onUpdate()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to update meter')
        } finally {
            setIsSubmitting(false)
        }
    }

    const handleDelete = async () => {
        const ok = await showConfirm({
            title: 'Delete Meter',
            message: `Delete this ${meter.meter_type} meter? All readings will be lost.`,
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return

        try {
            await metersApi.delete(meter.id)
            onUpdate()
        } catch (err: any) {
            console.error('Failed to delete meter:', err)
        }
    }

    const latestReading = meter.latest_reading
    const icon = meterIcons[meter.meter_type] || '📊'
    const unit = meterUnits[meter.meter_type] || ''

    return (
        <>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 relative">
                {/* Edit/Delete buttons — landlord only */}
                {isLandlord && (
                    <div className="absolute top-3 right-3 flex items-center gap-1">
                        <button
                            onClick={() => {
                                setSerialNumber(meter.serial_number || '')
                                setLocation(meter.location || '')
                                setShowEditForm(!showEditForm)
                                setShowForm(false)
                            }}
                            className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-600"
                            title="Edit meter"
                        >✏️
                        </button>
                        <button
                            onClick={handleDelete}
                            className="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-50 transition text-gray-400 hover:text-red-500"
                            title="Delete meter"
                        >🗑
                        </button>
                    </div>
                )}

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
                    <p className="text-xs text-gray-400 text-center mb-1">
                        S/N: {meter.serial_number}
                    </p>
                )}
                {meter.location && (
                    <p className="text-xs text-gray-400 text-center mb-3">
                        📍 {meter.location}
                    </p>
                )}

                {/* Edit meter form — landlord only */}
                {showEditForm && isLandlord && (
                    <form onSubmit={handleEditMeter} className="space-y-2 mt-3 border-t border-gray-100 pt-3">
                        <Input
                            type="text"
                            value={serialNumber}
                            onChange={(e) => setSerialNumber(e.target.value)}
                            placeholder="Serial number"
                        />
                        <Input
                            type="text"
                            value={location}
                            onChange={(e) => setLocation(e.target.value)}
                            placeholder="Location (e.g. Kitchen)"
                        />
                        {error && <p className="text-xs text-red-600">{error}</p>}
                        <div className="flex gap-2">
                            <Button type="submit" size="sm" className="flex-1" disabled={isSubmitting}>
                                {isSubmitting ? 'Saving...' : 'Save'}
                            </Button>
                            <Button type="button" variant="secondary" size="sm"
                                    onClick={() => setShowEditForm(false)}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                )}

                {/* Log reading form */}
                {showForm ? (
                    <form onSubmit={handleSubmitReading} className="space-y-2 mt-3">
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
                ) : canEdit && !showEditForm ? (
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
            {dialog}
        </>
    )
}
