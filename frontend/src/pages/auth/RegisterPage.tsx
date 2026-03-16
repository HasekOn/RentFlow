import * as React from 'react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import type { ApiError } from '../../types'
import { AxiosError } from 'axios'

export default function RegisterPage() {
    const [name, setName] = useState('')
    const [email, setEmail] = useState('')
    const [password, setPassword] = useState('')
    const [passwordConfirmation, setPasswordConfirmation] = useState('')
    const [role, setRole] = useState('landlord')
    const [error, setError] = useState('')
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)
    const { register } = useAuth()
    const navigate = useNavigate()

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        setError('')
        setErrors({})
        setIsLoading(true)

        try {
            await register(name, email, password, passwordConfirmation, role)
            navigate('/')
        } catch (err) {
            const axiosError = err as AxiosError<ApiError>
            setError(axiosError.response?.data?.message || 'Registration failed.')
            setErrors(axiosError.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    const fieldError = (field: string) =>
        errors[field]?.[0] ? <p className="mt-1 text-xs text-red-600">{errors[field][0]}</p> : null

    return (
        <div className="min-h-screen bg-gray-100 flex items-center justify-center px-4">
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <h1 className="text-2xl sm:text-4xl font-bold text-black">RentFlow</h1>
                    <p className="mt-2 text-gray-600">Create your account</p>
                </div>

                <div className="bg-white rounded-2xl p-6 sm:p-8 shadow-sm">
                    {error && !Object.keys(errors).length && (
                        <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Role Selector */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">I want to</label>
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => setRole('landlord')}
                                    className={`p-3 rounded-xl border-2 text-center transition cursor-pointer ${
                                        role === 'landlord'
                                            ? 'border-black bg-gray-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    }`}
                                >
                                    <span className="text-lg">🏠</span>
                                    <p className="text-sm font-semibold mt-1">Manage properties</p>
                                    <p className="text-xs text-gray-500">I'm a landlord</p>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setRole('tenant')}
                                    className={`p-3 rounded-xl border-2 text-center transition cursor-pointer ${
                                        role === 'tenant'
                                            ? 'border-black bg-gray-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    }`}
                                >
                                    <span className="text-lg">🔑</span>
                                    <p className="text-sm font-semibold mt-1">Rent a place</p>
                                    <p className="text-xs text-gray-500">I'm a tenant</p>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Name</label>
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition"
                                placeholder="Your name"
                                required
                            />
                            {fieldError('name')}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition"
                                placeholder="you@example.com"
                                required
                            />
                            {fieldError('email')}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition"
                                placeholder="••••••••"
                                required
                            />
                            {fieldError('password')}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                            <input
                                type="password"
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition"
                                placeholder="••••••••"
                                required
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={isLoading}
                            className="w-full py-2.5 bg-black text-white text-sm font-semibold rounded-full hover:bg-gray-800 transition disabled:opacity-50"
                        >
                            {isLoading ? 'Creating account...' : 'Sign Up'}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm text-gray-500">
                        Already have an account?{' '}
                        <Link to="/login" className="text-black font-semibold hover:underline">
                            Sign In
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    )
}
