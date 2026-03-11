import { useEffect, useState } from 'react'
import { propertiesApi } from '../../api/properties'
import { documentsApi } from '../../api/documents'
import type { Document as PropertyDocument, Property } from '../../types'
import { formatDate } from '../../utils/format'
import { useAuth } from '../../contexts/AuthContext'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import EmptyState from '../../components/ui/EmptyState'
import UploadDocumentModal from './UploadDocumentModal'
import { useConfirm } from '../../hooks/useConfirm'

const fileIcon = (name: string, filePath?: string) => {
    const combined = name + (filePath || '')
    if (combined.match(/\.pdf/i)) return '📄'
    if (combined.match(/\.(doc|docx)/i)) return '📝'
    if (combined.match(/\.(xls|xlsx)/i)) return '📊'
    if (combined.match(/\.(jpg|jpeg|png|webp)/i)) return '🖼️'
    return '📎'
}

export default function DocumentsPage() {
    const { isLandlord } = useAuth()
    const [properties, setProperties] = useState<Property[]>([])
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(null)
    const [documents, setDocuments] = useState<PropertyDocument[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [isLoadingDocs, setIsLoadingDocs] = useState(false)
    const [showUploadModal, setShowUploadModal] = useState(false)
    const { confirm: showConfirm, dialog } = useConfirm()

    useEffect(() => {
        const load = async () => {
            try {
                const res = await propertiesApi.getAll()
                const props = res.data.data
                setProperties(props)
                if (props.length > 0) {
                    setSelectedPropertyId(props[0].id)
                }
            } catch (error) {
                console.error('Failed to load properties:', error)
            } finally {
                setIsLoading(false)
            }
        }
        void load()
    }, [])

    useEffect(() => {
        if (!selectedPropertyId) return
        const loadDocs = async () => {
            setIsLoadingDocs(true)
            try {
                const res = await documentsApi.getByProperty(selectedPropertyId)
                setDocuments(Array.isArray(res.data) ? res.data : [])
            } catch (error) {
                console.error('Failed to load documents:', error)
                setDocuments([])
            } finally {
                setIsLoadingDocs(false)
            }
        }
        void loadDocs()
    }, [selectedPropertyId])

    const handleDownload = async (doc: PropertyDocument) => {
        try {
            const res = await documentsApi.download(doc.id)
            const blob = new Blob([res.data])
            const url = window.URL.createObjectURL(blob)
            const link = window.document.createElement('a')
            link.href = url
            const ext = doc.file_path.split('.').pop()
            link.download = doc.name.includes('.') ? doc.name : `${doc.name}.${ext}`
            link.click()
            window.URL.revokeObjectURL(url)
        } catch (error) {
            console.error('Failed to download:', error)
        }
    }

    const handleDelete = async (docId: number) => {
        const ok = await showConfirm({
            title: 'Delete Document',
            message: 'Are you sure you want to delete this document?',
            confirmLabel: 'Delete',
            variant: 'danger',
        })
        if (!ok) return

        try {
            await documentsApi.delete(docId)
            setDocuments((prev) => prev.filter((d) => d.id !== docId))
        } catch (error) {
            console.error('Failed to delete:', error)
        }
    }

    const reloadDocs = () => {
        if (selectedPropertyId) {
            documentsApi
                .getByProperty(selectedPropertyId)
                .then((res) => {
                    setDocuments(Array.isArray(res.data) ? res.data : [])
                })
                .catch(console.error)
        }
    }

    if (isLoading) return <Spinner />

    const selectedProperty = properties.find((p) => p.id === selectedPropertyId)

    return (
        <div>
            {/* Header */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <h1 className="text-2xl sm:text-4xl font-bold text-black">Documents</h1>
                {isLandlord && selectedPropertyId && <Button onClick={() => setShowUploadModal(true)}>+ Upload</Button>}
            </div>

            {/* Property selector */}
            <div className="mt-6 flex items-center gap-2 flex-wrap">
                {properties.map((property) => (
                    <button
                        key={property.id}
                        onClick={() => setSelectedPropertyId(property.id)}
                        className={`px-3 sm:px-4 py-2 rounded-full text-xs sm:text-sm font-semibold transition cursor-pointer ${
                            selectedPropertyId === property.id
                                ? 'bg-black text-white'
                                : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        {property.address.split(',')[0]}
                    </button>
                ))}
            </div>

            {/* Documents list */}
            <div className="bg-white rounded-2xl shadow-sm mt-6">
                <div className="p-4 border-b border-gray-100 flex items-center gap-2 text-sm text-gray-500 flex-wrap">
                    <span className="font-semibold text-black">Files</span>
                    <span>Total {documents.length}</span>
                    {selectedProperty && (
                        <span className="text-gray-400 hidden sm:inline">· {selectedProperty.address}</span>
                    )}
                </div>

                {isLoadingDocs ? (
                    <Spinner />
                ) : documents.length === 0 ? (
                    <EmptyState
                        title="No documents"
                        description="No files have been uploaded for this property yet."
                        action={
                            isLandlord ? (
                                <Button onClick={() => setShowUploadModal(true)}>+ Upload File</Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="divide-y divide-gray-50">
                        {documents.map((doc) => (
                            <div key={doc.id} className="p-4 sm:px-6 sm:py-4 hover:bg-gray-50/50 transition">
                                <div className="flex items-start gap-3">
                                    <span className="text-2xl mt-0.5">{fileIcon(doc.name)}</span>
                                    <div className="flex-1 min-w-0">
                                        <p
                                            className="text-sm font-semibold text-black truncate cursor-pointer hover:text-gray-700 transition"
                                            onClick={() => handleDownload(doc)}
                                        >
                                            {doc.name}
                                        </p>
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1">
                                            <span className="text-xs text-gray-500 capitalize">
                                                {doc.document_type}
                                            </span>
                                            <span className="text-xs text-gray-400">{formatDate(doc.created_at)}</span>
                                            {doc.uploaded_by && (
                                                <span className="text-xs text-gray-400 hidden sm:inline">
                                                    {doc.uploaded_by.name}
                                                </span>
                                            )}
                                        </div>
                                        {/* Mobile buttons */}
                                        <div className="flex items-center gap-2 mt-2 sm:hidden">
                                            <Button variant="success" size="sm" onClick={() => handleDownload(doc)}>
                                                Download
                                            </Button>
                                            {isLandlord && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => handleDelete(doc.id)}
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    {/* Desktop buttons */}
                                    <div className="hidden sm:flex items-center gap-2 shrink-0">
                                        <Button variant="success" size="sm" onClick={() => handleDownload(doc)}>
                                            Download
                                        </Button>
                                        {isLandlord && (
                                            <Button variant="secondary" size="sm" onClick={() => handleDelete(doc.id)}>
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {selectedPropertyId && (
                <UploadDocumentModal
                    isOpen={showUploadModal}
                    onClose={() => setShowUploadModal(false)}
                    propertyId={selectedPropertyId}
                    onSuccess={() => {
                        setShowUploadModal(false)
                        reloadDocs()
                    }}
                />
            )}
            {dialog}
        </div>
    )
}
