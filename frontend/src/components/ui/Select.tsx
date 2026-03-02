import * as React from 'react'
import {forwardRef} from 'react'

interface Props extends React.SelectHTMLAttributes<HTMLSelectElement> {
    label?: string
    error?: string
    options: { value: string; label: string }[]
    placeholder?: string
}

const Select = forwardRef<HTMLSelectElement, Props>(({
                                                         label,
                                                         error,
                                                         options,
                                                         placeholder,
                                                         className = '',
                                                         ...props
                                                     }, ref) => {
    return (
        <div>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
            )}
            <select
                ref={ref}
                className={`w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition bg-white ${error ? 'border-red-300' : ''} ${className}`}
                {...props}
            >
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    )
})

Select.displayName = 'Select'
export default Select
