import { useEffect, useRef, useState } from 'react'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { notificationsApi } from '../../api/notifications'
import type { Notification } from '../../types'

const allMenuItems = [
    { label: 'Overview', path: '/', icon: '📊', roles: ['landlord', 'tenant', 'manager'] },
    { label: 'Portfolio', path: '/properties', icon: '🏠', roles: ['landlord', 'tenant', 'manager'] },
    { label: 'Leases', path: '/leases', icon: '📋', roles: ['landlord', 'tenant', 'manager'] },
    { label: 'Payments', path: '/payments', icon: '💰', roles: ['landlord', 'tenant', 'manager'] },
    { label: 'Helpdesk', path: '/tickets', icon: '🎫', roles: ['landlord', 'tenant', 'manager'] },
    { label: 'People', path: '/people', icon: '👥', roles: ['landlord', 'manager'] },
    { label: 'Documents', path: '/documents', icon: '📄', roles: ['landlord', 'tenant', 'manager'] },
]

const supportItems = [{ label: 'Settings', path: '/settings', icon: '⚙️' }]

interface Props {
    isOpen: boolean
    onClose: () => void
}

const formatTimeAgo = (dateStr: string) => {
    const diff = Date.now() - new Date(dateStr).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d ago`
    return new Date(dateStr).toLocaleDateString()
}

const getNotificationIcon = (type: string): string => {
    switch (type) {
        case 'lease_created':
        case 'tenant_invitation':
            return '📋'
        case 'lease_expiring':
            return '⏰'
        case 'ticket_created':
            return '🎫'
        case 'ticket_resolved':
            return '✅'
        case 'payment_marked_paid':
            return '💚'
        case 'payment_overdue':
            return '🔴'
        default:
            return '🔔'
    }
}

const getNotificationText = (notification: Notification): { title: string; body: string; link?: string } => {
    const data = notification.data
    switch (notification.type) {
        case 'lease_created':
            return {
                title: 'Nová smlouva',
                body: `Byla vytvořena nová smlouva pro ${(data.property_address as string) || 'nemovitost'}.`,
                link: data.lease_id ? `/leases/${data.lease_id}` : '/leases',
            }
        case 'tenant_invitation':
            return {
                title: 'Pozvánka k nájmu',
                body: `Byli jste pozváni jako nájemník v ${(data.property_address as string) || 'nemovitosti'}.`,
                link: data.lease_id ? `/leases/${data.lease_id}` : '/leases',
            }
        case 'lease_expiring':
            return {
                title: 'Smlouva brzy končí',
                body: `Smlouva pro ${(data.property_address as string) || 'nemovitost'} končí za ${(data.days_left as number) || '?'} dní (${(data.end_date as string) || ''}).`,
                link: data.lease_id ? `/leases/${data.lease_id}` : '/leases',
            }
        case 'ticket_created':
            return {
                title: 'Nový ticket',
                body: `${(data.tenant_name as string) || 'Nájemník'} nahlásil: ${(data.ticket_title as string) || 'závadu'}.`,
                link: data.ticket_id ? `/tickets/${data.ticket_id}` : '/tickets',
            }
        case 'ticket_resolved':
            return {
                title: 'Ticket vyřešen',
                body: `Ticket "${(data.ticket_title as string) || ''}" byl vyřešen.`,
                link: data.ticket_id ? `/tickets/${data.ticket_id}` : '/tickets',
            }
        case 'payment_marked_paid':
            return {
                title: 'Platba přijata',
                body: `Platba ${(data.amount as string) || ''} byla označena jako zaplacená.`,
                link: '/payments',
            }
        case 'payment_overdue':
            return {
                title: 'Platba po splatnosti',
                body: `Platba pro ${(data.property_address as string) || 'nemovitost'} je ${(data.days_overdue as number) || '?'} dní po splatnosti (${(data.amount as string) || ''} Kč).`,
                link: '/payments',
            }
        default:
            return {
                title: 'Oznámení',
                body: (data.message as string) || 'Máte nové oznámení.',
            }
    }
}

export default function Sidebar({ isOpen, onClose }: Props) {
    const { user, logout } = useAuth()
    const navigate = useNavigate()
    useLocation()

    const [notifications, setNotifications] = useState<Notification[]>([])
    const [showNotifications, setShowNotifications] = useState(false)
    const notifRef = useRef<HTMLDivElement>(null)

    const unreadCount = notifications.filter((n) => !n.read).length

    const loadNotifications = async () => {
        try {
            const res = await notificationsApi.getAll()
            setNotifications(Array.isArray(res.data) ? res.data : [])
        } catch {
            // silently fail
        }
    }

    useEffect(() => {
        if (user) {
            void loadNotifications()
            // Poll every 60 seconds
            const interval = setInterval(() => void loadNotifications(), 60000)
            return () => clearInterval(interval)
        }
    }, [user])

    // Close dropdown on outside click
    useEffect(() => {
        const handleClick = (e: MouseEvent) => {
            if (notifRef.current && !notifRef.current.contains(e.target as Node)) {
                setShowNotifications(false)
            }
        }
        if (showNotifications) {
            window.document.addEventListener('mousedown', handleClick)
            return () => window.document.removeEventListener('mousedown', handleClick)
        }
    }, [showNotifications])

    const handleMarkRead = async (notification: Notification) => {
        if (!notification.read) {
            try {
                await notificationsApi.markRead(notification.id)
                setNotifications((prev) => prev.map((n) => (n.id === notification.id ? { ...n, read: true } : n)))
            } catch {
                // silently fail
            }
        }

        const { link } = getNotificationText(notification)
        if (link) {
            navigate(link)
            setShowNotifications(false)
            onClose()
        }
    }

    const handleMarkAllRead = async () => {
        const unread = notifications.filter((n) => !n.read)
        try {
            await Promise.all(unread.map((n) => notificationsApi.markRead(n.id)))
            setNotifications((prev) => prev.map((n) => ({ ...n, read: true })))
        } catch {
            // silently fail
        }
    }

    const handleNavClick = () => {
        onClose()
    }

    const userRole = user?.role || 'tenant'
    const menuItems = allMenuItems.filter((item) => item.roles.includes(userRole))

    return (
        <>
            {/* Overlay */}
            {isOpen && <div className="fixed inset-0 bg-black/40 z-40 lg:hidden cursor-pointer" onClick={onClose} />}

            {/* Sidebar */}
            <aside
                className={`fixed top-0 left-0 h-full w-62.5 bg-white border-r border-gray-200 flex flex-col z-50 transition-transform duration-300 lg:translate-x-0 ${
                    isOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Logo + Bell */}
                <div className="px-6 py-6 flex items-center justify-between">
                    <h1 className="text-xl font-bold text-black">RentFlow</h1>
                    <div className="flex items-center gap-2">
                        {/* Notification bell */}
                        <div ref={notifRef} className="relative">
                            <button
                                onClick={() => setShowNotifications(!showNotifications)}
                                className="relative w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 transition cursor-pointer"
                                title="Notifications"
                            >
                                <span className="text-lg">🔔</span>
                                {unreadCount > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                                        {unreadCount > 9 ? '9+' : unreadCount}
                                    </span>
                                )}
                            </button>

                            {/* Dropdown */}
                            {showNotifications && (
                                <div className="absolute left-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                                    <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                                        <h3 className="text-sm font-bold text-black">Oznámení</h3>
                                        {unreadCount > 0 && (
                                            <button
                                                onClick={handleMarkAllRead}
                                                className="text-xs text-gray-500 hover:text-black transition cursor-pointer"
                                            >
                                                Označit vše jako přečtené
                                            </button>
                                        )}
                                    </div>
                                    <div className="max-h-80 overflow-y-auto">
                                        {notifications.length === 0 ? (
                                            <div className="p-6 text-center">
                                                <span className="text-2xl">🔕</span>
                                                <p className="text-sm text-gray-400 mt-2">Zatím žádná oznámení</p>
                                            </div>
                                        ) : (
                                            notifications.map((notification) => {
                                                const { title, body } = getNotificationText(notification)
                                                const icon = getNotificationIcon(notification.type)
                                                return (
                                                    <div
                                                        key={notification.id}
                                                        onClick={() => handleMarkRead(notification)}
                                                        className={`p-3 border-b border-gray-50 hover:bg-gray-50 transition cursor-pointer ${
                                                            !notification.read ? 'bg-blue-50/50' : ''
                                                        }`}
                                                    >
                                                        <div className="flex items-start gap-2">
                                                            <span className="text-base mt-0.5 shrink-0">{icon}</span>
                                                            <div className="flex-1 min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <p className="text-sm font-semibold text-black">
                                                                        {title}
                                                                    </p>
                                                                    {!notification.read && (
                                                                        <span className="w-2 h-2 bg-blue-500 rounded-full shrink-0" />
                                                                    )}
                                                                </div>
                                                                <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">
                                                                    {body}
                                                                </p>
                                                                <p className="text-xs text-gray-400 mt-1">
                                                                    {formatTimeAgo(notification.created_at)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )
                                            })
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        <button
                            onClick={onClose}
                            className="lg:hidden text-gray-400 hover:text-black transition text-xl cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                {/* Menu */}
                <nav className="flex-1 px-3 overflow-y-auto">
                    <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">Menu</p>
                    {menuItems.map((item) => (
                        <NavLink
                            key={item.path}
                            to={item.path}
                            onClick={handleNavClick}
                            end={item.path === '/'}
                            className={({ isActive }) =>
                                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition mb-0.5 cursor-pointer ${
                                    isActive
                                        ? 'bg-gray-100 text-black'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-black'
                                }`
                            }
                        >
                            <span>{item.icon}</span>
                            {item.label}
                        </NavLink>
                    ))}

                    <div className="mt-8">
                        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">
                            Help & Support
                        </p>
                        {supportItems.map((item) => (
                            <NavLink
                                key={item.path}
                                to={item.path}
                                onClick={handleNavClick}
                                className={({ isActive }) =>
                                    `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition mb-0.5 cursor-pointer ${
                                        isActive
                                            ? 'bg-gray-100 text-black'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-black'
                                    }`
                                }
                            >
                                <span>{item.icon}</span>
                                {item.label}
                            </NavLink>
                        ))}
                    </div>
                </nav>

                {/* User */}
                {user && (
                    <div className="px-4 py-4 border-t border-gray-100">
                        <div className="flex items-center gap-3">
                            <div
                                onClick={() => {
                                    navigate('/people/' + user.id)
                                    handleNavClick()
                                }}
                                className="flex items-center gap-3 flex-1 min-w-0 cursor-pointer hover:opacity-80 transition"
                            >
                                <div className="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600">
                                    {user.name.charAt(0).toUpperCase()}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-black truncate">{user.name}</p>
                                    <p className="text-xs text-gray-500 truncate">{user.email}</p>
                                </div>
                            </div>
                            <button
                                onClick={() => {
                                    logout()
                                    handleNavClick()
                                }}
                                className="text-gray-400 hover:text-black transition cursor-pointer"
                                title="Logout"
                            >
                                ↪
                            </button>
                        </div>
                    </div>
                )}
            </aside>
        </>
    )
}
