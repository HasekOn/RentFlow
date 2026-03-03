import {useEffect, useState} from 'react'
import {propertiesApi} from '../../api/properties'
import type {Document} from '../../api/documents'
import {documentsApi} from '../../api/documents'
import type {Property} from '../../types'
import {formatDate} from '../../utils/format'
import {useAuth} from '../../contexts/AuthContext'
import Button from '../../components/ui/Button'
import Spinner from '../../components/ui/Spinner'
import EmptyState from '../../components/ui/EmptyState'
import UploadDocumentModal from './UploadDocumentModal'

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

export default function DocumentsPage() {
    const {isLandlord} = useAuth()
    const [properties, setProperties] = useState<Property[]>([])
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | null>(null)
    const [documents, setDocuments] = useState<Document[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [isLoadingDocs, setIsLoadingDocs] = useState(false)
    const [showUploadModal, setShowUploadModal] = useState(false)

    // Load properties
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

    // Load documents when property changes
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

    const handleDownload = async (doc: Document) => {
        try {
            const res = await documentsApi.download(doc.id)
            const blob = new Blob([res.data])
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = doc.name
            link.click()
            window.URL.revokeObjectURL(url)
        } catch (error) {
            console.error('Failed to download:', error)
        }
    }

    const handleDelete = async (docId: number) => {
        if (!confirm('Are you sure you want to delete this document?')) return
        try {
            await documentsApi.delete(docId)
            setDocuments((prev) => prev.filter((d) => d.id !== docId))
        } catch (error) {
            console.error('Failed to delete:', error)
        }
    }

    const reloadDocs = () => {
        if (selectedPropertyId) {
            documentsApi.getByProperty(selectedPropertyId).then((res) => {
                setDocuments(Array.isArray(res.data) ? res.data : [])
            }).catch(console.error)
        }
    }

    if (isLoading) return <Spinner/>

    const selectedProperty = properties.find((p) => p.id === selectedPropertyId)

    return (
        <div>
            {/* Header */}
            <div className="flex items-center justify-between">
                <h1 className="text-4xl font-bold text-black">Documents</h1>
                {isLandlord && selectedPropertyId && (
                    <Button onClick={() => setShowUploadModal(true)}>
                        + Upload File
                    </Button>
                )}
            </div>

            {/* Property selector */}
            <div className="mt-6 flex items-center gap-3 flex-wrap">
                {properties.map((property) => (
                    <button
                        key={property.id}
                        onClick={() => setSelectedPropertyId(property.id)}
                        className={`px-4 py-2 rounded-full text-sm font-semibold transition ${
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
                <div className="p-4 border-b border-gray-100 flex items-center gap-2 text-sm text-gray-500">
                    <span className="font-semibold text-black">Files</span>
                    <span>Total {documents.length}</span>
                    {selectedProperty && (
                        <span className="text-gray-400">· {selectedProperty.address}</span>
                    )}
                </div>

                {isLoadingDocs ? (
                    <Spinner/>
                ) : documents.length === 0 ? (
                    <EmptyState
                        title="No documents"
                        description="No files have been uploaded for this property yet."
                        action={isLandlord ? (
                            <Button onClick={() => setShowUploadModal(true)}>+ Upload File</Button>
                        ) : undefined}
                    />
                ) : (
                    <div className="divide-y divide-gray-50">
                        {documents.map((doc) => (
                            <div key={doc.id}
                                 className="flex items-center gap-4 px-6 py-4 hover:bg-gray-50/50 transition">
                                <span className="text-2xl">{fileIcon(doc.name)}</span>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-black truncate">{doc.name}</p>
                                    <div className="flex items-center gap-3 mt-0.5">
                                        {(doc as any).document_type && (
                                            <span
                                                className="text-xs text-gray-500 capitalize">{(doc as any).document_type}</span>
                                        )}
                                        {doc.description && (
                                            <span className="text-xs text-gray-400">{doc.description}</span>
                                        )}
                                        <span className="text-xs text-gray-400">
                      Uploaded {formatDate(doc.created_at)}
                    </span>
                                        {doc.valid_until && (
                                            <span className="text-xs text-gray-400">
                        Valid until {formatDate(doc.valid_until)}
                      </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="success"
                                        size="sm"
                                        onClick={() => handleDownload(doc)}
                                    >
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
        </div>
    )
}
