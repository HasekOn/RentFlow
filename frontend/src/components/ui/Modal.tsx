import * as React from 'react'
import { useEffect } from 'react'

interface Props {
    isOpen: boolean
    onClose: () => void
    title: string
    children: React.ReactNode
    size?: 'sm' | 'md' | 'lg'
}

const sizes = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
}

export default function Modal({ isOpen, onClose, title, children, size = 'md' }: Props) {
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden'
        } else {
            document.body.style.overflow = ''
        }
        return () => {
            document.body.style.overflow = ''
        }
    }, [isOpen])

    useEffect(() => {
        if (!isOpen) return
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose()
        }
        window.addEventListener('keydown', handleKey)
        return () => window.removeEventListener('keydown', handleKey)
    }, [isOpen, onClose])

    if (!isOpen) return null

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center"
            role="dialog"
            aria-modal="true"
            aria-label={title}
        >
            <div className="absolute inset-0 bg-black/40 cursor-pointer" onClick={onClose} aria-hidden="true" />
            <div
                className={`relative bg-white rounded-2xl shadow-xl p-6 w-full ${sizes[size]} mx-4 max-h-[90vh] overflow-y-auto`}
            >
                <div className="flex items-center justify-between mb-6">
                    <h2 className="text-xl font-bold text-black">{title}</h2>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-black transition text-xl leading-none cursor-pointer"
                        aria-label="Close dialog"
                    >
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    )
}
