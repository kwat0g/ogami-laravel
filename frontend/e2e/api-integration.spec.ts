/**
 * E2E-API-INTEGRATION — API Integration Tests
 * 
 * Tests backend-frontend API contracts:
 * - Authentication flow
 * - CRUD operations for all modules
 * - Permission enforcement at API level
 * - Data validation and error handling
 * - File uploads and downloads
 */
import { test, expect, Page, APIRequestContext } from '@playwright/test'

const BASE = 'http://localhost:5173'
const API_BASE = 'http://localhost:8000/api/v1'

// ═══════════════════════════════════════════════════════════════════════════════
// API TEST HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

interface ApiResponse {
  status: number
  data: any
  headers: Record<string, string>
}

async function apiLogin(
  request: APIRequestContext, 
  email: string, 
  password: string
): Promise<{ token: string; cookies: string[] }> {
  // Get CSRF cookie
  await request.get(`${API_BASE}/sanctum/csrf-cookie`)
  
  // Login
  const response = await request.post(`${API_BASE}/auth/login`, {
    data: { email, password },
    headers: { 'Accept': 'application/json' }
  })
  
  expect(response.status()).toBe(200)
  
  const data = await response.json()
  const cookies = response.headers()['set-cookie'] || []
  
  return { token: data.token || '', cookies: Array.isArray(cookies) ? cookies : [cookies] }
}

async function apiGet(
  request: APIRequestContext,
  endpoint: string,
  token: string,
  cookies: string[]
): Promise<ApiResponse> {
  const response = await request.get(`${API_BASE}${endpoint}`, {
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Cookie': cookies.join('; ')
    }
  })
  
  return {
    status: response.status(),
    data: await response.json().catch(() => null),
    headers: Object.fromEntries(response.headers() as unknown as Iterable<[string, string]>)
  }
}

async function apiPost(
  request: APIRequestContext,
  endpoint: string,
  data: any,
  token: string,
  cookies: string[]
): Promise<ApiResponse> {
  const response = await request.post(`${API_BASE}${endpoint}`, {
    data,
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Cookie': cookies.join('; '),
      'Content-Type': 'application/json'
    }
  })
  
  return {
    status: response.status(),
    data: await response.json().catch(() => null),
    headers: Object.fromEntries(response.headers() as unknown as Iterable<[string, string]>)
  }
}

async function apiPut(
  request: APIRequestContext,
  endpoint: string,
  data: any,
  token: string,
  cookies: string[]
): Promise<ApiResponse> {
  const response = await request.put(`${API_BASE}${endpoint}`, {
    data,
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Cookie': cookies.join('; '),
      'Content-Type': 'application/json'
    }
  })
  
  return {
    status: response.status(),
    data: await response.json().catch(() => null),
    headers: Object.fromEntries(response.headers() as unknown as Iterable<[string, string]>)
  }
}

async function apiDelete(
  request: APIRequestContext,
  endpoint: string,
  token: string,
  cookies: string[]
): Promise<ApiResponse> {
  const response = await request.delete(`${API_BASE}${endpoint}`, {
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Cookie': cookies.join('; ')
    }
  })
  
  return {
    status: response.status(),
    data: await response.json().catch(() => null),
    headers: Object.fromEntries(response.headers() as unknown as Iterable<[string, string]>)
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔐 API - Authentication', () => {
  
  test('Login with valid credentials', async ({ request }) => {
    const response = await request.post(`${API_BASE}/auth/login`, {
      data: {
        email: 'hr.manager@ogamierp.local',
        password: 'Manager@Test1234!'
      },
      headers: { 'Accept': 'application/json' }
    })
    
    expect(response.status()).toBe(200)
    
    const data = await response.json()
    expect(data).toHaveProperty('user')
    expect(data.user).toHaveProperty('email', 'hr.manager@ogamierp.local')
    expect(data.user).toHaveProperty('roles')
    expect(data.user).toHaveProperty('permissions')
    expect(Array.isArray(data.user.roles)).toBe(true)
    expect(Array.isArray(data.user.permissions)).toBe(true)
  })

  test('Login with invalid credentials', async ({ request }) => {
    const response = await request.post(`${API_BASE}/auth/login`, {
      data: {
        email: 'hr.manager@ogamierp.local',
        password: 'WrongPassword!'
      },
      headers: { 'Accept': 'application/json' }
    })
    
    expect(response.status()).toBe(401)
    
    const data = await response.json()
    expect(data).toHaveProperty('message')
    expect(data.message).toMatch(/invalid|unauthorized|credentials/i)
  })

  test('Get current user profile', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'acctg.manager@ogamierp.local',
      'Manager@12345!'
    )
    
    const response = await apiGet(request, '/auth/me', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('user')
    expect(response.data.user.email).toBe('acctg.manager@ogamierp.local')
  })

  test('Logout invalidates session', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'hr.manager@ogamierp.local',
      'Manager@Test1234!'
    )
    
    // Logout
    const logoutResponse = await request.post(`${API_BASE}/auth/logout`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Cookie': cookies.join('; ')
      }
    })
    
    expect(logoutResponse.status()).toBe(200)
    
    // Try to access protected endpoint
    const checkResponse = await apiGet(request, '/auth/me', token, cookies)
    expect(checkResponse.status).toBe(401)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// HR MODULE API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('👤 API - HR Module', () => {
  let token: string
  let cookies: string[]
  let createdEmployeeId: string
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'hr.manager@ogamierp.local', 'Manager@Test1234!')
    token = login.token
    cookies = login.cookies
  })

  test('List all employees', async ({ request }) => {
    const response = await apiGet(request, '/hr/employees', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
    expect(Array.isArray(response.data.data)).toBe(true)
  })

  test('Create new employee', async ({ request }) => {
    const timestamp = Date.now()
    const employeeData = {
      employee_code: `EMP-${timestamp}`,
      first_name: 'Test',
      last_name: `Employee ${timestamp}`,
      email: `test${timestamp}@ogamierp.local`,
      department_id: 1,
      position_id: 1,
      date_hired: '2026-03-01',
      employment_type: 'regular',
      sss_no: `SSS-${timestamp}`,
      philhealth_no: `PH-${timestamp}`,
      pagibig_no: `PAG-${timestamp}`,
      tin: `TIN-${timestamp}`,
      salary_grade_id: 1
    }
    
    const response = await apiPost(request, '/hr/employees', employeeData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data).toHaveProperty('data')
    expect(response.data.data).toHaveProperty('id')
    
    createdEmployeeId = response.data.data.id
  })

  test('Get employee details', async ({ request }) => {
    const response = await apiGet(request, `/hr/employees/${createdEmployeeId}`, token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data.data.employee_code).toContain('EMP-')
  })

  test('Update employee', async ({ request }) => {
    const updateData = {
      first_name: 'Updated',
      last_name: 'Name'
    }
    
    const response = await apiPut(request, `/hr/employees/${createdEmployeeId}`, updateData, token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data.data.first_name).toBe('Updated')
  })

  test('Delete employee (soft delete)', async ({ request }) => {
    const response = await apiDelete(request, `/hr/employees/${createdEmployeeId}`, token, cookies)
    
    expect(response.status).toBe(200)
    
    // Verify soft delete - should return 404 or marked as deleted
    const checkResponse = await apiGet(request, `/hr/employees/${createdEmployeeId}`, token, cookies)
    expect([404, 200]).toContain(checkResponse.status)
    
    if (checkResponse.status === 200) {
      expect(checkResponse.data.data).toHaveProperty('deleted_at')
    }
  })

  test('Non-HR user cannot create employee', async ({ request }) => {
    const { token: prodToken, cookies: prodCookies } = await apiLogin(
      request,
      'prod.manager@ogamierp.local',
      'Manager@12345!'
    )
    
    const response = await apiPost(request, '/hr/employees', {
      first_name: 'Should',
      last_name: 'Fail'
    }, prodToken, prodCookies)
    
    expect(response.status).toBe(403)
  })

  test('List departments', async ({ request }) => {
    const response = await apiGet(request, '/hr/departments', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
    
    // Verify expected departments exist
    const deptCodes = response.data.data.map((d: any) => d.code)
    expect(deptCodes).toContain('HR')
    expect(deptCodes).toContain('PROD')
    expect(deptCodes).toContain('ACCTG')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// INVENTORY MODULE API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📦 API - Inventory Module', () => {
  let token: string
  let cookies: string[]
  let createdItemId: string
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'warehouse.head@ogamierp.local', 'Head@123456789!')
    token = login.token
    cookies = login.cookies
  })

  test('List item categories', async ({ request }) => {
    const response = await apiGet(request, '/inventory/items/categories', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
    expect(Array.isArray(response.data.data)).toBe(true)
  })

  test('Create item master', async ({ request }) => {
    const timestamp = Date.now()
    const itemData = {
      item_code: `TEST-${timestamp}`,
      name: `Test Item ${timestamp}`,
      type: 'raw_material',
      category_id: 1,
      unit_of_measure: 'pcs',
      reorder_point: 50,
      reorder_quantity: 500,
      requires_iqc: true,
      description: 'API test item'
    }
    
    const response = await apiPost(request, '/inventory/items', itemData, token, cookies)
    
    expect(response.status).toBe(201)
    createdItemId = response.data.data.id
  })

  test('Get stock balance', async ({ request }) => {
    const response = await apiGet(request, '/inventory/stock', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
    
    // Verify stock data structure
    if (response.data.data.length > 0) {
      const firstItem = response.data.data[0]
      expect(firstItem).toHaveProperty('item_id')
      expect(firstItem).toHaveProperty('quantity_on_hand')
    }
  })

  test('Create stock adjustment', async ({ request }) => {
    const adjustmentData = {
      item_id: createdItemId,
      warehouse_location_id: 1,
      adjustment_type: 'receipt',
      quantity: 100,
      reason: 'Initial stock receipt - API test',
      reference_no: `ADJ-${Date.now()}`
    }
    
    const response = await apiPost(request, '/inventory/adjustments', adjustmentData, token, cookies)
    
    expect(response.status).toBe(201)
  })

  test('Create material requisition', async ({ request }) => {
    const mrqData = {
      department_id: 1,
      required_date: '2026-04-01',
      priority: 'normal',
      purpose: 'API test MRQ',
      items: [
        {
          item_id: createdItemId,
          quantity: 50,
          uom: 'pcs'
        }
      ]
    }
    
    const response = await apiPost(request, '/inventory/requisitions', mrqData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('mrq_number')
  })

  test('Production user cannot access inventory categories', async ({ request }) => {
    const { token: prodToken, cookies: prodCookies } = await apiLogin(
      request,
      'prod.manager@ogamierp.local',
      'Manager@12345!'
    )
    
    const response = await apiGet(request, '/inventory/items/categories', prodToken, prodCookies)
    
    expect(response.status).toBe(403)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PROCUREMENT MODULE API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🛒 API - Procurement Module', () => {
  let token: string
  let cookies: string[]
  let createdPrId: string
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'purchasing.officer@ogamierp.local', 'Officer@12345!')
    token = login.token
    cookies = login.cookies
  })

  test('List purchase requests', async ({ request }) => {
    const response = await apiGet(request, '/procurement/purchase-requests', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
    expect(response.data).toHaveProperty('meta')
    expect(response.data.meta).toHaveProperty('current_page')
  })

  test('Create purchase request', async ({ request }) => {
    const timestamp = Date.now()
    const prData = {
      department_id: 1,
      justification: 'API test PR',
      remarks: 'Test procurement',
      items: [
        {
          description: 'Test Material',
          quantity: 100,
          uom: 'pcs',
          estimated_unit_cost: 50
        }
      ]
    }
    
    const response = await apiPost(request, '/procurement/purchase-requests', prData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('pr_number')
    expect(response.data.data).toHaveProperty('status', 'draft')
    
    createdPrId = response.data.data.id
  })

  test('Submit PR for approval', async ({ request }) => {
    const response = await apiPost(
      request,
      `/procurement/purchase-requests/${createdPrId}/submit`,
      {},
      token,
      cookies
    )
    
    expect(response.status).toBe(200)
    expect(response.data.data.status).toMatch(/pending|for approval/i)
  })

  test('VP approves PR', async ({ request }) => {
    const { token: vpToken, cookies: vpCookies } = await apiLogin(
      request,
      'vp@ogamierp.local',
      'VicePresident@1!'
    )
    
    const response = await apiPost(
      request,
      `/procurement/purchase-requests/${createdPrId}/approve`,
      { notes: 'Approved for procurement' },
      vpToken,
      vpCookies
    )
    
    expect(response.status).toBe(200)
  })

  test('Create purchase order from PR', async ({ request }) => {
    const poData = {
      purchase_request_id: createdPrId,
      vendor_id: 1,
      payment_terms: 'Net 30',
      delivery_date: '2026-04-15',
      items: [
        {
          pr_line_id: 1,
          quantity: 100,
          unit_price: 45
        }
      ]
    }
    
    const response = await apiPost(request, '/procurement/purchase-orders', poData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('po_number')
  })

  test('List vendors', async ({ request }) => {
    const response = await apiGet(request, '/finance/vendors', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PRODUCTION MODULE API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🏭 API - Production Module', () => {
  let token: string
  let cookies: string[]
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'prod.manager@ogamierp.local', 'Manager@12345!')
    token = login.token
    cookies = login.cookies
  })

  test('List BOMs', async ({ request }) => {
    const response = await apiGet(request, '/production/boms', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })

  test('Create BOM', async ({ request }) => {
    const timestamp = Date.now()
    const bomData = {
      bom_number: `BOM-${timestamp}`,
      product_name: 'Test Assembly',
      product_type: 'finished_good',
      version: '1.0',
      description: 'API test BOM',
      materials: [
        {
          item_id: 1,
          quantity: 10,
          uom: 'pcs'
        }
      ],
      labor: [
        {
          operation: 'Assembly',
          hours: 2,
          rate: 150
        }
      ]
    }
    
    const response = await apiPost(request, '/production/boms', bomData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('id')
  })

  test('List work orders', async ({ request }) => {
    const response = await apiGet(request, '/production/orders', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })

  test('Create work order', async ({ request }) => {
    const timestamp = Date.now()
    const woData = {
      wo_number: `WO-${timestamp}`,
      bom_id: 1,
      quantity: 100,
      planned_start: '2026-04-01',
      planned_end: '2026-04-10',
      priority: 'high',
      remarks: 'API test work order'
    }
    
    const response = await apiPost(request, '/production/orders', woData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('wo_number')
  })

  test('Production user cannot access payroll', async ({ request }) => {
    const response = await apiGet(request, '/payroll/runs', token, cookies)
    
    expect(response.status).toBe(403)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// QC MODULE API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔍 API - QC Module', () => {
  let token: string
  let cookies: string[]
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'qc.manager@ogamierp.local', 'Manager@12345!')
    token = login.token
    cookies = login.cookies
  })

  test('List inspections', async ({ request }) => {
    const response = await apiGet(request, '/qc/inspections', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })

  test('Create incoming inspection', async ({ request }) => {
    const inspectionData = {
      inspection_type: 'incoming',
      reference_type: 'goods_receipt',
      reference_id: 1,
      inspector_id: 1,
      inspection_date: '2026-03-15',
      items: [
        {
          item_id: 1,
          quantity_inspected: 100,
          quantity_passed: 95,
          quantity_rejected: 5,
          result: 'conditional',
          remarks: 'Some defects found'
        }
      ]
    }
    
    const response = await apiPost(request, '/qc/inspections', inspectionData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('inspection_number')
  })

  test('List NCRs', async ({ request }) => {
    const response = await apiGet(request, '/qc/ncrs', token, cookies)
    
    expect(response.status).toBe(200)
  })

  test('Create NCR', async ({ request }) => {
    const timestamp = Date.now()
    const ncrData = {
      ncr_number: `NCR-${timestamp}`,
      source_type: 'inspection',
      source_id: 1,
      severity: 'major',
      description: 'Defective items received',
      immediate_containment: 'Items quarantined',
      reported_by: 1,
      reported_date: '2026-03-15'
    }
    
    const response = await apiPost(request, '/qc/ncrs', ncrData, token, cookies)
    
    expect(response.status).toBe(201)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// ACCOUNTING MODULE API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('💰 API - Accounting Module', () => {
  let token: string
  let cookies: string[]
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'acctg.manager@ogamierp.local', 'Manager@12345!')
    token = login.token
    cookies = login.cookies
  })

  test('List chart of accounts', async ({ request }) => {
    const response = await apiGet(request, '/accounting/accounts', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
    
    // Verify account structure
    if (response.data.data.length > 0) {
      const firstAccount = response.data.data[0]
      expect(firstAccount).toHaveProperty('account_code')
      expect(firstAccount).toHaveProperty('account_name')
      expect(firstAccount).toHaveProperty('account_type')
    }
  })

  test('List journal entries', async ({ request }) => {
    const response = await apiGet(request, '/accounting/journal-entries', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })

  test('Create journal entry', async ({ request }) => {
    const timestamp = Date.now()
    const jeData = {
      entry_number: `JE-${timestamp}`,
      entry_date: '2026-03-15',
      fiscal_period_id: 1,
      description: 'API test journal entry',
      source: 'manual',
      lines: [
        {
          account_id: 1,
          debit: 10000,
          credit: 0,
          description: 'Debit line'
        },
        {
          account_id: 2,
          debit: 0,
          credit: 10000,
          description: 'Credit line'
        }
      ]
    }
    
    const response = await apiPost(request, '/accounting/journal-entries', jeData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('id')
    expect(response.data.data).toHaveProperty('status', 'unposted')
  })

  test('List AP invoices', async ({ request }) => {
    const response = await apiGet(request, '/accounting/ap/invoices', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })

  test('Create vendor', async ({ request }) => {
    const timestamp = Date.now()
    const vendorData = {
      name: `Vendor ${timestamp}`,
      trade_name: 'Test Trade',
      address: '123 Business St',
      tin: '123-456-789-000',
      contact_person: 'Test Contact',
      contact_number: '+63 912 345 6789',
      email: `vendor${timestamp}@test.com`,
      payment_terms: 'net_30',
      tax_classification: 'vat_registered'
    }
    
    const response = await apiPost(request, '/finance/vendors', vendorData, token, cookies)
    
    expect(response.status).toBe(201)
    expect(response.data.data).toHaveProperty('id')
  })

  test('Get trial balance', async ({ request }) => {
    const response = await apiGet(request, '/accounting/trial-balance?as_of=2026-03-31', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data).toHaveProperty('data')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PERMISSION API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔒 API - Permission Enforcement', () => {
  
  test('HR Manager can access HR but not Production', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'hr.manager@ogamierp.local',
      'Manager@Test1234!'
    )
    
    // Should succeed
    const hrResponse = await apiGet(request, '/hr/employees', token, cookies)
    expect(hrResponse.status).toBe(200)
    
    // Should fail
    const prodResponse = await apiGet(request, '/production/orders', token, cookies)
    expect(prodResponse.status).toBe(403)
    
    // Should fail
    const invResponse = await apiGet(request, '/inventory/items', token, cookies)
    expect(invResponse.status).toBe(403)
  })

  test('Production Manager can access Production but not Payroll', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'prod.manager@ogamierp.local',
      'Manager@12345!'
    )
    
    // Should succeed
    const prodResponse = await apiGet(request, '/production/orders', token, cookies)
    expect(prodResponse.status).toBe(200)
    
    // Should succeed (Production can access inventory items via MRQ)
    const invResponse = await apiGet(request, '/inventory/requisitions', token, cookies)
    expect(invResponse.status).toBe(200)
    
    // Should fail
    const payrollResponse = await apiGet(request, '/payroll/runs', token, cookies)
    expect(payrollResponse.status).toBe(403)
    
    // Should fail - Inventory Categories requires locations.manage
    const categoriesResponse = await apiGet(request, '/inventory/items/categories', token, cookies)
    expect(categoriesResponse.status).toBe(403)
  })

  test('Warehouse Head can access full Inventory', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'warehouse.head@ogamierp.local',
      'Head@123456789!'
    )
    
    // Should all succeed
    const itemsResponse = await apiGet(request, '/inventory/items', token, cookies)
    expect(itemsResponse.status).toBe(200)
    
    const categoriesResponse = await apiGet(request, '/inventory/items/categories', token, cookies)
    expect(categoriesResponse.status).toBe(200)
    
    const locationsResponse = await apiGet(request, '/inventory/locations', token, cookies)
    expect(locationsResponse.status).toBe(200)
    
    const stockResponse = await apiGet(request, '/inventory/stock', token, cookies)
    expect(stockResponse.status).toBe(200)
  })

  test('VP can access approvals and wide view', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'vp@ogamierp.local',
      'VicePresident@1!'
    )
    
    // Should succeed
    const approvalsResponse = await apiGet(request, '/approvals/pending', token, cookies)
    expect(approvalsResponse.status).toBe(200)
    
    // Should succeed
    const procResponse = await apiGet(request, '/procurement/purchase-requests', token, cookies)
    expect(procResponse.status).toBe(200)
    
    // Should fail - VP doesn't have payroll.view_runs
    const payrollResponse = await apiGet(request, '/payroll/runs', token, cookies)
    expect(payrollResponse.status).toBe(403)
    
    // Should fail
    const bankingResponse = await apiGet(request, '/banking/accounts', token, cookies)
    expect(bankingResponse.status).toBe(403)
  })

  test('Admin has limited module access', async ({ request }) => {
    const { token, cookies } = await apiLogin(
      request,
      'admin@ogamierp.local',
      'Admin@1234567890!'
    )
    
    // Should succeed - Admin can access system settings
    const usersResponse = await apiGet(request, '/admin/users', token, cookies)
    expect(usersResponse.status).toBe(200)
    
    // Should fail - Admin doesn't have business module permissions
    const hrResponse = await apiGet(request, '/hr/employees', token, cookies)
    expect(hrResponse.status).toBe(403)
    
    const payrollResponse = await apiGet(request, '/payroll/runs', token, cookies)
    expect(payrollResponse.status).toBe(403)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PAGINATION AND FILTERING API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📄 API - Pagination and Filtering', () => {
  let token: string
  let cookies: string[]
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'hr.manager@ogamierp.local', 'Manager@Test1234!')
    token = login.token
    cookies = login.cookies
  })

  test('Employee list pagination', async ({ request }) => {
    const response = await apiGet(request, '/hr/employees?page=1&per_page=5', token, cookies)
    
    expect(response.status).toBe(200)
    expect(response.data.data.length).toBeLessThanOrEqual(5)
    expect(response.data.meta.per_page).toBe(5)
    expect(response.data.meta.current_page).toBe(1)
  })

  test('Employee search filtering', async ({ request }) => {
    const response = await apiGet(request, '/hr/employees?search=manager', token, cookies)
    
    expect(response.status).toBe(200)
    
    // All results should match search
    for (const employee of response.data.data) {
      const searchableText = `${employee.first_name} ${employee.last_name} ${employee.email}`.toLowerCase()
      expect(searchableText).toContain('manager')
    }
  })

  test('Department filter', async ({ request }) => {
    const response = await apiGet(request, '/hr/employees?department_id=1', token, cookies)
    
    expect(response.status).toBe(200)
    
    // All results should be from HR department
    for (const employee of response.data.data) {
      expect(employee.department_id).toBe(1)
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// ERROR HANDLING API TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('⚠️ API - Error Handling', () => {
  let token: string
  let cookies: string[]
  
  test.beforeAll(async ({ request }) => {
    const login = await apiLogin(request, 'hr.manager@ogamierp.local', 'Manager@Test1234!')
    token = login.token
    cookies = login.cookies
  })

  test('Validation error - missing required fields', async ({ request }) => {
    const response = await apiPost(request, '/hr/employees', {
      // Missing required fields
      first_name: 'Test'
    }, token, cookies)
    
    expect(response.status).toBe(422)
    expect(response.data).toHaveProperty('errors')
    expect(response.data.errors).toHaveProperty('last_name')
    expect(response.data.errors).toHaveProperty('email')
  })

  test('Validation error - invalid email format', async ({ request }) => {
    const response = await apiPost(request, '/hr/employees', {
      first_name: 'Test',
      last_name: 'User',
      email: 'invalid-email',
      department_id: 1
    }, token, cookies)
    
    expect(response.status).toBe(422)
    expect(response.data.errors).toHaveProperty('email')
  })

  test('Not found error', async ({ request }) => {
    const response = await apiGet(request, '/hr/employees/999999', token, cookies)
    
    expect(response.status).toBe(404)
  })

  test('Unauthorized access', async ({ request }) => {
    const response = await apiGet(request, '/hr/employees', '', [])
    
    expect(response.status).toBe(401)
  })
})
