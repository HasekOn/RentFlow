import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {propertiesApi} from '../../api/properties'
import {metersApi} from '../../api/meters'
import {expensesApi} from '../../api/expenses'
import {inventoryApi} from '../../api/inventory'
import {ticketsApi} from '../../api/tickets'
import {managersApi} from '../../api/managers'
import {documentsApi} from '../../api/documents'
import {noticesApi} from '../../api/notices'
import type {Document as PropertyDocument, Expense, InventoryItem, Meter, Notice, Property, Ticket} from '../../types'
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
import ManagerAssignment from './ManagerAssignment'
import UploadDocumentModal from './UploadDocumentModal'
import CreateNoticeModal from './CreateNoticeModal'
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
    const {isLandlord, isManager, user} = useAuth()
    const [property, setProperty] = useState<Property | null>(null)
    const [meters, setMeters] = useState<Meter[]>([])
    const [expenses, setExpenses] = useState<Expense[]>([])
    const [inventory, setInventory] = useState<InventoryItem[]>([])
    const [tickets, setTickets] = useState<Ticket[]>([])
    const [documents, setDocuments] = useState<PropertyDocument[]>([])
    const [notices, setNotices] = useState<Notice[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [activeTab, setActiveTab] = useState<Tab>('overview')
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)
    const [isAssignedManager, setIsAssignedManager] = useState(false)

    // Modals
    const [showEditModal, setShowEditModal] = useState(false)
    const [showMeterModal, setShowMeterModal] = useState(false)
    const [showExpenseModal, setShowExpenseModal] = useState(false)
    const [showInventoryModal, setShowInventoryModal] = useState(false)
    const [showImageModal, setShowImageModal] = useState(false)
    const [showDocumentModal, setShowDocumentModal] = useState(false)
    const [showNoticeModal, setShowNoticeModal] = useState(false)

    const propertyId = Number(id)
    const {confirm: showConfirm, dialog} = useConfirm()

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
            const [metersRes, expensesRes, inventoryRes, ticketsRes, documentsRes, noticesRes] = await Promise.all([
                metersApi.getByProperty(propertyId),
                expensesApi.getAll({property_id: propertyId, sort: '-expense_date'}),
                inventoryApi.getByProperty(propertyId),
                ticketsApi.getAll({property_id: propertyId, sort: '-created_at'}),
                documentsApi.getByProperty(propertyId),
                noticesApi.getByProperty(propertyId),
            ])
            setMeters(Array.isArray(metersRes.data) ? metersRes.data : [])
            setExpenses(expensesRes.data.data || [])
            setInventory(Array.isArray(inventoryRes.data) ? inventoryRes.data : [])
            setTickets(ticketsRes.data.data || [])
            setDocuments(Array.isArray(documentsRes.data) ? documentsRes.data : [])
            setNotices(Array.isArray(noticesRes.data) ? noticesRes.data : [])
        } catch (error) {
            console.error('Failed to load management data:', error)
        }
    }

    const handleDeleteImage = async (imageId: number) => {
        const ok = await showConfirm({
            title: 'Delete Photo',
            message: 'Are you sure you want to delete this photo?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return

        try {
            await propertiesApi.deleteImage(imageId)
            void loadProperty()
        } catch (error) {
            console.error('Failed to delete image:', error)
        }
    }

    const handleDownloadDocument = async (doc: PropertyDocument) => {
        try {
            const res = await documentsApi.download(doc.id)
            const blob = new Blob([res.data])
            const url = window.URL.createObjectURL(blob)
            const link = window.document.createElement('a')
            link.href = url
            const ext = doc.file_path.split('.').pop()
            link.download = doc.name.includes('.') ? doc.name : `${doc.name}.${ext}`
            link.click()
            window.URL.revokeObjectURL(url)
        } catch (error) {
            console.error('Failed to download document:', error)
        }
    }

    const handleDeleteDocument = async (docId: number) => {
        const ok = await showConfirm({
            title: 'Delete Document',
            message: 'Are you sure you want to delete this document?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return
        try {
            await documentsApi.delete(docId)
            void loadManagementData()
        } catch (error) {
            console.error('Failed to delete document:', error)
        }
    }

    const handleToggleNotice = async (notice: Notice) => {
        try {
            await noticesApi.update(notice.id, {is_active: !notice.is_active})
            void loadManagementData()
        } catch (error) {
            console.error('Failed to toggle notice:', error)
        }
    }

    const handleDeleteNotice = async (noticeId: number) => {
        const ok = await showConfirm({
            title: 'Delete Notice',
            message: 'Are you sure you want to delete this notice?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return
        try {
            await noticesApi.delete(noticeId)
            void loadManagementData()
        } catch (error) {
            console.error('Failed to delete notice:', error)
        }
    }

    useEffect(() => {
        void loadProperty()
    }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (isManager && propertyId) {
            managersApi.getByProperty(propertyId).then((res) => {
                const managers = Array.isArray(res.data) ? res.data : []
                setIsAssignedManager(managers.some((m) => m.id === user?.id))
            }).catch(() => setIsAssignedManager(false))
        }
    }, [isManager, propertyId, user?.id])

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
                        className="absolute top-6 right-6 text-white text-3xl hover:opacity-70 transition cursor-pointer"
                    >✕
                    </button>
                    {lightboxIndex > 0 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex - 1)
                            }}
                            className="absolute left-6 text-white text-4xl hover:opacity-70 transition cursor-pointer"
                        >‹</button>
                    )}
                    {lightboxIndex < images.length - 1 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex + 1)
                            }}
                            className="absolute right-6 text-white text-4xl hover:opacity-70 transition cursor-pointer"
                        >›</button>
                    )}
                    <img
                        src={images[lightboxIndex].image_url}
                        alt={images[lightboxIndex].description || 'Property'}
                        className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
                        onClick={(e) => e.stopPropagation()}
                    />
                    <div className="absolute bottom-6 flex items-center gap-4">
                        <p className="text-white text-sm">
                            {lightboxIndex + 1} / {images.length}
                            {images[lightboxIndex].description && ` — ${images[lightboxIndex].description}`}
                        </p>
                        {isLandlord && (
                            <button
                                onClick={(e) => {
                                    e.stopPropagation()
                                    setLightboxIndex(null)
                                    handleDeleteImage(images[lightboxIndex].id)
                                }}
                                className="text-red-400 hover:text-red-300 text-sm transition cursor-pointer"
                            >
                                🗑 Delete
                            </button>
                        )}
                    </div>
                </div>
            )}

            {/* Header */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-4">
                    <button onClick={() => navigate('/properties')}
                            className="text-gray-400 hover:text-black transition text-lg cursor-pointer">←
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
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition cursor-pointer ${
                        activeTab === 'overview' ? 'bg-black text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                    }`}
                >Overview
                </button>
                <button
                    onClick={() => setActiveTab('management')}
                    className={`px-6 py-2.5 rounded-full text-sm font-semibold transition cursor-pointer ${
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
                                            <img src={images[0].image_url}
                                                 alt={images[0].description || 'Property'}
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
                                        <p className="text-xs text-gray-500 truncate">{activeLease.tenant.email}</p>
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
                        {/* Notices */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-bold text-black">Notices</h2>
                                {isLandlord && (
                                    <Button size="sm" onClick={() => setShowNoticeModal(true)}>+ Add</Button>
                                )}
                            </div>
                            {notices.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-4">No notices posted</p>
                            ) : (
                                <div className="space-y-3">
                                    {notices.map((notice) => (
                                        <div
                                            key={notice.id}
                                            className={`p-4 border rounded-xl transition ${
                                                notice.is_active
                                                    ? 'border-green-200 bg-green-50/50'
                                                    : 'border-gray-100 bg-gray-50/50 opacity-60'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm">📢</span>
                                                        <p className="text-sm font-semibold text-black">{notice.title}</p>
                                                        {!notice.is_active && (
                                                            <span
                                                                className="text-xs text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded">Inactive</span>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-600 mt-1.5 whitespace-pre-line">{notice.content}</p>
                                                    <div className="flex items-center gap-2 mt-2 text-xs text-gray-400">
                                                        <span>{formatDate(notice.created_at)}</span>
                                                        {notice.created_by && (
                                                            <>
                                                                <span>·</span>
                                                                <span>{notice.created_by.name}</span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                                {isLandlord && (
                                                    <div className="flex items-center gap-1 shrink-0">
                                                        <button
                                                            onClick={() => handleToggleNotice(notice)}
                                                            className={`w-8 h-8 flex items-center justify-center rounded-lg transition cursor-pointer ${
                                                                notice.is_active
                                                                    ? 'hover:bg-yellow-50 text-green-500 hover:text-yellow-500'
                                                                    : 'hover:bg-green-50 text-gray-400 hover:text-green-500'
                                                            }`}
                                                            title={notice.is_active ? 'Deactivate' : 'Activate'}
                                                        >
                                                            {notice.is_active ? '🔕' : '🔔'}
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteNotice(notice.id)}
                                                            className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 transition text-gray-400 hover:text-red-500 cursor-pointer"
                                                            title="Delete"
                                                        >🗑
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

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

                        {/* Documents */}
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-lg font-bold text-black">Documents</h2>
                                {isLandlord && (
                                    <Button size="sm" onClick={() => setShowDocumentModal(true)}>+ Add</Button>
                                )}
                            </div>
                            {documents.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-4">No documents uploaded</p>
                            ) : (
                                <div className="space-y-2">
                                    {documents.map((doc) => (
                                        <div key={doc.id}
                                             className="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50 transition">
                                            <div
                                                className="flex items-center gap-3 flex-1 min-w-0 cursor-pointer"
                                                onClick={() => handleDownloadDocument(doc)}
                                            >
                                                <span className="text-xl shrink-0">
                                                    {(doc.name + doc.file_path).match(/\.pdf/i) ? '📄' :
                                                        (doc.name + doc.file_path).match(/\.(jpg|jpeg|png|webp)/i) ? '🖼️' :
                                                            (doc.name + doc.file_path).match(/\.(doc|docx)/i) ? '📝' :
                                                                (doc.name + doc.file_path).match(/\.(xls|xlsx)/i) ? '📊' : '📎'}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold text-black truncate">{doc.name}</p>
                                                    <div className="flex items-center gap-2 text-xs text-gray-400">
                                                        <span className="capitalize">{doc.document_type}</span>
                                                        <span>·</span>
                                                        <span>{formatDate(doc.created_at)}</span>
                                                        {doc.uploaded_by && (
                                                            <>
                                                                <span>·</span>
                                                                <span>{doc.uploaded_by.name}</span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1 shrink-0 ml-2">
                                                <button
                                                    onClick={() => handleDownloadDocument(doc)}
                                                    className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-600 cursor-pointer"
                                                    title="Download"
                                                >⬇️
                                                </button>
                                                {isLandlord && (
                                                    <button
                                                        onClick={() => handleDeleteDocument(doc.id)}
                                                        className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 transition text-gray-400 hover:text-red-500 cursor-pointer"
                                                        title="Delete"
                                                    >🗑</button>
                                                )}
                                            </div>
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
                                    canEdit={isLandlord || isAssignedManager}
                                    isLandlord={isLandlord}
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
            <UploadDocumentModal
                isOpen={showDocumentModal}
                onClose={() => setShowDocumentModal(false)}
                propertyId={propertyId}
                onSuccess={() => {
                    setShowDocumentModal(false)
                    void loadManagementData()
                }}
            />
            <CreateNoticeModal
                isOpen={showNoticeModal}
                onClose={() => setShowNoticeModal(false)}
                propertyId={propertyId}
                onSuccess={() => {
                    setShowNoticeModal(false)
                    void loadManagementData()
                }}
            />
            {dialog}
        </div>
    )
}
