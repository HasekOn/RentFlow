import {useEffect, useState} from 'react'
import {useNavigate, useParams} from 'react-router-dom'
import {usersApi} from '../../api/users'
import {leasesApi} from '../../api/leases'
import {ratingsApi} from '../../api/ratings'
import type {Lease, Rating, TrustScoreData, User} from '../../types'
import {formatCurrency, formatDate} from '../../utils/format'
import Spinner from '../../components/ui/Spinner'
import TrustScoreBadge from '../../components/ui/TrustScoreBadge'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'

export default function PersonDetailPage() {
    const {id} = useParams<{ id: string }>()
    const navigate = useNavigate()
    const [tenant, setTenant] = useState<User | null>(null)
    const [trustScore, setTrustScore] = useState<TrustScoreData | null>(null)
    const [leases, setLeases] = useState<Lease[]>([])
    const [ratings, setRatings] = useState<Rating[]>([])
    const [isLoading, setIsLoading] = useState(true)

    useEffect(() => {
        const load = async () => {
            try {
                const tenantId = Number(id)

                const [tenantsRes, trustRes, leasesRes] = await Promise.all([
                    usersApi.getTenants(),
                    usersApi.getTrustScore(tenantId).catch(() => null),
                    leasesApi.getByTenant(tenantId),
                ])

                const found = tenantsRes.data.find((t: User) => t.id === tenantId)
                
                if (!found) {
                    navigate('/people')

                    return
                }

                setTenant(found)
                if (trustRes) setTrustScore(trustRes.data)
                setLeases(leasesRes.data.data || [])

                // Load ratings from all leases
                const allRatings: Rating[] = []
                for (const lease of (leasesRes.data.data || [])) {
                    try {
                        const ratingsRes = await ratingsApi.getByLease(lease.id)
                        const ratingsData = Array.isArray(ratingsRes.data) ? ratingsRes.data : []
                        allRatings.push(...ratingsData)
                    } catch {
                        // No ratings for this lease
                    }
                }
                setRatings(allRatings)
            } catch (error) {
                console.error('Failed to load tenant:', error)
                navigate('/people')
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [id, navigate])

    if (isLoading) return <Spinner/>
    if (!tenant) return null

    const categoryLabel = (cat: string) => {
        switch (cat) {
            case 'apartment_condition':
                return 'Apartment Condition'
            case 'communication':
                return 'Communication'
            case 'rules':
                return 'Rules Compliance'
            case 'overall':
                return 'Overall'
            default:
                return cat
        }
    }

    return (
        <div>
            {/* Header */}
            <div className="flex items-center gap-4 mb-6">
                <button
                    onClick={() => navigate('/people')}
                    className="text-gray-400 hover:text-black transition text-lg"
                >
                    ←
                </button>
                <h1 className="text-4xl font-bold text-black">Tenant Detail</h1>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Profile card */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center gap-5">
                            <div
                                className="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-600">
                                {tenant.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-black">{tenant.name}</h2>
                                <p className="text-sm text-gray-500">{tenant.email}</p>
                                <div className="mt-2">
                                    <TrustScoreBadge score={tenant.trust_score}/>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Personal information */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-4">Personal Information</h2>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <p className="text-xs text-gray-400">Name</p>
                                <p className="text-sm text-gray-700 font-semibold">{tenant.name}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Email Address</p>
                                <p className="text-sm text-gray-700 font-semibold">{tenant.email}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Phone</p>
                                <p className="text-sm text-gray-700 font-semibold">{tenant.phone || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Role</p>
                                <p className="text-sm text-gray-700 font-semibold capitalize">{tenant.role}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Member Since</p>
                                <p className="text-sm text-gray-700 font-semibold">{formatDate(tenant.created_at)}</p>
                            </div>
                        </div>
                    </div>

                    {/* Lease history */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-4">
                            Lease History ({leases.length})
                        </h2>
                        {leases.length === 0 ? (
                            <p className="text-sm text-gray-400 text-center py-4">No leases found</p>
                        ) : (
                            <div className="space-y-3">
                                {leases.map((lease) => (
                                    <div
                                        key={lease.id}
                                        onClick={() => navigate('/leases/' + lease.id)}
                                        className="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 cursor-pointer transition"
                                    >
                                        <div className="flex items-start gap-3">
                                            {lease.property?.images?.[0] ? (
                                                <img
                                                    src={lease.property.images[0].image_url}
                                                    alt=""
                                                    className="w-12 h-12 rounded-lg object-cover shrink-0"
                                                />
                                            ) : (
                                                <div className="w-12 h-12 rounded-lg bg-gray-100 shrink-0"/>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-semibold text-black truncate">
                                                            {lease.property?.address || 'Property'}
                                                        </p>
                                                        <p className="text-xs text-gray-500">
                                                            {lease.property?.city}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant={lease.status === 'active' ? 'green' : lease.status === 'terminated' ? 'red' : 'gray'}
                                                    >
                                                        {lease.status}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center justify-between mt-2">
                                                    <p className="text-xs text-gray-400">
                                                        {formatDate(lease.start_date)} → {lease.end_date ? formatDate(lease.end_date) : 'Indefinite'}
                                                    </p>
                                                    <span className="text-sm font-semibold text-black">
                                                        {formatCurrency(lease.rent_amount)}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Trust Score breakdown */}
                    {trustScore && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-4">Trust Score</h2>

                            {/* Score visual */}
                            <div className="text-center mb-4">
                                <div
                                    className="inline-flex items-center justify-center w-20 h-20 rounded-full border-4 border-green-200">
                                    <span
                                        className="text-2xl font-bold text-black">{Math.round(trustScore.trust_score)}</span>
                                </div>
                            </div>

                            {/* Breakdown */}
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Total Payments</span>
                                    <span className="text-sm font-semibold">{trustScore.breakdown.total_payments}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">On Time</span>
                                    <span
                                        className="text-sm font-semibold">{trustScore.breakdown.on_time_payments}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Payment Ratio</span>
                                    <span className="text-sm font-semibold">{trustScore.breakdown.payment_ratio}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Average Rating</span>
                                    <span className="text-sm font-semibold">{trustScore.breakdown.average_rating}</span>
                                </div>
                            </div>

                            {/* Progress bar */}
                            <div className="mt-4">
                                <div className="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        className={`h-full rounded-full transition-all ${
                                            trustScore.trust_score >= 70 ? 'bg-green-500' :
                                                trustScore.trust_score >= 40 ? 'bg-yellow-500' : 'bg-red-500'
                                        }`}
                                        style={{width: `${trustScore.trust_score}%`}}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Ratings */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-4">
                            Ratings ({ratings.length})
                        </h2>
                        {ratings.length === 0 ? (
                            <p className="text-sm text-gray-400 text-center py-4">No ratings yet</p>
                        ) : (
                            <div className="space-y-3">
                                {ratings.map((rating) => (
                                    <div key={rating.id} className="p-4 border border-gray-100 rounded-xl">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm font-semibold text-black">
                                                {categoryLabel(rating.category)}
                                            </p>
                                            <div className="flex items-center gap-1">
                                                <span className="text-sm font-bold text-black">{rating.score}</span>
                                                <span className="text-yellow-500">★</span>
                                            </div>
                                        </div>
                                        {rating.comment && (
                                            <p className="text-xs text-gray-500 mt-2">{rating.comment}</p>
                                        )}
                                        {rating.rated_by && (
                                            <p className="text-xs text-gray-400 mt-1">
                                                by {rating.rated_by.name} · {formatDate(rating.created_at)}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Quick actions */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-3">Quick Actions</h2>
                        <div className="space-y-2">
                            <Button
                                variant="secondary"
                                className="w-full"
                                onClick={() => navigate('/leases')}
                            >
                                View Leases
                            </Button>
                            <Button
                                variant="secondary"
                                className="w-full"
                                onClick={() => navigate('/payments')}
                            >
                                View Payments
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    )
}
