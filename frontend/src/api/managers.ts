import api from './axios'
import type {User} from '../types'

export const managersApi = {
    promote: (userId: number) =>
        api.post('/users/' + userId + '/promote-manager'),

    demote: (userId: number) =>
        api.post('/users/' + userId + '/demote-manager'),

    getByProperty: (propertyId: number) =>
        api.get<User[]>('/properties/' + propertyId + '/managers'),

    assign: (propertyId: number, userId: number) =>
        api.post('/properties/' + propertyId + '/assign-manager', {user_id: userId}),

    remove: (propertyId: number, userId: number) =>
        api.delete('/properties/' + propertyId + '/remove-manager/' + userId),
}
