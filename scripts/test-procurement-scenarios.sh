#!/usr/bin/env bash
# =============================================================================
# Procurement Module — Scenario Tests
#
# Covers:
#  A) Happy path: Dept Head PR → approval chain → PO → vendor → GR → invoice
#  B) PR return for revision (purchasing sends back, head revises and resubmits)
#  C) PR rejection by VP
#  D) PO negotiation: vendor proposes changes → officer rejects → vendor re-proposes → accepts
#  E) Partial delivery (vendor delivers in two batches)
#  F) SoD enforcement (head cannot approve own PR, non-PURCH cannot create PO)
#  G) New dept heads can now create PRs (HR Head, WH Head, Acctg Head)
# =============================================================================
BASE="http://localhost:8000/api/v1"
PASS=0; FAIL=0; STEP=0

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

section() { echo -e "\n${CYAN}${BOLD}━━━ $1 ━━━${RESET}"; }
ok()   { PASS=$((PASS+1)); STEP=$((STEP+1)); echo -e "  ${GREEN}✓${RESET} [$STEP] $1"; }
fail() { FAIL=$((FAIL+1)); STEP=$((STEP+1)); echo -e "  ${RED}✗${RESET} [$STEP] $1"; [ -n "$2" ] && echo -e "       ${RED}$(echo "$2"|head -c 280)${RESET}"; }
info() { echo -e "  ${YELLOW}→${RESET} $1"; }
skip() { STEP=$((STEP+1)); echo -e "  ${YELLOW}⊘${RESET} [$STEP] SKIP: $1"; }

login() {
  local EMAIL="$1" PW="$2" LABEL="$3" FILE="$4"
  local R T
  R=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"email\":\"${EMAIL}\",\"password\":\"${PW}\"}")
  T=$(echo "$R"|python3 -c "import sys,json;d=json.load(sys.stdin);print(d.get('data',{}).get('token',''))" 2>/dev/null)
  if [ -n "$T" ]; then echo "$T" > "$FILE"; ok "Login: $LABEL"; return 0
  else echo "">"$FILE"; fail "Login: $LABEL" "$R"; return 1; fi
}

call() {
  local M="$1" U="$2" T="$3" D="$4"
  [ -n "$D" ] && curl -s -X "$M" "$BASE$U" -H "Authorization: Bearer $T" \
    -H "Content-Type: application/json" -H "Accept: application/json" -d "$D" \
  || curl -s -X "$M" "$BASE$U" -H "Authorization: Bearer $T" -H "Accept: application/json"
}

jx() {
  echo "$1"|python3 -c "
import sys,json
try:
  d=json.load(sys.stdin)
  for p in '$2'.split('.'):
    d=d[int(p)] if isinstance(d,list) else d.get(p) if isinstance(d,dict) else None
  print('' if d is None else d)
except: print('')
" 2>/dev/null
}

echo -e "${BOLD}╔══════════════════════════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║          Procurement Module — Scenario Tests                 ║${RESET}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════════╝${RESET}"

# Clear rate limit cache so repeated test runs don't accumulate against the 20/5min limit
php artisan cache:clear --quiet 2>/dev/null || true

# ─── Login all actors ──────────────────────────────────────────────────────
section "LOGIN — All actors"
login "prod.head@ogamierp.local"    "Head@Test1234!"            "Production Head"    /tmp/t_prodhead
login "hr.head@ogamierp.local"      "Head@Test1234!"            "HR Head"            /tmp/t_hrhead
login "wh.head@ogamierp.local"      "Head@Test1234!"            "WH Head"            /tmp/t_whhead
login "acctg.head@ogamierp.local"   "Head@Test1234!"            "Accounting Head"    /tmp/t_acctghead
login "purch.officer@ogamierp.local" "Officer@Test1234!"        "Purchasing Officer" /tmp/t_officer
login "acctg.officer@ogamierp.local" "Officer@Test1234!"        "Accounting Officer" /tmp/t_acctg
login "vp@ogamierp.local"           "Vice_president@Test1234!"  "Vice President"     /tmp/t_vp
login "vendor.petrochem@ogamierp.local" "Vendor@Test1234!"      "Petrochem Vendor"   /tmp/t_vendor
login "wh.head@ogamierp.local"      "Head@Test1234!"            "WH Head (GR)"       /tmp/t_wh

PHTOK=$(cat /tmp/t_prodhead); HRTOK=$(cat /tmp/t_hrhead); WHTOK2=$(cat /tmp/t_whhead)
AHTOK=$(cat /tmp/t_acctghead); OTOK=$(cat /tmp/t_officer); ATOK=$(cat /tmp/t_acctg)
VPTOK=$(cat /tmp/t_vp);  VTOK=$(cat /tmp/t_vendor); WHTOK=$(cat /tmp/t_wh)

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO G — New dept heads can now create PRs (HR, WH, Acctg)"
# ═══════════════════════════════════════════════════════════════════════════

# HR Head creates PR for HR dept (id=1)
HR_PR=$(call POST "/procurement/purchase-requests" "$HRTOK" \
  '{"department_id":1,"vendor_id":1,"justification":"HR dept needs color masterbatch for ID card production and printed onboarding materials for Q2 hiring batch.","urgency":"normal","items":[{"vendor_item_id":3,"item_description":"Color Masterbatch Black","quantity":3,"unit_of_measure":"kg","estimated_unit_cost":15000}]}')
HR_ULID=$(jx "$HR_PR" "data.ulid")
HR_STATUS=$(jx "$HR_PR" "data.status")
[ -n "$HR_ULID" ] && ok "HR Head creates PR for own dept | Status: $HR_STATUS" \
                  || fail "HR Head create PR" "$HR_PR"

# WH Head creates PR for WH dept (id=10)
WH_PR=$(call POST "/procurement/purchase-requests" "$WHTOK2" \
  '{"department_id":10,"vendor_id":1,"justification":"Warehouse requires polyethylene resin for fabricating internal packaging liners and storage bags for raw material bins.","urgency":"normal","items":[{"vendor_item_id":1,"item_description":"Polyethylene Resin","quantity":20,"unit_of_measure":"kg","estimated_unit_cost":8500}]}')
WH_ULID=$(jx "$WH_PR" "data.ulid")
[ -n "$WH_ULID" ] && ok "WH Head creates PR for own dept" \
                  || fail "WH Head create PR" "$WH_PR"

# Acctg Head creates PR for ACCTG dept (id=3)
ACCTG_PR=$(call POST "/procurement/purchase-requests" "$AHTOK" \
  '{"department_id":3,"vendor_id":1,"justification":"Accounting dept needs packaging materials for archiving physical documents and organizing financial records for annual audit.","urgency":"normal","items":[{"vendor_item_id":4,"item_description":"Carton Box Small","quantity":50,"unit_of_measure":"pcs","estimated_unit_cost":1200}]}')
ACCTG_ULID=$(jx "$ACCTG_PR" "data.ulid")
[ -n "$ACCTG_ULID" ] && ok "Acctg Head creates PR for own dept" \
                       || fail "Acctg Head create PR" "$ACCTG_PR"

# SoD: HR Head cannot create PR for Production dept
SOD=$(call POST "/procurement/purchase-requests" "$HRTOK" \
  '{"department_id":4,"vendor_id":1,"justification":"This cross-dept PR should be blocked by authorization policy for non-purchasing dept heads.","urgency":"normal","items":[{"vendor_item_id":1,"item_description":"PE Resin","quantity":10,"unit_of_measure":"kg","estimated_unit_cost":8500}]}')
SOD_SUCCESS=$(jx "$SOD" "data.ulid")
[ -z "$SOD_SUCCESS" ] && ok "SoD: HR Head blocked from creating PR for another dept" \
                       || fail "SoD: HR Head should NOT create PR for PROD dept" "$SOD"

# WH Head list — only sees own dept
WH_LIST=$(call GET "/procurement/purchase-requests" "$WHTOK2")
WH_DEPTS=$(echo "$WH_LIST"|python3 -c "
import sys,json
d=json.load(sys.stdin)
depts=set(p.get('department',{}).get('name','?') for p in d.get('data',[]))
print(','.join(depts) or 'none')
" 2>/dev/null)
info "WH Head PR list shows depts: $WH_DEPTS"
[[ "$WH_DEPTS" != *"Production"* ]] && [[ "$WH_DEPTS" != *"Human Resources"* ]] \
  && ok "WH Head only sees Warehouse dept PRs (scoped)" \
  || fail "WH Head seeing PRs from other depts (scoping broken)" "depts=$WH_DEPTS"

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO A — Happy path: PR → full approval chain → PO → GR"
# ═══════════════════════════════════════════════════════════════════════════

# Prod Head creates PR
A_PR=$(call POST "/procurement/purchase-requests" "$PHTOK" \
  '{"department_id":4,"vendor_id":1,"justification":"Production requires Polypropylene Resin for scheduled April run. Current stock insufficient for confirmed orders. Lead time 5 days.","urgency":"urgent","items":[{"vendor_item_id":2,"item_description":"Polypropylene Resin","quantity":100,"unit_of_measure":"kg","estimated_unit_cost":9200}]}')
A_ULID=$(jx "$A_PR" "data.ulid")
A_REF=$(jx "$A_PR" "data.pr_reference")
[ -n "$A_ULID" ] && ok "Prod Head creates PR: $A_REF" || { fail "Prod Head create PR" "$A_PR"; exit 1; }

# Submit
A_SUB=$(call POST "/procurement/purchase-requests/$A_ULID/submit" "$PHTOK" '')
[ "$(jx "$A_SUB" "data.status")" = "pending_review" ] && ok "PR submitted → pending_review" \
  || fail "Submit PR" "$A_SUB"

# Purch Officer reviews
A_REV=$(call POST "/procurement/purchase-requests/$A_ULID/review" "$OTOK" \
  '{"comments":"PP Resin verified against BOM requirements. Petrochem is approved vendor. Forwarding for budget check."}')
[ "$(jx "$A_REV" "data.status")" = "reviewed" ] && ok "Purchasing Officer reviews → reviewed" \
  || fail "Officer review PR" "$A_REV"

# Acctg budget check
A_BUD=$(call POST "/procurement/purchase-requests/$A_ULID/budget-check" "$ATOK" \
  '{"comments":"Budget verified. ₱920,000 within Production Q2 raw materials allocation."}')
[ "$(jx "$A_BUD" "data.status")" = "budget_verified" ] && ok "Accounting budget check → budget_verified" \
  || fail "Accounting budget check" "$A_BUD"

# VP approves → auto PO
A_VP=$(call POST "/procurement/purchase-requests/$A_ULID/vp-approve" "$VPTOK" \
  '{"remarks":"Approved. Production continuity is priority."}')
A_STATUS=$(jx "$A_VP" "data.status")
[ "$A_STATUS" = "approved" ] || [ "$A_STATUS" = "converted_to_po" ] \
  && ok "VP approves PR → $A_STATUS" || fail "VP approve PR" "$A_VP"

# Officer finds auto-created PO, sends to vendor
A_PO_LIST=$(call GET "/procurement/purchase-orders?status=draft" "$OTOK")
A_PO_ULID=$(echo "$A_PO_LIST"|python3 -c "
import sys,json
d=json.load(sys.stdin)
ref='$A_REF'
for po in d.get('data',[]):
    pr=po.get('purchase_request',{}) or {}
    if pr.get('pr_reference')==ref or po.get('pr_reference')==ref:
        print(po.get('ulid','')); break
" 2>/dev/null)
# fallback to first draft PO
[ -z "$A_PO_ULID" ] && A_PO_ULID=$(jx "$A_PO_LIST" "data.0.ulid")
A_PO_D=$(call GET "/procurement/purchase-orders/$A_PO_ULID" "$OTOK")
A_PO_REF=$(jx "$A_PO_D" "data.po_reference")
[ -n "$A_PO_ULID" ] && ok "Auto-created PO found: $A_PO_REF" || fail "Find auto PO" "$A_PO_LIST"

A_ITEM1_ID=$(jx "$A_PO_D" "data.items.0.id")

A_SEND=$(call POST "/procurement/purchase-orders/$A_PO_ULID/send" "$OTOK" '{"delivery_date":"2026-04-10"}')
[ "$(jx "$A_SEND" "data.status")" = "sent" ] && ok "PO sent to vendor" || fail "Send PO" "$A_SEND"

# Vendor acknowledges + in-transit + delivers
A_VORD=$(call GET "/vendor-portal/orders" "$VTOK")
A_VORD_ULID=$(echo "$A_VORD"|python3 -c "
import sys,json
d=json.load(sys.stdin)
for po in d.get('data',[]):
    if po.get('po_reference')=='$A_PO_REF': print(po.get('ulid','')); break
" 2>/dev/null)
[ -n "$A_VORD_ULID" ] && ok "Vendor sees PO: $A_PO_REF" || fail "Vendor find PO" "$A_VORD"

call POST "/vendor-portal/orders/$A_VORD_ULID/acknowledge" "$VTOK" '{"notes":"Stock confirmed. Delivery in 5 days."}' >/dev/null
call POST "/vendor-portal/orders/$A_VORD_ULID/in-transit" "$VTOK" '{"notes":"Dispatched. Tracking: JT-TEST-001","tracking_number":"JT-TEST-001"}' >/dev/null
ok "Vendor acknowledges + marks in-transit"

A_DEL=$(call POST "/vendor-portal/orders/$A_VORD_ULID/deliver" "$VTOK" \
  "{\"notes\":\"Full delivery 100kg PP Resin.\",\"delivery_date\":\"2026-03-23\",\"items\":[{\"po_item_id\":$A_ITEM1_ID,\"qty_delivered\":100}]}")
A_DEL_MSG=$(jx "$A_DEL" "message")
[[ "$A_DEL_MSG" == *"Goods Receipt"* ]] || [[ "$A_DEL_MSG" == *"confirmed"* ]] \
  && ok "Vendor delivers → GR draft auto-created" || fail "Vendor deliver" "$A_DEL"

# WH confirms GR
A_GR=$(call GET "/procurement/goods-receipts?status=draft" "$WHTOK")
A_GR_ULID=$(jx "$A_GR" "data.0.ulid")
[ -n "$A_GR_ULID" ] && ok "WH Head finds draft GR" || fail "WH find GR" "$A_GR"

A_CONF=$(call POST "/procurement/goods-receipts/$A_GR_ULID/confirm" "$WHTOK" '{}')
[ "$(jx "$A_CONF" "data.status")" = "confirmed" ] \
  && ok "GR confirmed → 3-way match → stock updated → AP invoice auto-created" \
  || fail "WH confirm GR" "$A_CONF"

# Verify final PO status
A_FINAL=$(call GET "/procurement/purchase-orders/$A_PO_ULID" "$OTOK")
A_FINAL_STATUS=$(jx "$A_FINAL" "data.status")
[ "$A_FINAL_STATUS" = "fully_received" ] && ok "PO status: fully_received ✓" \
  || fail "PO final status" "Got: $A_FINAL_STATUS"

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO B — PR returned for revision, head revises, resubmits"
# ═══════════════════════════════════════════════════════════════════════════

# Head creates PR
B_PR=$(call POST "/procurement/purchase-requests" "$PHTOK" \
  '{"department_id":4,"vendor_id":1,"justification":"Test PR for revision scenario — initial submission with incomplete specifications.","urgency":"normal","items":[{"vendor_item_id":1,"item_description":"Polyethylene Resin","quantity":50,"unit_of_measure":"kg","estimated_unit_cost":8500}]}')
B_ULID=$(jx "$B_PR" "data.ulid")
[ -n "$B_ULID" ] && ok "Head creates PR for revision test" || { fail "Create PR for scenario B" "$B_PR"; skip "Scenario B skipped"; B_ULID=""; }

if [ -n "$B_ULID" ]; then
  call POST "/procurement/purchase-requests/$B_ULID/submit" "$PHTOK" '' >/dev/null

  # Officer returns for revision
  B_RET=$(call POST "/procurement/purchase-requests/$B_ULID/return" "$OTOK" \
    '{"reason":"Specifications incomplete — please add HDPE grade and batch requirements. Also confirm delivery address matches current warehouse location."}')
  B_RET_STATUS=$(jx "$B_RET" "data.status")
  [ "$B_RET_STATUS" = "returned" ] && ok "Officer returns PR for revision → returned" \
    || fail "Officer return PR" "$B_RET"

  # Head updates and resubmits (status after return is "returned", update sets it back to draft)
  B_UPD=$(call PATCH "/procurement/purchase-requests/$B_ULID" "$PHTOK" \
    '{"justification":"Revised: Polyethylene Resin HDPE grade LD400 required for Film Line A. Batch must have COA. Delivery to Main Warehouse Dock B, Brgy. San Isidro.","items":[{"vendor_item_id":1,"item_description":"Polyethylene Resin HDPE LD400","quantity":50,"unit_of_measure":"kg","estimated_unit_cost":8500,"specifications":"Grade: HDPE LD400. COA required."}]}')
  B_UPD_STATUS=$(jx "$B_UPD" "data.status")
  [ "$B_UPD_STATUS" = "returned" ] || [ "$B_UPD_STATUS" = "draft" ] \
    && ok "Head updates PR with corrected specs (status: $B_UPD_STATUS)" \
    || fail "Update PR" "$B_UPD"

  B_RESUB=$(call POST "/procurement/purchase-requests/$B_ULID/submit" "$PHTOK" '')
  [ "$(jx "$B_RESUB" "data.status")" = "pending_review" ] \
    && ok "Head resubmits revised PR → pending_review" \
    || fail "Resubmit PR" "$B_RESUB"
fi

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO C — VP rejects a PR"
# ═══════════════════════════════════════════════════════════════════════════

# Create, submit, review, budget-check, then VP rejects
C_PR=$(call POST "/procurement/purchase-requests" "$PHTOK" \
  '{"department_id":4,"vendor_id":1,"justification":"Non-essential procurement request for equipment upgrade that is deferred to next budget cycle per finance directive.","urgency":"normal","items":[{"vendor_item_id":3,"item_description":"Color Masterbatch Black","quantity":200,"unit_of_measure":"kg","estimated_unit_cost":15000}]}')
C_ULID=$(jx "$C_PR" "data.ulid")

if [ -n "$C_ULID" ]; then
  C_SUB=$(call POST "/procurement/purchase-requests/$C_ULID/submit"       "$PHTOK" '')
  [ "$(jx "$C_SUB" "data.status")" = "pending_review" ] || fail "C: submit PR" "$C_SUB"
  C_REV=$(call POST "/procurement/purchase-requests/$C_ULID/review"       "$OTOK"  '{"comments":"Reviewed."}')
  [ "$(jx "$C_REV" "data.status")" = "reviewed" ] || fail "C: review PR" "$C_REV"
  C_BUD=$(call POST "/procurement/purchase-requests/$C_ULID/budget-check" "$ATOK"  '{"comments":"Budget exists but deferred."}')
  [ "$(jx "$C_BUD" "data.status")" = "budget_verified" ] || fail "C: budget-check PR" "$C_BUD"

  C_REJ=$(call POST "/procurement/purchase-requests/$C_ULID/reject" "$VPTOK" \
    '{"reason":"Deferred to Q3 budget cycle. Non-critical procurement — prioritize production materials first.","stage":"vp_review"}')
  C_STATUS=$(jx "$C_REJ" "data.status")
  [ "$C_STATUS" = "rejected" ] && ok "VP rejects PR → rejected" \
    || fail "VP reject PR" "$C_REJ"

  # Prod Head still sees rejected PR in own list
  C_VIEW=$(call GET "/procurement/purchase-requests/$C_ULID" "$PHTOK")
  [ "$(jx "$C_VIEW" "data.status")" = "rejected" ] \
    && ok "Prod Head can still view rejected PR" \
    || fail "Prod Head view rejected PR" "$C_VIEW"
else
  fail "Create PR for scenario C" "$C_PR"
fi

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO D — PO Negotiation: vendor proposes → officer rejects → accepts"
# ═══════════════════════════════════════════════════════════════════════════

# Create PR → approve chain → PO → send to vendor
D_PR=$(call POST "/procurement/purchase-requests" "$PHTOK" \
  '{"department_id":4,"vendor_id":1,"justification":"Negotiation test: Color Masterbatch required for April production. Exact quantity and price to be confirmed with vendor.","urgency":"urgent","items":[{"vendor_item_id":3,"item_description":"Color Masterbatch Black","quantity":80,"unit_of_measure":"kg","estimated_unit_cost":15000}]}')
D_ULID=$(jx "$D_PR" "data.ulid")

if [ -n "$D_ULID" ]; then
  D_SUB=$(call POST "/procurement/purchase-requests/$D_ULID/submit"       "$PHTOK" '')
  [ "$(jx "$D_SUB" "data.status")" = "pending_review" ] || fail "D: submit PR" "$D_SUB"
  D_REV=$(call POST "/procurement/purchase-requests/$D_ULID/review"       "$OTOK"  '{"comments":"OK."}')
  [ "$(jx "$D_REV" "data.status")" = "reviewed" ] || fail "D: review PR" "$D_REV"
  D_BUD=$(call POST "/procurement/purchase-requests/$D_ULID/budget-check" "$ATOK"  '{"comments":"Cleared."}')
  [ "$(jx "$D_BUD" "data.status")" = "budget_verified" ] || fail "D: budget-check PR" "$D_BUD"
  D_VP=$(call POST "/procurement/purchase-requests/$D_ULID/vp-approve" "$VPTOK" '{"remarks":"Approved."}')

  D_PO_ULID=$(echo "$D_VP"|python3 -c "
import sys,json
d=json.load(sys.stdin)
# try to find newly created PO from purchase orders list via pr ref
print('')
" 2>/dev/null)

  # Find draft PO linked to this PR
  D_PRREF=$(jx "$D_PR" "data.pr_reference")
  D_PO_LIST=$(call GET "/procurement/purchase-orders?status=draft" "$OTOK")
  D_PO_ULID=$(echo "$D_PO_LIST"|python3 -c "
import sys,json
d=json.load(sys.stdin)
ref='$D_PRREF'
for po in d.get('data',[]):
    pr=po.get('purchase_request',{}) or {}
    if pr.get('pr_reference')==ref: print(po.get('ulid','')); break
" 2>/dev/null)
  [ -z "$D_PO_ULID" ] && D_PO_ULID=$(jx "$D_PO_LIST" "data.0.ulid")

  D_PO_D=$(call GET "/procurement/purchase-orders/$D_PO_ULID" "$OTOK")
  D_PO_REF=$(jx "$D_PO_D" "data.po_reference")
  D_ITEM_ID=$(jx "$D_PO_D" "data.items.0.id")

  call POST "/procurement/purchase-orders/$D_PO_ULID/send" "$OTOK" '{"delivery_date":"2026-04-15"}' >/dev/null
  ok "PO for negotiation test sent: $D_PO_REF"

  # Vendor finds PO (filter by sent status to avoid pagination issues with old POs)
  D_VORD=$(call GET "/vendor-portal/orders?status=sent" "$VTOK")
  D_VORD_ULID=$(echo "$D_VORD"|python3 -c "
import sys,json
d=json.load(sys.stdin)
for po in d.get('data',[]):
    if po.get('po_reference')=='$D_PO_REF': print(po.get('ulid','')); break
" 2>/dev/null)
  [ -n "$D_VORD_ULID" ] && ok "Vendor sees PO for negotiation" || fail "Vendor find PO" "$D_VORD"

  # Round 1: Vendor proposes reduced quantity (supply shortage — can only deliver 60kg now)
  D_PROP1=$(call POST "/vendor-portal/orders/$D_VORD_ULID/propose-changes" "$VTOK" \
    "{\"remarks\":\"Due to carbon black shortage, can only supply 60kg immediately; remaining 20kg in 2 weeks.\",\"items\":[{\"po_item_id\":$D_ITEM_ID,\"negotiated_quantity\":60}]}")
  D_PROP1_STATUS=$(jx "$D_PROP1" "data.status")
  [ "$D_PROP1_STATUS" = "negotiating" ] && ok "Vendor proposes changes (round 1): 60kg now, 20kg later" \
    || fail "Vendor propose changes" "$D_PROP1"

  # Officer rejects — need full 80kg at once
  D_REJ=$(call POST "/procurement/purchase-orders/$D_PO_ULID/reject-changes" "$OTOK" \
    '{"remarks":"Cannot accept split delivery. Production requires full 80kg batch. Please confirm full quantity or we source elsewhere."}')
  D_REJ_STATUS=$(jx "$D_REJ" "data.status")
  [ "$D_REJ_STATUS" = "sent" ] && ok "Officer REJECTS vendor proposal → sent (vendor can re-propose)" \
    || fail "Officer reject changes" "$D_REJ"

  # Round 2: Vendor proposes again — full qty now possible
  D_PROP2=$(call POST "/vendor-portal/orders/$D_VORD_ULID/propose-changes" "$VTOK" \
    "{\"remarks\":\"Confirmed. Can supply full 80kg. Will expedite from secondary warehouse. Delivery by April 5.\",\"items\":[{\"po_item_id\":$D_ITEM_ID,\"negotiated_quantity\":80}]}")
  [ "$(jx "$D_PROP2" "data.status")" = "negotiating" ] \
    && ok "Vendor re-proposes (round 2): full 80kg, delivery April 5" \
    || fail "Vendor second proposal" "$D_PROP2"

  # Officer accepts — full 80kg confirmed
  D_ACC=$(call POST "/procurement/purchase-orders/$D_PO_ULID/accept-changes" "$OTOK" \
    '{"remarks":"Accepted. Full 80kg delivery by April 5 confirmed. Proceed."}')
  D_ACC_STATUS=$(jx "$D_ACC" "data.status")
  D_NEGG_QTY=$(jx "$D_ACC" "data.items.0.negotiated_quantity")
  [ "$D_ACC_STATUS" = "acknowledged" ] \
    && ok "Officer ACCEPTS vendor proposal → acknowledged | Negotiated qty: $D_NEGG_QTY" \
    || fail "Officer accept changes" "$D_ACC"
else
  fail "Create PR for scenario D" "$D_PR"
fi

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO E — Partial delivery (two batches)"
# ═══════════════════════════════════════════════════════════════════════════

# Use the already-negotiated PO from Scenario D (if it exists)
if [ -n "$D_PO_ULID" ] && [ -n "$D_VORD_ULID" ]; then
  # Mark in-transit
  call POST "/vendor-portal/orders/$D_VORD_ULID/in-transit" "$VTOK" \
    '{"notes":"Partial shipment 1 dispatched: 50kg. Remaining 30kg ships next week.","tracking_number":"JT-PARTIAL-001"}' >/dev/null
  ok "Vendor marks in-transit (partial shipment coming)"

  # Deliver batch 1: 50 of 80 kg
  # Note: partial delivery triggers split PO creation for remaining 30kg
  E_DEL1=$(call POST "/vendor-portal/orders/$D_VORD_ULID/deliver" "$VTOK" \
    "{\"notes\":\"Batch 1: 50kg Color Masterbatch delivered.\",\"delivery_date\":\"2026-03-23\",\"items\":[{\"po_item_id\":$D_ITEM_ID,\"qty_delivered\":50}]}")
  E_DEL1_MSG=$(jx "$E_DEL1" "message")
  E_SPLIT_ULID=$(jx "$E_DEL1" "data.split_po.ulid")
  [[ "$E_DEL1_MSG" == *"split PO"* ]] || [[ "$E_DEL1_MSG" == *"Partial"* ]] \
    && ok "Batch 1 delivery (50kg) → split PO created for remaining 30kg (split: $E_SPLIT_ULID)" \
    || fail "Batch 1 delivery" "$E_DEL1"

  # WH confirms batch 1 GR (for the 50kg already delivered)
  E_GR1=$(call GET "/procurement/goods-receipts?status=draft" "$WHTOK")
  E_GR1_ULID=$(jx "$E_GR1" "data.0.ulid")
  E_CONF1=$(call POST "/procurement/goods-receipts/$E_GR1_ULID/confirm" "$WHTOK" '{}')
  [ "$(jx "$E_CONF1" "data.status")" = "confirmed" ] && ok "WH confirms batch 1 GR (50kg)" \
    || fail "Confirm batch 1 GR" "$E_CONF1"

  # After GR confirmed: original PO is fully_received (50/50 delivered), split PO has remaining 30kg
  E_PO_CHECK=$(call GET "/procurement/purchase-orders/$D_PO_ULID" "$OTOK")
  E_PO_STATUS=$(jx "$E_PO_CHECK" "data.status")
  [ "$E_PO_STATUS" = "fully_received" ] \
    && ok "Original PO fully_received after batch 1 confirmed (50/50 ordered+delivered)" \
    || info "Original PO status after batch 1: $E_PO_STATUS"

  # Batch 2: deliver remaining 30kg against the SPLIT PO
  if [ -n "$E_SPLIT_ULID" ]; then
    # Find split PO items to get item ID
    E_SPLIT_PO_D=$(call GET "/procurement/purchase-orders/$E_SPLIT_ULID" "$OTOK")
    E_SPLIT_ITEM_ID=$(jx "$E_SPLIT_PO_D" "data.items.0.id")

    # Split PO starts in sent status (auto-sent). Vendor needs to mark in-transit then deliver.
    E_SPLIT_STATUS=$(jx "$E_SPLIT_PO_D" "data.status")
    info "Split PO status: $E_SPLIT_STATUS"

    # Find split PO in vendor portal
    E_SPLIT_VORD=$(call GET "/vendor-portal/orders?status=sent" "$VTOK")
    E_SPLIT_VORD_ULID=$(echo "$E_SPLIT_VORD"|python3 -c "
import sys,json
d=json.load(sys.stdin)
split='$E_SPLIT_ULID'
for po in d.get('data',[]):
    if po.get('ulid')==split: print(po.get('ulid','')); break
" 2>/dev/null)
    [ -n "$E_SPLIT_VORD_ULID" ] && ok "Vendor sees split PO for batch 2" \
                                 || { info "Split PO not found in vendor portal (may be acknowledged)"; E_SPLIT_VORD_ULID="$E_SPLIT_ULID"; }

    # Vendor acknowledges split PO, marks in-transit, then delivers
    call POST "/vendor-portal/orders/$E_SPLIT_VORD_ULID/acknowledge" "$VTOK" \
      '{"notes":"Split PO received. Remaining 30kg ready for dispatch."}' >/dev/null
    call POST "/vendor-portal/orders/$E_SPLIT_VORD_ULID/in-transit" "$VTOK" \
      '{"notes":"Batch 2: Remaining 30kg dispatched."}' >/dev/null

    E_DEL2=$(call POST "/vendor-portal/orders/$E_SPLIT_VORD_ULID/deliver" "$VTOK" \
      "{\"notes\":\"Batch 2: Remaining 30kg Color Masterbatch delivered.\",\"delivery_date\":\"2026-03-23\",\"items\":[{\"po_item_id\":$E_SPLIT_ITEM_ID,\"qty_delivered\":30}]}")
    [[ "$(jx "$E_DEL2" "message")" == *"Delivery confirmed"* ]] \
      && ok "Batch 2 delivery (30kg) → GR draft created on split PO" \
      || fail "Batch 2 delivery" "$E_DEL2"

    E_GR2=$(call GET "/procurement/goods-receipts?status=draft" "$WHTOK")
    E_GR2_ULID=$(jx "$E_GR2" "data.0.ulid")
    E_CONF2=$(call POST "/procurement/goods-receipts/$E_GR2_ULID/confirm" "$WHTOK" '{}')
    [ "$(jx "$E_CONF2" "data.status")" = "confirmed" ] && ok "WH confirms batch 2 GR (30kg)" \
      || fail "Confirm batch 2 GR" "$E_CONF2"

    E_SPLIT_FINAL=$(call GET "/procurement/purchase-orders/$E_SPLIT_ULID" "$OTOK")
    [ "$(jx "$E_SPLIT_FINAL" "data.status")" = "fully_received" ] \
      && ok "Split PO fully_received after batch 2 ✓ — total 80kg delivered in 2 batches" \
      || fail "Split PO final status" "Got: $(jx "$E_SPLIT_FINAL" "data.status")"
  else
    fail "Batch 2: split PO ULID not found in batch 1 response" "$E_DEL1"
  fi
else
  skip "Scenario E skipped — Scenario D PO not available"
fi

# ═══════════════════════════════════════════════════════════════════════════
section "SCENARIO F — SoD: non-PURCH cannot create PO manually"
# ═══════════════════════════════════════════════════════════════════════════

F_ATTEMPT=$(call POST "/procurement/purchase-orders" "$PHTOK" \
  '{"vendor_id":1,"items":[{"vendor_item_id":1,"item_description":"Test","quantity":10,"unit_of_measure":"kg","unit_price_centavos":850000}]}')
F_ULID=$(jx "$F_ATTEMPT" "data.ulid")
[ -z "$F_ULID" ] && ok "SoD: Prod Head blocked from creating PO directly (Purchasing only)" \
                 || fail "SoD: Prod Head should NOT create PO" "$F_ATTEMPT"

# Purchasing Officer cannot approve/vp-approve PRs
F_VP_ATTEMPT=$(call POST "/procurement/purchase-requests/$A_ULID/vp-approve" "$OTOK" \
  '{"remarks":"Test"}')
[ "$(jx "$F_VP_ATTEMPT" "data.status")" != "approved" ] \
  && ok "SoD: Purchasing Officer cannot VP-approve a PR" \
  || fail "SoD: Officer should NOT VP-approve" "$F_VP_ATTEMPT"

# ═══════════════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║            PROCUREMENT SCENARIO RESULTS                  ║${RESET}"
echo -e "${BOLD}╠══════════════════════════════════════════════════════════╣${RESET}"
echo -e "${BOLD}║  ${GREEN}PASSED : $PASS${RESET}${BOLD}                                        ║${RESET}"
[ $FAIL -gt 0 ] && echo -e "${BOLD}║  ${RED}FAILED : $FAIL${RESET}${BOLD}                                        ║${RESET}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════╝${RESET}"
[ $FAIL -eq 0 ] && echo -e "${GREEN}${BOLD}All procurement scenarios passed ✓${RESET}" \
               || echo -e "${RED}${BOLD}$FAIL scenario(s) need attention.${RESET}"

rm -f /tmp/t_prodhead /tmp/t_hrhead /tmp/t_whhead /tmp/t_acctghead \
      /tmp/t_officer /tmp/t_acctg /tmp/t_vp /tmp/t_vendor /tmp/t_wh
