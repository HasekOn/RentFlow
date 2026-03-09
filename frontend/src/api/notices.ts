import api from './axios'
import type {Notice} from '../types'

export const noticesApi = {
    getByProperty: (propertyId: number) =>
        api.get<Notice[]>('/properties/' + propertyId + '/notices'),

    create: (propertyId: number, data: { title: string; content: string }) =>
        api.post<Notice>('/properties/' + propertyId + '/notices', data),

    update: (noticeId: number, data: { title?: string; content?: string; is_active?: boolean }) =>
        api.put<Notice>('/notices/' + noticeId, data),

    delete: (noticeId: number) =>
        api.delete('/notices/' + noticeId),
}
