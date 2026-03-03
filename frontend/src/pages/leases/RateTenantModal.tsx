import * as React from 'react'
import {useState} from 'react'
import {ratingsApi} from '../../api/ratings'
import Modal from '../../components/ui/Modal'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    leaseId: number
    existingCategories: string[]
    onSuccess: () => void
}

const categories = [
    {value: 'overall', label: 'Overall'},
    {value: 'apartment_condition', label: 'Apartment Condition'},
    {value: 'communication', label: 'Communication'},
    {value: 'rules', label: 'Rules Compliance'},
]

export default function RateTenantModal({isOpen, onClose, leaseId, existingCategories, onSuccess}: Props) {
    const [category, setCategory] = useState('')
    const [score, setScore] = useState(5)
    const [comment, setComment] = useState('')
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState('')

    const availableCategories = categories.filter((c) => !existingCategories.includes(c.value))

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        if (!category) return

        setError('')
        setIsLoading(true)

        try {
            await ratingsApi.create(leaseId, {
                category,
                score,
                comment: comment || undefined,
            })
            setCategory('')
            setScore(5)
            setComment('')
            onSuccess()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to submit rating')
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Rate Tenant" size="md">
            {availableCategories.length === 0 ? (
                <div className="text-center py-6">
                    <span className="text-3xl">✅</span>
                    <p className="text-sm text-gray-500 mt-2">All categories have been rated</p>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Select
                        label="Category"
                        name="category"
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        options={[
                            {value: '', label: 'Select category...'},
                            ...availableCategories,
                        ]}
                    />

                    {/* Star rating */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Score</label>
                        <div className="flex items-center gap-1">
                            {[1, 2, 3, 4, 5].map((star) => (
                                <button
                                    key={star}
                                    type="button"
                                    onClick={() => setScore(star)}
                                    className={`text-2xl transition hover:scale-110 ${
                                        star <= score ? 'text-yellow-400' : 'text-gray-200'
                                    }`}
                                >
                                    ★
                                </button>
                            ))}
                            <span className="text-sm text-gray-500 ml-2">{score}/5</span>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Comment</label>
                        <textarea
                            value={comment}
                            onChange={(e) => setComment(e.target.value)}
                            rows={3}
                            className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition resize-none"
                            placeholder="Optional comment..."
                        />
                    </div>

                    {error && <p className="text-sm text-red-600">{error}</p>}

                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" type="button" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={isLoading || !category}>
                            {isLoading ? 'Submitting...' : 'Submit Rating'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    )
}
