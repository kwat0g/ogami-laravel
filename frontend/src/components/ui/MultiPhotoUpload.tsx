/**
 * MultiPhotoUpload - Reusable multi-photo upload component (max 3 photos).
 *
 * Features:
 * - Grid preview of uploaded photos
 * - Click to add, click X to remove
 * - Lightbox preview on click
 * - Shows count indicator (e.g. "2/3 photos")
 */
import { useState, useRef } from 'react'
import { Camera, X, Plus, ZoomIn } from 'lucide-react'

interface MultiPhotoUploadProps {
  /** Array of base64-encoded photo strings */
  photos: string[]
  /** Callback when photos change */
  onChange: (photos: string[]) => void
  /** Maximum number of photos allowed (default: 3) */
  maxPhotos?: number
  /** Label text */
  label?: string
  /** Whether the upload is disabled */
  disabled?: boolean
}

export default function MultiPhotoUpload({
  photos,
  onChange,
  maxPhotos = 3,
  label = 'Photos',
  disabled = false,
}: MultiPhotoUploadProps) {
  const [previewIndex, setPreviewIndex] = useState<number | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const canAddMore = photos.length < maxPhotos

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files || [])
    if (files.length === 0) return

    const remaining = maxPhotos - photos.length
    const filesToProcess = files.slice(0, remaining)

    filesToProcess.forEach((file) => {
      const reader = new FileReader()
      reader.onload = () => {
        onChange([...photos, reader.result as string].slice(0, maxPhotos))
      }
      reader.readAsDataURL(file)
    })

    // Reset input so same file can be selected again
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  function removePhoto(index: number) {
    onChange(photos.filter((_, i) => i !== index))
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <label className="block text-sm font-medium text-neutral-700">
          <Camera className="h-3.5 w-3.5 inline mr-1" />
          {label}
        </label>
        <span className="text-xs text-neutral-400">
          {photos.length}/{maxPhotos} photos
        </span>
      </div>

      {/* Photo grid */}
      <div className="grid grid-cols-3 gap-2">
        {photos.map((photo, index) => (
          <div key={index} className="relative group aspect-square rounded-lg overflow-hidden border border-neutral-200">
            <img
              src={photo}
              alt={`Photo ${index + 1}`}
              className="w-full h-full object-cover cursor-pointer"
              onClick={() => setPreviewIndex(index)}
            />
            {/* Zoom overlay */}
            <div
              className="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer"
              onClick={() => setPreviewIndex(index)}
            >
              <ZoomIn className="h-5 w-5 text-white" />
            </div>
            {/* Remove button */}
            {!disabled && (
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); removePhoto(index) }}
                className="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
              >
                <X className="h-3 w-3" />
              </button>
            )}
          </div>
        ))}

        {/* Add button */}
        {canAddMore && !disabled && (
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            className="aspect-square rounded-lg border-2 border-dashed border-neutral-300 hover:border-neutral-400 flex flex-col items-center justify-center gap-1 text-neutral-400 hover:text-neutral-500 transition-colors cursor-pointer"
          >
            <Plus className="h-5 w-5" />
            <span className="text-[10px] font-medium">Add Photo</span>
          </button>
        )}
      </div>

      {/* Empty state - when no photos yet */}
      {photos.length === 0 && !disabled && (
        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          className="w-full mt-1 py-3 border-2 border-dashed border-neutral-300 hover:border-neutral-400 rounded-lg flex items-center justify-center gap-2 text-neutral-500 hover:text-neutral-600 transition-colors text-sm"
        >
          <Camera className="h-4 w-4" />
          <span>Click to attach photos (max {maxPhotos})</span>
        </button>
      )}

      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        multiple
        onChange={handleFileSelect}
        className="hidden"
      />

      {/* Lightbox */}
      {previewIndex !== null && photos[previewIndex] && (
        <div
          className="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4"
          onClick={() => setPreviewIndex(null)}
        >
          <div className="relative max-w-3xl max-h-[85vh]" onClick={(e) => e.stopPropagation()}>
            <img
              src={photos[previewIndex]}
              alt={`Photo ${previewIndex + 1}`}
              className="max-w-full max-h-[85vh] object-contain rounded-lg"
            />
            <button
              onClick={() => setPreviewIndex(null)}
              className="absolute -top-3 -right-3 w-8 h-8 bg-white text-neutral-700 rounded-full shadow-lg flex items-center justify-center hover:bg-neutral-100"
            >
              <X className="h-4 w-4" />
            </button>
            {/* Navigation arrows */}
            {photos.length > 1 && (
              <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2">
                {photos.map((_, i) => (
                  <button
                    key={i}
                    onClick={() => setPreviewIndex(i)}
                    className={`w-2 h-2 rounded-full transition-colors ${
                      i === previewIndex ? 'bg-white' : 'bg-white/40 hover:bg-white/70'
                    }`}
                  />
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
