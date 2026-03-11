import api from './axios'
import type { TrustScoreData, User } from '../types'

export const usersApi = {
    getTenants: () => api.get<User[]>('/users', { params: { role: 'tenant' } }),

    getManagers: () => api.get<User[]>('/users', { params: { role: 'manager' } }),

    getAll: (filters?: { role?: string }) => api.get<User[]>('/users', { params: filters }),

    getTrustScore: (tenantId: number) => api.get<TrustScoreData>('/tenants/' + tenantId + '/trust-score'),
}
