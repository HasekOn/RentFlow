import api from './axios'
import type {DashboardStats, FinanceChartData, OccupancyChartData} from '../types'

export const dashboardApi = {
    getStats: () =>
        api.get<DashboardStats>('/dashboard/stats'),

    getFinanceChart: () =>
        api.get<FinanceChartData[]>('/dashboard/finance-chart'),

    getOccupancyChart: () =>
        api.get<OccupancyChartData[]>('/dashboard/occupancy-chart'),
}
