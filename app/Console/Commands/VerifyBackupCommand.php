<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;

/**
 * Automated backup integrity verification command.
 *
 * Usage:
 *   php artisan backup:verify              — trigger fresh backup then verify
 *   php artisan backup:verify --skip-backup — verify the latest existing backup
 *
 * Process:
 *  1. [Optional] Run php artisan backup:run --only-db to create a fresh backup
 *  2. Locate the latest .zip backup archive in storage/app/
 *  3. Extract the .sql dump inside the archive
 *  4. Restore the dump into the ogami_erp_restore_test database
 *  5. Run the golden payroll test suite against the restored DB
 *  6. Drop the test database
 *  7. Notify admin via email if any step fails
 *
 * Scheduled in routes/console.php:
 *   Schedule::command('backup:verify --skip-backup')->weekly()->sundays()->at('04:00');
 */
final class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify
                            {--skip-backup : Skip creating a new backup; use the latest existing one}
                            {--keep-test-db : Do not drop the restore test DB after verification (for debugging)}';

    protected $description = 'Verifies that the latest DB backup can be restored and passes the golden test suite';

    private const TEST_DB = 'ogami_erp_restore_test';

    public function handle(): int
    {
        $this->info('=== Ogami ERP Backup Integrity Verification ===');

        // ── Step 1: Create backup ─────────────────────────────────────────────
        if (! $this->option('skip-backup')) {
            $this->info('Step 1/5: Creating fresh database backup...');
            $result = Process::timeout(300)->run('php artisan backup:run --only-db 2>&1');

            if ($result->failed()) {
                $this->notifyFailure('Backup creation failed', $result->output());

                return self::FAILURE;
            }
            $this->line('<info>✓</info> Backup created successfully');
        } else {
            $this->info('Step 1/5: Skipped (--skip-backup flag set)');
        }

        // ── Step 2: Locate latest backup archive ─────────────────────────────
        $this->info('Step 2/5: Locating latest backup archive...');
        $backupPath = $this->findLatestBackupArchive();

        if ($backupPath === null) {
            $this->notifyFailure('No backup archive found in storage/app/');

            return self::FAILURE;
        }
        $this->line("<info>✓</info> Found: {$backupPath}");

        // ── Step 3: Extract and restore ───────────────────────────────────────
        $this->info('Step 3/5: Extracting and restoring to test database...');
        $dbConfig = config('database.connections.pgsql');
        $host = $dbConfig['host'];
        $port = $dbConfig['port'];
        $user = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Drop and recreate the test DB
        $this->runPsqlCommand(
            $host, $port, $user, $password,
            'DROP DATABASE IF EXISTS "'.self::TEST_DB.'";',
        );
        $this->runPsqlCommand(
            $host, $port, $user, $password,
            'CREATE DATABASE "'.self::TEST_DB."\" TEMPLATE template0 ENCODING 'UTF8';",
        );

        // Extract the .sql.gz or .sql from the zip archive
        $extractDir = storage_path('app/backup-temp/verify-'.now()->format('YmdHis'));
        File::ensureDirectoryExists($extractDir);

        $unzip = Process::timeout(120)->run("unzip -o \"{$backupPath}\" -d \"{$extractDir}\" 2>&1");
        if ($unzip->failed()) {
            $this->notifyFailure('Failed to extract backup archive', $unzip->output());
            $this->cleanup($extractDir, false);

            return self::FAILURE;
        }

        // Find the SQL dump (may be .sql or .sql.gz)
        $sqlFiles = File::glob("{$extractDir}/**/*.sql") ?: File::glob("{$extractDir}/*.sql");
        $gzFiles = File::glob("{$extractDir}/**/*.sql.gz") ?: File::glob("{$extractDir}/*.sql.gz");

        if (! empty($gzFiles)) {
            $gzFile = reset($gzFiles);
            $sqlFile = str_replace('.gz', '', $gzFile);
            Process::run("gunzip -f \"{$gzFile}\"");
            $sqlFile = file_exists($sqlFile) ? $sqlFile : null;
        } elseif (! empty($sqlFiles)) {
            $sqlFile = reset($sqlFiles);
        } else {
            $this->notifyFailure('No .sql or .sql.gz file found inside backup archive');
            $this->cleanup($extractDir, false);

            return self::FAILURE;
        }

        // Restore the dump
        $restoreResult = Process::timeout(600)->env(['PGPASSWORD' => $password])->run(
            "psql -h {$host} -p {$port} -U {$user} -d \"".self::TEST_DB.'" '
            ."-f \"{$sqlFile}\" 2>&1",
        );

        if ($restoreResult->failed()) {
            // psql can exit non-zero for warnings; check for actual ERROR lines
            if (str_contains($restoreResult->output(), 'ERROR:')) {
                $this->notifyFailure('Restore failed with errors', $restoreResult->output());
                $this->cleanup($extractDir, false);

                return self::FAILURE;
            }
        }
        $this->line('<info>✓</info> Restored to test database');
        File::deleteDirectory($extractDir);

        // ── Step 4: Run golden test suite against restored DB ─────────────────
        $this->info('Step 4/5: Running golden payroll test suite against restored DB...');

        // SAFETY GUARD 1: the dedicated phpunit-backup-verify.xml must exist.
        // It uses force="true" on ALL database env vars, guaranteeing that
        // RefreshDatabase / migrate:fresh targets ogami_erp_restore_test and
        // NEVER the production database, regardless of shell env or .env values.
        $verifyConfig = base_path('phpunit-backup-verify.xml');
        if (! file_exists($verifyConfig)) {
            $this->notifyFailure(
                'SAFETY ABORT: phpunit-backup-verify.xml not found at '.$verifyConfig.'. '
                .'Cannot run tests without the guaranteed-safe PHPUnit configuration. '
                .'Restore the file from git before re-running backup:verify.',
            );

            return self::FAILURE;
        }

        // SAFETY GUARD 2: confirm ogami_erp_restore_test actually exists in
        // PostgreSQL before running any tests. If the restore step above
        // silently failed, this prevents running migrate:fresh on an empty
        // DB that might resolve to production.
        $dbCheckResult = Process::timeout(10)
            ->env(['PGPASSWORD' => $password])
            ->run(
                "psql -h {$host} -p {$port} -U {$user} -d postgres -tAc "
                ."\"SELECT 1 FROM pg_database WHERE datname='".self::TEST_DB."';\" 2>&1",
            );

        if (trim($dbCheckResult->output()) !== '1') {
            $this->notifyFailure(
                'SAFETY ABORT: '.self::TEST_DB.' database does not exist in PostgreSQL. '
                .'The restore step may have failed silently. '
                .'Not running tests to protect production data.',
            );

            return self::FAILURE;
        }

        $this->line('<info>✓</info> Safety checks passed — restore DB exists, dedicated config found');

        // Use ./vendor/bin/pest with the dedicated phpunit-backup-verify.xml.
        // This config has force="true" on DB_DATABASE=ogami_erp_restore_test,
        // which is the only mechanism that unconditionally wins over every
        // other env source (OS env, .env file, phpunit.xml, Process::env()).
        $pestBin = base_path('vendor/bin/pest');
        $testResult = Process::timeout(300)->run(
            "{$pestBin} --configuration ".escapeshellarg($verifyConfig).' --no-coverage 2>&1',
        );

        $output = $testResult->output();
        $this->line($output);

        // Use the process exit code — reliable regardless of terminal colour codes.
        // Pest/PHPUnit exits 0 on full pass, non-zero on any failure.
        $testsPassed = $testResult->successful();

        if (! $testsPassed) {
            $this->notifyFailure('Golden test suite FAILED against restored database', $output);
            $this->cleanupTestDb($host, $port, $user, $password);

            return self::FAILURE;
        }

        $this->line('<info>✓</info> All golden tests passed on restored database');

        // ── Step 5: Cleanup ───────────────────────────────────────────────────
        $this->info('Step 5/5: Cleaning up...');

        if (! $this->option('keep-test-db')) {
            $this->cleanupTestDb($host, $port, $user, $password);
            $this->line('<info>✓</info> Test database dropped');
        } else {
            $this->warn('Test database "'.self::TEST_DB.'" retained (--keep-test-db)');
        }

        $this->info('');
        $this->info('✅ Backup verified — restore + golden suite: PASSED');
        $this->info("Backup file: {$backupPath}");
        $this->info('Timestamp: '.now()->toDateTimeString());

        return self::SUCCESS;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function findLatestBackupArchive(): ?string
    {
        $searchDir = storage_path('app');

        // Use RecursiveIterator to find all .zip files regardless of nesting depth
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'zip') {
                $files[] = $file->getPathname();
            }
        }

        if (empty($files)) {
            return null;
        }

        // Sort by modification time descending, return newest
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function runPsqlCommand(
        string $host,
        int|string $port,
        string $user,
        string $password,
        string $sql,
    ): void {
        Process::timeout(30)->env(['PGPASSWORD' => $password])->run(
            "psql -h {$host} -p {$port} -U {$user} -d postgres -c \"{$sql}\" 2>&1",
        );
    }

    private function cleanupTestDb(
        string $host,
        int|string $port,
        string $user,
        string $password,
    ): void {
        $this->runPsqlCommand($host, $port, $user, $password, 'DROP DATABASE IF EXISTS "'.self::TEST_DB.'";');
    }

    private function notifyFailure(string $message, string $detail = ''): void
    {
        $this->error("✗ {$message}");
        if ($detail) {
            $this->line($detail);
        }

        // Notify admin by email
        $adminEmail = config('backup.notifications.mail.to', env('BACKUP_NOTIFY_EMAIL'));
        if ($adminEmail) {
            try {
                Mail::raw(
                    'Backup verification FAILED at '.now()->toDateTimeString()."\n\n"
                    ."Reason: {$message}\n\n"
                    .($detail ? "Detail:\n{$detail}" : ''),
                    fn ($mail) => $mail->to($adminEmail)->subject('[Ogami ERP] Backup Integrity Check FAILED'),
                );
            } catch (\Throwable $e) {
                $this->warn("Could not send failure email: {$e->getMessage()}");
            }
        }
    }

    private function cleanup(string $dir, bool $success): void
    {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
}
