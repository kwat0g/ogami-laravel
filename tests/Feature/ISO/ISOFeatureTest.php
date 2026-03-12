<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'iso');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
});

it('lists controlled documents', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/iso/documents')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists internal audits', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/iso/audits')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
