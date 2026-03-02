import api from './axios'
import type {User} from '../types'

export const usersApi = {
    getTenants: () =>
        api.get<User[]>('/users', {params: {role: 'tenant'}}),
}
