import {Navigate} from 'react-router-dom'
import {useAuth} from '../../contexts/AuthContext'
import * as React from "react";

interface Props {
    children: React.ReactNode
    roles?: string[]
}

export default function ProtectedRoute({children, roles}: Props) {
    const {user, isLoading} = useAuth()

    if (isLoading) {
        return (
            <div className="min-h-screen bg-gray-100 flex items-center justify-center">
                <div className="w-8 h-8 border-4 border-gray-300 border-t-black rounded-full animate-spin"/>
            </div>
        )
    }

    if (!user) {
        return <Navigate to="/login" replace/>
    }

    if (roles && !roles.includes(user.role)) {
        return <Navigate to="/" replace/>
    }

    return <>{children}</>
}
