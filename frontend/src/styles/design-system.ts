/**
 * Ogami ERP Design System
 * 
 * A consistent, minimalist design system with subtle enhancements
 * for better visual hierarchy and usability.
 */

// ============================================================================
// STATUS BADGES - Consistent color coding across the system
// ============================================================================

export const statusBadges = {
  // General status
  draft:      'bg-neutral-100 text-neutral-600 border border-neutral-200',
  pending:    'bg-amber-50 text-amber-700 border border-amber-200',
  active:     'bg-emerald-50 text-emerald-700 border border-emerald-200',
  inactive:   'bg-neutral-100 text-neutral-500 border border-neutral-200',
  completed:  'bg-blue-50 text-blue-700 border border-blue-200',
  approved:   'bg-emerald-50 text-emerald-700 border border-emerald-200',
  rejected:   'bg-red-50 text-red-700 border border-red-200',
  cancelled:  'bg-neutral-100 text-neutral-400 border border-neutral-200',
  closed:     'bg-neutral-100 text-neutral-500 border border-neutral-200',
  
  // Priority/Severity
  low:        'bg-blue-50 text-blue-700 border border-blue-200',
  minor:      'bg-blue-50 text-blue-700 border border-blue-200',
  medium:     'bg-amber-50 text-amber-700 border border-amber-200',
  major:      'bg-amber-50 text-amber-700 border border-amber-200',
  high:       'bg-red-50 text-red-700 border border-red-200',
  critical:   'bg-red-50 text-red-700 border border-red-200',
  urgent:     'bg-red-50 text-red-700 border border-red-200',
  
  // Process status
  open:       'bg-neutral-50 text-neutral-600 border border-neutral-300',
  inProgress: 'bg-blue-50 text-blue-700 border border-blue-200',
  underReview:'bg-purple-50 text-purple-700 border border-purple-200',
  verified:   'bg-emerald-50 text-emerald-700 border border-emerald-200',
  
  // Document status
  sent:       'bg-blue-50 text-blue-700 border border-blue-200',
  received:   'bg-emerald-50 text-emerald-700 border border-emerald-200',
  partiallyReceived: 'bg-amber-50 text-amber-700 border border-amber-200',
  fullyReceived: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
  
  // QC specific
  passed:     'bg-emerald-50 text-emerald-700 border border-emerald-200',
  failed:     'bg-red-50 text-red-700 border border-red-200',
  onHold:     'bg-amber-50 text-amber-700 border border-amber-200',
  voided:     'bg-neutral-100 text-neutral-400 border border-neutral-200',
  
  // CAPA specific
  corrective: 'bg-blue-50 text-blue-700 border border-blue-200',
  preventive: 'bg-purple-50 text-purple-700 border border-purple-200',
} as const

// ============================================================================
// BUTTON STYLES - Consistent button hierarchy
// ============================================================================

export const buttons = {
  // Primary action
  primary: `inline-flex items-center justify-center gap-2 
    px-4 py-2 
    bg-neutral-900 text-white 
    rounded-md text-sm font-medium 
    hover:bg-neutral-800 
    active:bg-neutral-950
    transition-colors duration-150
    disabled:opacity-50 disabled:cursor-not-allowed`,
  
  // Secondary action
  secondary: `inline-flex items-center justify-center gap-2 
    px-4 py-2 
    bg-white text-neutral-700 
    border border-neutral-300
    rounded-md text-sm font-medium 
    hover:bg-neutral-50 hover:border-neutral-400
    active:bg-neutral-100
    transition-colors duration-150
    disabled:opacity-50 disabled:cursor-not-allowed`,
  
  // Tertiary/Ghost action
  tertiary: `inline-flex items-center justify-center gap-2 
    px-3 py-1.5 
    bg-transparent text-neutral-600 
    rounded-md text-sm font-medium 
    hover:bg-neutral-100 hover:text-neutral-900
    transition-colors duration-150
    disabled:opacity-50 disabled:cursor-not-allowed`,
  
  // Danger action
  danger: `inline-flex items-center justify-center gap-2 
    px-4 py-2 
    bg-white text-red-600 
    border border-red-300
    rounded-md text-sm font-medium 
    hover:bg-red-50 hover:border-red-400
    active:bg-red-100
    transition-colors duration-150
    disabled:opacity-50 disabled:cursor-not-allowed`,
  
  // Icon button
  icon: `inline-flex items-center justify-center 
    p-2 
    bg-white text-neutral-500 
    border border-neutral-200
    rounded-md
    hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-700
    transition-colors duration-150`,
  
  // Small button
  sm: `inline-flex items-center justify-center gap-1.5 
    px-3 py-1.5 
    text-xs font-medium`,
} as const

// ============================================================================
// CARD STYLES - Consistent container styling
// ============================================================================

export const cards = {
  // Main content card
  default: `bg-white 
    border border-neutral-200 
    rounded-lg 
    shadow-sm`,
  
  // Section card with header
  section: `bg-white 
    border border-neutral-200 
    rounded-lg 
    shadow-sm 
    overflow-hidden`,
  
  // Hoverable card
  hoverable: `bg-white 
    border border-neutral-200 
    rounded-lg 
    shadow-sm 
    hover:border-neutral-300 
    hover:shadow-md 
    transition-all duration-150`,
  
  // Header section of card
  header: `px-5 py-4 
    border-b border-neutral-100 
    flex items-center justify-between`,
  
  // Body section of card
  body: `p-5`,
  
  // Footer section of card
  footer: `px-5 py-4 
    border-t border-neutral-100 
    bg-neutral-50/50`,
} as const

// ============================================================================
// FORM STYLES - Consistent form elements
// ============================================================================

export const forms = {
  // Input field
  input: `w-full 
    px-3 py-2 
    bg-white 
    border border-neutral-300 
    rounded-md 
    text-sm text-neutral-900
    placeholder:text-neutral-400
    focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400
    disabled:bg-neutral-50 disabled:text-neutral-400`,
  
  // Select field
  select: `w-full 
    px-3 py-2 
    bg-white 
    border border-neutral-300 
    rounded-md 
    text-sm text-neutral-900
    focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400
    disabled:bg-neutral-50 disabled:text-neutral-400`,
  
  // Textarea
  textarea: `w-full 
    px-3 py-2 
    bg-white 
    border border-neutral-300 
    rounded-md 
    text-sm text-neutral-900
    placeholder:text-neutral-400
    focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400
    disabled:bg-neutral-50 disabled:text-neutral-400
    resize-y`,
  
  // Label
  label: `block text-sm font-medium text-neutral-700 mb-1.5`,
  
  // Error message
  error: `mt-1.5 text-xs text-red-600`,
  
  // Helper text
  helper: `mt-1.5 text-xs text-neutral-500`,
} as const

// ============================================================================
// TABLE STYLES - Consistent table styling
// ============================================================================

export const tables = {
  // Table container
  container: `bg-white 
    border border-neutral-200 
    rounded-lg 
    overflow-hidden`,
  
  // Table element
  table: `min-w-full text-sm`,
  
  // Header row
  header: `bg-neutral-50 
    border-b border-neutral-200`,
  
  // Header cell
  headerCell: `px-4 py-3 
    text-left text-xs font-semibold text-neutral-600 
    uppercase tracking-wider`,
  
  // Body row
  row: `border-b border-neutral-100 
    hover:bg-neutral-50/50 
    transition-colors`,
  
  // Body cell
  cell: `px-4 py-3 
    text-neutral-700`,
  
  // Empty state
  empty: `px-4 py-12 
    text-center text-neutral-400`,
} as const

// ============================================================================
// PAGE LAYOUTS - Consistent page structures
// ============================================================================

export const layouts = {
  // Detail page container
  detailPage: `max-w-5xl mx-auto`,
  
  // List page container
  listPage: ``, // No max-width constraint for lists
  
  // Form page container
  formPage: `max-w-3xl mx-auto`,
  
  // Page header
  pageHeader: `flex items-center justify-between gap-4 mb-6`,
  
  // Page title section
  titleSection: `flex items-center gap-3`,
  
  // Action buttons container (top right)
  actionBar: `flex items-center gap-3`,
  
  // Content sections container
  content: `space-y-5`,
} as const

// ============================================================================
// TYPOGRAPHY - Consistent text styling
// ============================================================================

export const typography = {
  // Page title
  pageTitle: `text-lg font-semibold text-neutral-900`,
  
  // Section title
  sectionTitle: `text-sm font-semibold text-neutral-900`,
  
  // Card title
  cardTitle: `text-sm font-medium text-neutral-900`,
  
  // Body text
  body: `text-sm text-neutral-700`,
  
  // Muted text
  muted: `text-sm text-neutral-500`,
  
  // Small text
  small: `text-xs text-neutral-500`,
  
  // Label text
  label: `text-xs font-medium text-neutral-500 uppercase tracking-wider`,
} as const

// ============================================================================
// UTILITY CLASSES - Common combinations
// ============================================================================

export const utils = {
  // Info row (label + value)
  infoRow: `flex items-start gap-4 py-3 border-b border-neutral-100 last:border-0`,
  infoLabel: `text-sm text-neutral-500 w-40 flex-shrink-0`,
  infoValue: `text-sm text-neutral-900 font-medium`,
  
  // Divider
  divider: `h-px bg-neutral-200 my-4`,
  
  // Section divider
  sectionDivider: `flex items-center gap-4 my-6`,
  sectionDividerLine: `h-px flex-1 bg-neutral-200`,
  sectionDividerText: `text-xs font-medium text-neutral-400 uppercase tracking-wider`,
} as const
