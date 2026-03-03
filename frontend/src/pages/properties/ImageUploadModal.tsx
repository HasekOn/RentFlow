import * as React from 'react'
import {useState} from 'react'
import {propertiesApi} from '../../api/properties'
import Modal from '../../components/ui/Modal'
import Select from '../../components/ui/Select'
import Button from '../../components/ui/Button'

interface Props {
    isOpen: boolean
    onClose: () => void
    propertyId: number
    onSuccess: () => void
}

export default function ImageUploadModal({isOpen, onClose, propertyId, onSuccess}: Props) {
    const [file, setFile] = useState<File | null>(null)
    const [preview, setPreview] = useState<string | null>(null)
    const [type, setType] = useState('marketing')
    const [description, setDescription] = useState('')
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState('')

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0]
        if (selected) {
            setFile(selected)
            setPreview(URL.createObjectURL(selected))
        }
    }

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        if (!file) return

        setError('')
        setIsLoading(true)

        try {
            const formData = new FormData()
            formData.append('image', file)
            formData.append('type', type)
            if (description) formData.append('description', description)

            await propertiesApi.uploadImage(propertyId, formData)
            setFile(null)
            setPreview(null)
            setDescription('')
            setType('marketing')
            onSuccess()
        } catch (err: any) {
            setError(err.response?.data?.message || 'Upload failed')
        } finally {
            setIsLoading(false)
        }
    }

    const handleClose = () => {
        setFile(null)
        setPreview(null)
        setError('')
        onClose()
    }

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="Upload Image" size="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Drop zone */}
                <div
                    className="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-gray-400 transition cursor-pointer"
                    onClick={() => document.getElementById('image-input')?.click()}
                >
                    {preview ? (
                        <img src={preview} alt="Preview" className="max-h-48 mx-auto rounded-lg object-contain"/>
                    ) : (
                        <div className="py-4">
                            <span className="text-3xl">📷</span>
                            <p className="text-sm text-gray-500 mt-2">Click to select an image</p>
                            <p className="text-xs text-gray-400">JPG, PNG, WebP (max 5MB)</p>
                        </div>
                    )}
                    <input
                        id="image-input"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={handleFileChange}
                        className="hidden"
                    />
                </div>

                <Select
                    label="Type"
                    name="type"
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                    options={[
                        {value: 'marketing', label: 'Marketing photo'},
                        {value: 'defect', label: 'Defect / Issue'},
                        {value: 'document', label: 'Document scan'},
                    ]}
                />

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                    <input
                        type="text"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-black/10 focus:border-gray-400 transition"
                        placeholder="Optional description..."
                    />
                </div>

                {error && <p className="text-sm text-red-600">{error}</p>}

                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" type="button" onClick={handleClose}>Cancel</Button>
                    <Button type="submit" disabled={isLoading || !file}>
                        {isLoading ? 'Uploading...' : 'Upload'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
