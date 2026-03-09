import api from './axios'
import type {PaginatedResponse, Ticket, TicketComment, TicketImage} from '../types'

interface TicketFilters {
    status?: string
    priority?: string
    category?: string
    property_id?: number
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

    addComment: (ticketId: number, message: string, attachment?: File) => {
        const formData = new FormData()

        formData.append('message', message)

        if (attachment) {
            formData.append('attachment', attachment)
        }

        return api.post<TicketComment>('/tickets/' + ticketId + '/comments', formData, {
            headers: {'Content-Type': 'multipart/form-data'},
        })
    },

    deleteComment: (ticketId: number, commentId: number) =>
        api.delete('/tickets/' + ticketId + '/comments/' + commentId),

    // Images
    getImages: (ticketId: number) =>
        api.get<TicketImage[]>('/tickets/' + ticketId + '/images'),

    uploadImage: (ticketId: number, formData: FormData) =>
        api.post('/tickets/' + ticketId + '/images', formData),

    deleteImage: (ticketId: number, imageId: number) =>
        api.delete('/tickets/' + ticketId + '/images/' + imageId),
}
