import api from './axios'
import type {InventoryItem} from '../types'

export const inventoryApi = {
    getByProperty: (propertyId: number) =>
        api.get<InventoryItem[]>('/properties/' + propertyId + '/inventory'),

    create: (propertyId: number, data: {
        name: string;
        category?: string;
        condition: string;
        purchase_date?: string;
        purchase_price?: number;
        note?: string
    }) =>
        api.post<InventoryItem>('/properties/' + propertyId + '/inventory', data),

    delete: (propertyId: number, itemId: number) =>
        api.delete('/properties/' + propertyId + '/inventory/' + itemId),
}
