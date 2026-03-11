import api from './axios'
import type { User } from '../types'

interface AuthResponse {
    user: User
    token: string
}

interface LoginData {
    email: string
    password: string
}

interface RegisterData {
    name: string
    email: string
    password: string
    password_confirmation: string
    role?: string
}

export const authApi = {
    login: (data: LoginData) => api.post<AuthResponse>('/login', data),

    register: (data: RegisterData) => api.post<AuthResponse>('/register', data),

    logout: () => api.post('/logout'),

    getUser: () => api.get<{ data: User }>('/user'),
}
