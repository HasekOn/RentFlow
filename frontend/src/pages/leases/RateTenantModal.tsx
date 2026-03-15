import * as React from 'react'
import { useState } from 'react'
import { ratingsApi } from '../../api/ratings'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    leaseId: number
    existingCategories: string[]
    onSuccess: () => void
}

const categories = [
    { value: 'overall', label: 'Overall', description: 'General impression of the tenant' },
    {
        value: 'apartment_condition',
        label: 'Apartment Condition',
        description: 'How well they maintained the apartment',
    },
    { value: 'communication', label: 'Communication', description: 'Responsiveness and clarity' },
    { value: 'rules', label: 'Rules Compliance', description: 'Following house rules and agreements' },
]

function StarInput({ value, onChange }: { value: number; onChange: (v: number) => void }) {
    const [hover, setHover] = useState(0)

    return (
        <div className="flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    onMouseEnter={() => setHover(star)}
                    onMouseLeave={() => setHover(0)}
                    onClick={() => onChange(star)}
                    className="text-2xl transition hover:scale-110 cursor-pointer"
                >
                    <span className={star <= (hover || value) ? 'text-yellow-400' : 'text-gray-200'}>★</span>
                </button>
            ))}
            {value > 0 && <span className="text-sm text-gray-500 ml-2">{value}/5</span>}
        </div>
    )
}

export default function RateTenantModal({ isOpen, onClose, leaseId, existingCategories, onSuccess }: Props) {
    const availableCategories = categories.filter((c) => !existingCategories.includes(c.value))

    const [scores, setScores] = useState<Record<string, number>>({})
    const [comments, setComments] = useState<Record<string, string>>({})
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState('')
    const [submitted, setSubmitted] = useState<string[]>([])

    const handleScoreChange = (category: string, score: number) => {
        setScores((prev) => ({ ...prev, [category]: score }))
    }

    const handleCommentChange = (category: string, comment: string) => {
        setComments((prev) => ({ ...prev, [category]: comment }))
    }

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        setError('')

        // Check at least one category is rated
        const toSubmit = availableCategories.filter(
            (c) => scores[c.value] && scores[c.value] > 0 && !submitted.includes(c.value),
        )

        if (toSubmit.length === 0) {
            setError('Please rate at least one category.')
            return
        }

        setIsLoading(true)

        try {
            for (const cat of toSubmit) {
                await ratingsApi.create(leaseId, {
                    category: cat.value,
                    score: scores[cat.value],
                    comment: comments[cat.value] || undefined,
                })
                setSubmitted((prev) => [...prev, cat.value])
            }
            onSuccess()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to submit ratings')
        } finally {
            setIsLoading(false)
        }
    }

    const handleClose = () => {
        setScores({})
        setComments({})
        setSubmitted([])
        setError('')
        onClose()
    }

    const allRated = availableCategories.length === 0

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="Rate Tenant" size="lg">
            {allRated ? (
                <div className="text-center py-8">
                    <span className="text-4xl">✅</span>
                    <p className="text-sm text-gray-500 mt-3">All categories have been rated for this lease.</p>
                    <Button variant="secondary" className="mt-4" onClick={handleClose}>
                        Close
                    </Button>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-5">
                    <p className="text-sm text-gray-500">
                        Rate the tenant across all categories. You can skip categories you don't want to rate.
                    </p>

                    {availableCategories.map((cat) => {
                        const isSubmittedAlready = submitted.includes(cat.value)

                        return (
                            <div
                                key={cat.value}
                                className={`p-4 border rounded-xl transition ${
                                    isSubmittedAlready
                                        ? 'border-green-200 bg-green-50/50 opacity-60'
                                        : scores[cat.value]
                                          ? 'border-yellow-200 bg-yellow-50/30'
                                          : 'border-gray-100'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1">
                                        <p className="text-sm font-semibold text-black">{cat.label}</p>
                                        <p className="text-xs text-gray-400 mt-0.5">{cat.description}</p>
                                    </div>
                                    {isSubmittedAlready ? (
                                        <span className="text-xs text-green-600 font-semibold">✓ Saved</span>
                                    ) : (
                                        <StarInput
                                            value={scores[cat.value] || 0}
                                            onChange={(v) => handleScoreChange(cat.value, v)}
                                        />
                                    )}
                                </div>
                                {!isSubmittedAlready && scores[cat.value] > 0 && (
                                    <textarea
                                        value={comments[cat.value] || ''}
                                        onChange={(e) => handleCommentChange(cat.value, e.target.value)}
                                        rows={2}
                                        className="w-full mt-3 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"
                                        placeholder="Optional comment..."
                                    />
                                )}
                            </div>
                        )
                    })}

                    {error && <p className="text-sm text-red-600">{error}</p>}

                    <div className="flex items-center justify-between pt-2">
                        <p className="text-xs text-gray-400">
                            {Object.values(scores).filter((s) => s > 0).length} of {availableCategories.length} rated
                        </p>
                        <div className="flex gap-3">
                            <Button variant="secondary" type="button" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isLoading || Object.values(scores).filter((s) => s > 0).length === 0}
                            >
                                {isLoading ? 'Submitting...' : 'Submit Ratings'}
                            </Button>
                        </div>
                    </div>
                </form>
            )}
        </Modal>
    )
}
