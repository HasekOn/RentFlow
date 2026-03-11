import { useCallback, useState } from 'react'
import ConfirmDialog from '../components/ui/ConfirmDialog'

interface ConfirmOptions {
    title: string
    message: string
    confirmLabel?: string
    variant?: 'danger' | 'primary'
}

export function useConfirm() {
    const [state, setState] = useState<{
        options: ConfirmOptions
        resolve: (value: boolean) => void
    } | null>(null)

    const confirm = useCallback((options: ConfirmOptions): Promise<boolean> => {
        return new Promise((resolve) => {
            setState({ options, resolve })
        })
    }, [])

    const handleConfirm = () => {
        state?.resolve(true)
        setState(null)
    }

    const handleCancel = () => {
        state?.resolve(false)
        setState(null)
    }

    const dialog = state ? (
        <ConfirmDialog
            isOpen={true}
            title={state.options.title}
            message={state.options.message}
            confirmLabel={state.options.confirmLabel}
            variant={state.options.variant}
            onConfirm={handleConfirm}
            onCancel={handleCancel}
        />
    ) : null

    return { confirm, dialog }
}
