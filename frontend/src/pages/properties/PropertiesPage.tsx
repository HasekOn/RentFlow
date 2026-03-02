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

    // Reset page on filter change
    useEffect(() => {
        setPage(1)
    }, [search, statusFilter])

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this property?')) return
        try {
            await propertiesApi.delete(id)
            void loadProperties()
        } catch (error) {
            console.error('Failed to delete property:', error)
        }
    }

    return (
        <div>
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-4xl font-bold text-black">Portfolio</h1>
                </div>
                {isLandlord && (
                    <Button onClick={() => setShowCreateModal(true)}>
                        + Add Property
                    </Button>
                )}
            </div>

            {/* Filters */}
            <div className="bg-white rounded-2xl shadow-sm mt-6">
                <div className="p-4 border-b border-gray-100 flex items-center gap-4">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <span className="font-semibold text-black">Unit</span>
                        <span>Total {meta?.total || 0}</span>
                    </div>
                    <div className="flex-1"/>
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search properties..."
                        className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 w-64"
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

                {/* Table */}
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
                                    <td className="px-4 py-4 text-sm text-gray-600">
                                        {property.leases?.find((l) => l.status === 'active')?.rent_amount
                                            ? formatCurrency(property.leases.find((l) => l.status === 'active')!.rent_amount)
                                            : '—'}
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center justify-end gap-2">
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
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>

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

            {/* Create Modal */}
            <PropertyFormModal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={() => {
                    setShowCreateModal(false)
                    void loadProperties()
                }}
            />
        </div>
    )
}
