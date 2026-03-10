import * as React from 'react'
import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {ticketsApi} from '../../api/tickets'
import type {Ticket, TicketComment, TicketImage} from '../../types'
import {formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Select from '../../components/ui/Select'
import {useConfirm} from '../../hooks/useConfirm'
import {usersApi} from '../../api/users'

const statusVariant = (status: string) => {
    switch (status) {
        case 'new':
            return 'pink'
        case 'in_progress':
            return 'yellow'
        case 'resolved':
            return 'green'
        case 'rejected':
            return 'red'
        default:
            return 'gray' as const
    }
}

const statusLabel = (status: string) => {
    switch (status) {
        case 'new':
            return 'New'
        case 'in_progress':
            return 'In Progress'
        case 'resolved':
            return 'Done'
        case 'rejected':
            return 'Rejected'
        default:
            return status
    }
}

export default function TicketDetailPage() {
    const {id} = useParams<{ id: string }>()
    const navigate = useNavigate()
    const {user, isLandlord, isManager} = useAuth()
    const [ticket, setTicket] = useState<Ticket | null>(null)
    const [comments, setComments] = useState<TicketComment[]>([])
    const [ticketImages, setTicketImages] = useState<TicketImage[]>([])
    const [newComment, setNewComment] = useState('')
    const [attachment, setAttachment] = useState<File | null>(null)
    const [attachmentPreview, setAttachmentPreview] = useState<string | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [isSending, setIsSending] = useState(false)
    const [isUpdating, setIsUpdating] = useState(false)
    const [isUploadingImage, setIsUploadingImage] = useState(false)
    const [lightboxUrl, setLightboxUrl] = useState<string | null>(null)
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)
    const [showEditModal, setShowEditModal] = useState(false)
    const {confirm: showConfirm, dialog} = useConfirm()

    const loadTicket = async () => {
        try {
            const [ticketRes, commentsRes] = await Promise.all([
                ticketsApi.getOne(Number(id)),
                ticketsApi.getComments(Number(id)),
            ])
            setTicket(ticketRes.data)
            setComments(Array.isArray(commentsRes.data) ? commentsRes.data : [])
            setTicketImages(ticketRes.data.images || [])
        } catch {
            navigate('/tickets')
        } finally {
            setIsLoading(false)
        }
    }

    useEffect(() => {
        void loadTicket()
    }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

    const handleSendComment = async (e: React.FormEvent) => {
        e.preventDefault()
        if (!newComment.trim() && !attachment) return
        setIsSending(true)

        try {
            await ticketsApi.addComment(Number(id), newComment.trim(), attachment || undefined)
            setNewComment('')
            setAttachment(null)
            setAttachmentPreview(null)
            void loadTicket()
        } catch (error) {
            console.error('Failed to send comment:', error)
        } finally {
            setIsSending(false)
        }
    }

    const handleAttachmentChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (file) {
            setAttachment(file)
            setAttachmentPreview(URL.createObjectURL(file))
        }
    }

    const removeAttachment = () => {
        setAttachment(null)
        setAttachmentPreview(null)
    }

    const handleDeleteComment = async (commentId: number) => {
        const ok = await showConfirm({
            title: 'Delete Comment',
            message: 'Are you sure you want to delete this comment?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return
        try {
            await ticketsApi.deleteComment(Number(id), commentId)
            void loadTicket()
        } catch (error) {
            console.error('Failed to delete comment:', error)
        }
    }

    const handleStatusChange = async (newStatus: string) => {
        const ok = await showConfirm({
            title: newStatus === 'resolved' ? 'Resolve Ticket' : newStatus === 'rejected' ? 'Reject Ticket' : 'Update Status',
            message: `Are you sure you want to mark this ticket as "${newStatus.replace('_', ' ')}"?`,
            confirmLabel: newStatus === 'resolved' ? 'Resolve' : newStatus === 'rejected' ? 'Reject' : 'Confirm',
            variant: newStatus === 'rejected' ? 'danger' : 'primary',
        })
        if (!ok) return

        setIsUpdating(true)
        try {
            await ticketsApi.update(Number(id), {status: newStatus})
            void loadTicket()
        } catch (error) {
            console.error('Failed to update status:', error)
        } finally {
            setIsUpdating(false)
        }
    }

    const handleDelete = async () => {
        const ok = await showConfirm({
            title: 'Delete Ticket',
            message: 'Are you sure you want to delete this ticket? This cannot be undone.',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return
        try {
            await ticketsApi.delete(Number(id))
            navigate('/tickets')
        } catch (error) {
            console.error('Failed to delete ticket:', error)
        }
    }

    const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (!file) return
        setIsUploadingImage(true)
        try {
            const formData = new FormData()
            formData.append('image', file)
            await ticketsApi.uploadImage(Number(id), formData)
            void loadTicket()
        } catch (error) {
            console.error('Failed to upload image:', error)
        } finally {
            setIsUploadingImage(false)
            e.target.value = ''
        }
    }

    const handleDeleteTicketImage = async (imageId: number) => {
        const ok = await showConfirm({
            title: 'Delete Photo',
            message: 'Are you sure you want to delete this photo?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return
        try {
            await ticketsApi.deleteImage(Number(id), imageId)
            void loadTicket()
        } catch (error) {
            console.error('Failed to delete image:', error)
        }
    }

    if (isLoading) return <Spinner/>
    if (!ticket) return null

    const isAuthor = ticket.tenant?.id === user?.id
    const canEdit = isLandlord || isManager || (isAuthor && ticket.status === 'new')

    return (
        <div>
            {/* Comment attachment lightbox */}
            {lightboxUrl && (
                <div className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center"
                     onClick={() => setLightboxUrl(null)}>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            setLightboxUrl(null)
                        }}
                        className="absolute top-6 right-6 text-white text-3xl hover:opacity-70 transition cursor-pointer"
                    >✕
                    </button>
                    <img
                        src={lightboxUrl}
                        alt="Attachment"
                        className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
                        onClick={(e) => e.stopPropagation()}
                    />
                </div>
            )}

            {/* Ticket images lightbox with navigation */}
            {lightboxIndex !== null && ticketImages.length > 0 && (
                <div className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center"
                     onClick={() => setLightboxIndex(null)}>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            setLightboxIndex(null)
                        }}
                        className="absolute top-6 right-6 text-white text-3xl hover:opacity-70 transition cursor-pointer"
                    >✕
                    </button>
                    {lightboxIndex > 0 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex - 1)
                            }}
                            className="absolute left-6 text-white text-4xl hover:opacity-70 transition cursor-pointer"
                        >‹
                        </button>
                    )}
                    {lightboxIndex < ticketImages.length - 1 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex + 1)
                            }}
                            className="absolute right-6 text-white text-4xl hover:opacity-70 transition cursor-pointer"
                        >›
                        </button>
                    )}
                    <img
                        src={ticketImages[lightboxIndex].image_url}
                        alt={ticketImages[lightboxIndex].description || 'Ticket photo'}
                        className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
                        onClick={(e) => e.stopPropagation()}
                    />
                    <div className="absolute bottom-6 flex items-center gap-4">
                        <p className="text-white text-sm">
                            {lightboxIndex + 1} / {ticketImages.length}
                            {ticketImages[lightboxIndex].uploader && ` — ${ticketImages[lightboxIndex].uploader!.name}`}
                        </p>
                        {(isLandlord || ticketImages[lightboxIndex].uploader?.id === user?.id) && (
                            <button
                                onClick={(e) => {
                                    e.stopPropagation()
                                    const imgId = ticketImages[lightboxIndex].id
                                    setLightboxIndex(null)
                                    handleDeleteTicketImage(imgId)
                                }}
                                className="text-red-400 hover:text-red-300 text-sm transition cursor-pointer"
                            >🗑 Delete
                            </button>
                        )}
                    </div>
                </div>
            )}

            {/* Header */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => navigate('/tickets')}
                        className="text-gray-400 hover:text-black transition text-lg cursor-pointer"
                    >←
                    </button>
                    <h1 className="text-2xl sm:text-4xl font-bold text-black">Ticket Detail</h1>
                </div>
                {canEdit && (
                    <Button variant="secondary" onClick={() => setShowEditModal(true)}>
                        ✏️ Edit
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main column */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Ticket info */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <div className="flex items-start justify-between">
                            <div>
                                <h2 className="text-xl font-bold text-black">{ticket.title}</h2>
                                <p className="text-sm text-gray-500 mt-1">{ticket.property?.address}</p>
                            </div>
                            <Badge variant={statusVariant(ticket.status)}>
                                {statusLabel(ticket.status)}
                            </Badge>
                        </div>
                        <p className="text-sm text-gray-600 mt-4 leading-relaxed">
                            {ticket.description}
                        </p>
                    </div>

                    {/* Photos */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-bold text-black">
                                Photos ({ticketImages.length})
                            </h2>
                            {ticket.status !== 'resolved' && ticket.status !== 'rejected' && (
                                <>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => document.getElementById('ticket-image-upload')?.click()}
                                        disabled={isUploadingImage}
                                    >
                                        {isUploadingImage ? 'Uploading...' : '📷 Add Photo'}
                                    </Button>
                                    <input
                                        id="ticket-image-upload"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={handleImageUpload}
                                        className="hidden"
                                    />
                                </>
                            )}
                        </div>
                        {ticketImages.length === 0 ? (
                            <p className="text-sm text-gray-400 text-center py-4">No photos attached</p>
                        ) : (
                            <div className="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                {ticketImages.map((img, idx) => (
                                    <div
                                        key={img.id}
                                        className="aspect-square cursor-pointer overflow-hidden rounded-xl relative group"
                                        onClick={() => setLightboxIndex(idx)}
                                    >
                                        <img
                                            src={img.image_url}
                                            alt={img.description || 'Ticket photo'}
                                            className="w-full h-full object-cover hover:scale-105 transition duration-300"
                                        />
                                        <div
                                            className="absolute bottom-0 left-0 right-0 bg-linear-to-t from-black/50 to-transparent p-1.5 opacity-0 group-hover:opacity-100 transition">
                                            <p className="text-white text-xs truncate">{img.uploader?.name}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Comments */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-4">
                            Comments ({comments.length})
                        </h2>

                        {comments.length === 0 ? (
                            <p className="text-sm text-gray-400 text-center py-6">No comments yet</p>
                        ) : (
                            <div className="space-y-4">
                                {comments.map((comment) => {
                                    const isOwn = comment.user?.id === user?.id

                                    return (
                                        <div key={comment.id}
                                             className={`flex gap-3 ${isOwn ? 'flex-row-reverse' : ''}`}>
                                            <div
                                                className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0">
                                                {comment.user?.name?.charAt(0)?.toUpperCase() || '?'}
                                            </div>
                                            <div className={`max-w-[75%] ${isOwn ? 'items-end' : ''}`}>
                                                <div
                                                    className={`rounded-2xl px-4 py-3 ${isOwn ? 'bg-black text-white' : 'bg-gray-100 text-gray-800'}`}>
                                                    <p className="text-sm">{comment.message}</p>
                                                    {comment.attachment_url && (
                                                        <img
                                                            src={comment.attachment_url}
                                                            alt="Attachment"
                                                            className="mt-2 max-h-48 rounded-lg cursor-pointer hover:opacity-90 transition"
                                                            onClick={(e) => {
                                                                e.stopPropagation()
                                                                setLightboxUrl(comment.attachment_url!)
                                                            }}
                                                        />
                                                    )}
                                                </div>
                                                <div
                                                    className={`flex items-center gap-2 mt-1 ${isOwn ? 'justify-end' : ''}`}>
                                                    <span
                                                        className="text-xs text-gray-400">{comment.user?.name}</span>
                                                    <span className="text-xs text-gray-300">·</span>
                                                    <span
                                                        className="text-xs text-gray-400">{formatDate(comment.created_at)}</span>
                                                    {isOwn && (
                                                        <>
                                                            <span className="text-xs text-gray-300">·</span>
                                                            <button
                                                                onClick={() => handleDeleteComment(comment.id)}
                                                                className="text-xs text-red-400 hover:text-red-600 transition cursor-pointer"
                                                            >
                                                                Delete
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )
                                })}
                            </div>
                        )}

                        {/* New comment */}
                        {ticket.status !== 'resolved' && ticket.status !== 'rejected' && (
                            <div className="mt-6">
                                {/* Attachment preview */}
                                {attachmentPreview && (
                                    <div className="mb-3 relative inline-block">
                                        <img src={attachmentPreview} alt="Preview"
                                             className="h-20 rounded-lg object-cover border border-gray-200"/>
                                        <button
                                            onClick={removeAttachment}
                                            className="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600 cursor-pointer"
                                        >✕
                                        </button>
                                    </div>
                                )}
                                <form onSubmit={handleSendComment} className="flex gap-3">
                                    <button
                                        type="button"
                                        onClick={() => document.getElementById('comment-attachment')?.click()}
                                        className="px-3 py-2.5 border border-gray-200 rounded-full text-sm hover:bg-gray-50 transition shrink-0 cursor-pointer"
                                        title="Attach photo"
                                    >📷
                                    </button>
                                    <input
                                        id="comment-attachment"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={handleAttachmentChange}
                                        className="hidden"
                                    />
                                    <input
                                        type="text"
                                        value={newComment}
                                        onChange={(e) => setNewComment(e.target.value)}
                                        placeholder="Write a comment..."
                                        className="flex-1 px-4 py-2.5 border border-gray-200 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                                    />
                                    <Button type="submit"
                                            disabled={isSending || (!newComment.trim() && !attachment)}>
                                        {isSending ? 'Sending...' : 'Send'}
                                    </Button>
                                </form>
                            </div>
                        )}
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Details */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-3">Details</h2>
                        <div className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Priority</span>
                                <span className="text-sm font-semibold capitalize">{ticket.priority}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Category</span>
                                <span
                                    className="text-sm font-semibold capitalize">{ticket.category || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Created</span>
                                <span className="text-sm font-semibold">{formatDate(ticket.created_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Reporter</span>
                                <span
                                    className="text-sm font-semibold">{ticket.tenant?.name || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Assigned to</span>
                                <span
                                    className="text-sm font-semibold">{ticket.assigned_user?.name || 'Unassigned'}</span>
                            </div>
                            {ticket.resolved_at && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Resolved</span>
                                    <span
                                        className="text-sm font-semibold">{formatDate(ticket.resolved_at)}</span>
                                </div>
                            )}
                            {ticket.resolution_time && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Resolution time</span>
                                    <span
                                        className="text-sm font-semibold">{ticket.resolution_time}h</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Actions — landlord/manager */}
                    {(isLandlord || isManager) && ticket.status !== 'resolved' && ticket.status !== 'rejected' && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Actions</h2>
                            <div className="space-y-2">
                                {ticket.status === 'new' && (
                                    <Button
                                        variant="primary"
                                        className="w-full"
                                        onClick={() => handleStatusChange('in_progress')}
                                        disabled={isUpdating}
                                    >
                                        Mark In Progress
                                    </Button>
                                )}
                                {(ticket.status === 'new' || ticket.status === 'in_progress') && (
                                    <>
                                        <Button
                                            variant="success"
                                            className="w-full"
                                            onClick={() => handleStatusChange('resolved')}
                                            disabled={isUpdating}
                                        >
                                            Resolve
                                        </Button>
                                        <Button
                                            variant="danger"
                                            className="w-full"
                                            onClick={() => handleStatusChange('rejected')}
                                            disabled={isUpdating}
                                        >
                                            Reject
                                        </Button>
                                    </>
                                )}
                                {isLandlord && (
                                    <Button variant="danger" className="w-full" onClick={handleDelete}>
                                        Delete Ticket
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Edit Modal */}
            {ticket && (
                <EditTicketModal
                    isOpen={showEditModal}
                    onClose={() => setShowEditModal(false)}
                    ticket={ticket}
                    isAuthor={isAuthor}
                    isLandlordOrManager={isLandlord || isManager}
                    onSuccess={() => {
                        setShowEditModal(false)
                        void loadTicket()
                    }}
                />
            )}
            {dialog}
        </div>
    )
}

// ─── Edit Ticket Modal ──────────────────────────
interface EditTicketModalProps {
    isOpen: boolean
    onClose: () => void
    ticket: Ticket
    isAuthor: boolean
    isLandlordOrManager: boolean
    onSuccess: () => void
}

function EditTicketModal({isOpen, onClose, ticket, isAuthor, isLandlordOrManager, onSuccess}: EditTicketModalProps) {
    const [formData, setFormData] = useState({
        title: '',
        description: '',
        priority: '',
        category: '',
        assigned_to: '',
    })
    const [managers, setManagers] = useState<Array<{ id: number; name: string; email: string }>>([])
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)

    useEffect(() => {
        if (ticket && isOpen) {
            setFormData({
                title: ticket.title || '',
                description: ticket.description || '',
                priority: ticket.priority || 'medium',
                category: ticket.category || '',
                assigned_to: ticket.assigned_user?.id?.toString() || '',
            })

            // Load managers for assignment
            if (isLandlordOrManager) {
                usersApi.getManagers().then((res) => {
                    setManagers(res.data)
                }).catch(console.error)
            }
        }
    }, [ticket, isOpen, isLandlordOrManager])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        setFormData((prev) => ({...prev, [e.target.name]: e.target.value}))
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)

        try {
            const updateData: Record<string, unknown> = {}

            if (isAuthor && ticket.status === 'new') {
                updateData.title = formData.title
                updateData.description = formData.description
            }

            if (isLandlordOrManager) {
                updateData.priority = formData.priority
                updateData.category = formData.category || undefined
                updateData.assigned_to = formData.assigned_to ? Number(formData.assigned_to) : null
            }

            await ticketsApi.update(ticket.id, updateData as any)
            onSuccess()
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    const canEditContent = isAuthor && ticket.status === 'new'

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Edit Ticket" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                {canEditContent && (
                    <>
                        <Input
                            label="Title"
                            name="title"
                            value={formData.title}
                            onChange={handleChange}
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
                                required
                            />
                            {errors.description?.[0] && (
                                <p className="mt-1 text-xs text-red-600">{errors.description[0]}</p>
                            )}
                        </div>
                    </>
                )}

                {isLandlordOrManager && (
                    <>
                        <div className="grid grid-cols-2 gap-4">
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
                            <Select
                                label="Category"
                                name="category"
                                value={formData.category}
                                onChange={handleChange}
                                placeholder="Select..."
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
                        </div>
                        <Select
                            label="Assign to Manager"
                            name="assigned_to"
                            value={formData.assigned_to}
                            onChange={handleChange}
                            placeholder="Unassigned"
                            options={managers.map((m) => ({
                                value: String(m.id),
                                label: `${m.name} (${m.email})`,
                            }))}
                            error={errors.assigned_to?.[0]}
                        />
                    </>
                )}

                {!canEditContent && !isLandlordOrManager && (
                    <p className="text-sm text-gray-500 text-center py-4">
                        Tickets can only be edited while status is "New".
                    </p>
                )}

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancel</Button>
                    {(canEditContent || isLandlordOrManager) && (
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? 'Saving...' : 'Save Changes'}
                        </Button>
                    )}
                </div>
            </form>
        </Modal>
    )
}
