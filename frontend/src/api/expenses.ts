import api from './axios'
import type { Expense, PaginatedResponse } from '../types'

interface ExpenseFilters {
    property_id?: number
    type?: string
    sort?: string
    page?: number
}

interface ExpenseData {
    property_id?: number
    type: string
    amount: number
    expense_date: string
    description?: string
}

export const expensesApi = {
    getAll: (filters?: ExpenseFilters) => api.get<PaginatedResponse<Expense>>('/expenses', { params: filters }),

    create: (data: ExpenseData) => api.post<Expense>('/expenses', data),

    update: (id: number, data: Partial<ExpenseData>) => api.put<Expense>('/expenses/' + id, data),

    delete: (id: number) => api.delete('/expenses/' + id),
}
