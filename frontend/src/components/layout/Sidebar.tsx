import {NavLink, useNavigate} from 'react-router-dom'
import {useAuth} from '../../contexts/AuthContext'

const menuItems = [
    {label: 'Overview', path: '/', icon: '📊'},
    {label: 'Portfolio', path: '/properties', icon: '🏠'},
    {label: 'Helpdesk', path: '/tickets', icon: '🎫'},
    {label: 'People', path: '/people', icon: '👥'},
    {label: 'Documents', path: '/documents', icon: '📄'},
]

const supportItems = [
    {label: 'Settings', path: '/settings', icon: '⚙️'},
]

export default function Sidebar() {
    const {user, logout} = useAuth()
    const navigate = useNavigate()

    const handleLogout = async () => {
        await logout()
        navigate('/login')
    }

    const linkClasses = ({isActive}: { isActive: boolean }) =>
        `flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-colors ${
            isActive
                ? 'bg-gray-100 text-black font-semibold'
                : 'text-gray-600 hover:bg-gray-50 hover:text-black'
        }`

    return (
        <aside className="fixed left-0 top-0 h-screen w-[250px] bg-white flex flex-col border-r border-gray-200">
            {/* Logo */}
            <div className="px-6 py-6">
                <h1 className="text-2xl font-bold text-black tracking-tight">
                    RentFlow
                </h1>
            </div>

            {/* Menu */}
            <nav className="flex-1 px-3">
                <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                    Menu
                </p>
                {menuItems.map((item) => (
                    <NavLink key={item.path} to={item.path} className={linkClasses}>
                        <span className="text-base">{item.icon}</span>
                        <span>{item.label}</span>
                    </NavLink>
                ))}

                <div className="my-6"/>

                <p className="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                    Help & Support
                </p>
                {supportItems.map((item) => (
                    <NavLink key={item.path} to={item.path} className={linkClasses}>
                        <span className="text-base">{item.icon}</span>
                        <span>{item.label}</span>
                    </NavLink>
                ))}
            </nav>

            {/* User profile */}
            <div className="px-4 py-4 border-t border-gray-100">
                <div className="flex items-center gap-3">
                    <div
                        className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600">
                        {user?.name?.charAt(0)?.toUpperCase()}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-black truncate">{user?.name}</p>
                        <p className="text-xs text-gray-500 truncate">{user?.email}</p>
                    </div>
                    <button
                        onClick={handleLogout}
                        className="text-gray-400 hover:text-black transition-colors"
                        title="Logout"
                    >
                        ⬡
                    </button>
                </div>
            </div>
        </aside>
    )
}
