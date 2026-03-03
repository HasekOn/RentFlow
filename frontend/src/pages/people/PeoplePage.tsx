import {useEffect, useState} from 'react'
import {useNavigate} from 'react-router-dom'
import {usersApi} from '../../api/users'
import type {User} from '../../types'
import {useAuth} from '../../contexts/AuthContext'
import Spinner from '../../components/ui/Spinner'
import EmptyState from '../../components/ui/EmptyState'
import TrustScoreBadge from '../../components/ui/TrustScoreBadge'

export default function PeoplePage() {
    const {isLandlord} = useAuth()
    const navigate = useNavigate()
    const [tenants, setTenants] = useState<User[]>([])
    const [managers, setManagers] = useState<User[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [search, setSearch] = useState('')

    useEffect(() => {
        const load = async () => {
            try {
                const [tenantsRes, managersRes] = await Promise.all([
                    usersApi.getTenants(),
                    usersApi.getManagers(),
                ])
                setTenants(tenantsRes.data)
                setManagers(managersRes.data)
            } catch (error) {
                console.error('Failed to load users:', error)
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [])

    const filteredTenants = tenants.filter((t) =>
        t.name.toLowerCase().includes(search.toLowerCase()) ||
        t.email.toLowerCase().includes(search.toLowerCase())
    )

    const filteredManagers = managers.filter((m) =>
        m.name.toLowerCase().includes(search.toLowerCase()) ||
        m.email.toLowerCase().includes(search.toLowerCase())
    )

    if (isLoading) return <Spinner/>

    return (
        <div>
            <h1 className="text-4xl font-bold text-black">People</h1>

            {/* Search */}
            <div className="mt-6 flex items-center gap-4">
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search people..."
                    className="px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-72"
                />
            </div>

            {/* Tenants */}
            <div className="mt-8">
                <h2 className="text-lg font-bold text-black mb-4">
                    Tenants ({filteredTenants.length})
                </h2>
                {filteredTenants.length === 0 ? (
                    <EmptyState title="No tenants found"/>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredTenants.map((tenant) => (
                            <div
                                key={tenant.id}
                                onClick={() => isLandlord ? navigate('/people/' + tenant.id) : undefined}
                                className={`bg-white rounded-2xl p-5 shadow-sm border border-gray-100 transition ${
                                    isLandlord ? 'cursor-pointer hover:shadow-md hover:border-gray-200' : ''
                                }`}
                            >
                                <div className="flex items-start gap-4">
                                    <div
                                        className="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-lg font-semibold text-gray-600 shrink-0">
                                        {tenant.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-bold text-black truncate">{tenant.name}</p>
                                            <TrustScoreBadge score={tenant.trust_score} size="sm"/>
                                        </div>
                                        <p className="text-xs text-gray-500 mt-0.5">{tenant.email}</p>
                                        {tenant.phone && (
                                            <p className="text-xs text-gray-400 mt-0.5">{tenant.phone}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Managers */}
            <div className="mt-10">
                <h2 className="text-lg font-bold text-black mb-4">
                    Managers ({filteredManagers.length})
                </h2>
                {filteredManagers.length === 0 ? (
                    <EmptyState title="No managers yet"/>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredManagers.map((manager) => (
                            <div
                                key={manager.id}
                                onClick={() => navigate('/people/' + manager.id)}
                                className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 cursor-pointer hover:shadow-md hover:border-gray-200 transition"
                            >
                                <div className="flex items-start gap-4">
                                    <div
                                        className="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-lg font-semibold text-gray-600 shrink-0">
                                        {manager.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-bold text-black truncate">{manager.name}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">{manager.email}</p>
                                        {manager.phone && (
                                            <p className="text-xs text-gray-400 mt-0.5">{manager.phone}</p>
                                        )}
                                        <span
                                            className="inline-block mt-1 px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">
                                            Manager
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    )
}
