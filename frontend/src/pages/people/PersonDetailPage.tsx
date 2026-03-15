import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { usersApi } from '../../api/users'
import { leasesApi } from '../../api/leases'
import { ratingsApi } from '../../api/ratings'
import type { Lease, Rating, TrustScoreData, User } from '../../types'
import { formatCurrency, formatDate } from '../../utils/format'
import Spinner from '../../components/ui/Spinner'
import TrustScoreBadge from '../../components/ui/TrustScoreBadge'
import Badge from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { managersApi } from '../../api/managers'
import { useConfirm } from '../../hooks/useConfirm'

export default function PersonDetailPage() {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const { user: authUser, isLandlord, isManager, isTenant } = useAuth()
    const [person, setPerson] = useState<User | null>(null)
    const [trustScore, setTrustScore] = useState<TrustScoreData | null>(null)
    const [leases, setLeases] = useState<Lease[]>([])
    const [ratings, setRatings] = useState<Rating[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const { confirm: showConfirm, dialog } = useConfirm()
    const [showRatingsDialog, setShowRatingsDialog] = useState(false)

    const personId = Number(id)
    const isOwnProfile = authUser?.id === personId

    const handlePromote = async () => {
        if (!person) {
            return
        }

        const ok = await showConfirm({
            title: 'Promote to Manager',
            message: `Promote ${person.name} to Manager?`,
            confirmLabel: 'Promote',
            variant: 'primary',
        })

        if (!ok) {
            return
        }

        try {
            await managersApi.promote(person.id)
            setPerson({ ...person, role: 'manager' })
        } catch (err: any) {
            alert(err.response?.data?.message || 'Failed to promote')
        }
    }

    const handleDemote = async () => {
        if (!person) return
        const ok = await showConfirm({
            title: 'Demote to Tenant',
            message: `Demote ${person.name} back to Tenant? All property assignments will be removed.`,
            confirmLabel: 'Demote',
            variant: 'danger',
        })
        if (!ok) return

        try {
            await managersApi.demote(person.id)
            setPerson({ ...person, role: 'tenant' })
        } catch (err: any) {
            alert(err.response?.data?.message || 'Failed to demote')
        }
    }

    useEffect(() => {
        const load = async () => {
            try {
                // ─── ACCESS CONTROL ───
                // Tenant can only see own profile
                if (isTenant && !isOwnProfile) {
                    navigate('/')
                    return
                }

                // Resolve person data
                let foundPerson: User | null

                if (isOwnProfile && authUser) {
                    foundPerson = authUser
                } else if (isLandlord) {
                    // Landlord can see all users
                    const usersRes = await usersApi.getAll()
                    foundPerson = usersRes.data.find((u: User) => u.id === personId) || null
                } else if (isManager) {
                    // Manager can see all users (filtered in People page, but allow detail)
                    const usersRes = await usersApi.getAll()
                    foundPerson = usersRes.data.find((u: User) => u.id === personId) || null
                } else {
                    navigate('/')
                    return
                }

                if (!foundPerson) {
                    navigate(isLandlord ? '/people' : '/')
                    return
                }

                setPerson(foundPerson)

                // Only load tenant-specific data for tenant/manager roles
                const isTenantOrManager = foundPerson.role === 'tenant' || foundPerson.role === 'manager'

                const promises: Promise<any>[] = []

                if (isTenantOrManager) {
                    promises.push(
                        usersApi.getTrustScore(personId).catch(() => null),
                        leasesApi.getAll({ tenant_id: personId } as any),
                    )
                }

                const results = await Promise.all(promises)

                if (isTenantOrManager && results.length >= 2) {
                    if (results[0]) setTrustScore(results[0].data)

                    const leasesData = results[1]?.data?.data || []
                    setLeases(leasesData)

                    // Load all ratings for this user (from all landlords)
                    try {
                        const ratingsRes = await ratingsApi.getByUser(personId)
                        setRatings(Array.isArray(ratingsRes.data) ? ratingsRes.data : [])
                    } catch {
                        setRatings([])
                    }
                }
            } catch (error) {
                console.error('Failed to load person:', error)
                navigate(isLandlord ? '/people' : '/')
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [id]) // eslint-disable-line react-hooks/exhaustive-deps

    if (isLoading) return <Spinner />
    if (!person) return null

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

    const isTenantOrManager = person.role === 'tenant' || person.role === 'manager'
    const pageTitle = isOwnProfile ? 'My Profile' : isTenantOrManager ? 'Tenant Detail' : 'User Detail'
    const backPath = isOwnProfile ? '/' : '/people'

    return (
        <div>
            {/* Header */}
            <div className="flex items-center gap-4 mb-6">
                <button
                    onClick={() => navigate(backPath)}
                    className="text-gray-400 hover:text-black transition text-lg cursor-pointer"
                >
                    ←
                </button>
                <h1 className="text-2xl sm:text-4xl font-bold text-black">{pageTitle}</h1>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Profile card */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center gap-5">
                            <div className="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-600">
                                {person.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-black">{person.name}</h2>
                                <p className="text-sm text-gray-500 truncate">{person.email}</p>
                                <div className="flex items-center gap-2 mt-2">
                                    <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full capitalize">
                                        {person.role}
                                    </span>
                                    {isTenantOrManager && <TrustScoreBadge score={person.trust_score} />}
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
                                <p className="text-sm text-gray-700 font-semibold">{person.name}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Email Address</p>
                                <p className="text-sm text-gray-700 font-semibold truncate">{person.email}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Phone</p>
                                <p className="text-sm text-gray-700 font-semibold">{person.phone || '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Role</p>
                                <p className="text-sm text-gray-700 font-semibold capitalize">{person.role}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400">Member Since</p>
                                <p className="text-sm text-gray-700 font-semibold">{formatDate(person.created_at)}</p>
                            </div>
                        </div>
                    </div>

                    {/* Lease history — only for tenants/managers */}
                    {isTenantOrManager && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-4">Lease History ({leases.length})</h2>
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
                                                    <div className="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-lg shrink-0">
                                                        🏠
                                                    </div>
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
                                                            variant={
                                                                lease.status === 'active'
                                                                    ? 'green'
                                                                    : lease.status === 'terminated'
                                                                      ? 'red'
                                                                      : 'gray'
                                                            }
                                                        >
                                                            {lease.status}
                                                        </Badge>
                                                    </div>
                                                    <div className="flex items-center justify-between mt-2">
                                                        <p className="text-xs text-gray-400">
                                                            {formatDate(lease.start_date)} →{' '}
                                                            {lease.end_date ? formatDate(lease.end_date) : 'Indefinite'}
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
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Trust Score breakdown — only for tenants/managers */}
                    {isTenantOrManager && trustScore && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-4">Trust Score</h2>
                            <div className="text-center mb-4">
                                <div className="inline-flex items-center justify-center w-20 h-20 rounded-full border-4 border-green-200">
                                    <span className="text-2xl font-bold text-black">
                                        {Math.round(trustScore.trust_score)}
                                    </span>
                                </div>
                            </div>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Total Payments</span>
                                    <span className="text-sm font-semibold">{trustScore.breakdown.total_payments}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">On Time</span>
                                    <span className="text-sm font-semibold">
                                        {trustScore.breakdown.on_time_payments}
                                    </span>
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
                            <div className="mt-4">
                                <div className="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        className={`h-full rounded-full transition-all ${
                                            trustScore.trust_score >= 70
                                                ? 'bg-green-500'
                                                : trustScore.trust_score >= 40
                                                  ? 'bg-yellow-500'
                                                  : 'bg-red-500'
                                        }`}
                                        style={{ width: `${trustScore.trust_score}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Ratings summary — only for tenants/managers */}
                    {isTenantOrManager && ratings.length > 0 && (
                        <div
                            className="bg-white rounded-2xl p-6 shadow-sm cursor-pointer hover:shadow-md transition"
                            onClick={() => setShowRatingsDialog(true)}
                        >
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-lg font-bold text-black">Rating</h2>
                                <span className="text-xs text-gray-400">{ratings.length} reviews →</span>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="w-16 h-16 rounded-2xl bg-yellow-50 border border-yellow-200 flex flex-col items-center justify-center">
                                    <span className="text-xl font-bold text-black">
                                        {(ratings.reduce((sum, r) => sum + r.score, 0) / ratings.length).toFixed(1)}
                                    </span>
                                    <span className="text-yellow-500 text-sm">★</span>
                                </div>
                                <div className="flex-1">
                                    {['overall', 'apartment_condition', 'communication', 'rules']
                                        .filter((cat) => ratings.some((r) => r.category === cat))
                                        .map((cat) => {
                                            const catRatings = ratings.filter((r) => r.category === cat)
                                            const avg = catRatings.reduce((s, r) => s + r.score, 0) / catRatings.length
                                            return (
                                                <div key={cat} className="flex items-center gap-2 mb-1">
                                                    <span className="text-xs text-gray-500 w-24 truncate">
                                                        {categoryLabel(cat)}
                                                    </span>
                                                    <div className="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                        <div
                                                            className={`h-full rounded-full ${
                                                                avg >= 4
                                                                    ? 'bg-green-500'
                                                                    : avg >= 3
                                                                      ? 'bg-yellow-500'
                                                                      : 'bg-red-500'
                                                            }`}
                                                            style={{ width: `${(avg / 5) * 100}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs font-semibold text-gray-600 w-6 text-right">
                                                        {avg.toFixed(1)}
                                                    </span>
                                                </div>
                                            )
                                        })}
                                </div>
                            </div>
                        </div>
                    )}

                    {isTenantOrManager && ratings.length === 0 && (
                        <div className="bg-white rounded-2xl p-6 shadow-sm">
                            <h2 className="text-lg font-bold text-black mb-3">Rating</h2>
                            <p className="text-sm text-gray-400 text-center py-2">No ratings yet</p>
                        </div>
                    )}

                    {/* Quick actions */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-black mb-3">Quick Actions</h2>
                        <div className="space-y-2">
                            {isOwnProfile && (
                                <Button variant="secondary" className="w-full" onClick={() => navigate('/settings')}>
                                    Edit Profile
                                </Button>
                            )}
                            {isLandlord && !isOwnProfile && person.role === 'tenant' && (
                                <Button variant="primary" className="w-full" onClick={handlePromote}>
                                    👑 Promote to Manager
                                </Button>
                            )}
                            {isLandlord && !isOwnProfile && person.role === 'manager' && (
                                <Button variant="danger" className="w-full" onClick={handleDemote}>
                                    Demote to Tenant
                                </Button>
                            )}
                            {isTenantOrManager && (
                                <>
                                    <Button variant="secondary" className="w-full" onClick={() => navigate('/leases')}>
                                        View Leases
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        className="w-full"
                                        onClick={() => navigate('/payments')}
                                    >
                                        View Payments
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            {/* Ratings Detail Dialog */}
            {showRatingsDialog && (
                <div className="fixed inset-0 z-50 flex items-center justify-center">
                    <div className="fixed inset-0 bg-black/40" onClick={() => setShowRatingsDialog(false)} />
                    <div className="relative bg-white rounded-2xl p-6 shadow-xl max-w-md w-full mx-4 max-h-[80vh] overflow-y-auto">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-bold text-black">All Ratings</h3>
                            <button
                                onClick={() => setShowRatingsDialog(false)}
                                className="text-gray-400 hover:text-black transition cursor-pointer text-xl"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Overall average */}
                        <div className="text-center mb-6 p-4 bg-gray-50 rounded-xl">
                            <div className="flex items-center justify-center gap-1 mb-1">
                                {[1, 2, 3, 4, 5].map((star) => {
                                    const avg = ratings.reduce((s, r) => s + r.score, 0) / ratings.length
                                    return (
                                        <span
                                            key={star}
                                            className={`text-xl ${star <= Math.round(avg) ? 'text-yellow-400' : 'text-gray-200'}`}
                                        >
                                            ★
                                        </span>
                                    )
                                })}
                            </div>
                            <p className="text-2xl font-bold text-black">
                                {(ratings.reduce((s, r) => s + r.score, 0) / ratings.length).toFixed(1)}
                            </p>
                            <p className="text-xs text-gray-500">Average from {ratings.length} ratings</p>
                        </div>

                        {/* Per category */}
                        <div className="space-y-4">
                            {['overall', 'apartment_condition', 'communication', 'rules'].map((cat) => {
                                const catRatings = ratings.filter((r) => r.category === cat)
                                if (catRatings.length === 0) {
                                    return null
                                }

                                return (
                                    <div key={cat}>
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-semibold text-black">
                                                {categoryLabel(cat)}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <span className="text-sm font-bold text-black">
                                                    {(
                                                        catRatings.reduce((s, r) => s + r.score, 0) / catRatings.length
                                                    ).toFixed(1)}
                                                </span>
                                                <span className="text-yellow-500 text-sm">★</span>
                                            </div>
                                        </div>
                                        {catRatings.map((rating) => (
                                            <div key={rating.id} className="ml-2 mb-2 p-3 bg-gray-50 rounded-lg">
                                                <div className="flex items-center gap-1 mb-1">
                                                    {[1, 2, 3, 4, 5].map((star) => (
                                                        <span
                                                            key={star}
                                                            className={`text-xs ${star <= rating.score ? 'text-yellow-400' : 'text-gray-200'}`}
                                                        >
                                                            ★
                                                        </span>
                                                    ))}
                                                </div>
                                                {rating.comment && (
                                                    <p className="text-xs text-gray-600 mt-1">{rating.comment}</p>
                                                )}
                                                <p className="text-xs text-gray-400 mt-1">
                                                    {rating.rated_by?.name || '—'}
                                                    {(rating as any).property?.address &&
                                                        ` · ${(rating as any).property.address}`}
                                                    {' · '}
                                                    {formatDate(rating.created_at)}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )
                            })}
                        </div>
                    </div>
                </div>
            )}
            {dialog}
        </div>
    )
}
