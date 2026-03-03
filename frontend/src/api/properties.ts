import api from './axios'
import type {PaginatedResponse, Property, PropertyImage} from '../types'

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

    getList: () =>
        api.get<PaginatedResponse<Property>>('/properties', {params: {per_page: 100}}),

    // Images
    getImages: (propertyId: number) =>
        api.get<PropertyImage[]>('/properties/' + propertyId + '/images'),

    uploadImage: (propertyId: number, formData: FormData) =>
        api.post('/properties/' + propertyId + '/images', formData, {
            headers: {'Content-Type': 'multipart/form-data'},
        }),

    deleteImage: (imageId: number) =>
        api.delete('/property-images/' + imageId),
}
