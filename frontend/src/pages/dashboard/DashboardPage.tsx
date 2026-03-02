import {useAuth} from '../../contexts/AuthContext'

export default function DashboardPage() {
    const {user} = useAuth()

    return (
        <div>
            <h1 className="text-4xl font-bold text-black">Overview</h1>
            <p className="mt-2 text-gray-500">Welcome back, {user?.name}</p>
        </div>
    )
}
