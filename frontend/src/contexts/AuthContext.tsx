import {createContext, type ReactNode, useContext, useEffect, useState} from 'react'
import {authApi} from '../api/auth'
import type {User} from '../types'

interface AuthContextType {
    user: User | null
    setUser: (user: User) => void
    token: string | null
    isLoading: boolean
    login: (email: string, password: string) => Promise<void>
    register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
    logout: () => Promise<void>
    isLandlord: boolean
    isTenant: boolean
    isManager: boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({children}: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null)
    const [token, setToken] = useState<string | null>(localStorage.getItem('token'))
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        if (token) {
            authApi.getUser()
                .then((res) => {
                    const userData = res.data.data ?? res.data
                    setUser(userData as User)
                })
                .catch(() => {
                    setToken(null)
                    localStorage.removeItem('token')
                    localStorage.removeItem('user')
                })
                .finally(() => setIsLoading(false))
        } else {
            setIsLoading(false)
        }
    }, [token])

    const login = async (email: string, password: string) => {
        const res = await authApi.login({email, password})
        const {user: userData, token: newToken} = res.data
        setUser(userData)
        setToken(newToken)
        localStorage.setItem('token', newToken)
        localStorage.setItem('user', JSON.stringify(userData))
    }

    const register = async (name: string, email: string, password: string, passwordConfirmation: string) => {
        const res = await authApi.register({
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
        })
        const {user: userData, token: newToken} = res.data
        setUser(userData)
        setToken(newToken)
        localStorage.setItem('token', newToken)
        localStorage.setItem('user', JSON.stringify(userData))
    }

    const logout = async () => {
        try {
            await authApi.logout()
        } finally {
            setUser(null)
            setToken(null)
            localStorage.removeItem('token')
            localStorage.removeItem('user')
        }
    }

    return (
        <AuthContext.Provider value={{
            user,
            setUser,
            token,
            isLoading,
            login,
            register,
            logout,
            isLandlord: user?.role === 'landlord',
            isTenant: user?.role === 'tenant',
            isManager: user?.role === 'manager',
        }}>
            {children}
        </AuthContext.Provider>
    )
}

export function useAuth() {
    const context = useContext(AuthContext)
    if (!context) {
        throw new Error('useAuth must be used within AuthProvider')
    }
    return context
}
