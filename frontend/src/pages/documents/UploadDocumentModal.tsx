import * as React from 'react'
import {useRef, useState} from 'react'
import {documentsApi} from '../../api/documents'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

const fileIcon = (name: string) => {
    const ext = name.split('.').pop()?.toLowerCase()
    switch (ext) {
        case 'pdf':
            return '📄'
        case 'doc':
        case 'docx':
            return '📝'
        case 'xls':
        case 'xlsx':
            return '📊'
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'webp':
            return '🖼️'
        default:
            return '📎'
    }
}

interface Props {
    isOpen: boolean
    onClose: () => void
    propertyId: number
    onSuccess: () => void
}

export default function UploadDocumentModal({isOpen, onClose, propertyId, onSuccess}: Props) {
    const [file, setFile] = useState<File | null>(null)
    const [type, setType] = useState('')
    const [description, setDescription] = useState('')
    const [validUntil, setValidUntil] = useState('')
    const [errors, setErrors] = useState<Record<string, string[]>>({})
    const [isUploading, setIsUploading] = useState(false)
    const fileInputRef = useRef<HTMLInputElement>(null)

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        if (!file) return

        setErrors({})
        setIsUploading(true)

        const formData = new FormData()
        formData.append('file', file)
        formData.append('name', file.name)
        if (type) formData.append('document_type', type)
        if (description) formData.append('description', description)
        if (validUntil) formData.append('valid_until', validUntil)

        try {
            await documentsApi.upload(propertyId, formData)
            setFile(null)
            setType('')
            setDescription('')
            setValidUntil('')
            if (fileInputRef.current) fileInputRef.current.value = ''
            onSuccess()
        } catch (err: any) {
            setErrors(err.response?.data?.errors || {})
        } finally {
            setIsUploading(false)
        }
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Upload Document" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">File</label>
                    <input
                        ref={fileInputRef}
                        type="file"
                        onChange={(e) => setFile(e.target.files?.[0] || null)}
                        className="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-black file:text-white hover:file:bg-gray-800 file:cursor-pointer"
                        required
                    />
                    {errors.file?.[0] && (
                        <p className="mt-1 text-xs text-red-600">{errors.file[0]}</p>
                    )}
                </div>

                <Select
                    label="Document Type"
                    name="type"
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                    placeholder="Select type..."
                    options={[
                        {value: 'contract', label: 'Contract'},
                        {value: 'protocol', label: 'Protocol'},
                        {value: 'invoice', label: 'Invoice'},
                        {value: 'insurance', label: 'Insurance'},
                        {value: 'photo', label: 'Photo'},
                        {value: 'other', label: 'Other'},
                    ]}
                />

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <textarea
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        rows={2}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 resize-none"
                        placeholder="Optional description..."
                    />
                </div>

                <Input
                    label="Valid Until (optional)"
                    type="date"
                    value={validUntil}
                    onChange={(e) => setValidUntil(e.target.value)}
                />

                {file && (
                    <div className="bg-gray-50 rounded-lg p-3 flex items-center gap-3">
                        <span className="text-xl">{fileIcon(file.name)}</span>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-black truncate">{file.name}</p>
                            <p className="text-xs text-gray-400">{(file.size / 1024).toFixed(1)} KB</p>
                        </div>
                    </div>
                )}

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={isUploading || !file}>
                        {isUploading ? 'Uploading...' : 'Upload'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
