import {NavLink, useLocation} from 'react-router-dom'
import {useAuth} from '../../contexts/AuthContext'

const menuItems = [
    {label: 'Overview', path: '/', icon: '📊'},
    {label: 'Portfolio', path: '/properties', icon: '🏠'},
    {label: 'Leases', path: '/leases', icon: '📋'},
    {label: 'Payments', path: '/payments', icon: '💰'},
    {label: 'Helpdesk', path: '/tickets', icon: '🎫'},
    {label: 'People', path: '/people', icon: '👥'},
    {label: 'Documents', path: '/documents', icon: '📄'},
]

const supportItems = [
    {label: 'Settings', path: '/settings', icon: '⚙️'},
]

interface Props {
    isOpen: boolean
    onClose: () => void
}

export default function Sidebar({isOpen, onClose}: Props) {
    const {user, logout} = useAuth()
    useLocation();
    const handleNavClick = () => {
        onClose()
    }

    return (
        <>
            {/* Overlay */}
            {isOpen && (
                <div
                    className="fixed inset-0 bg-black/40 z-40 lg:hidden"
                    onClick={onClose}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed top-0 left-0 h-full w-62.5 bg-white border-r border-gray-200 flex flex-col z-50 transition-transform duration-300 lg:translate-x-0 ${
                    isOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Logo */}
                <div className="px-6 py-6 flex items-center justify-between">
                    <h1 className="text-xl font-bold text-black">RentFlow</h1>
                    <button
                        onClick={onClose}
                        className="lg:hidden text-gray-400 hover:text-black transition text-xl"
                    >
                        ✕
                    </button>
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
                            className={({isActive}) =>
                                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition mb-0.5 ${
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
                        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">Help &
                            Support</p>
                        {supportItems.map((item) => (
                            <NavLink
                                key={item.path}
                                to={item.path}
                                onClick={handleNavClick}
                                className={({isActive}) =>
                                    `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition mb-0.5 ${
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
                                className="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold text-black truncate">{user.name}</p>
                                <p className="text-xs text-gray-500 truncate">{user.email}</p>
                            </div>
                            <button
                                onClick={() => {
                                    logout();
                                    handleNavClick()
                                }}
                                className="text-gray-400 hover:text-black transition"
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
