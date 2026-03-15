import * as React from 'react'
import { useState } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import api from '../../api/axios'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'

type Tab = 'profile' | 'password'

export default function SettingsPage() {
    const { user, setUser } = useAuth()
    const [activeTab, setActiveTab] = useState<Tab>('profile')

    return (
        <div>
            <h1 className="text-2xl sm:text-4xl font-bold text-black">Settings</h1>

            {/* Tabs */}
            <div className="flex gap-2 mt-6 mb-6">
                <button
                    onClick={() => setActiveTab('profile')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition cursor-pointer ${
                        activeTab === 'profile'
                            ? 'bg-black text-white'
                            : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >
                    Profile
                </button>
                <button
                    onClick={() => setActiveTab('password')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition cursor-pointer ${
                        activeTab === 'password'
                            ? 'bg-black text-white'
                            : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >
                    Password
                </button>
            </div>

            {activeTab === 'profile' && user && <ProfileForm user={user} onUpdate={setUser} />}

            {activeTab === 'password' && <PasswordForm />}
        </div>
    )
}

// ─── Profile Form ──────────────────────────────
interface ProfileFormProps {
    user: { name: string; email: string; phone?: string | null }
    onUpdate: (user: any) => void
}

function ProfileForm({ user, onUpdate }: ProfileFormProps) {
    const [formData, setFormData] = useState({
        name: user.name,
        email: user.email,
        phone: user.phone || '',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)
    const [success, setSuccess] = useState(false)

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }))
        setSuccess(false)
    }

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)
        setSuccess(false)

        try {
            const res = await api.put('/profile', formData)
            onUpdate(res.data)
            localStorage.setItem('user', JSON.stringify(res.data))
            setSuccess(true)
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <div className="max-w-lg">
            <div className="bg-white rounded-2xl p-6 shadow-sm">
                <h2 className="text-lg font-bold text-black mb-4">Profile Information</h2>

                {/* Avatar */}
                <div className="flex items-center gap-4 mb-6">
                    <div className="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-600">
                        {formData.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-black">{formData.name}</p>
                        <p className="text-xs text-gray-500 truncate">{formData.email}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Name"
                        name="name"
                        value={formData.name}
                        onChange={handleChange}
                        error={errors.name?.[0]}
                        required
                    />
                    <Input
                        label="Email"
                        name="email"
                        type="email"
                        value={formData.email}
                        onChange={handleChange}
                        error={errors.email?.[0]}
                        required
                    />
                    <Input
                        label="Phone"
                        name="phone"
                        value={formData.phone}
                        onChange={handleChange}
                        placeholder="+420 123 456 789"
                        error={errors.phone?.[0]}
                    />

                    <div className="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? 'Saving...' : 'Save Changes'}
                        </Button>
                        {success && <span className="text-sm text-green-600 font-semibold">Saved successfully!</span>}
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Password Form ─────────────────────────────
function PasswordForm() {
    const [formData, setFormData] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    })
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isLoading, setIsLoading] = useState(false)
    const [success, setSuccess] = useState(false)

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }))
        setSuccess(false)
    }

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        setErrors({})
        setIsLoading(true)
        setSuccess(false)

        try {
            await api.put('/profile/password', formData)
            setFormData({ current_password: '', password: '', password_confirmation: '' })
            setSuccess(true)
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <div className="max-w-lg">
            <div className="bg-white rounded-2xl p-6 shadow-sm">
                <h2 className="text-lg font-bold text-black mb-4">Change Password</h2>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Current Password"
                        name="current_password"
                        type="password"
                        value={formData.current_password}
                        onChange={handleChange}
                        error={errors.current_password?.[0]}
                        required
                    />
                    <Input
                        label="New Password"
                        name="password"
                        type="password"
                        value={formData.password}
                        onChange={handleChange}
                        placeholder="Min 8 characters"
                        error={errors.password?.[0]}
                        required
                    />
                    <Input
                        label="Confirm New Password"
                        name="password_confirmation"
                        type="password"
                        value={formData.password_confirmation}
                        onChange={handleChange}
                        error={errors.password_confirmation?.[0]}
                        required
                    />

                    <div className="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? 'Changing...' : 'Change Password'}
                        </Button>
                        {success && <span className="text-sm text-green-600 font-semibold">Password changed!</span>}
                    </div>
                </form>
            </div>
        </div>
    )
}
