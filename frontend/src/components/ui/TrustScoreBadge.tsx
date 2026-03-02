interface Props {
    score: number
    size?: 'sm' | 'md'
}

function getScoreInfo(score: number) {
    if (score >= 80) return {label: 'Excellent', color: 'bg-green-100 text-green-700'}
    if (score >= 60) return {label: 'Good', color: 'bg-green-50 text-green-600'}
    if (score >= 40) return {label: 'Fair', color: 'bg-yellow-100 text-yellow-700'}
    if (score >= 20) return {label: 'Poor', color: 'bg-red-100 text-red-600'}
    return {label: 'Critical', color: 'bg-red-200 text-red-700'}
}

export default function TrustScoreBadge({score, size = 'md'}: Props) {
    const {label, color} = getScoreInfo(score)

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full font-semibold ${color} ${
            size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'
        }`}>
      {Math.round(score)}
            <span className="font-normal">{label}</span>
    </span>
    )
}
