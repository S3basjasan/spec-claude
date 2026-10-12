export function Skeleton({ className = '' }: { className?: string }) {
  return (
    <div
      aria-hidden="true"
      className={`rounded-lg bg-border ${className}`}
      style={{ animation: 'hrms-pulse 1.4s ease-in-out infinite' }}
    />
  )
}
