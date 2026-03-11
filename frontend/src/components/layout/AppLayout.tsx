import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'

export default function AppLayout() {
    const [sidebarOpen, setSidebarOpen] = useState(false)

    return (
        <div className="min-h-screen bg-gray-100">
            <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            {/* Mobile header */}
            <div className="lg:hidden fixed top-0 left-0 right-0 h-14 bg-white border-b border-gray-200 flex items-center px-4 z-30">
                <button
                    onClick={() => setSidebarOpen(true)}
                    className="text-xl text-gray-600 hover:text-black transition mr-3 cursor-pointer"
                >
                    ☰
                </button>
                <h1 className="text-lg font-bold text-black">RentFlow</h1>
            </div>

            {/* Main content */}
            <main className="lg:ml-62.5 p-4 sm:p-6 lg:p-8 pt-18 lg:pt-8">
                <Outlet />
            </main>
        </div>
    )
}
