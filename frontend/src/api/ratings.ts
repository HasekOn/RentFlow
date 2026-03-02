import api from './axios'
import type {Rating} from '../types'

export const ratingsApi = {
    getByLease: (leaseId: number) =>
        api.get<Rating[]>('/leases/' + leaseId + '/ratings'),

    create: (leaseId: number, data: { category: string; score: number; comment?: string }) =>
        api.post<Rating>('/leases/' + leaseId + '/ratings', data),
}
