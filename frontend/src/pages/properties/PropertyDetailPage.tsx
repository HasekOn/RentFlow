import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {propertiesApi} from '../../api/properties'
import type {Property} from '../../types'
import {formatCurrency} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'

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

export default function PropertyDetailPage() {
    const {id} = useParams<{ id: string }>()
    const navigate = useNavigate()
    const {isLandlord} = useAuth()
    const [property, setProperty] = useState<Property | null>(null)
    const [isLoading, setIsLoading] = useState(true)
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)

    useEffect(() => {
        const load = async () => {
            try {
                const res = await propertiesApi.getOne(Number(id))
                setProperty(res.data)
            } catch {
                navigate('/properties')
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [id, navigate])

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
                    >
                        ✕
                    </button>
                    {lightboxIndex > 0 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex - 1)
                            }}
                            className="absolute left-6 text-white text-4xl hover:opacity-70 transition"
                        >
                            ‹
                        </button>
                    )}
                    {lightboxIndex < images.length - 1 && (
                        <button
                            onClick={(e) => {
                                e.stopPropagation();
                                setLightboxIndex(lightboxIndex + 1)
                            }}
                            className="absolute right-6 text-white text-4xl hover:opacity-70 transition"
                        >
                            ›
                        </button>
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
                    <button
                        onClick={() => navigate('/properties')}
                        className="text-gray-400 hover:text-black transition text-lg"
                    >
                        ←
                    </button>
                    <h1 className="text-4xl font-bold text-black">Property Detail</h1>
                </div>
                {isLandlord && (
                    <Button variant="secondary" onClick={() => navigate('/properties/' + property.id + '/edit')}>
                        Edit property
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main info */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Image Gallery */}
                    {images.length > 0 && (
                        <div className="bg-white rounded-2xl p-4 shadow-sm">
                            <div className="grid grid-cols-4 grid-rows-2 gap-2 h-80">
                                {/* Main large image */}
                                <div
                                    className={`${images.length === 1 ? 'col-span-4 row-span-2' : 'col-span-2 row-span-2'} cursor-pointer overflow-hidden rounded-xl`}
                                    onClick={() => setLightboxIndex(0)}
                                >
                                    <img
                                        src={images[0].image_url}
                                        alt={images[0].description || 'Property'}
                                        className="w-full h-full object-cover hover:scale-105 transition duration-300"
                                    />
                                </div>
                                {/* Remaining images */}
                                {images.slice(1, 5).map((img, idx) => (
                                    <div
                                        key={img.id}
                                        className="cursor-pointer overflow-hidden rounded-xl relative"
                                        onClick={() => setLightboxIndex(idx + 1)}
                                    >
                                        <img
                                            src={img.image_url}
                                            alt={img.description || 'Property'}
                                            className="w-full h-full object-cover hover:scale-105 transition duration-300"
                                        />
                                        {/* "More" overlay on last visible image */}
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
                        </div>
                    )}

                    {/* Price & Status */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-2xl font-bold text-black">
                                    {activeLease ? formatCurrency(activeLease.rent_amount) : 'No active lease'}
                                </p>
                                <p className="text-sm text-gray-500 mt-1">
                                    {property.disposition || '—'} · {property.size ? `${property.size} m²` : '—'}
                                </p>
                                <p className="text-sm text-gray-500">
                                    {property.address}{property.city ? `, ${property.city}` : ''}
                                </p>
                            </div>
                            <Badge variant={statusVariant(property.status)}>{property.status}</Badge>
                        </div>
                    </div>

                    {/* About */}
                    {property.description && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">About</h2>
                            <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                                {property.description}
                            </p>
                        </div>
                    )}

                    {/* Details */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-3">Details</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs text-gray-400">Address</p>
                                <p className="text-sm text-gray-700">{property.address}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">City</p>
                                <p className="text-sm text-gray-700">{property.city || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">ZIP Code</p>
                                <p className="text-sm text-gray-700">{property.zip_code || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Floor</p>
                                <p className="text-sm text-gray-700">{property.floor ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Disposition</p>
                                <p className="text-sm text-gray-700">{property.disposition || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Size</p>
                                <p className="text-sm text-gray-700">{property.size ? `${property.size} m²` : '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Purchase Price</p>
                                <p className="text-sm text-gray-700">{property.purchase_price ? formatCurrency(property.purchase_price) : '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Status</p>
                                <p className="text-sm text-gray-700 capitalize">{property.status}</p>
                            </div>
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
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Leases</span>
                                <span className="text-sm font-semibold">{property.leases?.length || 0}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Meters</span>
                                <span className="text-sm font-semibold">{property.meters?.length || 0}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Images</span>
                                <span className="text-sm font-semibold">{images.length}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    )
}
