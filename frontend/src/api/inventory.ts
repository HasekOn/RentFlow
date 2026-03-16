import api from './axios'
import type { InventoryItem } from '../types'

interface InventoryData {
    name: string
    category?: string
    condition: string
    purchase_date?: string
    purchase_price?: number
    note?: string
}

export const inventoryApi = {
    getByProperty: (propertyId: number) => api.get<InventoryItem[]>('/properties/' + propertyId + '/inventory'),

    create: (propertyId: number, data: InventoryData) =>
        api.post<InventoryItem>('/properties/' + propertyId + '/inventory', data),

    update: (id: number, data: Partial<InventoryData>) => api.put<InventoryItem>('/inventory/' + id, data),

    delete: (id: number) => api.delete('/inventory/' + id),
}
