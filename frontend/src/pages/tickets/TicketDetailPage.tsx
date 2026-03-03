import * as React from 'react'
import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {ticketsApi} from '../../api/tickets'
import type {Ticket, TicketComment} from '../../types'
import {formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'

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
    const [newComment, setNewComment] = useState('')
    const [isLoading, setIsLoading] = useState(true)
    const [isSending, setIsSending] = useState(false)
    const [isUpdating, setIsUpdating] = useState(false)

    const loadTicket = async () => {
        try {
            const [ticketRes, commentsRes] = await Promise.all([
                ticketsApi.getOne(Number(id)),
                ticketsApi.getComments(Number(id)),
            ])
            setTicket(ticketRes.data)
            setComments(Array.isArray(commentsRes.data) ? commentsRes.data : [])
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
        if (!newComment.trim()) return
        setIsSending(true)

        try {
            await ticketsApi.addComment(Number(id), newComment.trim())
            setNewComment('')
            void loadTicket()
        } catch (error) {
            console.error('Failed to send comment:', error)
        } finally {
            setIsSending(false)
        }
    }

    const handleDeleteComment = async (commentId: number) => {
        if (!confirm('Delete this comment?')) return
        try {
            await ticketsApi.deleteComment(Number(id), commentId)
            void loadTicket()
        } catch (error) {
            console.error('Failed to delete comment:', error)
        }
    }

    const handleStatusChange = async (newStatus: string) => {
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

    if (isLoading) return <Spinner/>
    if (!ticket) return null

    return (
        <div>
            {/* Header */}
            <div className="flex items-center gap-4 mb-6">
                <button
                    onClick={() => navigate('/tickets')}
                    className="text-gray-400 hover:text-black transition text-lg"
                >
                    ←
                </button>
                <h1 className="text-4xl font-bold text-black">Ticket Detail</h1>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main — comments */}
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
                                                </div>
                                                <div
                                                    className={`flex items-center gap-2 mt-1 ${isOwn ? 'justify-end' : ''}`}>
                                                    <span className="text-xs text-gray-400">{comment.user?.name}</span>
                                                    <span className="text-xs text-gray-300">·</span>
                                                    <span
                                                        className="text-xs text-gray-400">{formatDate(comment.created_at)}</span>
                                                    {isOwn && (
                                                        <>
                                                            <span className="text-xs text-gray-300">·</span>
                                                            <button
                                                                onClick={() => handleDeleteComment(comment.id)}
                                                                className="text-xs text-red-400 hover:text-red-600 transition"
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
                            <form onSubmit={handleSendComment} className="mt-6 flex gap-3">
                                <input
                                    type="text"
                                    value={newComment}
                                    onChange={(e) => setNewComment(e.target.value)}
                                    placeholder="Write a comment..."
                                    className="flex-1 px-4 py-2.5 border border-gray-200 rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                                />
                                <Button type="submit" disabled={isSending || !newComment.trim()}>
                                    {isSending ? 'Sending...' : 'Send'}
                                </Button>
                            </form>
                        )}
                    </div>
                </div>

                {/* Sidebar — ticket info */}
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
                                <span className="text-sm font-semibold capitalize">{ticket.category || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Created</span>
                                <span className="text-sm font-semibold">{formatDate(ticket.created_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Reporter</span>
                                <span className="text-sm font-semibold">{ticket.tenant?.name || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Assigned to</span>
                                <span
                                    className="text-sm font-semibold">{ticket.assigned_user?.name || 'Unassigned'}</span>
                            </div>
                            {ticket.resolved_at && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Resolved</span>
                                    <span className="text-sm font-semibold">{formatDate(ticket.resolved_at)}</span>
                                </div>
                            )}
                            {ticket.resolution_time && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Resolution time</span>
                                    <span className="text-sm font-semibold">{ticket.resolution_time}h</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Actions — landlord only */}
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
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    )
}
