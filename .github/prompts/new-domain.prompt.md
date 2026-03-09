---
name: new-domain
description: "Scaffold all files for a new Ogami ERP domain module. Generates migration, model, service, controller, FormRequest, API Resource, policy, route registration, and optional frontend hook + type file."
argument-hint: "Domain name in PascalCase, e.g. 'Fleet' or 'SafetyIncident'"
---
Scaffold a complete new domain module for Ogami ERP.

## Input

Domain name: `$ARGUMENTS`

Derive from it:
- **PascalCase** class prefix: e.g. `Fleet`
- **snake_case** table/route prefix: e.g. `fleet`
- **kebab-case** route resource prefix: e.g. `fleet`
- **camelCase** JS variable prefix: e.g. `fleet`

---

## Step 1 — Read Existing Patterns

Before generating anything, read these reference files to ensure exact pattern compliance:

1. `app/Domains/HR/Services/EmployeeService.php` — service pattern
2. `app/Http/Controllers/Leave/LeaveRequestController.php` — controller pattern
3. `app/Shared/Exceptions/DomainException.php` — exception base
4. `app/Shared/ValueObjects/Money.php` — value object pattern (if domain needs money)
5. `routes/api/v1/hr.php` — route file pattern
6. `frontend/src/hooks/useLeave.ts` — hook pattern (for frontend files)

---

## Step 2 — Generate Backend Files

Create the following files in order. Each must pass the ARCH test suite.

### 2a. Migration
`database/migrations/<timestamp>_create_<snake>_table.php`

Rules (from migration-writer skill):
- `declare(strict_types=1)`
- `$table->id()` + `$table->ulid('ulid')->unique()`
- Money columns as `unsignedBigInteger` (centavos)
- Status column as `string(30)` + CHECK constraint via `DB::statement()`
- `$table->foreignId('created_by_id')->constrained('users')`
- `$table->timestamps()` + `$table->softDeletes()`
- No `$table->enum()` calls

### 2b. Eloquent Model
`app/Domains/<Domain>/Models/<Domain>.php`

```php
<?php
declare(strict_types=1);

namespace App\Domains\<Domain>\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class <Domain> extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [/* list all user-settable columns except id, ulid, timestamps */];

    // Money casts — cast centavo columns to int (keep as int; use Money VO in service layer)
    protected $casts = [
        'status' => 'string',
    ];
}
```

### 2c. Domain Service
`app/Domains/<Domain>/Services/<Domain>Service.php`

```php
<?php
declare(strict_types=1);

namespace App\Domains\<Domain>\Services;

use App\Shared\Contracts\ServiceContract;
use App\Domains\<Domain>\Models\<Domain>;
use Illuminate\Support\Facades\DB;

final class <Domain>Service implements ServiceContract
{
    public function list(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        return <Domain>::query()
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->paginate(20);
    }

    public function create(array $data, int $createdById): <Domain>
    {
        return DB::transaction(function () use ($data, $createdById): <Domain> {
            return <Domain>::create([...$data, 'created_by_id' => $createdById]);
        });
    }

    public function update(<Domain> $record, array $data): <Domain>
    {
        return DB::transaction(function () use ($record, $data): <Domain> {
            $record->update($data);
            return $record->refresh();
        });
    }

    public function delete(<Domain> $record): void
    {
        DB::transaction(fn () => $record->delete());
    }
}
```

### 2d. Form Requests
`app/Http/Requests/<Domain>/Store<Domain>Request.php`
`app/Http/Requests/<Domain>/Update<Domain>Request.php`

Both extend `Illuminate\Foundation\Http\FormRequest`. Include sensible `rules()` based on the model's fillable fields.

### 2e. API Resource
`app/Http/Resources/<Domain>/<Domain>Resource.php`

```php
<?php
declare(strict_types=1);

namespace App\Http\Resources\<Domain>;

use Illuminate\Http\Resources\Json\JsonResource;

final class <Domain>Resource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'ulid'       => $this->ulid,
            // ... all public fields ...
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### 2f. Policy
`app/Domains/<Domain>/Policies/<Domain>Policy.php`

Implement `viewAny`, `view`, `create`, `update`, `delete` methods. Use `$user->hasPermission()` (Spatie) for each. Add SoD check on `update`/workflow methods: `$user->id !== $record->created_by_id`.

### 2g. Controller
`app/Http/Controllers/<Domain>/<Domain>Controller.php`

```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers\<Domain>;

use App\Http\Controllers\Controller;
use App\Domains\<Domain>\Services\<Domain>Service;
use App\Domains\<Domain>\Models\<Domain>;
use App\Http\Requests\<Domain>\Store<Domain>Request;
use App\Http\Requests\<Domain>\Update<Domain>Request;
use App\Http\Resources\<Domain>\<Domain>Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

final class <Domain>Controller extends Controller
{
    public function __construct(private readonly <Domain>Service $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', <Domain>::class);
        return <Domain>Resource::collection($this->service->list($request->query()));
    }

    public function store(Store<Domain>Request $request): JsonResponse
    {
        $this->authorize('create', <Domain>::class);
        $record = $this->service->create($request->validated(), (int) $request->user()->id);
        return (new <Domain>Resource($record))->response()->setStatusCode(201);
    }

    public function show(<Domain> $<snake>): <Domain>Resource
    {
        $this->authorize('view', $<snake>);
        return new <Domain>Resource($<snake>);
    }

    public function update(Update<Domain>Request $request, <Domain> $<snake>): <Domain>Resource
    {
        $this->authorize('update', $<snake>);
        return new <Domain>Resource($this->service->update($<snake>, $request->validated()));
    }

    public function destroy(<Domain> $<snake>): JsonResponse
    {
        $this->authorize('delete', $<snake>);
        $this->service->delete($<snake>);
        return response()->json(null, 204);
    }
}
```

### 2h. Route File
`routes/api/v1/<kebab>.php`

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\<Domain>\<Domain>Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:writes'])->group(function (): void {
    Route::apiResource('<kebab>', <Domain>Controller::class);
});
```

Then register it in **`routes/api.php`**:
```php
require __DIR__ . '/api/v1/<kebab>.php';
```

### 2i. Policy Registration
Add to `app/Providers/AppServiceProvider.php` in the `boot()` method:
```php
Gate::policy(\App\Domains\<Domain>\Models\<Domain>::class,
              \App\Domains\<Domain>\Policies\<Domain>Policy::class);
```

---

## Step 3 — Generate Frontend Files (optional, ask user)

### 3a. TypeScript Types
`frontend/src/types/<camel>.ts`

```typescript
export interface <Domain> {
  id: number;
  ulid: string;
  // ... domain fields ...
  created_at: string;
  updated_at: string;
}

export interface <Domain>Filters {
  search?: string;
  page?: number;
}
```

### 3b. TanStack Query Hook
`frontend/src/hooks/use<Domain>.ts`

```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import type { <Domain>, <Domain>Filters } from '@/types/<camel>';

type PaginatedResponse<T> = { data: T[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };

export function use<Domain>s(filters: <Domain>Filters = {}) {
  return useQuery<PaginatedResponse<<Domain>>>({
    queryKey: ['<kebab>s', filters],
    queryFn: () => api.get('/<kebab>s', { params: filters }).then(r => r.data),
  });
}

export function useCreate<Domain>() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<<Domain>>) => api.post('/<kebab>s', data).then(r => r.data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['<kebab>s'] }),
  });
}

export function useUpdate<Domain>() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ ulid, ...data }: Partial<<Domain>> & { ulid: string }) =>
      api.put(`/<kebab>s/${ulid}`, data).then(r => r.data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['<kebab>s'] }),
  });
}

export function useDelete<Domain>() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (ulid: string) => api.delete(`/<kebab>s/${ulid}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['<kebab>s'] }),
  });
}
```

---

## Step 4 — Verify

After generating all files, run:

```bash
# Architecture rules (fast — no DB needed)
./vendor/bin/pest --testsuite=Arch

# PHPStan
./vendor/bin/phpstan analyse app/Domains/<Domain> app/Http/Controllers/<Domain>

# Frontend type check
cd frontend && pnpm typecheck
```

Fix any reported errors before returning output.

---

## Output Summary

When done, report:
1. Files created (with paths)
2. Manual steps remaining (policy registration, route include)
3. Any decisions made (e.g. money columns added, enum fields chosen)
4. Suggested Feature test file to create next
