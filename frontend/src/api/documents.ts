import api from './axios'
import type {Document} from '../types'

export const documentsApi = {
    getByProperty: (propertyId: number) =>
        api.get<Document[]>('/properties/' + propertyId + '/documents'),

    upload: (propertyId: number, formData: FormData) =>
        api.post('/properties/' + propertyId + '/documents', formData),

    download: (documentId: number) =>
        api.get('/documents/' + documentId + '/download', {responseType: 'blob'}),

    delete: (documentId: number) =>
        api.delete('/documents/' + documentId),
}
