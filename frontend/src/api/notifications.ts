import api from './axios'
import type {Notification} from '../types'

export const notificationsApi = {
    getAll: () =>
        api.get<Notification[]>('/notifications'),

    markRead: (notificationId: string) =>
        api.put('/notifications/' + notificationId + '/read'),
}
