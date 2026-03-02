import * as React from "react";

interface Props extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'danger' | 'success'
    size?: 'sm' | 'md'
}

const variants = {
    primary: 'bg-black text-white hover:bg-gray-800',
    secondary: 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    success: 'bg-green-600 text-white hover:bg-green-700',
}

const sizes = {
    sm: 'px-4 py-1.5 text-xs',
    md: 'px-5 py-2.5 text-sm',
}

export default function Button({variant = 'primary', size = 'md', className = '', children, ...props}: Props) {
    return (
        <button
            className={`font-semibold rounded-full transition disabled:opacity-50 ${variants[variant]} ${sizes[size]} ${className}`}
            {...props}
        >
            {children}
        </button>
    )
}
