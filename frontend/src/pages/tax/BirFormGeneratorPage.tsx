import { useState } from 'react'
import { FileText } from 'lucide-react'
import { useVatReturn, useWithholdingTax } from '@/hooks/useBirForms'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

export default function BirFormGeneratorPage() {
  const currentYear = new Date().getFullYear()
  const [month, setMonth] = useState(new Date().getMonth() + 1)
  const [year, setYear] = useState(currentYear)
  const [formType, setFormType] = useState<'vat' | 'withholding'>('vat')

  const { data: vatData, isLoading: vatLoading } = useVatReturn(month, year)
  const { data: whData, isLoading: whLoading } = useWithholdingTax(month, year)

  return (
    <div className="space-y-6">
      <PageHeader title="BIR Form Generator" icon={<FileText className="w-5 h-5 text-neutral-600" />} />
      <Card className="p-4">
        <div className="flex flex-wrap gap-3">
          <select className="input-sm" value={formType} onChange={e => setFormType(e.target.value as 'vat' | 'withholding')}>
            <option value="vat">Form 2550M - VAT Return</option>
            <option value="withholding">Form 1601-C - Withholding Tax</option>
          </select>
          <select className="input-sm" value={month} onChange={e => setMonth(Number(e.target.value))}>
            {MONTHS.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
          </select>
          <select className="input-sm" value={year} onChange={e => setYear(Number(e.target.value))}>
            {[currentYear - 1, currentYear, currentYear + 1].map(y => <option key={y} value={y}>{y}</option>)}
          </select>
        </div>
      </Card>

      {formType === 'vat' && (
        vatLoading ? <SkeletonLoader rows={6} /> : vatData ? (
          <Card className="p-6 space-y-4">
            <h3 className="font-semibold text-lg">Form 2550M - Monthly VAT Return</h3>
            <p className="text-sm text-neutral-500">Period: {vatData.period}</p>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
              <div className="p-3 bg-blue-50 rounded"><p className="text-neutral-500">Taxable Sales</p><p className="font-mono font-bold">{fmt(vatData.taxable_sales_centavos)}</p></div>
              <div className="p-3 bg-green-50 rounded"><p className="text-neutral-500">Zero-Rated Sales</p><p className="font-mono font-bold">{fmt(vatData.zero_rated_sales_centavos)}</p></div>
              <div className="p-3 bg-neutral-50 rounded"><p className="text-neutral-500">Exempt Sales</p><p className="font-mono font-bold">{fmt(vatData.exempt_sales_centavos)}</p></div>
              <div className="p-3 bg-red-50 rounded"><p className="text-neutral-500">Output VAT</p><p className="font-mono font-bold">{fmt(vatData.output_vat_centavos)}</p></div>
              <div className="p-3 bg-green-50 rounded"><p className="text-neutral-500">Input VAT</p><p className="font-mono font-bold">{fmt(vatData.input_vat_centavos)}</p></div>
              <div className="p-3 bg-yellow-50 rounded border-2 border-yellow-200"><p className="text-neutral-500 font-semibold">VAT Payable</p><p className="font-mono font-bold text-lg">{fmt(vatData.vat_payable_centavos)}</p></div>
            </div>
          </Card>
        ) : null
      )}

      {formType === 'withholding' && (
        whLoading ? <SkeletonLoader rows={4} /> : whData ? (
          <Card className="p-6 space-y-4">
            <h3 className="font-semibold text-lg">Form 1601-C - Monthly Withholding Tax on Compensation</h3>
            <p className="text-sm text-neutral-500">Period: {whData.period}</p>
            <div className="grid grid-cols-3 gap-4 text-sm">
              <div className="p-3 bg-blue-50 rounded"><p className="text-neutral-500">Total Compensation</p><p className="font-mono font-bold">{fmt(whData.total_compensation_centavos)}</p></div>
              <div className="p-3 bg-red-50 rounded"><p className="text-neutral-500">Tax Withheld</p><p className="font-mono font-bold">{fmt(whData.tax_withheld_centavos)}</p></div>
              <div className="p-3 bg-neutral-50 rounded"><p className="text-neutral-500">Employees</p><p className="font-bold text-lg">{whData.employee_count}</p></div>
            </div>
          </Card>
        ) : null
      )}
    </div>
  )
}
