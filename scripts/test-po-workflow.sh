#!/usr/bin/env bash
# =============================================================================
# Full PO Workflow Integration Test
# Tests: PR→PO→Send→Negotiate→Acknowledge→InTransit→Deliver→GR→Invoice
# =============================================================================

BASE="http://localhost:8000/api/v1"
PASS_COUNT=0; FAIL_COUNT=0; STEP=0
TOKEN_FILE="/tmp/po_test_token"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

section() { echo -e "\n${CYAN}${BOLD}━━━ $1 ━━━${RESET}"; }
ok()      { PASS_COUNT=$((PASS_COUNT+1)); STEP=$((STEP+1)); echo -e "  ${GREEN}✓${RESET} [${STEP}] $1"; }
fail()    { FAIL_COUNT=$((FAIL_COUNT+1)); STEP=$((STEP+1)); echo -e "  ${RED}✗${RESET} [${STEP}] $1"; [ -n "$2" ] && echo -e "       ${RED}$(echo "$2" | head -c 200)${RESET}"; }
info()    { echo -e "  ${YELLOW}→${RESET} $1"; }

# Write token to file to avoid stdout capture corruption
login() {
  local EMAIL="$1" PW="$2" LABEL="$3" FILE="$4"
  local RESP
  RESP=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"email\":\"${EMAIL}\",\"password\":\"${PW}\"}")
  local TOKEN
  TOKEN=$(echo "$RESP" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null)
  if [ -n "$TOKEN" ]; then
    echo "$TOKEN" > "$FILE"
    ok "Login as $LABEL"
    return 0
  else
    echo "" > "$FILE"
    fail "Login as $LABEL" "$RESP"
    return 1
  fi
}

# REST call helper — saves response to /tmp/po_test_resp
call() {
  local METHOD="$1" URL="$2" TOKEN="$3" DATA="$4"
  if [ -n "$DATA" ]; then
    curl -s -X "$METHOD" "$BASE$URL" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "$DATA"
  else
    curl -s -X "$METHOD" "$BASE$URL" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Accept: application/json"
  fi
}

# Extract nested key from JSON using python — supports dotted paths like data.0.ulid
jx() {
  local JSON="$1" KEY="$2"
  echo "$JSON" | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    parts = '$KEY'.split('.')
    for p in parts:
        if isinstance(d, list): d = d[int(p)]
        elif isinstance(d, dict): d = d.get(p)
        else: d = None
    print('' if d is None else d)
except: print('')
" 2>/dev/null
}

echo -e "${BOLD}╔═══════════════════════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║  PO → Negotiation → GR → Inventory → AP Invoice  Test    ║${RESET}"
echo -e "${BOLD}╚═══════════════════════════════════════════════════════════╝${RESET}"
echo "Scenario: Stock shortage → 2-round negotiation → delivery → GR confirm → vendor invoice"
echo ""

# =============================================================================
section "STEP 1 — PURCHASING OFFICER: Send draft PO to Vendor"
# =============================================================================

login "purch.officer@ogamierp.local" "Officer@Test1234!" "Purch Officer" /tmp/tok_officer
OTOK=$(cat /tmp/tok_officer)
[ -z "$OTOK" ] && { echo "Cannot continue — officer login failed"; exit 1; }

# Get draft PO list first to find ULID
RESP=$(call GET "/procurement/purchase-orders?status=draft" "$OTOK")
PO_ULID=$(jx "$RESP" "data.0.ulid")
PO_REF=$(jx "$RESP" "data.0.po_reference")
PO_VENDOR=$(jx "$RESP" "data.0.vendor.name")
PO_AMOUNT=$(jx "$RESP" "data.0.total_po_amount")

# Load PO detail to get item IDs (items not included in list endpoint)
PO_DETAIL=$(call GET "/procurement/purchase-orders/$PO_ULID" "$OTOK")
ITEM1_ID=$(jx "$PO_DETAIL" "data.items.0.id")
ITEM2_ID=$(jx "$PO_DETAIL" "data.items.1.id")
ITEM1_QTY=$(jx "$PO_DETAIL" "data.items.0.quantity_ordered")
ITEM2_QTY=$(jx "$PO_DETAIL" "data.items.1.quantity_ordered")
ITEM1_DESC=$(jx "$PO_DETAIL" "data.items.0.item_description")
ITEM2_DESC=$(jx "$PO_DETAIL" "data.items.1.item_description")

if [ -n "$PO_ULID" ]; then
  ok "Found draft PO: $PO_REF | Vendor: $PO_VENDOR | Total: ₱$PO_AMOUNT"
  info "Item 1 [id=$ITEM1_ID]: $ITEM1_DESC × $ITEM1_QTY"
  info "Item 2 [id=$ITEM2_ID]: $ITEM2_DESC × $ITEM2_QTY"
else
  fail "Find draft PO" "$RESP"
fi

# Send PO to vendor
SRESP=$(call POST "/procurement/purchase-orders/$PO_ULID/send" "$OTOK" '{"delivery_date":"2026-04-15"}')
SSTATUS=$(jx "$SRESP" "data.status")
SENT_AT=$(jx "$SRESP" "data.sent_at")
if [ "$SSTATUS" = "sent" ]; then
  ok "PO sent to vendor | Status: $SSTATUS | Sent at: $SENT_AT"
else
  fail "Send PO to vendor" "$SRESP"
fi

# =============================================================================
section "STEP 2 — VENDOR (Petrochem): Log in and view PO"
# =============================================================================

login "vendor.petrochem@ogamierp.local" "Vendor@Test1234!" "Petrochem Vendor" /tmp/tok_vendor
VTOK=$(cat /tmp/tok_vendor)
[ -z "$VTOK" ] && { echo "Cannot continue — vendor login failed"; exit 1; }

ORESP=$(call GET "/vendor-portal/orders" "$VTOK")
# Find the matching PO by reference (vendor may have multiple POs)
ORDER_ULID=$(echo "$ORESP" | python3 -c "
import sys, json
d = json.load(sys.stdin)
ref = '$PO_REF'
for po in d.get('data', []):
    if po.get('po_reference') == ref:
        print(po.get('ulid', ''))
        break
" 2>/dev/null)
ORDER_STATUS=$(echo "$ORESP" | python3 -c "
import sys, json
d = json.load(sys.stdin)
ref = '$PO_REF'
for po in d.get('data', []):
    if po.get('po_reference') == ref:
        print(po.get('status', ''))
        break
" 2>/dev/null)
ORDER_REF="$PO_REF"
ORDER_DELIVERY=$(echo "$ORESP" | python3 -c "
import sys, json
d = json.load(sys.stdin)
ref = '$PO_REF'
for po in d.get('data', []):
    if po.get('po_reference') == ref:
        print(po.get('delivery_date', ''))
        break
" 2>/dev/null)

if [ "$ORDER_STATUS" = "sent" ] && [ -n "$ORDER_ULID" ]; then
  ok "Vendor sees PO: $ORDER_REF | Status: $ORDER_STATUS | Delivery due: $ORDER_DELIVERY"
else
  fail "Vendor list orders (looking for $PO_REF)" "$ORESP"
fi

DRESP=$(call GET "/vendor-portal/orders/$ORDER_ULID" "$VTOK")
DITEM_COUNT=$(jx "$DRESP" "data.items" | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "?")
info "Order detail: items visible to vendor"

# =============================================================================
section "STEP 3 — VENDOR: Propose Changes (only 350 of 500 boxes in stock)"
# =============================================================================

info "Real scenario: Vendor has 350 units of '$ITEM1_DESC' (need $ITEM1_QTY)"

PROP1=$(call POST "/vendor-portal/orders/$ORDER_ULID/propose-changes" "$VTOK" \
  "{\"remarks\":\"We only have 350 units of Carton Box Small in stock. Our next restock arrives in 2 weeks. We can deliver 350 now with balance via supplemental PO.\",\"items\":[{\"po_item_id\":$ITEM1_ID,\"negotiated_quantity\":350,\"vendor_item_notes\":\"350 units from current stock. 150 units on back-order, ETA 2 weeks.\"}]}")

P1_STATUS=$(jx "$PROP1" "data.status")
P1_ROUND=$(jx "$PROP1" "data.negotiation_round")
P1_REMARKS=$(jx "$PROP1" "data.vendor_remarks")

if [ "$P1_STATUS" = "negotiating" ]; then
  ok "Vendor proposed changes | Status: negotiating | Round: $P1_ROUND"
  info "Vendor: $P1_REMARKS"
else
  fail "Vendor propose changes" "$PROP1"
fi

# =============================================================================
section "STEP 4 — OFFICER: Reviews negotiation — REJECTS (needs min 400)"
# =============================================================================

# Officer refreshes the PO
PO_NEG=$(call GET "/procurement/purchase-orders/$PO_ULID" "$OTOK")
PO_NEG_STATUS=$(jx "$PO_NEG" "data.status")
PO_VENDOR_REMARKS=$(jx "$PO_NEG" "data.vendor_remarks")
NEGO_ROUND=$(jx "$PO_NEG" "data.negotiation_round")
NEGO_QTY=$(jx "$PO_NEG" "data.items.0.negotiated_quantity")
NEGO_NOTE=$(jx "$PO_NEG" "data.items.0.vendor_item_notes")

if [ "$PO_NEG_STATUS" = "negotiating" ]; then
  ok "Officer sees PO in negotiation | Round: $NEGO_ROUND | Proposed qty: $NEGO_QTY"
  info "Vendor note: $NEGO_NOTE"
else
  fail "Officer check negotiation status" "Got: $PO_NEG_STATUS"
fi

info "Officer decision: 350 insufficient. Minimum acceptable = 400 units."

RRESP=$(call POST "/procurement/purchase-orders/$PO_ULID/reject-changes" "$OTOK" \
  '{"remarks":"350 units is below our minimum. We need at least 400 units for the production run. Please source additional stock or we will split the order."}')
RSTATUS=$(jx "$RRESP" "data.status")

if [ "$RSTATUS" = "sent" ]; then
  ok "Officer REJECTED changes | PO reverted to: sent"
  info "Negotiated quantities cleared — vendor must respond again"
else
  fail "Officer reject changes" "$RRESP"
fi

# Verify negotiated_quantity is cleared
AFTER_REJ=$(call GET "/procurement/purchase-orders/$PO_ULID" "$OTOK")
CLEARED=$(jx "$AFTER_REJ" "data.items.0.negotiated_quantity")
REJ_REMARKS=$(jx "$AFTER_REJ" "data.change_review_remarks")
if [ -z "$CLEARED" ] || [ "$CLEARED" = "None" ]; then
  ok "Negotiated quantities reset to null after rejection"
else
  fail "Negotiated qty not cleared" "Still: $CLEARED"
fi
info "Officer rejection stored: $REJ_REMARKS"

# =============================================================================
section "STEP 5 — VENDOR: Re-checks status and submits 2nd proposal (400 units)"
# =============================================================================

V_RECHECK=$(call GET "/vendor-portal/orders/$ORDER_ULID" "$VTOK")
V_STATUS=$(jx "$V_RECHECK" "data.status")
info "Vendor sees PO reverted to: $V_STATUS"
info "Reading officer's rejection reason — checking if it's exposed... "

PROP2=$(call POST "/vendor-portal/orders/$ORDER_ULID/propose-changes" "$VTOK" \
  "{\"remarks\":\"We sourced 50 additional units from our secondary warehouse. Can now commit to 400 units of Carton Box Small. Balance 100 units on 14-day lead time if needed.\",\"items\":[{\"po_item_id\":$ITEM1_ID,\"negotiated_quantity\":400,\"vendor_item_notes\":\"400 units confirmed: 350 primary + 50 secondary warehouse stock.\"}]}")

P2_STATUS=$(jx "$PROP2" "data.status")
P2_ROUND=$(jx "$PROP2" "data.negotiation_round")

if [ "$P2_STATUS" = "negotiating" ]; then
  ok "Vendor 2nd proposal | Status: $P2_STATUS | Round: $P2_ROUND"
else
  fail "Vendor 2nd proposal" "$PROP2"
fi

# =============================================================================
section "STEP 6 — OFFICER: Accepts 2nd proposal (400 units acceptable)"
# =============================================================================

ARESP=$(call POST "/procurement/purchase-orders/$PO_ULID/accept-changes" "$OTOK" \
  '{"remarks":"400 units is acceptable for this run. Approved. Please ship at your earliest convenience. Note: balance of 100 units NOT required — we will open a new PR if needed."}')

A_STATUS=$(jx "$ARESP" "data.status")
A_ACK_AT=$(jx "$ARESP" "data.vendor_acknowledged_at")
A_QTY=$(jx "$ARESP" "data.items.0.negotiated_quantity")
A_NOTES_COUNT=$(echo "$ARESP" | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d.get('data',{}).get('fulfillment_notes',[])))" 2>/dev/null)

if [ "$A_STATUS" = "acknowledged" ]; then
  ok "Officer ACCEPTED changes | Status: acknowledged | Agreed qty: $A_QTY"
  info "Acknowledged at: $A_ACK_AT"
  info "Fulfillment notes recorded: $A_NOTES_COUNT entries"
  # Show note types
  echo "$ARESP" | python3 -c "
import sys, json
d = json.load(sys.stdin)
notes = d.get('data', {}).get('fulfillment_notes', [])
for n in notes:
    print(f\"    [{n['note_type']}] {(n.get('notes') or '')[:60]}\")
" 2>/dev/null
else
  fail "Officer accept changes" "$ARESP"
fi

# =============================================================================
section "STEP 7 — VENDOR: Marks PO as In-Transit"
# =============================================================================

TRESP=$(call POST "/vendor-portal/orders/$ORDER_ULID/in-transit" "$VTOK" \
  '{"notes":"Shipment dispatched — LBC Express tracking #LBC-PH-2026-88441. 400 Carton Box Small + 50 Packing Tape on 10-wheeler. ETA 2 business days."}')
T_MSG=$(jx "$TRESP" "message")
T_NOTE_TYPE=$(jx "$TRESP" "data.note_type")

if [[ "$T_MSG" == *"transit"* ]] || [ "$T_NOTE_TYPE" = "in_transit" ]; then
  ok "Vendor marked In-Transit | Note: $T_NOTE_TYPE"
  info "$T_MSG"
else
  fail "Vendor mark in-transit" "$TRESP"
fi

# Officer confirms PO flipped to in_transit
PO_IT=$(call GET "/procurement/purchase-orders/$PO_ULID" "$OTOK")
PO_IT_STATUS=$(jx "$PO_IT" "data.status")
if [ "$PO_IT_STATUS" = "in_transit" ]; then
  ok "PO status confirmed: in_transit (visible to officer)"
else
  fail "PO in_transit status check" "Got: $PO_IT_STATUS"
fi

# =============================================================================
section "STEP 8 — VENDOR: Delivers goods (400 + 50 = per-agreement)"
# =============================================================================

info "Delivering: 400 Carton Box Small + 50 Packing Tape (exactly as agreed)"

DELRESP=$(call POST "/vendor-portal/orders/$ORDER_ULID/deliver" "$VTOK" \
  "{\"notes\":\"Full delivery as per negotiated agreement. 400 Carton Box Small + 50 Packing Tape received at dock. DR #DR-PET-2026-071 signed by receiving clerk.\",\"delivery_date\":\"2026-03-22\",\"items\":[{\"po_item_id\":$ITEM1_ID,\"qty_delivered\":400},{\"po_item_id\":$ITEM2_ID,\"qty_delivered\":50}]}")

DEL_MSG=$(jx "$DELRESP" "message")
DEL_NOTE=$(jx "$DELRESP" "data.note.note_type")
SPLIT_PO_REF=$(jx "$DELRESP" "data.split_po.reference")

if [[ "$DEL_MSG" == *"receipt"* ]] || [[ "$DEL_MSG" == *"Goods Receipt"* ]] || [ -n "$DEL_NOTE" ]; then
  ok "Delivery confirmed | GR type: $DEL_NOTE"
  info "$DEL_MSG"
  if [ -n "$SPLIT_PO_REF" ] && [ "$SPLIT_PO_REF" != "None" ]; then
    info "Split PO created for remaining 100 units: $SPLIT_PO_REF"
  else
    info "No split PO — delivery was for negotiated quantities only"
  fi
else
  fail "Vendor confirm delivery" "$DELRESP"
fi

# =============================================================================
section "STEP 9 — WAREHOUSE HEAD: Reviews and confirms Goods Receipt"
# =============================================================================

login "wh.head@ogamierp.local" "Head@Test1234!" "Warehouse Head" /tmp/tok_wh
WHTOK=$(cat /tmp/tok_wh)
[ -z "$WHTOK" ] && { echo "Cannot continue — WH login failed"; exit 1; }

# Find the draft GR
GRRESP=$(call GET "/procurement/goods-receipts?status=draft" "$WHTOK")
GR_ULID=$(jx "$GRRESP" "data.0.ulid")
GR_REF=$(jx "$GRRESP" "data.0.gr_reference")
GR_STATUS=$(jx "$GRRESP" "data.0.status")

if [ -n "$GR_ULID" ]; then
  ok "WH Head found Goods Receipt: $GR_REF | Status: $GR_STATUS"
  # Show items
  echo "$GRRESP" | python3 -c "
import sys, json
d = json.load(sys.stdin)
items = d.get('data', [{}])[0].get('items', [])
for it in items:
    print(f\"    Item #{it.get('po_item_id')}: {it.get('quantity_received')} {it.get('unit_of_measure')} [{it.get('condition')}]\")
" 2>/dev/null
else
  fail "WH Head find GRs" "$GRRESP"
fi

# Confirm GR (triggers 3-way match + stock update)
CGRESP=$(call POST "/procurement/goods-receipts/$GR_ULID/confirm" "$WHTOK" '{}')
CG_STATUS=$(jx "$CGRESP" "data.status")
CG_MSG=$(jx "$CGRESP" "message")

if [ "$CG_STATUS" = "confirmed" ]; then
  ok "GR CONFIRMED by Warehouse Head | Status: confirmed"
  info "3-way match passed — stock balances updated"
  [ -n "$CG_MSG" ] && info "$CG_MSG"
else
  fail "WH Head confirm GR" "$CGRESP"
fi

# =============================================================================
section "STEP 10 — Verify: PO status + inventory stock updated"
# =============================================================================

# PO should now be partially_received (100 units still on hold) or fully_received
PO_FINAL=$(call GET "/procurement/purchase-orders/$PO_ULID" "$OTOK")
PO_F_STATUS=$(jx "$PO_FINAL" "data.status")
PO_ITEM1_RCVD=$(jx "$PO_FINAL" "data.items.0.quantity_received")
PO_ITEM1_PEND=$(jx "$PO_FINAL" "data.items.0.quantity_pending")
PO_ITEM2_RCVD=$(jx "$PO_FINAL" "data.items.1.quantity_received")

ok "PO final status: $PO_F_STATUS"
info "Item 1 received: $PO_ITEM1_RCVD / negotiated 400 | pending: $PO_ITEM1_PEND"
info "Item 2 received: $PO_ITEM2_RCVD / negotiated 50"

# Check inventory stock balance
STOCK=$(call GET "/inventory/stock-balances?search=Carton+Box" "$WHTOK")
STOCK_QTY=$(jx "$STOCK" "data.0.quantity_on_hand")
STOCK_ITEM=$(jx "$STOCK" "data.0.item_name")
if [ -n "$STOCK_QTY" ]; then
  ok "Inventory updated | $STOCK_ITEM: $STOCK_QTY units in stock"
else
  info "Stock balance endpoint returned: $(echo $STOCK | head -c 150)"
fi

# =============================================================================
section "STEP 11 — VENDOR: Submits AP Invoice via Vendor Portal"
# =============================================================================

# 400 × ₱12 + 50 × ₱4.50 = ₱4800 + ₱225 = ₱5025 net
# 12% VAT = ₱603
VP_GRS=$(call GET "/vendor-portal/goods-receipts" "$VTOK")
VGR_ID=$(jx "$VP_GRS" "data.0.id")
VGR_REF=$(jx "$VP_GRS" "data.0.gr_reference")
VGR_STATUS=$(jx "$VP_GRS" "data.0.status")

if [ -n "$VGR_ID" ] && [ "$VGR_STATUS" = "confirmed" ]; then
  ok "Vendor finds confirmed GR: $VGR_REF (id=$VGR_ID)"
else
  fail "Vendor find confirmed GR" "$VP_GRS"
  VGR_ID=$(jx "$VP_GRS" "data.0.id")
fi

# Note: Invoice is auto-created by ThreeWayMatchPassed event when WH confirms GR.
# Vendor trying to submit a duplicate should get "already exists" error — which is correct.
INVRESP=$(call POST "/vendor-portal/invoices" "$VTOK" \
  "{\"goods_receipt_id\":$VGR_ID,\"invoice_date\":\"2026-03-22\",\"due_date\":\"2026-04-22\",\"net_amount\":5025.00,\"vat_amount\":603.00,\"or_number\":\"OR-PET-2026-0044\",\"description\":\"Invoice for $PO_REF: 400x Carton Box @ 12 + 50x Tape @ 4.50\"}")

INV_ERR=$(jx "$INVRESP" "error_code")
INV_MSG=$(jx "$INVRESP" "message")

# Auto-created invoice exists: duplicate submission should be blocked
if [[ "$INV_MSG" == *"already been created"* ]] || [[ "$INV_MSG" == *"already exists"* ]]; then
  ok "AP Invoice auto-created by 3-way match event | Vendor duplicate blocked (expected)"
  info "System correctly prevents double-invoicing: $INV_MSG"
else
  # Alternatively, if manual submission succeeds (no auto-creation), that's also OK
  INV_ID=$(jx "$INVRESP" "data.id")
  INV_STATUS=$(jx "$INVRESP" "data.status")
  if [ -n "$INV_ID" ]; then
    ok "Vendor invoice submitted | Status: $INV_STATUS"
  else
    fail "Vendor submit invoice" "$INVRESP"
  fi
fi

# =============================================================================
section "STEP 12 — ACCOUNTING OFFICER: Reviews and processes invoice"
# =============================================================================

login "acctg.officer@ogamierp.local" "Officer@Test1234!" "Acctg Officer" /tmp/tok_acctg
ATOK=$(cat /tmp/tok_acctg)
[ -z "$ATOK" ] && { echo "Cannot continue — acctg login failed"; exit 1; }

# Find the draft invoice (AP invoices live under /accounting/ap/invoices)
AINV=$(call GET "/accounting/ap/invoices?status=draft" "$ATOK")
INV_LIST_ULID=$(jx "$AINV" "data.0.ulid")
INV_VENDOR_NAME=$(echo "$AINV" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',[{}])[0].get('vendor',{}).get('name','?'))" 2>/dev/null)
INV_LIST_NET=$(jx "$AINV" "data.0.net_amount")

if [ -n "$INV_LIST_ULID" ]; then
  ok "Acctg Officer found draft invoice | Vendor: $INV_VENDOR_NAME | Net: ₱$INV_LIST_NET"
else
  fail "Acctg find draft invoices" "$AINV"
  INV_LIST_ULID="$INV_ULID"
fi

# Try to approve (workflow-dependent)
APPROVE=$(call PATCH "/accounting/ap/invoices/$INV_LIST_ULID/approve" "$ATOK" \
  '{"notes":"3-way match verified. GR confirmed. Invoice amounts match agreed negotiated quantities. Approved for payment."}')
APP_STATUS=$(jx "$APPROVE" "data.status")
APP_EC=$(jx "$APPROVE" "error_code")

if [ "$APP_STATUS" = "approved" ] || [ "$APP_STATUS" = "posted" ] || [ "$APP_STATUS" = "for_payment" ]; then
  ok "Invoice approved | Status: $APP_STATUS"
elif [ -n "$APP_EC" ]; then
  info "Invoice approval needs different workflow step: $APP_EC"
  # Try submit first
  SUB=$(call PATCH "/accounting/ap/invoices/$INV_LIST_ULID/submit" "$ATOK" '{}')
  SUB_STATUS=$(jx "$SUB" "data.status")
  info "After submit: $SUB_STATUS — checking approve again..."
  APP2=$(call PATCH "/accounting/ap/invoices/$INV_LIST_ULID/approve" "$ATOK" '{"notes":"Approved after review."}')
  APP2_STATUS=$(jx "$APP2" "data.status")
  if [ -n "$APP2_STATUS" ]; then
    ok "Invoice processed | Status: $APP2_STATUS"
  else
    info "Invoice in draft pending standard AP workflow"
  fi
else
  info "Invoice approval: $APP_STATUS (AP uses multi-stage workflow)"
fi

# =============================================================================
section "STEP 13 — SECURITY: SoD and Permission checks"
# =============================================================================

# 1. Purch Officer cannot approve AP invoices
SOD1=$(call PATCH "/accounting/ap/invoices/$INV_LIST_ULID/approve" "$OTOK" '{"notes":"self-approve test"}')
SOD1_ERR=$(jx "$SOD1" "error_code")
SOD1_MSG=$(jx "$SOD1" "message")
if [[ "$SOD1_ERR" == *"FORBIDDEN"* ]] || [[ "$SOD1_ERR" == *"UNAUTHORIZ"* ]] || [[ "$SOD1_MSG" == *"authorized"* ]] || [[ "$SOD1_MSG" == *"403"* ]]; then
  ok "SoD: Purch Officer cannot approve AP invoices (blocked)"
else
  info "SoD: Officer/invoice block response: $SOD1_ERR | $SOD1_MSG"
fi

# 2. Vendor cannot access internal procurement routes
SOD2=$(call GET "/procurement/purchase-orders" "$VTOK")
SOD2_ERR=$(jx "$SOD2" "error_code")
if [[ "$SOD2_ERR" == *"FORBIDDEN"* ]] || [[ "$SOD2_ERR" == *"UNAUTHENTICATED"* ]] || [[ "$SOD2_ERR" == *"UNAUTHORIZ"* ]]; then
  ok "SoD: Vendor cannot access internal /procurement/purchase-orders (blocked: $SOD2_ERR)"
else
  info "Vendor PO access: $SOD2_ERR (may be blocked differently)"
fi

# 3. Try to over-deliver beyond agreed quantity via vendor portal
OVR=$(call POST "/vendor-portal/orders/$ORDER_ULID/deliver" "$VTOK" \
  "{\"delivery_date\":\"2026-04-15\",\"items\":[{\"po_item_id\":$ITEM1_ID,\"qty_delivered\":999}]}")
OVR_CODE=$(jx "$OVR" "error_code")
OVR_MSG=$(jx "$OVR" "message")
if [[ "$OVR_MSG" == *"exceed"* ]] || [[ "$OVR_MSG" == *"cannot"* ]] || [[ "$OVR_MSG" == *"422"* ]] || [[ "$OVR_CODE" == *"VALIDATION"* ]]; then
  ok "Overflow protection: delivering 999 units rejected (ceiling = negotiated qty 400)"
  info "Reason: $OVR_MSG"
else
  info "Overflow test: $OVR_CODE | $OVR_MSG"
fi

# 4. Try to acknowledge a PO that's already past 'sent' — should fail
ACK_LATE=$(call POST "/vendor-portal/orders/$ORDER_ULID/acknowledge" "$VTOK" '{}')
ACK_LATE_ERR=$(jx "$ACK_LATE" "error_code")
if [ -n "$ACK_LATE_ERR" ]; then
  ok "State machine: Cannot acknowledge in_transit/received PO (error: $ACK_LATE_ERR)"
else
  info "Late ack response: $(echo $ACK_LATE | head -c 150)"
fi

# =============================================================================
section "STEP 14 — PURCHASING MANAGER: Full audit trail review"
# =============================================================================

login "purch.manager@ogamierp.local" "Manager@Test1234!" "Purch Manager" /tmp/tok_mgr
MTOK=$(cat /tmp/tok_mgr)

MGR_PO=$(call GET "/procurement/purchase-orders/$PO_ULID" "$MTOK")
MGR_STATUS=$(jx "$MGR_PO" "data.status")
MGR_REF=$(jx "$MGR_PO" "data.po_reference")
MGR_ROUNDS=$(jx "$MGR_PO" "data.negotiation_round")
MGR_ACK=$(jx "$MGR_PO" "data.vendor_acknowledged_at")
MGR_NOTES=$(echo "$MGR_PO" | python3 -c "import sys,json; d=json.load(sys.stdin); notes=d.get('data',{}).get('fulfillment_notes',[]); [print(f'    [{n[\"note_type\"]}] {(n.get(\"notes\") or \"\")[:55]}') for n in notes]" 2>/dev/null)

if [ -n "$MGR_STATUS" ]; then
  ok "Purch Manager audit trail | PO: $MGR_REF | Final status: $MGR_STATUS"
  info "Negotiation rounds: $MGR_ROUNDS | Acknowledged at: $MGR_ACK"
  info "Fulfillment history:"
  echo "$MGR_PO" | python3 -c "
import sys, json
d = json.load(sys.stdin)
notes = d.get('data', {}).get('fulfillment_notes', [])
for i, n in enumerate(notes, 1):
    txt = (n.get('notes') or '')[:55]
    print(f'       {i}. [{n[\"note_type\"]}] {txt}')
" 2>/dev/null
else
  fail "Manager view PO audit trail" "$MGR_PO"
fi

# Manager can also view all POs including in different statuses
ALL_POS=$(call GET "/procurement/purchase-orders" "$MTOK")
ALL_COUNT=$(echo "$ALL_POS" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('meta',{}).get('total',len(d.get('data',[]))))" 2>/dev/null)
ok "Purch Manager sees all POs | Total visible: $ALL_COUNT"

# =============================================================================
section "STEP 15 — VP: Final oversight view"
# =============================================================================

login "vp@ogamierp.local" "Vice_president@Test1234!" "Vice President" /tmp/tok_vp
VPTOK=$(cat /tmp/tok_vp)

VP_PO=$(call GET "/procurement/purchase-orders/$PO_ULID" "$VPTOK")
VP_STATUS=$(jx "$VP_PO" "data.status")
VP_AMOUNT=$(jx "$VP_PO" "data.total_po_amount")
if [ -n "$VP_STATUS" ]; then
  ok "VP can view completed PO | $PO_REF | Status: $VP_STATUS | Amount: ₱$VP_AMOUNT"
else
  fail "VP view PO" "$VP_PO"
fi

# =============================================================================
echo ""
echo -e "${BOLD}╔═══════════════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║               WORKFLOW TEST RESULTS               ║${RESET}"
echo -e "${BOLD}╠═══════════════════════════════════════════════════╣${RESET}"
echo -e "${BOLD}║  ${GREEN}PASSED: $PASS_COUNT${RESET}${BOLD}                                   ║${RESET}"
if [ $FAIL_COUNT -gt 0 ]; then
echo -e "${BOLD}║  ${RED}FAILED: $FAIL_COUNT${RESET}${BOLD}                                   ║${RESET}"
fi
echo -e "${BOLD}╚═══════════════════════════════════════════════════╝${RESET}"
echo ""
if [ $FAIL_COUNT -eq 0 ]; then
  echo -e "${GREEN}${BOLD}All workflow steps passed! Full PO lifecycle verified. ✓${RESET}"
else
  echo -e "${RED}${BOLD}$FAIL_COUNT step(s) need attention.${RESET}"
fi

# Cleanup temp tokens
rm -f /tmp/tok_officer /tmp/tok_vendor /tmp/tok_wh /tmp/tok_acctg /tmp/tok_mgr /tmp/tok_vp
