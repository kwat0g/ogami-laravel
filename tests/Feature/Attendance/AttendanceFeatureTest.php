<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'attendance');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
});

it('lists attendance logs', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/attendance/logs')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists shift schedules', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/attendance/shifts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists overtime requests', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/attendance/overtime')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('rejects unauthenticated access to attendance logs', function () {
    $this->getJson('/api/v1/attendance/logs')
        ->assertUnauthorized();
});
