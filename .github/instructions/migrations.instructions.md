---
description: "Use when writing or editing Laravel migrations in database/migrations/. Covers PostgreSQL-specific patterns: ULID keys, centavo money columns, CHECK constraints instead of enums, stored generated columns, and encrypted government IDs."
applyTo: "database/migrations/**"
---
# Ogami ERP — Migration Writing Guidelines

> For complex or novel schema questions, invoke the `migration-writer` skill.

## File Header

Every migration must start with:
```php
<?php

declare(strict_types=1);
```

## Standard Template

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            // ... domain columns ...
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // PgSQL-only constraints AFTER Schema::create()
        DB::statement("ALTER TABLE table_name ADD CONSTRAINT chk_table_name_status CHECK (status IN ('draft','active','closed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
};
```

## Column Patterns

### ULID (required on every domain entity table)
```php
$table->id();
$table->ulid('ulid')->unique();  // used in API URLs; frontend never uses integer id
```

### Money (centavos — never decimal/float)
```php
$table->unsignedBigInteger('basic_monthly_rate');  // ₱25,000 = 2_500_000
$table->unsignedBigInteger('amount')->default(0);
```

### Enums → string + CHECK constraint
Never use `$table->enum()`. Use `string` with a PgSQL CHECK constraint instead:
```php
$table->string('status', 30)->default('draft');
// After Schema::create():
DB::statement("ALTER TABLE foo ADD CONSTRAINT chk_foo_status CHECK (status IN ('draft','active','closed'))");
```

### Standard lifecycle columns
```php
$table->string('status', 30)->default('draft');
$table->foreignId('created_by_id')->constrained('users');
$table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('approved_at')->nullable();
$table->softDeletes();
```

### Foreign key to employees
Always reference the integer `id`, not the `ulid`:
```php
$table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
```

## PostgreSQL-Only Patterns

### Stored Generated Columns
Define AFTER `Schema::create()` with `DB::statement()`. **Never include in `$fillable`, factories, or test overrides.**
```php
DB::statement("
    ALTER TABLE employees
    ADD COLUMN daily_rate NUMERIC(12,4)
        GENERATED ALWAYS AS (basic_monthly_rate::numeric / 22.0) STORED,
    ADD COLUMN hourly_rate NUMERIC(12,4)
        GENERATED ALWAYS AS (basic_monthly_rate::numeric / 22.0 / 8.0) STORED
");
```
Use `PayrollTestHelper::normalizeOverrides()` in tests to strip these columns automatically.

### Encrypted Government IDs
```php
$table->text('tin')->nullable();                     // encrypted raw value (model cast)
$table->string('tin_hash', 64)->nullable()->unique(); // SHA-256 hash for uniqueness
$table->text('sss_no')->nullable();
$table->string('sss_no_hash', 64)->nullable()->unique();
```
Uniqueness constraint goes only on the `_hash` column, never the encrypted column.

### Non-negative money constraint
```php
DB::statement("ALTER TABLE foo ADD CONSTRAINT chk_foo_amount_nonneg CHECK (amount >= 0)");
```

## Prohibited

- `$table->enum()` — use `string` + CHECK constraint
- `decimal()` or `float` for money — use `unsignedBigInteger` (centavos)
- SQLite-compatible workarounds — PgSQL features only
- `nullable()` on `ulid`
- Raw SQL in `down()` unless mirroring a `DB::statement()` in `up()`
- Omitting `declare(strict_types=1)`
