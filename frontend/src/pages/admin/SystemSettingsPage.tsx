import { useState, useMemo } from 'react'
import { useSystemSettings, useBulkUpdateSettings, type SystemSetting } from '@/hooks/useSettings'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import { 
  AlertTriangle, 
  Info, 
  Lock, 
  Unlock, 
  Save, 
  X, 
  Edit3,
  Shield,
  Clock,
  DollarSign,
  Users,
  FileText,
  BookOpen,
  AlertCircle,
  CheckCircle2,
  Code2
} from 'lucide-react'
import { cn } from '@/lib/utils'

const SETTING_GROUPS = [
  { 
    key: 'payroll', 
    label: 'Payroll Settings', 
    icon: DollarSign,
    description: 'Configure payroll computation parameters, deduction periods, and working day calculations.',
    warning: 'Changes affect the next payroll run. Existing computed payrolls are not retroactively modified.'
  },
  { 
    key: 'tax', 
    label: 'Tax & VAT Settings', 
    icon: FileText,
    description: 'Tax computation rates, VAT configuration, and exemption thresholds.',
    warning: 'Tax rates here are modifiers. Primary tax brackets are managed in Reference Tables.'
  },
  { 
    key: 'attendance', 
    label: 'Attendance Settings', 
    icon: Clock,
    description: 'Grace periods, tardiness rules, and attendance tracking parameters.',
    warning: null
  },
  { 
    key: 'leave', 
    label: 'Leave Settings', 
    icon: Users,
    description: 'Leave approval policies, backdating rules, and SIL conversion rates.',
    warning: null
  },
  { 
    key: 'security', 
    label: 'Security Settings', 
    icon: Shield,
    description: 'Session timeouts, password policies, login attempt limits, and SoD conflict matrix.',
    warning: 'SoD matrix changes take effect immediately. Incorrect configuration may block legitimate workflows.'
  },
  { 
    key: 'accounting', 
    label: 'Accounting Settings', 
    icon: BookOpen,
    description: 'GL account mappings for auto-posting, fiscal period rules, and stale draft handling.',
    warning: 'Account codes must exist in the Chart of Accounts. Invalid codes will cause posting failures.'
  },
  { 
    key: 'ap', 
    label: 'Accounts Payable', 
    icon: FileText,
    description: 'AP alert windows, due date notifications, and default liability accounts.',
    warning: null
  },
  { 
    key: 'ar', 
    label: 'Accounts Receivable', 
    icon: DollarSign,
    description: 'AR overdue alerts, bad debt accounts, and receivable configuration.',
    warning: null
  },
] as const

type GroupKey = typeof SETTING_GROUPS[number]['key']

interface ConfirmDialogProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  message: string
  confirmText: string
  confirmVariant?: 'danger' | 'primary'
}

function ConfirmDialog({ isOpen, onClose, onConfirm, title, message, confirmText, confirmVariant = 'primary' }: ConfirmDialogProps) {
  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded max-w-md w-full max-h-[90vh] overflow-y-auto overflow-hidden">
        <div className={cn(
          "px-6 py-4 border-b flex items-center gap-3",
          'bg-neutral-50 border-neutral-200'
        )}>
          <AlertTriangle className={cn(
            "h-6 w-6 text-neutral-600"
          )} />
          <h3 className="text-lg font-semibold text-neutral-900">{title}</h3>
        </div>
        <div className="p-6">
          <p className="text-neutral-600 mb-6">{message}</p>
          <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded border border-neutral-300 transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              className={cn(
                "px-4 py-2 text-sm text-white rounded transition-colors",
                confirmVariant === 'danger' 
                  ? 'bg-neutral-800 hover:bg-neutral-700' 
                  : 'bg-neutral-900 hover:bg-neutral-800'
              )}
            >
              {confirmText}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default function SystemSettingsPage() {
  const { data: settingsByGroup, isLoading } = useSystemSettings()
  const bulkUpdate = useBulkUpdateSettings()
  const [activeGroup, setActiveGroup] = useState<GroupKey>('payroll')
  const [isEditMode, setIsEditMode] = useState(false)
  const [editedValues, setEditedValues] = useState<Record<string, unknown>>({})
  const [showSaveConfirm, setShowSaveConfirm] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)

  const currentGroup = SETTING_GROUPS.find(g => g.key === activeGroup)!
  const currentSettings = useMemo(() => {
    return settingsByGroup?.[activeGroup] || []
  }, [settingsByGroup, activeGroup])

  const hasChanges = Object.keys(editedValues).length > 0

  const handleValueChange = (key: string, value: unknown) => {
    if (!isEditMode) return
    setEditedValues(prev => ({ ...prev, [key]: value }))
  }

  const handleEnableEdit = () => {
    setIsEditMode(true)
    toast.info('Edit mode enabled. Changes will not be saved until you click "Save Changes".')
  }

  const handleCancelClick = () => {
    if (hasChanges) {
      setShowCancelConfirm(true)
    } else {
      handleCancelConfirm()
    }
  }

  const handleCancelConfirm = () => {
    setEditedValues({})
    setIsEditMode(false)
    setShowCancelConfirm(false)
    toast.info('Edit mode disabled. All unsaved changes have been discarded.')
  }

  const handleSaveClick = () => {
    if (!hasChanges) return
    setShowSaveConfirm(true)
  }

  const handleSaveConfirm = async () => {
    const settingsToUpdate = Object.entries(editedValues).map(([key, value]) => ({
      key,
      value,
    }))

    try {
      const result = await bulkUpdate.mutateAsync(settingsToUpdate)
      
      if (result.success) {
        toast.success(result.message)
        setEditedValues({})
        setIsEditMode(false)
      } else {
        toast.error('Some settings failed to update', {
          description: result.data.errors.map((e: {key: string, error: string}) => `${e.key}: ${e.error}`).join(', '),
        })
      }
    } catch (_error) {
      toast.error('Failed to save settings')
    } finally {
      setShowSaveConfirm(false)
    }
  }

  if (isLoading) {
    return (
      <div className="max-w-6xl mx-auto">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2 bg-neutral-100 rounded">
            <SettingsIcon className="h-6 w-6 text-neutral-600" />
          </div>
          <div>
            <h1 className="text-lg font-semibold text-neutral-900">System Settings</h1>
            <p className="text-sm text-neutral-500">Configure operational parameters for the ERP system</p>
          </div>
        </div>
        <SkeletonLoader rows={10} />
      </div>
    )
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header with Info */}
      <div className="bg-neutral-50 border border-neutral-200 rounded p-6">
        <div className="flex items-start gap-4">
          <div className="p-3 bg-neutral-100 rounded">
            <SettingsIcon className="h-8 w-8 text-neutral-700" />
          </div>
          <div className="flex-1">
            <h1 className="text-lg font-semibold text-neutral-900 mb-2">System Settings</h1>
            <p className="text-neutral-600 mb-4">
              This page controls <strong>application behavior and configuration parameters</strong>. 
              These are single-value settings (like timeouts, account codes, and flags) that determine 
              how the system operates.
            </p>
            <div className="flex flex-wrap gap-3">
              <InfoBadge 
                icon={CheckCircle2} 
                text="Single values: numbers, text, booleans, JSON" 
                variant="info"
              />
              <InfoBadge 
                icon={AlertCircle} 
                text="No version history — current value is always active" 
                variant="warning"
              />
              <InfoBadge 
                icon={Lock} 
                text="All changes are audited with user and timestamp" 
                variant="success"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Distinction Notice */}
      <div className="bg-neutral-50 border border-neutral-200 rounded p-4">
        <div className="flex items-start gap-3">
          <Info className="h-5 w-5 text-neutral-600 mt-0.5" />
          <div className="text-sm text-neutral-800">
            <p className="font-medium mb-1">Looking for government rates or multi-row tables?</p>
            <p>
              Government contribution tables (SSS, PhilHealth, Pag-IBIG, Tax Brackets) and other 
              reference data with <strong>effective dates and version history</strong> are managed in 
              <a href="/admin/reference-tables" className="font-medium underline ml-1 hover:text-neutral-900">
                Reference Tables
              </a>.
              This page is for application configuration only.
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Sidebar Navigation */}
        <div className="lg:col-span-1 space-y-4">
          <nav className="space-y-1">
            {SETTING_GROUPS.map((group) => (
              <button
                key={group.key}
                onClick={() => {
                  setActiveGroup(group.key)
                  if (hasChanges) {
                    toast.warning('You have unsaved changes. Save or cancel before switching groups.')
                  }
                }}
                className={`w-full flex items-center gap-3 px-4 py-3 text-left rounded transition-colors ${
                  activeGroup === group.key
                    ? 'bg-neutral-900 text-white'
                    : 'text-neutral-700 hover:bg-neutral-100 bg-white border border-neutral-200'
                }`}
              >
                <group.icon className={`h-5 w-5 ${activeGroup === group.key ? 'text-neutral-100' : 'text-neutral-500'}`} />
                <span className="font-medium text-sm">{group.label}</span>
              </button>
            ))}
          </nav>

          {/* Edit Mode Card */}
          <div className={cn(
            "rounded p-4 border transition-all",
            isEditMode 
              ? 'bg-neutral-50 border-neutral-300' 
              : 'bg-neutral-50 border-neutral-200'
          )}>
            <div className="flex items-center gap-2 mb-3">
              {isEditMode ? (
                <Unlock className="h-5 w-5 text-neutral-600" />
              ) : (
                <Lock className="h-5 w-5 text-neutral-500" />
              )}
              <span className={cn(
                "font-medium",
                isEditMode ? 'text-neutral-800' : 'text-neutral-700'
              )}>
                {isEditMode ? 'Editing Enabled' : 'View Mode'}
              </span>
            </div>
            
            {isEditMode ? (
              <div className="space-y-3">
                <p className="text-sm text-neutral-700">
                  You can now modify settings. Remember to save your changes.
                </p>
                {hasChanges && (
                  <div className="flex items-center gap-2 text-sm text-neutral-700 bg-neutral-100 rounded px-3 py-2">
                    <AlertTriangle className="h-4 w-4" />
                    <span>{Object.keys(editedValues).length} unsaved change(s)</span>
                  </div>
                )}
                <div className="flex gap-2">
                  <button
                    onClick={handleCancelClick}
                    className="flex-1 flex items-center justify-center gap-2 px-3 py-2 text-sm text-neutral-600 hover:bg-white rounded border border-neutral-300 transition-all"
                  >
                    <X className="h-4 w-4" />
                    Cancel
                  </button>
                  <button
                    onClick={handleSaveClick}
                    disabled={!hasChanges || bulkUpdate.isPending}
                    className="flex-1 flex items-center justify-center gap-2 px-3 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 disabled:bg-neutral-400 text-white rounded transition-all"
                  >
                    <Save className="h-4 w-4" />
                    {bulkUpdate.isPending ? 'Saving...' : 'Save'}
                  </button>
                </div>
              </div>
            ) : (
              <>
                <p className="text-sm text-neutral-600 mb-3">
                  Settings are locked to prevent accidental changes. Enable editing to modify values.
                </p>
                <button
                  onClick={handleEnableEdit}
                  className="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 text-white rounded transition-colors"
                >
                  <Edit3 className="h-4 w-4" />
                  Enable Editing
                </button>
              </>
            )}
          </div>
        </div>

        {/* Settings Form */}
        <div className="lg:col-span-3">
          <div className="bg-white border border-neutral-200 rounded overflow-hidden">
            <div className="px-6 py-4 border-b border-neutral-200 bg-neutral-50">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-neutral-100 rounded">
                  <currentGroup.icon className="h-5 w-5 text-neutral-600" />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-neutral-900">{currentGroup.label}</h2>
                  <p className="text-sm text-neutral-500">{currentGroup.description}</p>
                </div>
              </div>
              
              {currentGroup.warning && (
                <div className="mt-3 flex items-start gap-2 text-sm text-neutral-700 bg-neutral-50 rounded px-4 py-3">
                  <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                  <span>{currentGroup.warning}</span>
                </div>
              )}
            </div>

            <div className="p-6 space-y-6">
              {!isEditMode && currentSettings.length > 0 && (
                <div className="flex items-center gap-2 text-sm text-neutral-500 bg-neutral-50 rounded px-4 py-3">
                  <Lock className="h-4 w-4" />
                  <span>View mode: Click "Enable Editing" in the sidebar to modify these settings</span>
                </div>
              )}

              {currentSettings.length === 0 ? (
                <p className="text-neutral-500 text-center py-8">No settings found for this group.</p>
              ) : (
                currentSettings.map((setting) => (
                  <SettingField
                    key={setting.key}
                    setting={setting}
                    value={editedValues[setting.key] ?? setting.value}
                    onChange={(value) => handleValueChange(setting.key, value)}
                    isEdited={setting.key in editedValues}
                    isEditMode={isEditMode}
                  />
                ))
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Confirmation Dialogs */}
      <ConfirmDialog
        isOpen={showSaveConfirm}
        onClose={() => setShowSaveConfirm(false)}
        onConfirm={handleSaveConfirm}
        title="Confirm Save Changes"
        message={`You are about to save ${Object.keys(editedValues).length} setting(s). These changes take effect immediately and will be recorded in the audit trail. Are you sure?`}
        confirmText="Yes, Save Changes"
        confirmVariant="primary"
      />

      <ConfirmDialog
        isOpen={showCancelConfirm}
        onClose={() => setShowCancelConfirm(false)}
        onConfirm={handleCancelConfirm}
        title="Discard Changes?"
        message={`You have ${Object.keys(editedValues).length} unsaved change(s). If you cancel, these changes will be lost. Are you sure?`}
        confirmText="Yes, Discard Changes"
        confirmVariant="danger"
      />
    </div>
  )
}

// ── Helper Components ────────────────────────────────────────────────────────

function SettingsIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  )
}

function InfoBadge({ icon: Icon, text, variant }: { icon: React.ElementType, text: string, variant: 'info' | 'warning' | 'success' }) {
  const variants = {
    info: 'bg-neutral-100 text-neutral-700',
    warning: 'bg-neutral-100 text-neutral-700',
    success: 'bg-neutral-100 text-neutral-700'
  }
  
  return (
    <div className={cn("flex items-center gap-1.5 px-3 py-1.5 rounded text-xs font-medium", variants[variant])}>
      <Icon className="h-3.5 w-3.5" />
      <span>{text}</span>
    </div>
  )
}

interface SettingFieldProps {
  setting: SystemSetting
  value: unknown
  onChange: (value: unknown) => void
  isEdited: boolean
  isEditMode: boolean
}

function SettingField({ setting, value, onChange, isEdited, isEditMode }: SettingFieldProps) {
  const inputId = `setting-${setting.key}`
  const [jsonError, setJsonError] = useState<string | null>(null)
  const [jsonText, setJsonText] = useState(() => 
    typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
  )

  const renderInput = () => {
    const baseClassName = cn(
      "w-full px-3 py-2 border rounded transition-all",
      !isEditMode && "bg-neutral-50 text-neutral-500 cursor-not-allowed",
      isEditMode && "border-neutral-300 focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400",
      isEdited && isEditMode && "border-neutral-400 bg-neutral-50"
    )

    switch (setting.data_type) {
      case 'boolean':
        return (
          <label className={cn(
            "flex items-center gap-3 cursor-pointer p-3 rounded border transition-all",
            !isEditMode && "bg-neutral-50 cursor-not-allowed opacity-60",
            isEditMode && "cursor-pointer hover:bg-neutral-50",
            value ? 'border-neutral-300 bg-neutral-50' : 'border-neutral-200'
          )}>
            <input
              type="checkbox"
              id={inputId}
              checked={Boolean(value)}
              onChange={(e) => isEditMode && onChange(e.target.checked)}
              disabled={!isEditMode}
              className="w-5 h-5 text-neutral-600 rounded border-neutral-300 focus:ring-neutral-400 disabled:opacity-50"
            />
            <span className={cn(
              "text-sm font-medium",
              value ? 'text-neutral-700' : 'text-neutral-600'
            )}>
              {value ? '✓ Enabled' : '✗ Disabled'}
            </span>
          </label>
        )

      case 'integer':
        return (
          <input
            type="number"
            id={inputId}
            value={String(value)}
            onChange={(e) => isEditMode && onChange(parseInt(e.target.value, 10) || 0)}
            disabled={!isEditMode}
            step={setting.validation_rules?.step || 1}
            className={cn(baseClassName, "max-w-xs font-mono")}
          />
        )

      case 'decimal':
        return (
          <input
            type="number"
            id={inputId}
            value={String(value)}
            onChange={(e) => isEditMode && onChange(parseFloat(e.target.value) || 0)}
            disabled={!isEditMode}
            step={setting.validation_rules?.step || 0.01}
            className={cn(baseClassName, "max-w-xs font-mono")}
          />
        )

      case 'json':
        return (
          <div className="space-y-2">
            <div className="flex items-center gap-2 text-sm text-neutral-600 mb-2">
              <Code2 className="h-4 w-4" />
              <span>JSON Format — Must be valid JSON</span>
            </div>
            <textarea
              id={inputId}
              value={jsonText}
              onChange={(e) => {
                if (!isEditMode) return
                setJsonText(e.target.value)
                try {
                  const parsed = JSON.parse(e.target.value)
                  setJsonError(null)
                  onChange(parsed)
                } catch (_err) {
                  setJsonError('Invalid JSON format')
                }
              }}
              disabled={!isEditMode}
              rows={6}
              className={cn(
                baseClassName,
                "font-mono text-sm",
                jsonError && isEditMode && "border-red-300 focus:border-red-500 focus:ring-red-500"
              )}
            />
            {jsonError && isEditMode && (
              <div className="flex items-center gap-2 text-sm text-red-600">
                <AlertCircle className="h-4 w-4" />
                <span>{jsonError}</span>
              </div>
            )}
            <p className="text-xs text-neutral-500">
              Example: {JSON.stringify(setting.value).substring(0, 100)}
              {JSON.stringify(setting.value).length > 100 ? '...' : ''}
            </p>
          </div>
        )

      case 'string':
      default:
        // Check if this is a select field (specific string patterns)
        if (setting.key.includes('period') && (value === 'first_half' || value === 'second_half' || !value)) {
          return (
            <select
              id={inputId}
              value={String(value || '')}
              onChange={(e) => isEditMode && onChange(e.target.value)}
              disabled={!isEditMode}
              className={cn(baseClassName, "max-w-xs")}
            >
              <option value="first_half">First Half of Month</option>
              <option value="second_half">Second Half of Month</option>
            </select>
          )
        }

        return (
          <input
            type="text"
            id={inputId}
            value={String(value || '')}
            onChange={(e) => isEditMode && onChange(e.target.value)}
            disabled={!isEditMode}
            className={cn(baseClassName, "max-w-md")}
          />
        )
    }
  }

  return (
    <div className={cn(
      "p-4 rounded border transition-all",
      isEdited && isEditMode
        ? 'bg-neutral-50 border-neutral-400' 
        : 'bg-white border-neutral-200 hover:border-neutral-300'
    )}>
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <label htmlFor={inputId} className="block text-sm font-medium text-neutral-900">
              {setting.label}
            </label>
            {isEdited && isEditMode && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
                <Edit3 className="h-3 w-3" />
                Modified
              </span>
            )}
            {setting.is_sensitive && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
                <Lock className="h-3 w-3" />
                Sensitive
              </span>
            )}
          </div>
          <p className="text-xs text-neutral-500 mb-3 font-mono">
            {setting.key}
          </p>
          {renderInput()}
        </div>
      </div>
    </div>
  )
}
