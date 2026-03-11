import * as React from 'react'
import { forwardRef } from 'react'

interface Props extends React.SelectHTMLAttributes<HTMLSelectElement> {
    label?: string
    error?: string
    options: { value: string; label: string }[]
    placeholder?: string
}

const Select = forwardRef<HTMLSelectElement, Props>(
    ({ label, error, options, placeholder, className = '', ...props }, ref) => {
        return (
            <div>
                {label && <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>}
                <select
                    ref={ref}
                    className={`
                    cursor-pointer disabled:cursor-not-allowed disabled:opacity-50
                    w-full px-4 py-2.5 rounded-lg text-sm transition-all duration-200
                    focus:outline-none focus:ring-2 focus:ring-black/10
                    ${
                        error
                            ? 'border border-red-300 bg-white hover:bg-red-50 focus:border-red-500 focus:ring-red-500/20'
                            : 'border border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300 focus:border-gray-400'
                    }
                    ${className}
                `}
                    {...props}
                >
                    {placeholder && <option value="">{placeholder}</option>}
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
                {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            </div>
        )
    },
)

Select.displayName = 'Select'
export default Select
