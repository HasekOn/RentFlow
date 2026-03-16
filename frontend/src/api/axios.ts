import axios from 'axios'

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    // Let axios set Content-Type automatically for FormData
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type']
    }

    return config
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Don't redirect on auth endpoints — let the login/register page handle the error
            const url = error.config?.url || ''
            const isAuthRequest = url.includes('/login') || url.includes('/register')

            if (!isAuthRequest) {
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                window.location.href = '/login'
            }
        }
        return Promise.reject(error)
    },
)

export default api
