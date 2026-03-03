import {useCallback, useEffect, useState} from 'react'
import {useNavigate} from 'react-router-dom'
import {propertiesApi} from '../../api/properties'
import type {PaginatedResponse, Property} from '../../types'
import {formatCurrency} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import Pagination from '../../components/ui/Pagination'
import EmptyState from '../../components/ui/EmptyState'
import PropertyFormModal from './PropertyFormModal'
import {useConfirm} from '../../hooks/useConfirm'

const statusVariant = (status: string) => {
    switch (status) {
        case 'occupied':
            return 'green'
        case 'available':
            return 'gray'
        case 'renovation':
            return 'yellow'
        default:
            return 'gray' as const
    }
}

export default function PropertiesPage() {
    const {isLandlord} = useAuth()
    const navigate = useNavigate()
    const [properties, setProperties] = useState<Property[]>([])
    const [meta, setMeta] = useState<PaginatedResponse<Property>['meta'] | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [showCreateModal, setShowCreateModal] = useState(false)
    const [search, setSearch] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const [page, setPage] = useState(1)
    const {confirm, dialog} = useConfirm()

    const loadProperties = useCallback(async () => {
        setIsLoading(true)
        try {
            const res = await propertiesApi.getAll({
                search: search || undefined,
                status: statusFilter || undefined,
                page,
                sort: '-created_at',
            })
            setProperties(res.data.data)
            setMeta(res.data.meta)
        } catch (error) {
            console.error('Failed to load properties:', error)
        } finally {
            setIsLoading(false)
        }
    }, [search, statusFilter, page])

    useEffect(() => {
        void loadProperties()
    }, [loadProperties])

    useEffect(() => {
        setPage(1)
    }, [search, statusFilter])

    const handleDelete = async (id: number) => {
        const ok = await confirm({
            title: 'Delete Property',
            message: 'Are you sure you want to delete this property?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })

        if (!ok) {
            return
        }

        try {
            await propertiesApi.delete(id)
            void loadProperties()
        } catch (error) {
            console.error('Failed to delete property:', error)
        }
    }

    const getRent = (property: Property) => {
        const active = property.leases?.find((l) => l.status === 'active')
        return active ? formatCurrency(active.rent_amount) : '—'
    }

    return (
        <div>
            {/* Header */}
            <div className="flex items-center justify-between">
                <h1 className="text-2xl sm:text-4xl font-bold text-black">Portfolio</h1>
                {isLandlord && (
                    <Button onClick={() => setShowCreateModal(true)}>
                        + Add Property
                    </Button>
                )}
            </div>

            {/* Filters */}
            <div className="bg-white rounded-2xl shadow-sm mt-6">
                <div
                    className="p-4 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <span className="font-semibold text-black">Unit</span>
                        <span>Total {meta?.total || 0}</span>
                    </div>
                    <div className="flex-1"/>
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search properties..."
                            className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-full sm:w-56"
                        />
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="px-4 py-2 border border-gray-200 rounded-lg text-sm bg-white"
                        >
                            <option value="">All Status</option>
                            <option value="occupied">Occupied</option>
                            <option value="available">Available</option>
                            <option value="renovation">Renovation</option>
                        </select>
                    </div>
                </div>

                {isLoading ? (
                    <Spinner/>
                ) : properties.length === 0 ? (
                    <EmptyState
                        title="No properties found"
                        description={search || statusFilter ? 'Try adjusting your filters.' : 'Add your first property to get started.'}
                        action={isLandlord && !search && !statusFilter ? (
                            <Button onClick={() => setShowCreateModal(true)}>+ Add Property</Button>
                        ) : undefined}
                    />
                ) : (
                    <>
                        {/* ── Mobile Cards ── */}
                        <div className="lg:hidden divide-y divide-gray-50">
                            {properties.map((property) => (
                                <div
                                    key={property.id}
                                    className="p-4 hover:bg-gray-50/50 transition"
                                >
                                    <div className="flex items-start gap-3">
                                        {property.images && property.images[0] ? (
                                            <img
                                                src={property.images[0].image_url}
                                                alt=""
                                                className="w-16 h-16 rounded-xl object-cover shrink-0"
                                            />
                                        ) : (
                                            <div className="w-16 h-16 rounded-xl bg-gray-100 shrink-0"/>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="text-sm font-semibold text-black">{property.address}</p>
                                                    <p className="text-xs text-gray-500">{property.city}</p>
                                                </div>
                                                <Badge
                                                    variant={statusVariant(property.status)}>{property.status}</Badge>
                                            </div>
                                            <div className="flex items-center gap-3 mt-2 text-xs text-gray-500">
                                                {property.disposition && <span>{property.disposition}</span>}
                                                {property.size && <span>{property.size} m²</span>}
                                                <span className="font-semibold text-black">{getRent(property)}</span>
                                            </div>
                                            <div className="flex items-center gap-2 mt-3">
                                                <Button
                                                    variant="success"
                                                    size="sm"
                                                    onClick={() => navigate('/properties/' + property.id)}
                                                >
                                                    View
                                                </Button>
                                                {isLandlord && (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => handleDelete(property.id)}
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* ── Desktop Table ── */}
                        <div className="hidden lg:block">
                            <table className="w-full">
                                <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Property</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Disposition</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Size</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Rent</th>
                                    <th className="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                {properties.map((property) => (
                                    <tr key={property.id}
                                        className="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                {property.images && property.images[0] ? (
                                                    <img
                                                        src={property.images[0].image_url}
                                                        alt=""
                                                        className="w-12 h-12 rounded-lg object-cover shrink-0"
                                                    />
                                                ) : (
                                                    <div className="w-12 h-12 rounded-lg bg-gray-100 shrink-0"/>
                                                )}
                                                <div>
                                                    <p className="text-sm font-semibold text-black">{property.address}</p>
                                                    <p className="text-xs text-gray-500">{property.city}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-600">{property.disposition || '—'}</td>
                                        <td className="px-4 py-4 text-sm text-gray-600">{property.size ? `${property.size} m²` : '—'}</td>
                                        <td className="px-4 py-4">
                                            <Badge variant={statusVariant(property.status)}>{property.status}</Badge>
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-600">{getRent(property)}</td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="success" size="sm"
                                                        onClick={() => navigate('/properties/' + property.id)}>
                                                    View
                                                </Button>
                                                {isLandlord && (
                                                    <Button variant="secondary" size="sm"
                                                            onClick={() => handleDelete(property.id)}>
                                                        Delete
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>

                        {meta && (
                            <Pagination
                                currentPage={meta.current_page}
                                lastPage={meta.last_page}
                                total={meta.total}
                                perPage={meta.per_page}
                                onPageChange={setPage}
                            />
                        )}
                    </>
                )}
            </div>

            <PropertyFormModal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={() => {
                    setShowCreateModal(false)
                    void loadProperties()
                }}
            />
            {dialog}
        </div>
    )
}
