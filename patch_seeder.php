<?php
$content = file_get_contents('database/seeders/RolePermissionSeeder.php');

// Manager
$content = str_replace(
    "'loans.manager_check', 'loans.officer_review',",
    "'loans.manager_check', 'loans.officer_review',\n            // Recruitment\n            'recruitment.requisitions.view', 'recruitment.requisitions.create', 'recruitment.requisitions.edit',\n            'recruitment.requisitions.submit', 'recruitment.requisitions.reject', 'recruitment.requisitions.cancel',\n            'recruitment.requisitions.approve',\n            'recruitment.postings.view', 'recruitment.postings.create', 'recruitment.postings.publish', 'recruitment.postings.close',\n            'recruitment.applications.view', 'recruitment.applications.review', 'recruitment.applications.shortlist', 'recruitment.applications.reject',\n            'recruitment.interviews.view', 'recruitment.interviews.schedule', 'recruitment.interviews.evaluate',\n            'recruitment.offers.view', 'recruitment.offers.create', 'recruitment.offers.send',\n            'recruitment.preemployment.view', 'recruitment.preemployment.verify',\n            'recruitment.reports.view', 'recruitment.candidates.view', 'recruitment.candidates.manage',",
    $content
);

// Officer
$content = str_replace(
    "'budget.forecast',\n        ]);\n\n        // ── Head",
    "'budget.forecast',\n            // Recruitment\n            'recruitment.requisitions.view', 'recruitment.applications.view', 'recruitment.applications.review', 'recruitment.applications.shortlist',\n            'recruitment.interviews.view',\n            'recruitment.candidates.view',\n        ]);\n\n        // ── Head",
    $content
);

// Head
$content = str_replace(
    "'mold.view', 'mold.log_shots',",
    "'mold.view', 'mold.log_shots',\n            // Recruitment\n            'recruitment.requisitions.view', 'recruitment.requisitions.create', 'recruitment.requisitions.edit',\n            'recruitment.requisitions.submit', 'recruitment.requisitions.reject',\n            'recruitment.applications.view', 'recruitment.candidates.view',",
    $content
);

// VP
$content = str_replace(
    "'loans.view_department', 'loans.vp_approve',",
    "'loans.view_department', 'loans.vp_approve',\n            // Recruitment VP\n            'recruitment.requisitions.approve',",
    $content
);

// Staff
$content = str_replace(
    "'leave_balances.view', 'loans.view', 'attendance.view',",
    "'leave_balances.view', 'loans.view', 'attendance.view',\n            // Recruitment\n            'recruitment.postings.view', 'recruitment.candidates.view',",
    $content
);

file_put_contents('database/seeders/RolePermissionSeeder.php', $content);
echo "Patched successfully\n";
