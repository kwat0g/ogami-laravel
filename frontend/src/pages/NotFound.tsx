import { useNavigate } from 'react-router-dom'
import { FileQuestion } from 'lucide-react'

export default function NotFound() {
  const navigate = useNavigate()

  return (
    <div className="min-h-screen flex items-center justify-center bg-neutral-50">
      <div className="text-center max-w-md">
        <FileQuestion className="h-16 w-16 text-neutral-300 mx-auto mb-4" />
        <h1 className="text-lg font-semibold text-neutral-900 mb-2">Page not found</h1>
        <p className="text-neutral-500 mb-6">
          The page you're looking for doesn't exist or has been moved.
        </p>
        <button
          onClick={() => navigate('/')}
          className="bg-neutral-900 text-white px-4 py-2 rounded text-sm font-medium hover:bg-neutral-800 transition-colors"
        >
          Go to dashboard
        </button>
      </div>
    </div>
  )
}
