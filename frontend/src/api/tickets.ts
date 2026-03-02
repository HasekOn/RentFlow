import api from './axios'
import type {PaginatedResponse, Ticket, TicketComment} from '../types'

interface TicketFilters {
    status?: string
    priority?: string
    category?: string
    search?: string
    sort?: string
    page?: number
}

interface CreateTicketData {
    property_id: number
    title: string
    description: string
    category?: string
    priority?: string
}

interface UpdateTicketData {
    status?: string
    assigned_to?: number
    priority?: string
}

export const ticketsApi = {
    getAll: (filters?: TicketFilters) =>
        api.get<PaginatedResponse<Ticket>>('/tickets', {params: filters}),

    getOne: (id: number) =>
        api.get<Ticket>('/tickets/' + id),

    create: (data: CreateTicketData) =>
        api.post<Ticket>('/tickets', data),

    update: (id: number, data: UpdateTicketData) =>
        api.put<Ticket>('/tickets/' + id, data),

    delete: (id: number) =>
        api.delete('/tickets/' + id),

    // Comments
    getComments: (ticketId: number) =>
        api.get<TicketComment[]>('/tickets/' + ticketId + '/comments'),

    addComment: (ticketId: number, message: string) =>
        api.post<TicketComment>('/tickets/' + ticketId + '/comments', {message}),

    deleteComment: (ticketId: number, commentId: number) =>
        api.delete('/tickets/' + ticketId + '/comments/' + commentId),
}
