# SoD Leakage & Frontend Permission Mismatch Audit Spec

## Why
Ensuring Segregation of Duties (SoD) is critical for preventing fraud and errors. A single role should not control an entire transaction chain. Additionally, frontend UI elements must match backend permissions to provide a secure and consistent user experience, preventing unauthorized actions and confusion.

## What Changes
This spec outlines a comprehensive audit and remediation process based on `sod.md`:
- **Discovery**: Identify all roles, permissions, and their inferred responsibilities.
- **Frontend Audit**: detect UI elements (buttons, pages, tabs) visible to roles without proper permissions.
- **SoD Audit**: Identify workflows where a single role can initiate and approve/complete transactions.
- **Consistency Check**: Verify frontend permission strings match backend definitions and ensure API endpoints are protected.
- **Remediation**: Apply fixes for critical and high-priority findings (SoD violations, unprotected endpoints, permission mismatches).

## Impact
- **Security**: Reduced risk of fraud through enforced SoD and protected API endpoints.
- **UX**: Consistent UI where users only see actions they can perform.
- **Compliance**: Alignment with internal control standards.
- **Codebase**: potential updates to seeders (permissions), middleware, and frontend components.

## ADDED Requirements
### Requirement: SoD Enforcement
The system SHALL prevent a single user from approving their own transactions in critical workflows (e.g., Payroll, Procurement).

#### Scenario: Self-Approval Prevention
- **WHEN** a user with approval rights attempts to approve a record they created
- **THEN** the system SHALL block the action and return an error.

### Requirement: Frontend-Backend Permission Alignment
The system SHALL hide or disable UI elements for actions the user does not have permission to perform.

#### Scenario: Hidden Action Button
- **WHEN** a user without `delete` permission views a resource
- **THEN** the "Delete" button SHALL be hidden or disabled.

## MODIFIED Requirements
### Requirement: Role Permissions
Existing roles may have permissions revoked or added to satisfy SoD requirements.
**Reason**: To eliminate SoD conflicts.
**Migration**: Update `RolePermissionSeeder`.
