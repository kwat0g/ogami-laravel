-- Fix Accounting Manager Role Permissions
-- This removes HR access that shouldn't be there for Accounting Managers

-- Remove hr.full_access from Accounting Manager role
DELETE FROM role_has_permissions
WHERE permission_id IN (
    SELECT id FROM permissions WHERE name = 'hr.full_access'
)
AND role_id IN (
    SELECT id FROM roles WHERE name = 'Accounting Manager'
);

-- Also remove other HR permissions that might have been assigned
DELETE FROM role_has_permissions
WHERE permission_id IN (
    SELECT id FROM permissions 
    WHERE name IN (
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'attendance.view',
        'attendance.manage',
        'leaves.view',
        'leaves.manage',
        'leave_balances.manage',
        'overtime.manage',
        'loans.view',
        'loans.manage',
        'employees.manage_structure',
        'attendance.manage_shifts'
    )
)
AND role_id IN (
    SELECT id FROM roles WHERE name = 'Accounting Manager'
);

-- Verify Accounting Manager has correct permissions
-- Should have: team view, payroll view, accounting view, banking view
SELECT r.name as role, p.name as permission
FROM role_has_permissions rhp
JOIN roles r ON rhp.role_id = r.id
JOIN permissions p ON rhp.permission_id = p.id
WHERE r.name = 'Accounting Manager'
ORDER BY p.name;
