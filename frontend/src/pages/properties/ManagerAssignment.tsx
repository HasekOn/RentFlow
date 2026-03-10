import {useEffect, useState} from 'react'
import {managersApi} from '../../api/managers'
import {usersApi} from '../../api/users'
import type {User} from '../../types'
import Button from '../../components/ui/Button'
import {useConfirm} from '../../hooks/useConfirm'

interface Props {
    propertyId: number
}

export default function ManagerAssignment({propertyId}: Props) {
    const [managers, setManagers] = useState<User[]>([])
    const [allManagers, setAllManagers] = useState<User[]>([])
    const [selectedId, setSelectedId] = useState('')
    const [isLoading, setIsLoading] = useState(true)
    const {confirm: showConfirm, dialog} = useConfirm()

    const load = async () => {
        try {
            const [assignedRes, usersRes] = await Promise.all([
                managersApi.getByProperty(propertyId),
                usersApi.getAll({role: 'manager'}),
            ])
            setManagers(Array.isArray(assignedRes.data) ? assignedRes.data : [])
            setAllManagers(Array.isArray(usersRes.data) ? usersRes.data : [])
        } catch (error) {
            console.error('Failed to load managers:', error)
        } finally {
            setIsLoading(false)
        }
    }

    useEffect(() => {
        void load()
    }, [propertyId])

    const handleAssign = async () => {
        if (!selectedId) return
        try {
            await managersApi.assign(propertyId, Number(selectedId))
            setSelectedId('')
            void load()
        } catch (err: any) {
            alert(err.response?.data?.message || 'Failed to assign')
        }
    }

    const handleRemove = async (userId: number) => {
        const ok = await showConfirm({
            title: 'Remove Manager',
            message: 'Remove this manager from property?',
            confirmLabel: 'Remove',
            variant: 'danger',
        })

        if (!ok) {
            return
        }

        try {
            await managersApi.remove(propertyId, userId)
            void load()
        } catch (err: any) {
            alert(err.response?.data?.message || 'Failed to remove')
        }
    }

    if (isLoading) return null

    const assignedIds = managers.map((m) => m.id)
    const available = allManagers.filter((m) => !assignedIds.includes(m.id))

    return (
        <div className="bg-white rounded-2xl p-6 shadow-sm">
            <h2 className="text-lg font-bold text-black mb-3">Managers</h2>

            {managers.length === 0 ? (
                <p className="text-sm text-gray-400 mb-3">No managers assigned</p>
            ) : (
                <div className="space-y-2 mb-3">
                    {managers.map((manager) => (
                        <div key={manager.id}
                             className="flex items-center justify-between p-2 border border-gray-100 rounded-xl">
                            <div className="flex items-center gap-2">
                                <div
                                    className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600">
                                    {manager.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-black">{manager.name}</p>
                                    <p className="text-xs text-gray-500">{manager.email}</p>
                                </div>
                            </div>
                            <button
                                onClick={() => handleRemove(manager.id)}
                                className="text-xs text-red-500 hover:text-red-700 transition cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>
                    ))}
                </div>
            )}
            {dialog}
            {available.length > 0 && (
                <div className="flex gap-2">
                    <select
                        value={selectedId}
                        onChange={(e) => setSelectedId(e.target.value)}
                        className="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10"
                    >
                        <option value="">Select manager...</option>
                        {available.map((m) => (
                            <option key={m.id} value={m.id}>{m.name}</option>
                        ))}
                    </select>
                    <Button size="sm" onClick={handleAssign} disabled={!selectedId}>
                        Assign
                    </Button>
                </div>
            )}

            {available.length === 0 && managers.length === 0 && (
                <p className="text-xs text-gray-400">No managers available. Promote a tenant first in People.</p>
            )}
        </div>
    )
}
