<?php

declare(strict_types=1);

use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\InternalAudit;
use App\Domains\ISO\Models\AuditFinding;
use App\Domains\ISO\Services\DocumentControlService;
use App\Domains\ISO\Services\AuditService;
use App\Models\User;

uses()->group('feature', 'iso');

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

// ── Document Control ─────────────────────────────────────────────────────────

it('creates a controlled document in draft status', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $service = app(DocumentControlService::class);
    $doc = $service->store([
        'document_code' => 'QMS-DOC-001',
        'title' => 'Quality Management System Manual',
        'category' => 'manual',
        'department_id' => null,
        'content' => 'This is the QMS manual content.',
    ], $user);

    expect($doc)->toBeInstanceOf(ControlledDocument::class);
    expect($doc->status)->toBe('draft');
    expect($doc->document_code)->toBe('QMS-DOC-001');
});

it('transitions document through review to effective', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $service = app(DocumentControlService::class);
    $doc = $service->store([
        'document_code' => 'QMS-PROC-001',
        'title' => 'Incoming Inspection Procedure',
        'category' => 'procedure',
        'content' => 'Step 1: Receive. Step 2: Inspect. Step 3: Release.',
    ], $user);

    expect($doc->status)->toBe('draft');

    // Submit for review
    $doc = $service->submitForReview($doc, $user);
    expect($doc->status)->toBe('under_review');

    // Approve / make effective
    $approver = User::factory()->create();
    $approver->assignRole('manager');

    $doc = $service->approve($doc, $approver);
    expect($doc->status)->toBe('effective');
});

// ── Internal Audit ───────────────────────────────────────────────────────────

it('creates an internal audit schedule', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    $service = app(AuditService::class);
    $audit = $service->schedule([
        'title' => 'Q1 2026 Internal Audit',
        'audit_type' => 'internal',
        'scheduled_date' => '2026-03-15',
        'scope' => 'Production department ISO 9001 clause 8',
        'lead_auditor_id' => $user->id,
    ], $user);

    expect($audit)->toBeInstanceOf(InternalAudit::class);
    expect($audit->status)->toBe('scheduled');
});

// ── Service Instantiation ────────────────────────────────────────────────────

it('resolves DocumentControlService from container', function () {
    expect(app(DocumentControlService::class))->toBeInstanceOf(DocumentControlService::class);
});

it('resolves AuditService from container', function () {
    expect(app(AuditService::class))->toBeInstanceOf(AuditService::class);
});

it('resolves DocumentAcknowledgmentService from container', function () {
    $service = app(\App\Domains\ISO\Services\DocumentAcknowledgmentService::class);
    expect($service)->toBeInstanceOf(\App\Domains\ISO\Services\DocumentAcknowledgmentService::class);
});

it('resolves ISOService from container', function () {
    $service = app(\App\Domains\ISO\Services\ISOService::class);
    expect($service)->toBeInstanceOf(\App\Domains\ISO\Services\ISOService::class);
});
