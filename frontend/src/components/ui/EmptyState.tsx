import * as React from 'react'

interface Props {
    title: string
    description?: string
    action?: React.ReactNode
}

export default function EmptyState({ title, description, action }: Props) {
    return (
        <div className="text-center py-16">
            <p className="text-lg font-semibold text-gray-400">{title}</p>
            {description && <p className="mt-1 text-sm text-gray-400">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    )
}
