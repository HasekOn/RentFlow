import api from './axios'
import type {TrustScoreData, User} from '../types'

export const usersApi = {
    getTenants: () =>
        api.get<User[]>('/users', {params: {role: 'tenant'}}),

    getManagers: () =>
        api.get<User[]>('/users', {params: {role: 'manager'}}),

    getAll: () =>
        api.get<User[]>('/users'),

    getTrustScore: (tenantId: number) =>
        api.get<TrustScoreData>('/tenants/' + tenantId + '/trust-score'),
}
