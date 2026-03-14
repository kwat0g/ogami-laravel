<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'attendance');

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->hrManager = User::factory()->create();
    $this->hrManager->assignRole('manager');

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
});

it('lists attendance logs', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/attendance/logs')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists overtime requests', function () {
    $this->actingAs($this->hrManager)
        ->getJson('/api/v1/attendance/overtime-requests')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
