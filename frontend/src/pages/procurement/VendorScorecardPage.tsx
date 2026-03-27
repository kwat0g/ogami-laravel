import { useState } from 'react'
import { useVendorScores, type VendorScore } from '@/hooks/useAnalytics'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Award, TrendingUp, Truck, DollarSign } from 'lucide-react'

const currentYear = new Date().getFullYear()

function GradeBadge({ grade }: { grade: string }): JSX.Element {
  const colors: Record<string, string> = {
    A: 'bg-green-100 text-green-700 border-green-300',
    B: 'bg-blue-100 text-blue-700 border-blue-300',
    C: 'bg-amber-100 text-amber-700 border-amber-300',
    D: 'bg-orange-100 text-orange-700 border-orange-300',
    F: 'bg-red-100 text-red-700 border-red-300',
  }

  return (
    <span className={`inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold border-2 ${colors[grade] ?? 'bg-neutral-100 text-neutral-600 border-neutral-300'}`}>
      {grade}
    </span>
  )
}

function ScoreBar({ score, label, color }: { score: number; label: string; color: string }): JSX.Element {
  return (
    <div>
      <div className="flex justify-between text-xs mb-0.5">
        <span className="text-neutral-500">{label}</span>
        <span className="font-semibold text-neutral-700">{score.toFixed(1)}</span>
      </div>
      <div className="h-1.5 bg-neutral-100 rounded-full overflow-hidden">
        <div className={`h-full rounded-full ${color}`} style={{ width: `${Math.min(score, 100)}%` }} />
      </div>
    </div>
  )
}

function VendorDetailCard({ vendor }: { vendor: VendorScore }): JSX.Element {
  return (
    <Card className="p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-3">
          <GradeBadge grade={vendor.grade} />
          <div>
            <h4 className="font-semibold text-neutral-800">{vendor.vendor_name}</h4>
            <p className="text-xs text-neutral-400">
              {vendor.total_pos} POs · {vendor.total_grs} GRs · {vendor.total_inspections} inspections
            </p>
          </div>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-neutral-900">{vendor.composite_score.toFixed(1)}</p>
          <p className="text-[10px] uppercase tracking-wide text-neutral-400">Composite</p>
        </div>
      </div>
      <div className="space-y-2">
        <ScoreBar score={vendor.quality_score} label="Quality" color="bg-green-500" />
        <ScoreBar score={vendor.delivery_score} label="Delivery" color="bg-blue-500" />
        <ScoreBar score={vendor.price_score} label="Price" color="bg-amber-500" />
      </div>
    </Card>
  )
}

export default function VendorScorecardPage(): JSX.Element {
  const [year, setYear] = useState<number | undefined>(undefined)
  const { data, isLoading, isError } = useVendorScores(year)

  return (
    <div className="space-y-6">
      <PageHeader title="Vendor Scorecard" subtitle="Composite vendor performance ratings" />

      <div className="flex items-end gap-4">
        <div>
          <label className="text-xs text-neutral-500 block mb-1">Year Filter</label>
          <select
            value={year ?? ''}
            onChange={(e) => setYear(e.target.value ? Number(e.target.value) : undefined)}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm"
          >
            <option value="">All Time</option>
            {[currentYear - 1, currentYear].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
        </div>
      </div>

      {isLoading && <SkeletonLoader lines={8} />}
      {isError && <p className="text-red-600 p-4">Failed to load vendor scores.</p>}

      {/* Summary Stats */}
      {data && data.length > 0 && (
        <>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <Card className="p-3 flex items-center gap-3">
              <Award className="h-5 w-5 text-green-600" />
              <div>
                <p className="text-xs text-neutral-500">Grade A Vendors</p>
                <p className="text-lg font-bold">{data.filter((v) => v.grade === 'A').length}</p>
              </div>
            </Card>
            <Card className="p-3 flex items-center gap-3">
              <TrendingUp className="h-5 w-5 text-blue-600" />
              <div>
                <p className="text-xs text-neutral-500">Avg Score</p>
                <p className="text-lg font-bold">{(data.reduce((s, v) => s + v.composite_score, 0) / data.length).toFixed(1)}</p>
              </div>
            </Card>
            <Card className="p-3 flex items-center gap-3">
              <Truck className="h-5 w-5 text-amber-600" />
              <div>
                <p className="text-xs text-neutral-500">Avg Delivery Score</p>
                <p className="text-lg font-bold">{(data.reduce((s, v) => s + v.delivery_score, 0) / data.length).toFixed(1)}</p>
              </div>
            </Card>
            <Card className="p-3 flex items-center gap-3">
              <DollarSign className="h-5 w-5 text-red-600" />
              <div>
                <p className="text-xs text-neutral-500">Below C Grade</p>
                <p className="text-lg font-bold">{data.filter((v) => ['D', 'F'].includes(v.grade)).length}</p>
              </div>
            </Card>
          </div>

          {/* Vendor Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {data.map((vendor) => (
              <VendorDetailCard key={vendor.vendor_id} vendor={vendor} />
            ))}
          </div>
        </>
      )}

      {data && data.length === 0 && (
        <p className="text-center text-neutral-400 py-12">No vendor data available for the selected period.</p>
      )}
    </div>
  )
}
