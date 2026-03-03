import api from './axios'

export interface Document {
    id: number
    property_id: number
    name: string
    file_path: string
    type: string | null
    description: string | null
    valid_until: string | null
    uploaded_by: number
    uploader?: { id: number; name: string }
    created_at: string
    updated_at: string
}

export const documentsApi = {
    getByProperty: (propertyId: number) =>
        api.get<Document[]>('/properties/' + propertyId + '/documents'),

    upload: (propertyId: number, data: FormData) =>
        api.post<Document>('/properties/' + propertyId + '/documents', data, {
            headers: {'Content-Type': 'multipart/form-data'},
        }),

    download: (documentId: number) =>
        api.get('/documents/' + documentId + '/download', {responseType: 'blob'}),

    delete: (documentId: number) =>
        api.delete('/documents/' + documentId),
}
