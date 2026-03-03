import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {propertiesApi} from '../../api/properties'
import {metersApi} from '../../api/meters'
import {expensesApi} from '../../api/expenses'
import {inventoryApi} from '../../api/inventory'
import {ticketsApi} from '../../api/tickets'
import type {Expense, InventoryItem, Meter, Property, Ticket} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import EmptyState from '../../components/ui/EmptyState'
import MeterCard from './MeterCard'
import PropertyFormModal from './PropertyFormModal'
import CreateMeterModal from './CreateMeterModal'
import CreateExpenseModal from './CreateExpenseModal'
import CreateInventoryModal from './CreateInventoryModal'
import ImageUploadModal from './ImageUploadModal'
import ManagerAssignment from "./ManagerAssignment.tsx";

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

const ticketStatusVariant = (status: string) => {
    switch (status) {
        case 'new':
            return 'pink'
        case 'in_progress':
            return 'yellow'
        case 'resolved':
            return 'green'
        case 'rejected':
            return 'red'
        default:
            return 'gray' as const
    }
}

const conditionVariant = (condition: string) => {
    switch (condition) {
        case 'new':
        case 'good':
            return 'green'
        case 'fair':
            return 'yellow'
        case 'poor':
        case 'broken':
            return 'red'
        default:
            return 'gray' as const
    }
}

type Tab = 'overview' | 'management'

export default function PropertyDetailPage() {
    const {id} = useParams<{ id: string }>()
    const navigate = useNavigate()
    const {isLandlord} = useAuth()
    const [property, setProperty] = useState<Property | null>(null)
    const [meters, setMeters] = useState<Meter[]>([])
    const [expenses, setExpenses] = useState<Expense[]>([])
    const [inventory, setInventory] = useState<InventoryItem[]>([])
    const [tickets, setTickets] = useState<Ticket[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [activeTab, setActiveTab] = useState<Tab>('overview')
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)

    // Modals
    const [showEditModal, setShowEditModal] = useState(false)
    const [showMeterModal, setShowMeterModal] = useState(false)
    const [showExpenseModal, setShowExpenseModal] = useState(false)
    const [showInventoryModal, setShowInventoryModal] = useState(false)
    const [showImageModal, setShowImageModal] = useState(false)

    const propertyId = Number(id)

    const loadProperty = async () => {
        try {
            const res = await propertiesApi.getOne(propertyId)
            setProperty(res.data)
        } catch {
            navigate('/properties')
        } finally {
            setIsLoading(false)
        }
    }

    const loadManagementData = async () => {
        try {
            const [metersRes, expensesRes, inventoryRes, ticketsRes] = await Promise.all([
                metersApi.getByProperty(propertyId),
                expensesApi.getAll({property_id: propertyId, sort: '-expense_date'}),
                inventoryApi.getByProperty(propertyId),
                ticketsApi.getAll({property_id: propertyId, sort: '-created_at'}),
            ])
            setMeters(Array.isArray(metersRes.data) ? metersRes.data : [])
            setExpenses(expensesRes.data.data || [])
            setInventory(Array.isArray(inventoryRes.data) ? inventoryRes.data : [])
            setTickets(ticketsRes.data.data || [])
        } catch (error) {
            console.error('Failed to load management data:', error)
        }
    }

    useEffect(() => {
        void loadProperty()
    }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (activeTab === 'management') {
            void loadManagementData()
        }
    }, [activeTab, id]) // eslint-disable-line react-hooks/exhaustive-deps

    if (isLoading) return <Spinner/>
    if (!property) return null

    const activeLease = property.leases?.find((l) => l.status === 'active')
    const images = property.images || []

    return (
        <div>
            {/* Lightbox */}
            {lightboxIndex !== null && images.length > 0 && (
                <div className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center"
                     onClick={() => setLightboxIndex(null)}>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            setLightboxIndex(null)
                        }}
                        className="absolute top-6 right-6 text-white text-3xl hover:opacity-70 transition"
                    >✕
                    </button>
                    {lightboxIndex > 0 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex - 1)
                            }}
                            className="absolute left-6 text-white text-4xl hover:opacity-70 transition"
                        >‹</button>
                    )}
                    {lightboxIndex < images.length - 1 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex + 1)
                            }}
                            className="absolute right-6 text-white text-4xl hover:opacity-70 transition"
                        >›</button>
                    )}
                    <img
                        src={images[lightboxIndex].image_url}
                        alt={images[lightboxIndex].description || 'Property'}
                        className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
                        onClick={(e) => e.stopPropagation()}
                    />
                    <p className="absolute bottom-6 text-white text-sm">
                        {lightboxIndex + 1} / {images.length}
                        {images[lightboxIndex].description && ` — ${images[lightboxIndex].description}`}
                    </p>
                </div>
            )}

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-4">
                    <button onClick={() => navigate('/properties')}
                            className="text-gray-400 hover:text-black transition text-lg">←
                    </button>
                    <h1 className="text-2xl sm:text-4xl font-bold text-black">Property Detail</h1>
                </div>
                {isLandlord && (
                    <Button variant="secondary" onClick={() => setShowEditModal(true)}>
                        Edit property
                    </Button>
                )}
            </div>

            {/* Tabs */}
            <div className="flex gap-2 mb-6">
                <button
                    onClick={() => setActiveTab('overview')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition ${
                        activeTab === 'overview' ? 'bg-black text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >Overview
                </button>
                <button
                    onClick={() => setActiveTab('management')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition ${
                        activeTab === 'management' ? 'bg-black text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >{isLandlord ? 'Management' : 'Details'}</button>
            </div>

            {/* ══════════ OVERVIEW TAB ══════════ */}
            {activeTab === 'overview' && (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Gallery */}
                        <div className="bg-white rounded-2xl p-4 shadow-sm">
                            {images.length > 0 ? (
                                <>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 grid-rows-2 gap-2 h-60 sm:h-80">
                                        <div
                                            className={`${images.length === 1 ? 'col-span-4 row-span-2' : 'col-span-2 row-span-2'} cursor-pointer overflow-hidden rounded-xl`}
                                            onClick={() => setLightboxIndex(0)}
                                        >
                                            <img src={images[0].image_url} alt={images[0].description || 'Property'}
                                                 className="w-full h-full object-cover hover:scale-105 transition duration-300"/>
                                        </div>
                                        {images.slice(1, 5).map((img, idx) => (
                                            <div key={img.id}
                                                 className="cursor-pointer overflow-hidden rounded-xl relative"
                                                 onClick={() => setLightboxIndex(idx + 1)}>
                                                <img src={img.image_url} alt={img.description || 'Property'}
                                                     className="w-full h-full object-cover hover:scale-105 transition duration-300"/>
                                                {idx === 3 && images.length > 5 && (
                                                    <div
                                                        className="absolute inset-0 bg-black/50 flex items-center justify-center">
                                                        <span
                                                            className="text-white text-lg font-bold">+{images.length - 5}</span>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                    {isLandlord && (
                                        <div className="mt-3 flex justify-end">
                                            <Button size="sm" variant="secondary"
                                                    onClick={() => setShowImageModal(true)}>
                                                📷 Add Photo
                                            </Button>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-8">
                                    <span className="text-4xl">🏠</span>
                                    <p className="text-sm text-gray-400 mt-2">No photos yet</p>
                                    {isLandlord && (
                                        <Button size="sm" className="mt-3" onClick={() => setShowImageModal(true)}>
                                            📷 Add First Photo
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Price & Status */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-2xl font-bold text-black">
                                        {activeLease ? formatCurrency(activeLease.rent_amount) : 'No active lease'}
                                    </p>
                                    <p className="text-sm text-gray-500 mt-1">{property.disposition || '—'} · {property.size ? `${property.size} m²` : '—'}</p>
                                    <p className="text-sm text-gray-500">{property.address}{property.city ? `, ${property.city}` : ''}</p>
                                </div>
                                <Badge variant={statusVariant(property.status)}>{property.status}</Badge>
                            </div>
                        </div>

                        {/* About */}
                        {property.description && (
                            <div className="bg-white rounded-2xl p-6 shadow-sm">
                                <h2 className="text-lg font-bold text-black mb-3">About</h2>
                                <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{property.description}</p>
                            </div>
                        )}

                        {/* Details */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Details</h2>
                            <div className="grid grid-cols-2 gap-4">
                                <div><p className="text-xs text-gray-400">Address</p><p
                                    className="text-sm text-gray-700">{property.address}</p></div>
                                <div><p className="text-xs text-gray-400">City</p><p
                                    className="text-sm text-gray-700">{property.city || '—'}</p></div>
                                <div><p className="text-xs text-gray-400">ZIP Code</p><p
                                    className="text-sm text-gray-700">{property.zip_code || '—'}</p></div>
                                <div><p className="text-xs text-gray-400">Floor</p><p
                                    className="text-sm text-gray-700">{property.floor ?? '—'}</p></div>
                                <div><p className="text-xs text-gray-400">Disposition</p><p
                                    className="text-sm text-gray-700">{property.disposition || '—'}</p></div>
                                <div><p className="text-xs text-gray-400">Size</p><p
                                    className="text-sm text-gray-700">{property.size ? `${property.size} m²` : '—'}</p>
                                </div>
                                <div><p className="text-xs text-gray-400">Purchase Price</p><p
                                    className="text-sm text-gray-700">{property.purchase_price ? formatCurrency(property.purchase_price) : '—'}</p>
                                </div>
                                <div><p className="text-xs text-gray-400">Status</p><p
                                    className="text-sm text-gray-700 capitalize">{property.status}</p></div>
                            </div>
                        </div>

                        {/* Occupiers */}
                        {activeLease?.tenant && (
                            <div className="bg-white rounded-2xl p-6 shadow-sm">
                                <h2 className="text-lg font-bold text-black mb-3">Occupiers</h2>
                                <div className="flex items-center gap-4">
                                    <div
                                        className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600">
                                        {activeLease.tenant.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-black">{activeLease.tenant.name}</p>
                                        <p className="text-xs text-gray-500">{activeLease.tenant.email}</p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Quick Info</h2>
                            <div className="space-y-3">
                                <div className="flex justify-between"><span
                                    className="text-sm text-gray-500">Leases</span><span
                                    className="text-sm font-semibold">{property.leases?.length || 0}</span></div>
                                <div className="flex justify-between"><span
                                    className="text-sm text-gray-500">Meters</span><span
                                    className="text-sm font-semibold">{property.meters?.length || 0}</span></div>
                                <div className="flex justify-between"><span
                                    className="text-sm text-gray-500">Images</span><span
                                    className="text-sm font-semibold">{images.length}</span></div>
                            </div>
                        </div>

                        {/* Manager Assignment — landlord only */}
                        {isLandlord && (
                            <ManagerAssignment propertyId={propertyId}/>
                        )}
                    </div>
                </div>
            )}

            {/* ══════════ MANAGEMENT TAB ══════════ */}
            {activeTab === 'management' && (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left column */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Tickets */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-bold text-black">Tickets</h2>
                                <Button size="sm" onClick={() => navigate('/tickets')}>View All</Button>
                            </div>
                            {tickets.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-4">No tickets for this property</p>
                            ) : (
                                <div className="space-y-3">
                                    {tickets.slice(0, 5).map((ticket) => (
                                        <div
                                            key={ticket.id}
                                            className="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition"
                                            onClick={() => navigate('/tickets/' + ticket.id)}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold text-black">{ticket.title}</p>
                                                    <p className="text-xs text-gray-500 mt-0.5">
                                                        {formatDate(ticket.created_at)} ·
                                                        Priority: {ticket.priority} · {ticket.tenant?.name || '—'}
                                                    </p>
                                                </div>
                                                <Badge variant={ticketStatusVariant(ticket.status)}>
                                                    {ticket.status === 'in_progress' ? 'In Progress' : ticket.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Expenses — landlord only */}
                        {isLandlord && (
                            <div className="bg-white rounded-2xl p-6 shadow-sm">
                                <div className="flex items-center justify-between mb-4">
                                    <h2 className="text-lg font-bold text-black">Expenses</h2>
                                    <Button size="sm" onClick={() => setShowExpenseModal(true)}>+ Add</Button>
                                </div>
                                {expenses.length === 0 ? (
                                    <p className="text-sm text-gray-400 text-center py-4">No expenses recorded</p>
                                ) : (
                                    <>
                                        {/* Desktop table */}
                                        <div className="hidden sm:block">
                                            <table className="w-full">
                                                <thead>
                                                <tr className="border-b border-gray-100">
                                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Date</th>
                                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Type</th>
                                                    <th className="text-left py-2 text-xs font-semibold text-gray-400 uppercase">Description</th>
                                                    <th className="text-right py-2 text-xs font-semibold text-gray-400 uppercase">Amount</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                {expenses.slice(0, 10).map((expense) => (
                                                    <tr key={expense.id} className="border-b border-gray-50">
                                                        <td className="py-3 text-sm text-gray-600">{formatDate(expense.expense_date)}</td>
                                                        <td className="py-3 text-sm text-gray-600 capitalize">{expense.type}</td>
                                                        <td className="py-3 text-sm text-gray-600">{expense.description || '—'}</td>
                                                        <td className="py-3 text-sm font-semibold text-right text-black">{formatCurrency(expense.amount)}</td>
                                                    </tr>
                                                ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        {/* Mobile cards */}
                                        <div className="sm:hidden space-y-2">
                                            {expenses.slice(0, 10).map((expense) => (
                                                <div key={expense.id} className="p-3 border border-gray-100 rounded-xl">
                                                    <div className="flex items-center justify-between">
                                                        <span
                                                            className="text-sm font-semibold text-black capitalize">{expense.type}</span>
                                                        <span
                                                            className="text-sm font-bold text-black">{formatCurrency(expense.amount)}</span>
                                                    </div>
                                                    <div className="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                                        <span>{formatDate(expense.expense_date)}</span>
                                                        {expense.description && <span>· {expense.description}</span>}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {/* Inventory */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-bold text-black">Inventory</h2>
                                {isLandlord && (
                                    <Button size="sm" onClick={() => setShowInventoryModal(true)}>+ Add</Button>
                                )}
                            </div>
                            {inventory.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-4">No inventory items</p>
                            ) : (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {inventory.map((item) => (
                                        <div key={item.id} className="p-4 border border-gray-100 rounded-xl">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-semibold text-black">{item.name}</p>
                                                <Badge
                                                    variant={conditionVariant(item.condition)}>{item.condition}</Badge>
                                            </div>
                                            {item.category &&
                                                <p className="text-xs text-gray-500 mt-1">{item.category}</p>}
                                            <div className="flex items-center gap-3 mt-2">
                                                {item.purchase_price && <span
                                                    className="text-xs text-gray-400">{formatCurrency(item.purchase_price)}</span>}
                                                {item.purchase_date && <span
                                                    className="text-xs text-gray-400">{formatDate(item.purchase_date)}</span>}
                                            </div>
                                            {item.note && <p className="text-xs text-gray-400 mt-1">{item.note}</p>}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right column — Utilities / Meters */}
                    <div className="space-y-6">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-bold text-black">Utilities</h2>
                            {isLandlord && (
                                <Button size="sm" onClick={() => setShowMeterModal(true)}>+ Add</Button>
                            )}
                        </div>
                        {meters.length === 0 ? (
                            <div className="bg-white rounded-2xl p-6 shadow-sm">
                                <EmptyState title="No meters"
                                            description="No meters have been set up for this property."/>
                            </div>
                        ) : (
                            meters.map((meter) => (
                                <MeterCard
                                    key={meter.id}
                                    meter={meter}
                                    onUpdate={() => void loadManagementData()}
                                />
                            ))
                        )}
                    </div>
                </div>
            )}

            {/* ══════════ MODALS ══════════ */}
            <PropertyFormModal
                isOpen={showEditModal}
                onClose={() => setShowEditModal(false)}
                property={property}
                onSuccess={() => {
                    setShowEditModal(false)
                    void loadProperty()
                }}
            />
            <CreateMeterModal
                isOpen={showMeterModal}
                onClose={() => setShowMeterModal(false)}
                propertyId={propertyId}
                onSuccess={() => {
                    setShowMeterModal(false)
                    void loadManagementData()
                }}
            />
            <CreateExpenseModal
                isOpen={showExpenseModal}
                onClose={() => setShowExpenseModal(false)}
                propertyId={propertyId}
                onSuccess={() => {
                    setShowExpenseModal(false)
                    void loadManagementData()
                }}
            />
            <CreateInventoryModal
                isOpen={showInventoryModal}
                onClose={() => setShowInventoryModal(false)}
                propertyId={propertyId}
                onSuccess={() => {
                    setShowInventoryModal(false)
                    void loadManagementData()
                }}
            />
            <ImageUploadModal
                isOpen={showImageModal}
                onClose={() => setShowImageModal(false)}
                propertyId={propertyId}
                onSuccess={() => {
                    setShowImageModal(false)
                    void loadProperty()
                }}
            />
        </div>
    )
}
