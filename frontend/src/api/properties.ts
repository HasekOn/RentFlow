import api from './axios'
import type {PaginatedResponse, Property} from '../types'

interface PropertyFilters {
    status?: string
    city?: string
    search?: string
    sort?: string
    page?: number
}

interface PropertyFormData {
    address: string
    city?: string
    zip_code?: string
    size?: number
    disposition?: string
    floor?: number
    status: string
    purchase_price?: number
    description?: string
}

export const propertiesApi = {
    getAll: (filters?: PropertyFilters) =>
        api.get<PaginatedResponse<Property>>('/properties', {params: filters}),

    getOne: (id: number) =>
        api.get<Property>('/properties/' + id),

    create: (data: PropertyFormData) =>
        api.post<Property>('/properties', data),

    update: (id: number, data: Partial<PropertyFormData>) =>
        api.put<Property>('/properties/' + id, data),

    delete: (id: number) =>
        api.delete('/properties/' + id),

    restore: (id: number) =>
        api.put('/properties/' + id + '/restore'),
}
