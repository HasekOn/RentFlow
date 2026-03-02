import api from './axios'
import type {Lease, PaginatedResponse} from '../types'

interface LeaseFilters {
    status?: string
    property_id?: number
    tenant_id?: number
    search?: string
    sort?: string
    page?: number
}

interface CreateLeaseData {
    property_id: number
    tenant_id: number
    start_date: string
    end_date?: string
    rent_amount: number
    deposit_amount?: number
    utility_advances?: number
    variable_symbol?: string
    status?: string
}

export const leasesApi = {
    getAll: (filters?: LeaseFilters) =>
        api.get<PaginatedResponse<Lease>>('/leases', {params: filters}),

    getOne: (id: number) =>
        api.get<Lease>('/leases/' + id),

    create: (data: CreateLeaseData) =>
        api.post<Lease>('/leases', data),

    update: (id: number, data: Partial<CreateLeaseData>) =>
        api.put<Lease>('/leases/' + id, data),

    delete: (id: number) =>
        api.delete('/leases/' + id),

    downloadPdf: (id: number) =>
        api.get('/leases/' + id + '/generate-pdf', {responseType: 'blob'}),

    getByTenant: (tenantId: number) =>
        api.get<PaginatedResponse<Lease>>('/leases', {params: {tenant_id: tenantId}}),
}
