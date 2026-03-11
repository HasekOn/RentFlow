import api from './axios'
import type { PaginatedResponse, Payment } from '../types'

interface PaymentFilters {
    status?: string
    type?: string
    lease_id?: number
    sort?: string
    page?: number
    date_from?: string
    date_to?: string
}

interface CreatePaymentData {
    lease_id: number
    type: string
    amount: number
    due_date: string
    paid_date?: string
    variable_symbol?: string
    note?: string
}

export const paymentsApi = {
    getAll: (filters?: PaymentFilters) => api.get<PaginatedResponse<Payment>>('/payments', { params: filters }),

    getOne: (id: number) => api.get<Payment>('/payments/' + id),

    create: (data: CreatePaymentData) => api.post<Payment>('/payments', data),

    update: (id: number, data: Partial<CreatePaymentData>) => api.put<Payment>('/payments/' + id, data),

    delete: (id: number) => api.delete('/payments/' + id),

    markPaid: (id: number) => api.put('/payments/' + id + '/mark-paid'),

    importCsv: (file: File) => {
        const formData = new FormData()
        formData.append('file', file)
        return api.post('/payments/import-csv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
    },

    generateMonthly: () => api.post('/payments/generate-monthly'),
}
