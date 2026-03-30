<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domains\Inventory\StateMachines\MaterialRequisitionStateMachine;
use App\Domains\Procurement\StateMachines\GoodsReceiptStateMachine;
use App\Domains\Procurement\StateMachines\PurchaseRequestStateMachine;
use App\Domains\Production\StateMachines\ProductionOrderStateMachine;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Domains\AR\StateMachines\CustomerInvoiceStateMachine;
use App\Domains\Delivery\StateMachines\DeliveryReceiptStateMachine;

/**
 * M9 FIX: Negative/edge-case tests for state machines.
 *
 * Verifies that invalid state transitions are blocked and terminal
 * states cannot be exited. This catches regressions in state machine
 * TRANSITIONS maps that could allow workflow bypasses.
 */

// ── MaterialRequisition StateMachine ──────────────────────────────────────────

test('MR: draft cannot skip to approved', function () {
    $sm = new MaterialRequisitionStateMachine();
    expect($sm->isAllowed('draft', 'approved'))->toBeFalse();
});

test('MR: fulfilled is terminal', function () {
    $sm = new MaterialRequisitionStateMachine();
    expect($sm->allowedTransitions('fulfilled'))->toBe([]);
});

test('MR: cancelled is terminal', function () {
    $sm = new MaterialRequisitionStateMachine();
    expect($sm->allowedTransitions('cancelled'))->toBe([]);
});

test('MR: submitted cannot skip to fulfilled', function () {
    $sm = new MaterialRequisitionStateMachine();
    expect($sm->isAllowed('submitted', 'fulfilled'))->toBeFalse();
});

test('MR: valid chain draft -> submitted -> noted -> checked -> reviewed -> approved -> fulfilled', function () {
    $sm = new MaterialRequisitionStateMachine();
    expect($sm->isAllowed('draft', 'submitted'))->toBeTrue();
    expect($sm->isAllowed('submitted', 'noted'))->toBeTrue();
    expect($sm->isAllowed('noted', 'checked'))->toBeTrue();
    expect($sm->isAllowed('checked', 'reviewed'))->toBeTrue();
    expect($sm->isAllowed('reviewed', 'approved'))->toBeTrue();
    expect($sm->isAllowed('approved', 'fulfilled'))->toBeTrue();
});

// ── GoodsReceipt StateMachine ─────────────────────────────────────────────────

test('GR: draft cannot skip to returned', function () {
    $sm = new GoodsReceiptStateMachine();
    expect($sm->isAllowed('draft', 'returned'))->toBeFalse();
});

test('GR: returned is terminal', function () {
    $sm = new GoodsReceiptStateMachine();
    expect($sm->allowedTransitions('returned'))->toBe([]);
});

test('GR: cancelled is terminal', function () {
    $sm = new GoodsReceiptStateMachine();
    expect($sm->allowedTransitions('cancelled'))->toBe([]);
});

test('GR: qc_failed cannot go directly to confirmed', function () {
    $sm = new GoodsReceiptStateMachine();
    expect($sm->isAllowed('qc_failed', 'confirmed'))->toBeFalse();
});

// ── PurchaseRequest StateMachine ──────────────────────────────────────────────

test('PR: draft cannot skip to approved', function () {
    $sm = new PurchaseRequestStateMachine();
    expect($sm->isAllowed('draft', 'approved'))->toBeFalse();
});

test('PR: rejected is terminal', function () {
    $sm = new PurchaseRequestStateMachine();
    expect($sm->allowedTransitions('rejected'))->toBe([]);
});

test('PR: converted_to_po is terminal', function () {
    $sm = new PurchaseRequestStateMachine();
    expect($sm->allowedTransitions('converted_to_po'))->toBe([]);
});

// ── ProductionOrder StateMachine ──────────────────────────────────────────────

test('PO: draft cannot skip to completed', function () {
    $sm = new ProductionOrderStateMachine();
    expect($sm->isAllowed('draft', 'completed'))->toBeFalse();
});

test('PO: closed is terminal', function () {
    $sm = new ProductionOrderStateMachine();
    expect($sm->allowedTransitions('closed'))->toBe([]);
});

test('PO: cancelled is terminal', function () {
    $sm = new ProductionOrderStateMachine();
    expect($sm->allowedTransitions('cancelled'))->toBe([]);
});

test('PO: completed can rework (go back to in_progress)', function () {
    $sm = new ProductionOrderStateMachine();
    expect($sm->isAllowed('completed', 'in_progress'))->toBeTrue();
});

// ── PayrollRun StateMachine ───────────────────────────────────────────────────

test('Payroll: DRAFT cannot skip to DISBURSED', function () {
    $sm = new PayrollRunStateMachine();
    expect($sm->canTransition(
        new \App\Domains\Payroll\Models\PayrollRun(['status' => 'DRAFT']),
        'DISBURSED'
    ))->toBeFalse();
});

test('Payroll: PUBLISHED is terminal', function () {
    $sm = new PayrollRunStateMachine();
    expect($sm->allowedNext(
        new \App\Domains\Payroll\Models\PayrollRun(['status' => 'PUBLISHED'])
    ))->toBe([]);
});

// ── CustomerInvoice (AR) StateMachine ─────────────────────────────────────────

test('AR: draft can be submitted (H1 fix)', function () {
    $sm = new CustomerInvoiceStateMachine();
    expect($sm->isAllowed('draft', 'submitted'))->toBeTrue();
});

test('AR: submitted can be approved', function () {
    $sm = new CustomerInvoiceStateMachine();
    expect($sm->isAllowed('submitted', 'approved'))->toBeTrue();
});

test('AR: paid is terminal', function () {
    $sm = new CustomerInvoiceStateMachine();
    expect($sm->allowedTransitions('paid'))->toBe([]);
});

test('AR: written_off is terminal', function () {
    $sm = new CustomerInvoiceStateMachine();
    expect($sm->allowedTransitions('written_off'))->toBe([]);
});

// ── DeliveryReceipt StateMachine ──────────────────────────────────────────────

test('DR: confirmed can go to partially_delivered (M6 fix)', function () {
    $sm = new DeliveryReceiptStateMachine();
    expect($sm->isAllowed('confirmed', 'partially_delivered'))->toBeTrue();
});

test('DR: partially_delivered can go to delivered', function () {
    $sm = new DeliveryReceiptStateMachine();
    expect($sm->isAllowed('partially_delivered', 'delivered'))->toBeTrue();
});

test('DR: delivered is terminal', function () {
    $sm = new DeliveryReceiptStateMachine();
    expect($sm->allowedTransitions('delivered'))->toBe([]);
});

// ── Invalid state handling ────────────────────────────────────────────────────

test('all state machines return empty array for unknown status', function () {
    expect((new MaterialRequisitionStateMachine())->allowedTransitions('nonexistent'))->toBe([]);
    expect((new GoodsReceiptStateMachine())->allowedTransitions('nonexistent'))->toBe([]);
    expect((new PurchaseRequestStateMachine())->allowedTransitions('nonexistent'))->toBe([]);
    expect((new ProductionOrderStateMachine())->allowedTransitions('nonexistent'))->toBe([]);
    expect((new CustomerInvoiceStateMachine())->allowedTransitions('nonexistent'))->toBe([]);
    expect((new DeliveryReceiptStateMachine())->allowedTransitions('nonexistent'))->toBe([]);
});
