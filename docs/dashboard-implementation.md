# Role-Based Dashboard Implementation

## Overview
This implementation provides separate professional dashboards for each user role in the Ogami ERP system:

- **Department Manager** - Team management with department headcounts and pending approvals
- **Supervisor** - Team oversight with attendance tracking and approval workflows
- **HR Manager** - Company-wide HR overview with headcounts and pending HR approvals
- **Accounting Manager** - Financial oversight with pending AP, JE, loans, and payroll approvals
- **Admin** - System health monitoring with user management and active sessions
- **Staff/Employee** - Personal dashboard with attendance, leave balance, loans, and YTD payroll
- **Executive** - High-level company overview with key metrics and financial health

## Design Principles
- **No gradient colors** - Uses solid colors and borders for a professional look
- **Non-AI appearance** - Clean, traditional dashboard design with clear visual hierarchy
- **Consistent UI patterns** - Each dashboard follows similar card, table, and alert patterns
- **Role-appropriate data** - Each role only sees data relevant to their responsibilities

## Files Created

### Frontend Components
```
frontend/src/hooks/useDashboard.ts          # Dashboard data hooks and types
frontend/src/pages/dashboard/ManagerDashboard.tsx      # Department manager view
frontend/src/pages/dashboard/SupervisorDashboard.tsx   # Supervisor view
frontend/src/pages/dashboard/HrDashboard.tsx           # HR manager view
frontend/src/pages/dashboard/AccountingDashboard.tsx   # Accounting manager view
frontend/src/pages/dashboard/AdminDashboard.tsx        # System admin view
frontend/src/pages/dashboard/ExecutiveDashboard.tsx    # Executive view
frontend/src/pages/dashboard/EmployeeDashboard.tsx     # Staff view (updated)
frontend/src/pages/Dashboard.tsx                       # Main router (updated)
```

### Backend API
```
routes/api/v1/dashboard.php       # Role-specific dashboard endpoints
routes/api.php                    # Added dashboard route registration
```

## API Endpoints

All endpoints require authentication and return role-specific dashboard data:

| Endpoint | Method | Access | Description |
|----------|--------|--------|-------------|
| `/api/v1/dashboard/manager` | GET | Manager | Department headcount, pending approvals, attendance |
| `/api/v1/dashboard/supervisor` | GET | Supervisor | Team stats, pending approvals, weekly attendance |
| `/api/v1/dashboard/hr` | GET | HR Manager | Company-wide HR stats, all pending HR approvals |
| `/api/v1/dashboard/accounting` | GET | Accounting Manager | Financial approvals, AP, JE, payroll review |
| `/api/v1/dashboard/admin` | GET | Admin | System health, user activity, locked accounts |
| `/api/v1/dashboard/staff` | GET | Employee | Personal attendance, leave, loans, YTD payroll |
| `/api/v1/dashboard/executive` | GET | Executive | Company overview, financial health, key metrics |

## Dashboard Features by Role

### Department Manager
- Department headcount (total, active, on leave)
- Today's attendance (present, absent, late, on leave)
- Pending approvals (leaves, overtime, loans)
- Recent leave and overtime requests
- Quick links to team management

### Supervisor
- Team member count
- Today's present/on leave count
- Weekly attendance summary
- Pending approvals for supervised staff
- Recent team requests

### HR Manager
- Company-wide employee count
- Department breakdown with headcount
- New hires this month
- HR pending approvals (leaves, OT, loans)
- Monthly attendance summary
- Active payroll run status

### Accounting Manager
- Pending loans for disbursement
- Pending journal entries
- Pending vendor invoices (AP)
- Payroll runs awaiting review
- Financial summary (pending AP/AR)
- Current fiscal period

### Admin
- Active user sessions
- Total registered users
- Locked accounts requiring attention
- Failed login attempts
- Recent system activity
- Queue and system status

### Staff/Employee
- Leave balance and pending requests
- Active loans and outstanding balance
- This month's attendance (present, absent, late, OT)
- YTD gross and net pay
- Recent personal requests

### Executive
- Company headcount and departments
- Financial overview (payroll, AP, AR)
- Pending executive approvals
- Key HR metrics (headcount change, attrition, tenure)

## UI Components

### Shared Components
- `StatCard` - Metric display with icon, value, and optional link
- `PendingAlert` - Highlighted alert for items requiring action
- `StatusBadge` - Colored status indicator
- `RecentRequestsTable` - Table for displaying recent requests

### Design Tokens
- Border radius: `rounded-lg` (8px)
- Card padding: `p-4` (16px)
- Section spacing: `space-y-6` (24px)
- Colors: Blue, Green, Amber, Red, Gray, Purple (solid, no gradients)
- Shadows: Only on hover (`hover:shadow-sm`)

## Security Considerations
- Each endpoint checks appropriate permissions
- Data is scoped to user's accessible departments
- Admin dashboard requires `system.manage_users` permission
- No sensitive data exposed in staff dashboard

## Future Enhancements
- Real-time updates via WebSockets
- Export dashboard data to PDF/Excel
- Customizable dashboard widgets
- Historical trend charts
- Drill-down capabilities
