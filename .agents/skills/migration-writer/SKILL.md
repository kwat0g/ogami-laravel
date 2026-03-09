---
name: migration-writer
description: "Write PostgreSQL-compatible Laravel migrations for Ogami ERP. Use when creating new tables, adding columns, or altering schemas. Knows PgSQL-specific patterns: stored generated columns, CHECK constraints, SHA-256 hash columns, encrypted government IDs, and ULID primary keys."
argument-hint: "Describe the schema change (e.g. 'add equipment_type table with status enum')"
---
# Migration Writer

Generate correct PostgreSQL migrations for Ogami ERP. All migrations target **PostgreSQL 16** — never write SQLite-compatible code.

## When to Use

- Creating new domain tables
- Adding columns or indexes to existing tables
- Writing CHECK constraints or trigger-based logic
- Adding encrypted government ID columns with hash uniqueness
- Creating stored generated columns (computed from other columns)

## Migration Template

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            // ... columns ...
            $table->timestamps();
            $table->softDeletes();
        });

        // PgSQL-only constraints go AFTER Schema::create()
        DB::statement("ALTER TABLE table_name ADD CONSTRAINT chk_... CHECK (...)");
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
};
```

## Key Patterns

### ULID Primary Key Pattern
Every domain entity table must have both an integer `id` (auto-increment) and a `ulid` (unique, used in API URLs):
```php
$table->id();
$table->ulid('ulid')->unique();
```
Frontend URL params use `.ulid`, never the integer `id`.

### Money Columns (Centavos)
Store money as `unsignedBigInteger` (centavos), never `decimal`/`float`:
```php
$table->unsignedBigInteger('basic_monthly_rate');    // ₱25,000 = 2_500_000
$table->unsignedBigInteger('amount')->default(0);
```

### Enums as Strings with CHECK Constraint
Use `string` + a PgSQL CHECK constraint instead of `enum()` (avoids ALTER TABLE lock on MySQL/PgSQL enum changes):
```php
$table->string('status', 30)->default('draft');
// after Schema::create():
DB::statement("ALTER TABLE foo ADD CONSTRAINT chk_foo_status CHECK (status IN ('draft','active','closed'))");
```

### Stored Generated Columns
`GENERATED ALWAYS AS` columns cannot be in INSERT/UPDATE. Define them AFTER Schema::create() using DB::statement():
```php
// In migration (after the Schema::create() block):
DB::statement("
    ALTER TABLE employees
    ADD COLUMN daily_rate NUMERIC(12,4)
        GENERATED ALWAYS AS (basic_monthly_rate::numeric / 22.0) STORED,
    ADD COLUMN hourly_rate NUMERIC(12,4)
        GENERATED ALWAYS AS (basic_monthly_rate::numeric / 22.0 / 8.0) STORED
");
```
**NEVER** include these columns in `$fillable`, factory definitions, or test overrides.  
Use `PayrollTestHelper::normalizeOverrides()` to strip them in tests.

### Encrypted Government ID Columns
Government IDs (SSS, TIN, PhilHealth, Pag-IBIG) follow the encrypt-raw + hash-for-uniqueness pattern:
```php
$table->text('tin')->nullable();                    // encrypted raw value (via model cast)
$table->string('tin_hash', 64)->nullable()->unique(); // SHA-256 hash for DB uniqueness
$table->text('sss_no')->nullable();
$table->string('sss_no_hash', 64)->nullable()->unique();
$table->text('philhealth_no')->nullable();
$table->string('philhealth_no_hash', 64)->nullable()->unique();
$table->text('pagibig_no')->nullable();
$table->string('pagibig_no_hash', 64)->nullable()->unique();
```
The model computes the hash in a `saving` observer/boot method before persist.  
Do **not** add a plain unique index on the encrypted column — use only the `_hash` column for uniqueness. 

### Foreign Keys to employees
Always reference the integer `id`, not the `ulid`:
```php
$table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
```

### Soft Deletes
All domain entity tables use soft deletes:
```php
$table->softDeletes();
```

### Audit Trail
Models that need Owen-it auditing do NOT need migration changes — add `implements Auditable` + `use \OwenIt\Auditing\Auditable` to the Eloquent model.

### Fiscal Period Foreign Key
Payroll and finance tables that belong to a period:
```php
$table->foreignId('fiscal_period_id')->nullable()->constrained('fiscal_periods')->nullOnDelete();
```

### Standard Status Lifecycle Columns
```php
$table->string('status', 30)->default('draft');
$table->foreignId('created_by_id')->constrained('users');
$table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('approved_at')->nullable();
```

## Common CHECK Constraints

```sql
-- Non-negative money
ALTER TABLE foo ADD CONSTRAINT chk_foo_amount_nonneg CHECK (amount >= 0);

-- Date ordering
ALTER TABLE foo ADD CONSTRAINT chk_foo_dates CHECK (end_date >= start_date);

-- Period number (payroll)
ALTER TABLE foo ADD CONSTRAINT chk_foo_period_number CHECK (period_number IN (1, 2));
```

## What NOT to Do

- **No `$table->enum()`** — use `string` + CHECK constraint for portability
- **No `decimal()` for money** — use `unsignedBigInteger` (centavos)
- **No SQLite-compatible workarounds** — use PgSQL features directly
- **No nullable on `ulid`** — always required and unique
- **No raw SQL in `down()`** unless mirroring a `DB::statement()` in `up()`

## Checklist Before Saving

- [ ] `declare(strict_types=1)` at top
- [ ] `up()` and `down()` are exact inverses
- [ ] Money columns are `unsignedBigInteger`
- [ ] Generated columns defined via `DB::statement()` after `Schema::create()`
- [ ] CHECK constraints added via `DB::statement()` after `Schema::create()`
- [ ] ULID column present and unique
- [ ] Soft deletes included for domain entity tables
- [ ] No `$table->enum()` calls
