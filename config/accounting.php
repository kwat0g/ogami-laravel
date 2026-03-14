<?php

return [
    'payroll' => [
        'gl_accounts' => [
            'salaries_expense' => env('ACC_PAYROLL_SALARIES_EXPENSE', '5001'),
            'sss_payable' => env('ACC_PAYROLL_SSS_PAYABLE', '2100'),
            'philhealth_payable' => env('ACC_PAYROLL_PHILHEALTH_PAYABLE', '2101'),
            'pagibig_payable' => env('ACC_PAYROLL_PAGIBIG_PAYABLE', '2102'),
            'tax_payable' => env('ACC_PAYROLL_TAX_PAYABLE', '2103'),
            'loans_payable' => env('ACC_PAYROLL_LOANS_PAYABLE', '2104'),
            'other_deductions_payable' => env('ACC_PAYROLL_OTHER_DEDUCTIONS_PAYABLE', '2001'),
            'net_pay_payable' => env('ACC_PAYROLL_NET_PAY_PAYABLE', '2200'),
        ],
    ],
];
