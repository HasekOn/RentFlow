import * as React from 'react'
import { useState } from 'react'
import { noticesApi } from '../../api/notices'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    propertyId: number
    onSuccess: () => void
}

export default function CreateNoticeModal({ isOpen, onClose, propertyId, onSuccess }: Props) {
    const [title, setTitle] = useState('')
    const [content, setContent] = useState('')
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState('')

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        if (!title.trim() || !content.trim()) return

        setError('')
        setIsLoading(true)

        try {
            await noticesApi.create(propertyId, { title: title.trim(), content: content.trim() })
            setTitle('')
            setContent('')
            onSuccess()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to create notice')
        } finally {
            setIsLoading(false)
        }
    }

    const handleClose = () => {
        setTitle('')
        setContent('')
        setError('')
        onClose()
    }

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="New Notice" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Title"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="e.g. Hot water outage March 15"
                    required
                />

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Content</label>
                    <textarea
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        rows={4}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition resize-none"
                        placeholder="Describe the notice in detail..."
                        required
                    />
                </div>

                {error && <p className="text-sm text-red-600">{error}</p>}

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={handleClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading || !title.trim() || !content.trim()}>
                        {isLoading ? 'Creating...' : 'Post Notice'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
