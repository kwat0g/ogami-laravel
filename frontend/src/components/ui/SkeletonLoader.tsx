// SkeletonLoader component for loading states
export interface SkeletonLoaderProps {
  rows?: number
  className?: string
}

export function SkeletonLoader({ rows = 5, className }: SkeletonLoaderProps) {
  return (
    <div className={className}>
      {Array.from({ length: rows }).map((_, i) => (
        <div
          key={i}
          className="h-4 bg-neutral-100 rounded animate-pulse mb-3"
          style={{ width: `${85 + Math.random() * 15}%` }}
        />
      ))}
    </div>
  )
}

export default SkeletonLoader
