import * as React from "react";

interface Props {
    children: React.ReactNode
    variant?: 'green' | 'red' | 'yellow' | 'gray' | 'pink'
}

const variants = {
    green: 'bg-green-100 text-green-700',
    red: 'bg-red-100 text-red-700',
    yellow: 'bg-yellow-100 text-yellow-700',
    gray: 'bg-gray-100 text-gray-700',
    pink: 'bg-pink-100 text-pink-700',
}

export default function Badge({children, variant = 'gray'}: Props) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium ${variants[variant]}`}>
      <span className="w-1.5 h-1.5 rounded-full bg-current"/>
            {children}
    </span>
    )
}
