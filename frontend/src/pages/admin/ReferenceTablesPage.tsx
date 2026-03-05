import { useState, createContext, useContext } from 'react'
import { 
  Calculator, 
  Building2, 
  Heart, 
  Home, 
  Banknote, 
  Calendar, 
  GraduationCap, 
  Wallet,
  BookOpen,
  ChevronRight,
  Info,
  Lock,
  Unlock,
  Edit3,
  AlertTriangle,
  CheckCircle2,
  AlertCircle,
  History,
  Database
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { toast } from 'sonner'

// Import all reference table components
import TaxBracketsTable from './reference/TaxBracketsTable'
import SssContributionsTable from './reference/SssContributionsTable'
import PhilhealthContributionsTable from './reference/PhilhealthContributionsTable'
import PagibigContributionsTable from './reference/PagibigContributionsTable'
import MinimumWageRatesTable from './reference/MinimumWageRatesTable'
import HolidayCalendarTable from './reference/HolidayCalendarTable'
import SalaryGradesTable from './reference/SalaryGradesTable'
import LoanTypesTable from './reference/LoanTypesTable'
import ChartOfAccountsTable from './reference/ChartOfAccountsTable'

// Context for sharing edit mode across table components
interface EditModeContextType {
  isEditMode: boolean
}

// eslint-disable-next-line react-refresh/only-export-components
export const EditModeContext = createContext<EditModeContextType>({ isEditMode: false })

// eslint-disable-next-line react-refresh/only-export-components
export const useEditMode = () => useContext(EditModeContext)

interface TableTab {
  id: string
  label: string
  icon: React.ElementType
  description: string
  component: React.ComponentType
  editable: boolean
  warning?: string
}

const TABLE_TABS: TableTab[] = [
  {
    id: 'tax-brackets',
    label: 'TRAIN Tax Brackets',
    icon: Calculator,
    description: 'Annual income tax brackets under TRAIN Law (RA 10963) with effective date versioning',
    component: TaxBracketsTable,
    editable: true,
    warning: 'Changes affect tax computations for payroll periods on or after the effective date.',
  },
  {
    id: 'sss',
    label: 'SSS Contribution Table',
    icon: Building2,
    description: 'Social Security System monthly salary credit brackets per SSS MC-003-2021',
    component: SssContributionsTable,
    editable: true,
    warning: 'New SSS tables require new rows with future effective dates. Historical data must be preserved for past payrolls.',
  },
  {
    id: 'philhealth',
    label: 'PhilHealth Premium',
    icon: Heart,
    description: 'PhilHealth premium contribution rates by coverage year (Circular 2022-0005)',
    component: PhilhealthContributionsTable,
    editable: true,
    warning: 'Premium rates are versioned by year. Ensure the correct year is set as effective_date.',
  },
  {
    id: 'pagibig',
    label: 'Pag-IBIG Contribution',
    icon: Home,
    description: 'Pag-IBIG (HDMF) contribution rates and salary thresholds per MRD 2012-001',
    component: PagibigContributionsTable,
    editable: true,
  },
  {
    id: 'minimum-wage',
    label: 'Minimum Wage Rates',
    icon: Banknote,
    description: 'Regional daily minimum wage rates by region code and effective date (DOLE Wage Orders)',
    component: MinimumWageRatesTable,
    editable: true,
    warning: 'Wage Order updates must use the actual effectivity date published by DOLE.',
  },
  {
    id: 'holidays',
    label: 'Holiday Calendar',
    icon: Calendar,
    description: 'Philippine regular and special non-working holidays 2024–2026 (Proclamations)',
    component: HolidayCalendarTable,
    editable: true,
  },
  {
    id: 'salary-grades',
    label: 'Salary Grade Table',
    icon: GraduationCap,
    description: 'Internal salary grade levels and pay ranges for position classification',
    component: SalaryGradesTable,
    editable: true,
  },
  {
    id: 'loan-types',
    label: 'Loan Types',
    icon: Wallet,
    description: 'Available employee loan products, interest rates, and terms',
    component: LoanTypesTable,
    editable: true,
  },
  {
    id: 'chart-of-accounts',
    label: 'Chart of Accounts',
    icon: BookOpen,
    description: 'General ledger account hierarchy for financial reporting (PH GAAP/BIR compliance)',
    component: ChartOfAccountsTable,
    editable: true,
    warning: 'Account codes referenced in System Settings → Accounting must exist here. Changes affect financial statements.',
  },
]

interface ConfirmDialogProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  message: string
  confirmText: string
}

function ConfirmDialog({ isOpen, onClose, onConfirm, title, message, confirmText }: ConfirmDialogProps) {
  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div className="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
        <div className="px-6 py-4 border-b border-amber-200 bg-amber-50 flex items-center gap-3">
          <AlertTriangle className="h-6 w-6 text-amber-600" />
          <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
        </div>
        <div className="p-6">
          <p className="text-gray-600 mb-6 whitespace-pre-line">{message}</p>
          <div className="flex justify-end gap-3">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg border border-gray-300 transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              className="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors"
            >
              {confirmText}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default function ReferenceTablesPage(): JSX.Element {
  const [activeTab, setActiveTab] = useState<string>('tax-brackets')
  const [isEditMode, setIsEditMode] = useState(false)
  const [showDisableConfirm, setShowDisableConfirm] = useState(false)

  const activeTable = TABLE_TABS.find(t => t.id === activeTab)
  const ActiveComponent = activeTable?.component || TaxBracketsTable

  const handleEnableEdit = () => {
    setIsEditMode(true)
    toast.info('Edit mode enabled. You can now add, edit, or delete records.')
  }

  const handleDisableClick = () => {
    setShowDisableConfirm(true)
  }

  const handleDisableConfirm = () => {
    setIsEditMode(false)
    setShowDisableConfirm(false)
    toast.info('Edit mode disabled. View-only mode active.')
  }

  return (
    <EditModeContext.Provider value={{ isEditMode }}>
      <div className="space-y-6">
        {/* Header with Info */}
        <div className="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-xl p-6">
          <div className="flex items-start gap-4">
            <div className="p-3 bg-indigo-100 rounded-xl">
              <Database className="h-8 w-8 text-indigo-600" />
            </div>
            <div className="flex-1">
              <h1 className="text-2xl font-bold text-gray-900 mb-2">Reference Tables</h1>
              <p className="text-gray-600 mb-4">
                This page manages <strong>versioned reference data with effective dates</strong>. 
                These are multi-row datasets (tax brackets, contribution tables, wage rates) that 
                change over time based on government regulations.
              </p>
              <div className="flex flex-wrap gap-3">
                <InfoBadge 
                  icon={History} 
                  text="Versioned by effective_date — historical records preserved" 
                  variant="info"
                />
                <InfoBadge 
                  icon={CheckCircle2} 
                  text="Used by payroll engine to compute actual amounts" 
                  variant="success"
                />
                <InfoBadge 
                  icon={Lock} 
                  text="Audit trail tracks all changes per record" 
                  variant="warning"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Distinction Notice */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <Info className="h-5 w-5 text-blue-600 mt-0.5" />
            <div className="text-sm text-blue-800">
              <p className="font-semibold mb-1">Looking for application settings or configuration?</p>
              <p>
                System behavior settings (timeouts, account codes, flags) are managed in 
                <a href="/admin/system-settings" className="font-semibold underline ml-1 hover:text-blue-900">
                  System Settings
                </a>.
                This page is for government-mandated rate tables only.
              </p>
            </div>
          </div>
        </div>

        <div className="flex gap-6">
          {/* Sidebar Navigation */}
          <div className="w-80 shrink-0 space-y-4">
            <nav className="space-y-1">
              {TABLE_TABS.map((tab) => {
                const Icon = tab.icon
                const isActive = activeTab === tab.id
                
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={cn(
                      'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-all',
                      'hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                      isActive 
                        ? 'bg-indigo-600 text-white shadow-sm' 
                        : 'bg-white text-gray-700 hover:text-gray-900 border border-gray-200'
                    )}
                  >
                    <Icon className={cn('h-5 w-5 shrink-0', isActive ? 'text-indigo-100' : 'text-gray-400')} />
                    <div className="flex-1 min-w-0">
                      <p className={cn('font-medium text-sm truncate', isActive ? 'text-white' : 'text-gray-900')}>
                        {tab.label}
                      </p>
                    </div>
                    <ChevronRight className={cn(
                      'h-4 w-4 shrink-0 transition-transform',
                      isActive ? 'rotate-90 text-indigo-200' : 'text-gray-300'
                    )} />
                  </button>
                )
              })}
            </nav>

            {/* Edit Mode Card */}
            <div className={cn(
              "rounded-xl p-4 border-2 transition-all",
              isEditMode 
                ? 'bg-green-50 border-green-300' 
                : 'bg-gray-50 border-gray-200'
            )}>
              <div className="flex items-center gap-2 mb-3">
                {isEditMode ? (
                  <Unlock className="h-5 w-5 text-green-600" />
                ) : (
                  <Lock className="h-5 w-5 text-gray-500" />
                )}
                <span className={cn(
                  "font-semibold",
                  isEditMode ? 'text-green-800' : 'text-gray-700'
                )}>
                  {isEditMode ? 'Editing Enabled' : 'View Mode'}
                </span>
              </div>
              
              {isEditMode ? (
                <div className="space-y-3">
                  <p className="text-sm text-green-700">
                    You can now modify reference data. New records will use the effective date you specify.
                  </p>
                  <div className="flex gap-2">
                    <button
                      onClick={handleDisableClick}
                      className="flex-1 flex items-center justify-center gap-2 px-3 py-2 text-sm text-gray-600 hover:bg-white hover:shadow-sm rounded-lg border border-gray-300 transition-all"
                    >
                      <Lock className="h-4 w-4" />
                      Lock Editing
                    </button>
                  </div>
                </div>
              ) : (
                <>
                  <p className="text-sm text-gray-600 mb-3">
                    Reference tables are locked to prevent accidental modifications. Enable editing to make changes.
                  </p>
                  <button
                    onClick={handleEnableEdit}
                    className="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors"
                  >
                    <Edit3 className="h-4 w-4" />
                    Enable Editing
                  </button>
                </>
              )}
            </div>

            {/* Help Card */}
            <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
              <h4 className="font-semibold text-amber-900 mb-2 flex items-center gap-2">
                <AlertCircle className="h-4 w-4" />
                How Versioning Works
              </h4>
              <ul className="text-sm text-amber-800 space-y-1.5 list-disc list-inside">
                <li>Add new rows with future effective dates</li>
                <li>Payroll uses the latest effective date ≤ pay period</li>
                <li>Never delete historical records — they preserve past payroll accuracy</li>
                <li>Each change is audited with user and timestamp</li>
              </ul>
            </div>
          </div>

          {/* Content Area */}
          <div className="flex-1 min-w-0">
            {activeTable && (
              <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                {/* Table Header */}
                <div className="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-indigo-100 rounded-lg">
                      <activeTable.icon className="h-5 w-5 text-indigo-600" />
                    </div>
                    <div className="flex-1">
                      <h2 className="text-lg font-semibold text-gray-900">{activeTable.label}</h2>
                      <p className="text-sm text-gray-500">{activeTable.description}</p>
                    </div>
                  </div>
                  
                  {activeTable.warning && (
                    <div className="mt-3 flex items-start gap-2 text-sm text-amber-700 bg-amber-50 rounded-lg px-4 py-3">
                      <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                      <span>{activeTable.warning}</span>
                    </div>
                  )}

                  {!isEditMode && (
                    <div className="mt-3 flex items-center gap-2 text-sm text-gray-500 bg-gray-50 rounded-lg px-4 py-3">
                      <Lock className="h-4 w-4" />
                      <span>View mode: Click "Enable Editing" in the sidebar to modify reference data</span>
                    </div>
                  )}
                </div>
                
                {/* Table Component */}
                <div className="p-6">
                  <ActiveComponent />
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Confirmation Dialog */}
        <ConfirmDialog
          isOpen={showDisableConfirm}
          onClose={() => setShowDisableConfirm(false)}
          onConfirm={handleDisableConfirm}
          title="Disable Edit Mode?"
          message="Any unsaved changes in open forms will be lost.&#10;&#10;Are you sure you want to lock editing?"
          confirmText="Yes, Lock Editing"
        />
      </div>
    </EditModeContext.Provider>
  )
}

// ── Helper Components ────────────────────────────────────────────────────────

function InfoBadge({ icon: Icon, text, variant }: { icon: React.ElementType, text: string, variant: 'info' | 'warning' | 'success' }) {
  const variants = {
    info: 'bg-indigo-100 text-indigo-700',
    warning: 'bg-amber-100 text-amber-700',
    success: 'bg-green-100 text-green-700'
  }
  
  return (
    <div className={cn("flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium", variants[variant])}>
      <Icon className="h-3.5 w-3.5" />
      <span>{text}</span>
    </div>
  )
}
