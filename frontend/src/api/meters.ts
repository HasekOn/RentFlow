import api from './axios'
import type { Meter, MeterReading } from '../types'

interface CreateMeterData {
    meter_type: string
    serial_number?: string
    location?: string
}

interface CreateReadingData {
    reading_value: number
    reading_date: string
}

export const metersApi = {
    getByProperty: (propertyId: number) => api.get<Meter[]>('/properties/' + propertyId + '/meters'),

    create: (propertyId: number, data: CreateMeterData) =>
        api.post<Meter>('/properties/' + propertyId + '/meters', data),

    update: (meterId: number, data: { serial_number?: string; location?: string }) =>
        api.put<Meter>('/meters/' + meterId, data),

    delete: (meterId: number) => api.delete('/meters/' + meterId),

    getReadings: (meterId: number) => api.get<MeterReading[]>('/meters/' + meterId + '/readings'),

    addReading: (meterId: number, data: CreateReadingData) =>
        api.post<MeterReading>('/meters/' + meterId + '/readings', data),
}
