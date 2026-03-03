import Button from './Button'

interface Props {
    isOpen: boolean
    title: string
    message: string
    confirmLabel?: string
    variant?: 'danger' | 'primary'
    onConfirm: () => void
    onCancel: () => void
}

export default function ConfirmDialog({
                                          isOpen,
                                          title,
                                          message,
                                          confirmLabel = 'Confirm',
                                          variant = 'danger',
                                          onConfirm,
                                          onCancel
                                      }: Props) {
    if (!isOpen) return null

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-black/40" onClick={onCancel}/>
            <div className="relative bg-white rounded-2xl p-6 shadow-xl max-w-sm w-full mx-4 animate-in">
                <h3 className="text-lg font-bold text-black">{title}</h3>
                <p className="text-sm text-gray-500 mt-2">{message}</p>
                <div className="flex justify-end gap-3 mt-6">
                    <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                    <Button variant={variant} onClick={onConfirm}>{confirmLabel}</Button>
                </div>
            </div>
        </div>
    )
}
