<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin Backup Management Controller.
 *
 * Provides read/write operations for the backup management UI.
 * All endpoints require the `system.manage_backups` permission (admin only).
 *
 * Endpoints:
 *   GET    /api/v1/admin/backups           — list all backup archives
 *   GET    /api/v1/admin/backups/status    — quick status card data
 *   POST   /api/v1/admin/backups/run       — trigger a fresh on-demand backup
 *   POST   /api/v1/admin/backups/restore   — restore a selected archive to production
 *   GET    /api/v1/admin/backups/download  — download a backup archive
 *
 * @permission system.manage_backups
 */
final class BackupController extends Controller
{
    // ── List all backup archives ──────────────────────────────────────────────

    /**
     * GET /api/v1/admin/backups
     *
     * Returns all .zip archives found under storage/app/, sorted newest first.
     */
    public function index(): JsonResponse
    {
        abort_unless(Auth::user()->can('system.manage_backups'), 403, 'Insufficient permissions.');

        $files = $this->getAllBackupFiles();

        $data = array_map(fn (string $path) => [
            'filename'    => basename($path),
            'size_bytes'  => filesize($path),
            'size_human'  => $this->humanFileSize((int) filesize($path)),
            'created_at'  => date('Y-m-d H:i:s', (int) filemtime($path)),
            'age_days'    => (int) floor((time() - (int) filemtime($path)) / 86400),
        ], $files);

        return response()->json(['data' => array_values($data)]);
    }

    // ── Quick status card data ────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/backups/status
     *
     * Lightweight endpoint for the status card on the backup page.
     */
    public function status(): JsonResponse
    {
        abort_unless(Auth::user()->can('system.manage_backups'), 403, 'Insufficient permissions.');

        $files  = $this->getAllBackupFiles();
        $latest = $files[0] ?? null;

        return response()->json([
            'data' => [
                'backup_count'  => count($files),
                'latest_backup' => $latest ? [
                    'filename'   => basename($latest),
                    'size_human' => $this->humanFileSize((int) filesize($latest)),
                    'created_at' => date('Y-m-d H:i:s', (int) filemtime($latest)),
                    'age_days'   => (int) floor((time() - (int) filemtime($latest)) / 86400),
                ] : null,
            ],
        ]);
    }

    // ── Trigger an on-demand backup ───────────────────────────────────────────

    /**
     * POST /api/v1/admin/backups/run
     *
     * Runs `php artisan backup:run --only-db` synchronously.
     * Usually completes in 5–30s for a typical ERP DB size.
     */
    public function run(): JsonResponse
    {
        abort_unless(Auth::user()->can('system.manage_backups'), 403, 'Insufficient permissions.');

        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
        $output   = Artisan::output();

        if ($exitCode !== 0) {
            return response()->json([
                'success'    => false,
                'error_code' => 'BACKUP_FAILED',
                'message'    => 'Backup process failed. Check server logs for details.',
                'detail'     => substr($output, -2000),
            ], 500);
        }

        Log::info('[Backup] On-demand backup triggered via admin UI', [
            'by_user_id' => Auth::id(),
            'by_user'    => Auth::user()->email,
        ]);

        $files  = $this->getAllBackupFiles();
        $latest = $files[0] ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Backup completed successfully.',
            'data'    => $latest ? [
                'filename'   => basename($latest),
                'size_human' => $this->humanFileSize((int) filesize($latest)),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($latest)),
            ] : null,
        ]);
    }

    // ── Restore a backup archive to production ────────────────────────────────

    /**
     * POST /api/v1/admin/backups/restore
     *
     * Restores the selected archive into the production database.
     *
     * Body: { filename: string, confirm: "CONFIRM" }
     *
     * Safety measures applied:
     *   1. Validates `confirm === "CONFIRM"` (server-side)
     *   2. Creates a safety backup of the current production DB first
     *   3. Drops and recreates the public schema (wipes all tables)
     *   4. Restores from the selected archive via psql
     *   5. Logs who performed the restore and from which file
     */
    public function restore(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('system.manage_backups'), 403, 'Insufficient permissions.');

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'confirm'  => ['required', 'in:CONFIRM'],
        ]);

        // Locate the archive on disk
        $archivePath = $this->findArchiveByFilename($validated['filename']);

        if ($archivePath === null) {
            return response()->json([
                'success'    => false,
                'error_code' => 'BACKUP_FILE_NOT_FOUND',
                'message'    => "Backup file '{$validated['filename']}' not found on server.",
            ], 404);
        }

        $dbConfig = config('database.connections.pgsql');
        $host     = (string) $dbConfig['host'];
        $port     = (string) $dbConfig['port'];
        $user     = (string) $dbConfig['username'];
        $password = (string) $dbConfig['password'];
        $prodDb   = (string) $dbConfig['database'];

        // ── Safety backup ─────────────────────────────────────────────────────
        $safetyExitCode = Artisan::call('backup:run', ['--only-db' => true]);
        if ($safetyExitCode !== 0) {
            return response()->json([
                'success'    => false,
                'error_code' => 'SAFETY_BACKUP_FAILED',
                'message'    => 'Could not create a safety backup of the current database. Restore aborted to protect your data.',
                'detail'     => substr(Artisan::output(), -1000),
            ], 500);
        }

        // ── Extract archive ───────────────────────────────────────────────────
        $extractDir = storage_path('app/backup-temp/restore-ui-'.now()->format('YmdHis'));
        File::ensureDirectoryExists($extractDir);

        // Pass archive password if encryption is configured
        $archivePassword = config('backup.backup.password');
        $passwordFlag    = ($archivePassword !== null && $archivePassword !== '')
            ? '-P '.escapeshellarg((string) $archivePassword).' '
            : '';

        $unzip = Process::timeout(120)->run("unzip -o {$passwordFlag}\"{$archivePath}\" -d \"{$extractDir}\" 2>&1");
        if ($unzip->failed()) {
            File::deleteDirectory($extractDir);

            return response()->json([
                'success'    => false,
                'error_code' => 'EXTRACT_FAILED',
                'message'    => 'Failed to extract backup archive. If the archive is password-protected, verify BACKUP_ARCHIVE_PASSWORD matches what was set when the backup was created.',
                'detail'     => substr($unzip->output(), -1000),
            ], 500);
        }

        // Locate SQL dump inside the archive
        $sqlFiles = File::glob("{$extractDir}/**/*.sql") ?: File::glob("{$extractDir}/*.sql");
        $gzFiles  = File::glob("{$extractDir}/**/*.sql.gz") ?: File::glob("{$extractDir}/*.sql.gz");
        $sqlFile  = null;

        if (! empty($gzFiles)) {
            $gzFile  = reset($gzFiles);
            $sqlFile = str_replace('.gz', '', $gzFile);
            Process::run("gunzip -f \"{$gzFile}\"");
            $sqlFile = file_exists($sqlFile) ? $sqlFile : null;
        } elseif (! empty($sqlFiles)) {
            $sqlFile = reset($sqlFiles);
        }

        if ($sqlFile === null) {
            File::deleteDirectory($extractDir);

            return response()->json([
                'success'    => false,
                'error_code' => 'NO_SQL_FILE',
                'message'    => 'No SQL dump found inside the backup archive.',
            ], 422);
        }

        // ── Wipe production schema ────────────────────────────────────────────
        // Drop and recreate the public schema so every table, sequence, index
        // is removed before the restore. This avoids duplicate-object errors.
        try {
            DB::statement('DROP SCHEMA public CASCADE;');
            DB::statement('CREATE SCHEMA public;');
            DB::statement("GRANT ALL ON SCHEMA public TO \"{$user}\";");
            DB::statement('GRANT ALL ON SCHEMA public TO public;');
        } catch (\Throwable $e) {
            File::deleteDirectory($extractDir);

            return response()->json([
                'success'    => false,
                'error_code' => 'SCHEMA_RESET_FAILED',
                'message'    => 'Failed to reset database schema: '.$e->getMessage(),
            ], 500);
        }

        // ── Run the restore ───────────────────────────────────────────────────
        $restoreResult = Process::timeout(600)->env(['PGPASSWORD' => $password])->run(
            "psql -h {$host} -p {$port} -U {$user} -d \"{$prodDb}\" -f \"{$sqlFile}\" 2>&1",
        );

        File::deleteDirectory($extractDir);

        if ($restoreResult->failed() && str_contains($restoreResult->output(), 'ERROR:')) {
            return response()->json([
                'success'    => false,
                'error_code' => 'RESTORE_FAILED',
                'message'    => 'Database restore failed with errors. A safety backup was taken before this attempt; use it to recover.',
                'detail'     => substr($restoreResult->output(), -2000),
            ], 500);
        }

        Log::warning('[BackupRestore] Production database restored via admin UI', [
            'by_user_id' => Auth::id(),
            'by_user'    => Auth::user()->email,
            'archive'    => $validated['filename'],
            'timestamp'  => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Database successfully restored from '{$validated['filename']}'. All users need to log in again.",
        ]);
    }

    // ── Download a backup archive ─────────────────────────────────────────────

    /**
     * GET /api/v1/admin/backups/download?file=filename.zip
     */
    public function download(Request $request): JsonResponse|BinaryFileResponse
    {
        abort_unless(Auth::user()->can('system.manage_backups'), 403, 'Insufficient permissions.');

        $filename    = $request->query('file', '');
        $archivePath = $this->findArchiveByFilename((string) $filename);

        if ($archivePath === null) {
            return response()->json([
                'success'    => false,
                'error_code' => 'BACKUP_FILE_NOT_FOUND',
                'message'    => "File '{$filename}' not found.",
            ], 404);
        }

        Log::info('[Backup] Archive downloaded via admin UI', [
            'by_user_id' => Auth::id(),
            'by_user'    => Auth::user()->email,
            'filename'   => $filename,
        ]);

        return response()->download($archivePath, $filename);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /** Returns all .zip backup archives sorted by modification time (newest first). */
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

    /** Finds an archive by its basename. Returns null if not found or path escapes storage dir. */
    private function findArchiveByFilename(string $filename): ?string
    {
        // Sanitise: reject any path traversal attempts
        if ($filename !== basename($filename) || $filename === '') {
            return null;
        }

        foreach ($this->getAllBackupFiles() as $path) {
            if (basename($path) === $filename) {
                return $path;
            }
        }

        return null;
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
}
