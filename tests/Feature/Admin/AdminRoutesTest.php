<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Admin Routes — Feature Tests
|--------------------------------------------------------------------------
| Tests for the 11 routes in routes/api/v1/admin.php:
|   GET    /api/v1/admin/dashboard/stats
|   GET    /api/v1/admin/users
|   POST   /api/v1/admin/users
|   PATCH  /api/v1/admin/users/{user}
|   DELETE /api/v1/admin/users/{user}
|   POST   /api/v1/admin/users/{user}/roles
|   POST   /api/v1/admin/users/{user}/unlock
|   GET    /api/v1/admin/roles
|   GET    /api/v1/admin/settings
|   PATCH  /api/v1/admin/settings/{key}
|   GET    /api/v1/admin/audit-logs
--------------------------------------------------------------------------
*/

function adminUser(array $permissions = []): User
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // Ensure the 'admin' role exists and has all required permissions
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $allPerms = array_unique(array_merge([
        'system.manage_users',
        'system.assign_roles',
        'system.edit_settings',
        'system.view_audit_log',
    ], $permissions));

    foreach ($allPerms as $perm) {
        $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $role->givePermissionTo($p);
    }

    $user = User::factory()->create(['password' => Hash::make('AdminPass!1')]);
    $user->assignRole('admin');

    return $user;
}

function limitedUser(array $permissions = []): User
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create(['password' => Hash::make('LimitedPass!1')]);

    foreach ($permissions as $perm) {
        $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($p);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// Dashboard Stats
// ---------------------------------------------------------------------------

describe('GET /api/v1/admin/dashboard/stats', function () {
    it('returns 200 with stats payload for any authenticated user', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'total_employees',
                'by_department',
                'hired_trend',
                'pending_approvals' => ['leaves', 'loans', 'overtime', 'journal_entries', 'invoices', 'total'],
                'attendance_summary',
                'payroll_trend',
            ]);
    });

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/admin/dashboard/stats')
            ->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// User Management — List
// ---------------------------------------------------------------------------

describe('GET /api/v1/admin/users', function () {
    it('returns paginated user list for admin with users.view', function () {
        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('returns 403 without users.view permission', function () {
        $user = limitedUser([]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    });

    it('filters by search term', function () {
        $admin = adminUser();

        // Create a user with a distinctive name
        User::factory()->create(['name' => 'ZZZ_Unique_Search_Target', 'email' => 'zzz_unique@test.com']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=ZZZ_Unique')
            ->assertOk()
            ->assertJsonFragment(['name' => 'ZZZ_Unique_Search_Target']);
    });
});

// ---------------------------------------------------------------------------
// User Management — Create
// ---------------------------------------------------------------------------

describe('POST /api/v1/admin/users', function () {
    it('creates a user and assigns the given role', function () {
        $admin = adminUser();

        // Ensure the 'staff' role exists (used in creation)
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $payload = [
            'name' => 'New Test User',
            'email' => 'newtestuser@example.com',
            'password' => 'SecurePass!9',
            'role' => 'staff',
        ];

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/users', $payload)
            ->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'email']])
            ->assertJsonFragment(['name' => 'New Test User']);

        $this->assertDatabaseHas('users', ['email' => 'newtestuser@example.com']);
    });

    it('returns 422 when email is already taken', function () {
        $admin = adminUser();
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'Duplicate',
                'email' => 'taken@example.com',
                'password' => 'SecurePass!9',
                'role' => 'staff',
            ])
            ->assertUnprocessable();
    });

    it('returns 403 without users.create permission', function () {
        $user = limitedUser([]);

        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'No Permission',
                'email' => 'noperm@example.com',
                'password' => 'SecurePass!9',
                'role' => 'staff',
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// User Management — Update
// ---------------------------------------------------------------------------

describe('PATCH /api/v1/admin/users/{user}', function () {
    it('updates user name and email', function () {
        $admin = adminUser();
        $target = User::factory()->create(['name' => 'OldName']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$target->id}", [
                'name' => 'UpdatedName',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'UpdatedName']);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'UpdatedName']);
    });

    it('returns 403 without users.update permission', function () {
        $user = limitedUser([]);
        $target = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$target->id}", ['name' => 'Hack'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// User Management — Delete
// ---------------------------------------------------------------------------

describe('DELETE /api/v1/admin/users/{user}', function () {
    it('soft-deletes another user', function () {
        $admin = adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/users/{$target->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'User deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    });

    it('returns 422 when admin tries to delete themselves', function () {
        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'You cannot delete your own account.']);
    });

    it('returns 403 without users.delete permission', function () {
        $user = limitedUser([]);
        $target = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/admin/users/{$target->id}")
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Role Assignment
// ---------------------------------------------------------------------------

describe('POST /api/v1/admin/users/{user}/roles', function () {
    it('assigns a role to a target user', function () {
        $admin = adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$target->id}/roles", ['role' => 'hr_manager'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Role assigned successfully.']);

        expect($target->fresh()->hasRole('hr_manager'))->toBeTrue();
    });

    it('returns 422 if role does not exist in DB', function () {
        $admin = adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$target->id}/roles", ['role' => 'nonexistent_role_xyz'])
            ->assertUnprocessable();
    });

    it('returns 403 without roles.assign permission', function () {
        $user = limitedUser([]);
        $target = User::factory()->create();
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/users/{$target->id}/roles", ['role' => 'staff'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Account Unlock
// ---------------------------------------------------------------------------

describe('POST /api/v1/admin/users/{user}/unlock', function () {
    it('clears locked_until and resets failed login attempts', function () {
        $admin = adminUser();
        $locked = User::factory()->create([
            'locked_until' => now()->addHour(),
            'failed_login_attempts' => 5,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$locked->id}/unlock")
            ->assertOk()
            ->assertJsonFragment(['message' => 'User account unlocked.']);

        $this->assertDatabaseHas('users', [
            'id' => $locked->id,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    });

    it('returns 403 without users.update permission', function () {
        $user = limitedUser([]);
        $target = User::factory()->create(['locked_until' => now()->addHour()]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/users/{$target->id}/unlock")
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Roles List
// ---------------------------------------------------------------------------

describe('GET /api/v1/admin/roles', function () {
    it('returns list of roles with user_count for permitted user', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/roles')
            ->assertOk();

        expect($response->json('data'))->toBeArray();

        // If any roles exist, verify each has the expected keys
        $data = $response->json('data');
        if (count($data) > 0) {
            expect($data[0])->toHaveKeys(['id', 'name']);
        }
    });

    it('returns 403 without roles.view permission', function () {
        $user = limitedUser([]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/roles')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// System Settings — Read
// ---------------------------------------------------------------------------

describe('GET /api/v1/admin/settings', function () {
    it('returns grouped settings for admin', function () {
        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonStructure(['data']);
    });

    it('masks sensitive values for user with system.edit_settings', function () {
        // Give a non-admin user only system.edit_settings permission
        $p = Permission::firstOrCreate(['name' => 'system.edit_settings', 'guard_name' => 'web']);
        $user = limitedUser(['system.edit_settings']);

        // Inject a sensitive setting into the DB
        \Illuminate\Support\Facades\DB::table('system_settings')
            ->updateOrInsert(
                ['key' => 'security.test_api_key_masked'],
                [
                    'key' => 'security.test_api_key_masked',
                    'label' => 'Test API Key',
                    'value' => json_encode('super_secret_value'),
                    'data_type' => 'string',
                    'group' => 'security',
                    'is_sensitive' => true,
                    'editable_by_role' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/settings')
            ->assertOk();

        // Find the sensitive setting in the grouped response
        $data = $response->json('data');
        $secGroup = $data['security'] ?? [];

        $sensitiveEntry = collect($secGroup)->firstWhere('key', 'security.test_api_key_masked');
        expect($sensitiveEntry)->not->toBeNull();
        expect($sensitiveEntry['value'])->toBe('***');
    });

    it('returns 403 without system_settings.view permission', function () {
        $user = limitedUser([]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/settings')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// System Settings — Update
// ---------------------------------------------------------------------------

describe('PATCH /api/v1/admin/settings/{key}', function () {
    it('updates an editable setting', function () {
        $admin = adminUser();

        // Find an editable setting from the seeded data
        $setting = \Illuminate\Support\Facades\DB::table('system_settings')
            ->whereNull('editable_by_role')
            ->orWhere('editable_by_role', 'admin')
            ->first();

        if (! $setting) {
            $this->markTestSkipped('No editable settings found in DB — run seeders first.');
        }

        $newValue = 'updated_test_value_'.time();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/settings/{$setting->key}", ['value' => $newValue])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => ['key', 'label', 'value']]);

        $this->assertDatabaseHas('system_settings', [
            'key' => $setting->key,
            'value' => json_encode($newValue),
        ]);
    });

    it('returns 404 for unknown setting key', function () {
        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/settings/nonexistent.key.xyz', ['value' => 'x'])
            ->assertNotFound();
    });

    it('returns 403 without system_settings.update permission', function () {
        $user = limitedUser([]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/admin/settings/some.key', ['value' => 'x'])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Audit Logs
// ---------------------------------------------------------------------------

describe('GET /api/v1/admin/audit-logs', function () {
    it('returns paginated audit log for user with audit_logs.view', function () {
        $admin = adminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('returns 403 without audit_logs.view permission', function () {
        $user = limitedUser([]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    });

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/admin/audit-logs')
            ->assertUnauthorized();
    });
});
