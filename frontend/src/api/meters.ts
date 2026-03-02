import api from './axios'
import type {Meter, MeterReading} from '../types'

export const metersApi = {
    getByProperty: (propertyId: number) =>
        api.get<Meter[]>('/properties/' + propertyId + '/meters'),

    create: (propertyId: number, data: { meter_type: string; serial_number?: string; location?: string }) =>
        api.post<Meter>('/properties/' + propertyId + '/meters', data),

    delete: (propertyId: number, meterId: number) =>
        api.delete('/properties/' + propertyId + '/meters/' + meterId),

    getReadings: (propertyId: number, meterId: number) =>
        api.get<MeterReading[]>('/properties/' + propertyId + '/meters/' + meterId + '/readings'),

    addReading: (propertyId: number, meterId: number, data: { reading_value: number; reading_date: string }) =>
        api.post<MeterReading>('/properties/' + propertyId + '/meters/' + meterId + '/readings', data),
}
