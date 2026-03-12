<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'crm');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('staff');

    $this->client = User::factory()->create();
    $this->client->assignRole('client');
});

it('lists tickets', function () {
    $this->actingAs($this->agent)
        ->getJson('/api/v1/crm/tickets')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a ticket', function () {
    $this->actingAs($this->agent)
        ->postJson('/api/v1/crm/tickets', [
            'subject'     => 'Test Ticket',
            'description' => 'Something is broken.',
            'priority'    => 'high',
        ])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Test Ticket');
});

it('returns CRM dashboard metrics', function () {
    $this->actingAs($this->agent)
        ->getJson('/api/v1/crm/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'open_tickets',
            'in_progress_tickets',
            'sla_compliance_pct',
        ]]);
});
