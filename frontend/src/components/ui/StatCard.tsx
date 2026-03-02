interface Props {
    label: string
    value: string | number
    subtitle?: string
    accent?: 'green' | 'red' | 'yellow' | 'default'
}

const accentColors = {
    green: 'text-green-600',
    red: 'text-red-600',
    yellow: 'text-yellow-600',
    default: 'text-black',
}

export default function StatCard({label, value, subtitle, accent = 'default'}: Props) {
    return (
        <div className="bg-white rounded-2xl p-6 shadow-sm">
            <p className="text-sm text-gray-500">{label}</p>
            <p className={`text-2xl font-bold mt-1 ${accentColors[accent]}`}>
                {value}
            </p>
            {subtitle && (
                <p className="text-xs text-gray-400 mt-1">{subtitle}</p>
            )}
        </div>
    )
}
