import { useNavigate } from 'react-router-dom'
import { ShieldAlert } from 'lucide-react'

export default function Forbidden() {
  const navigate = useNavigate()

  return (
    <div className="min-h-screen flex items-center justify-center bg-neutral-50">
      <div className="text-center max-w-md">
        <ShieldAlert className="h-16 w-16 text-neutral-400 mx-auto mb-4" />
        <h1 className="text-lg font-semibold text-neutral-900 mb-2">Access Denied</h1>
        <p className="text-neutral-500 mb-6">
          You don't have permission to view this page. Contact your administrator if you believe
          this is an error.
        </p>
        <button
          onClick={() => navigate(-1)}
          className="bg-neutral-900 text-white px-4 py-2 rounded text-sm font-medium hover:bg-neutral-800 transition-colors"
        >
          Go back
        </button>
      </div>
    </div>
  )
}
