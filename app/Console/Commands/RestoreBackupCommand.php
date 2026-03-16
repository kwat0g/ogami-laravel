<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Manual production database restore from a Spatie Backup archive.
 *
 * Usage:
 *   php artisan backup:restore              — interactive: choose from a list
 *   php artisan backup:restore /path/to.zip — restore a specific archive
 *
 * Safety measures:
 *  1. Always creates a safety backup of the current production database first
 *  2. Requires typing CONFIRM before anything is touched
 *  3. Drops and recreates the public schema (not the whole DB) so active
 *     connections are not forcibly terminated
 *  4. Logs who ran the restore and from which archive
 *
 * Scheduled: NOT scheduled — manual CLI use only.
 */
final class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore
                            {archive? : Full path to the .zip backup archive to restore}
                            {--skip-safety-backup : Skip the pre-restore safety backup (DANGEROUS)}';

    protected $description = 'Restores a database backup archive to the production database';

    public function handle(): int
    {
        $this->warn('╔══════════════════════════════════════════════════════╗');
        $this->warn('║     ⚠  PRODUCTION DATABASE RESTORE  ⚠                ║');
        $this->warn('║  This will REPLACE all production data with the      ║');
        $this->warn('║  contents of the selected backup archive.            ║');
        $this->warn('╚══════════════════════════════════════════════════════╝');
        $this->newLine();

        // ── Step 1: Pick the archive ──────────────────────────────────────────
        $archivePath = $this->argument('archive');

        if ($archivePath === null) {
            $archivePath = $this->pickArchiveInteractively();
            if ($archivePath === null) {
                $this->error('Aborted — no backup archive selected.');

                return self::FAILURE;
            }
        }

        if (! file_exists($archivePath)) {
            $this->error("Archive not found: {$archivePath}");

            return self::FAILURE;
        }

        $this->info('Selected: '.basename($archivePath));
        $this->info('Size:     '.$this->humanFileSize((int) filesize($archivePath)));
        $this->info('Created:  '.date('Y-m-d H:i:s', (int) filemtime($archivePath)));
        $this->newLine();

        // ── Step 2: Require explicit confirmation ─────────────────────────────
        $confirm = $this->ask('Type CONFIRM to proceed (or anything else to abort)');
        if ($confirm !== 'CONFIRM') {
            $this->info('Aborted — confirmation was not typed.');

            return self::FAILURE;
        }

        $dbConfig = config('database.connections.pgsql');
        $host = $dbConfig['host'];
        $port = $dbConfig['port'];
        $user = $dbConfig['username'];
        $password = $dbConfig['password'];
        $prodDb = $dbConfig['database'];

        // ── Step 3: Safety backup of current state ────────────────────────────
        if (! $this->option('skip-safety-backup')) {
            $this->info('Step 1/4: Creating safety backup of current production database...');
            $safetyResult = Process::timeout(300)->run('php artisan backup:run --only-db 2>&1');

            if ($safetyResult->failed()) {
                $this->error('✗ Safety backup FAILED. Restore aborted to protect your data.');
                $this->line($safetyResult->output());

                return self::FAILURE;
            }
            $this->line('<info>✓</info> Safety backup created');
        } else {
            $this->warn('Step 1/4: Safety backup SKIPPED (--skip-safety-backup flag)');
        }

        // ── Step 4: Extract archive ───────────────────────────────────────────
        $this->info('Step 2/4: Extracting archive...');
        $extractDir = storage_path('app/backup-temp/restore-'.now()->format('YmdHis'));
        File::ensureDirectoryExists($extractDir);

        $unzip = Process::timeout(120)->run("unzip -o \"{$archivePath}\" -d \"{$extractDir}\" 2>&1");
        if ($unzip->failed()) {
            File::deleteDirectory($extractDir);
            $this->error('✗ Failed to extract backup archive.');
            $this->line($unzip->output());

            return self::FAILURE;
        }

        // Locate the SQL dump
        $sqlFiles = File::glob("{$extractDir}/**/*.sql") ?: File::glob("{$extractDir}/*.sql");
        $gzFiles = File::glob("{$extractDir}/**/*.sql.gz") ?: File::glob("{$extractDir}/*.sql.gz");
        $sqlFile = null;

        if (! empty($gzFiles)) {
            $gzFile = reset($gzFiles);
            $sqlFile = str_replace('.gz', '', $gzFile);
            Process::run("gunzip -f \"{$gzFile}\"");
            $sqlFile = file_exists($sqlFile) ? $sqlFile : null;
        } elseif (! empty($sqlFiles)) {
            $sqlFile = reset($sqlFiles);
        }

        if ($sqlFile === null) {
            File::deleteDirectory($extractDir);
            $this->error('✗ No .sql or .sql.gz file found inside the archive.');

            return self::FAILURE;
        }
        $this->line('<info>✓</info> SQL dump extracted: '.basename($sqlFile));

        // ── Step 5: Replace production schema ────────────────────────────────
        $this->info('Step 3/4: Replacing production database schema...');

        try {
            DB::statement('DROP SCHEMA public CASCADE;');
            DB::statement('CREATE SCHEMA public;');
            DB::statement("GRANT ALL ON SCHEMA public TO \"{$user}\";");
            DB::statement('GRANT ALL ON SCHEMA public TO public;');
        } catch (\Throwable $e) {
            File::deleteDirectory($extractDir);
            $this->error('✗ Failed to reset schema: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line('<info>✓</info> Schema wiped and recreated');

        // ── Step 6: Restore the dump ──────────────────────────────────────────
        $this->info('Step 4/4: Restoring production database...');

        $restoreResult = Process::timeout(600)->env(['PGPASSWORD' => $password])->run(
            "psql -h {$host} -p {$port} -U {$user} -d \"{$prodDb}\" -f \"{$sqlFile}\" 2>&1",
        );

        File::deleteDirectory($extractDir);

        if ($restoreResult->failed() && str_contains($restoreResult->output(), 'ERROR:')) {
            $this->error('✗ Restore failed with errors.');
            $this->line($restoreResult->output());
            $this->warn('A safety backup was taken before this attempt. Run backup:restore again with that file to recover.');

            return self::FAILURE;
        }

        $this->line('<info>✓</info> Production database restored');

        // Log the restore event
        Log::warning('[BackupRestore] Production database restored via CLI', [
            'archive' => basename($archivePath),
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->newLine();
        $this->info('✅ Restore complete — production database has been replaced with: '.basename($archivePath));
        $this->warn('All currently active user sessions are now invalid. Users will need to log in again.');

        return self::SUCCESS;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function pickArchiveInteractively(): ?string
    {
        $files = $this->getAllBackupFiles();

        if (empty($files)) {
            $this->error('No backup archives found in storage/app/');

            return null;
        }

        $choices = array_map(function (string $path) {
            return sprintf(
                '%s  (%s — %s ago)',
                basename($path),
                $this->humanFileSize((int) filesize($path)),
                $this->humanAge((int) filemtime($path)),
            );
        }, $files);

        $choice = $this->choice('Select a backup archive to restore:', $choices, 0);
        $index = array_search($choice, $choices, true);

        return $index !== false ? $files[(int) $index] : null;
    }

    private function getAllBackupFiles(): array
    {
        $searchDir = storage_path('app');

        if (! is_dir($searchDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'zip') {
                $files[] = $file->getPathname();
            }
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files;
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2).' GB';
        }
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }
        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function humanAge(int $mtime): string
    {
        $diff = time() - $mtime;
        if ($diff < 3600) {
            return (int) ($diff / 60).' min';
        }
        if ($diff < 86400) {
            return (int) ($diff / 3600).' hr';
        }

        return (int) ($diff / 86400).' days';
    }
}
