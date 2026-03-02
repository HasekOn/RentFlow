import * as React from 'react'
import {forwardRef} from 'react'

interface Props extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string
    error?: string
}

const Input = forwardRef<HTMLInputElement, Props>(({label, error, className = '', ...props}, ref) => {
    return (
        <div>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
            )}
            <input
                ref={ref}
                className={`w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition ${error ? 'border-red-300' : ''} ${className}`}
                {...props}
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    )
})

Input.displayName = 'Input'
export default Input
