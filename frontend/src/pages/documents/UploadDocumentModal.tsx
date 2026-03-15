import * as React from 'react'
import { useState } from 'react'
import { documentsApi } from '../../api/documents'
import Modal from '../../components/ui/Modal'
import Input from '../../components/ui/Input'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    propertyId: number
    onSuccess: () => void
}

export default function UploadDocumentModal({ isOpen, onClose, propertyId, onSuccess }: Props) {
    const [file, setFile] = useState<File | null>(null)
    const [name, setName] = useState('')
    const [documentType, setDocumentType] = useState('contract')
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState('')

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0]
        if (selected) {
            setFile(selected)
            if (!name) {
                setName(selected.name)
            }
        }
    }

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        if (!file) return

        setError('')
        setIsLoading(true)

        try {
            const formData = new FormData()
            formData.append('file', file)
            formData.append('name', name || file.name)
            formData.append('document_type', documentType)

            await documentsApi.upload(propertyId, formData)
            setFile(null)
            setName('')
            setDocumentType('contract')
            onSuccess()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Upload failed')
        } finally {
            setIsLoading(false)
        }
    }

    const handleClose = () => {
        setFile(null)
        setName('')
        setError('')
        onClose()
    }

    const fileIcon = file
        ? file.name.endsWith('.pdf')
            ? '📄'
            : file.name.match(/\.(jpg|jpeg|png|webp)$/i)
              ? '🖼️'
              : file.name.match(/\.(doc|docx)$/i)
                ? '📝'
                : file.name.match(/\.(xls|xlsx)$/i)
                  ? '📊'
                  : '📎'
        : '📁'

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="Upload Document" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Drop zone */}
                <div
                    className="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-gray-400 transition cursor-pointer"
                    onClick={() => window.document.getElementById('doc-file-input')?.click()}
                >
                    {file ? (
                        <div>
                            <span className="text-3xl">{fileIcon}</span>
                            <p className="text-sm font-semibold text-black mt-2">{file.name}</p>
                            <p className="text-xs text-gray-400">{(file.size / 1024 / 1024).toFixed(2)} MB</p>
                        </div>
                    ) : (
                        <div className="py-2">
                            <span className="text-3xl">📁</span>
                            <p className="text-sm text-gray-500 mt-2">Click to select a file</p>
                            <p className="text-xs text-gray-400">PDF, DOC, XLS, images (max 10MB)</p>
                        </div>
                    )}
                    <input
                        id="doc-file-input"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                        onChange={handleFileChange}
                        className="hidden"
                    />
                </div>

                <Input
                    label="Document Name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g. Insurance Policy 2025"
                    required
                />

                <Select
                    label="Document Type"
                    name="document_type"
                    value={documentType}
                    onChange={(e) => setDocumentType(e.target.value)}
                    options={[
                        { value: 'contract', label: 'Contract' },
                        { value: 'insurance', label: 'Insurance' },
                        { value: 'inspection', label: 'Inspection / Revision' },
                        { value: 'energy_certificate', label: 'Energy Certificate' },
                        { value: 'tax', label: 'Tax Document' },
                        { value: 'protocol', label: 'Handover Protocol' },
                        { value: 'invoice', label: 'Invoice' },
                        { value: 'other', label: 'Other' },
                    ]}
                />

                {error && <p className="text-sm text-red-600">{error}</p>}

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={handleClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isLoading || !file}>
                        {isLoading ? 'Uploading...' : 'Upload'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
