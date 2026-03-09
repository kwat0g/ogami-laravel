<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            'created_at'  => date('M j, Y g:i A', (int) filemtime($path)),
            'age_days'    => (int) floor((time() - (int) filemtime($path)) / 86400),
            'type'        => $this->getBackupType(basename($path)),
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
                    'created_at' => date('M j, Y g:i A', (int) filemtime($latest)),
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

        $filesBeforeRun = $this->getAllBackupFiles();

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

        // Rename the spatie-generated archive to a human-readable filename.
        $filesAfterRun = $this->getAllBackupFiles();
        $newFiles      = array_values(array_diff($filesAfterRun, $filesBeforeRun));
        $newest        = $newFiles[0] ?? ($filesAfterRun[0] ?? null);

        if ($newest !== null) {
            $readable = now()->format('M-d-Y_H-i-s');
            $newName  = 'ogami-erp-backup-'.$readable.'.zip';
            $newPath  = dirname($newest).'/'.$newName;
            if (@rename($newest, $newPath)) {
                $newest = $newPath;
            }
        }

        // Verify the archive with 7z (supports AES-256 used by spatie).
        // unzip 6.x cannot handle AES-256 encrypted zips (PK compat v5.1).
        if ($newest !== null) {
            $archivePassword = config('backup.backup.password');
            $testPassFlag    = ($archivePassword !== null && $archivePassword !== '')
                ? '-p'.escapeshellarg((string) $archivePassword).' '
                : '';
            $testResult = Process::timeout(60)->run("7z t {$testPassFlag}\"{$newest}\" 2>&1");
            // Retry without password in case encryption was skipped on this run
            if ($testResult->failed() && $testPassFlag !== '') {
                $testResult = Process::timeout(60)->run("7z t \"{$newest}\" 2>&1");
            }
            if ($testResult->failed()) {
                @unlink($newest);
                return response()->json([
                    'success'    => false,
                    'error_code' => 'BACKUP_CORRUPTED',
                    'message'    => 'Backup was created but failed integrity check — the archive is unreadable. Check the BACKUP_ARCHIVE_PASSWORD setting.',
                    'detail'     => substr($testResult->output(), -500),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Backup completed successfully.',
            'data'    => $newest ? [
                'filename'   => basename($newest),
                'size_human' => $this->humanFileSize((int) filesize($newest)),
                'created_at' => date('M j, Y g:i A', (int) filemtime($newest)),
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

        try {
            return $this->doRestore($request, $validated);
        } catch (\Throwable $e) {
            // Clear the in-progress flag so the maintenance modal dismisses.
            // Use 'done' only if the schema may have already been dropped so
            // clients are redirected to login; otherwise just forget the flag.
            Cache::forget('system.restore_in_progress');

            Log::error('[BackupRestore] Unhandled exception during restore', [
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'filename' => $validated['filename'] ?? null,
            ]);

            return response()->json([
                'success'    => false,
                'error_code' => 'RESTORE_EXCEPTION',
                'message'    => 'Restore failed unexpectedly: '.$e->getMessage(),
            ], 500);
        }
    }

    private function doRestore(Request $request, array $validated): JsonResponse
    {
        // Backup + restore can take several minutes. Remove the PHP execution
        // time limit for this request only so it is not killed by php-fpm's
        // max_execution_time (default 30 s) before the work finishes.
        set_time_limit(0);

        // ── Locate archive (no DB / cache changes yet) ────────────────────────
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

        // ── Extract + validate archive FIRST (before touching anything) ───────
        $extractDir = storage_path('app/backup-temp/restore-ui-'.now()->format('YmdHis'));
        File::ensureDirectoryExists($extractDir);

        $archivePassword = config('backup.backup.password');
        $passwordFlag    = ($archivePassword !== null && $archivePassword !== '')
            ? '-p'.escapeshellarg((string) $archivePassword).' '
            : '';

        // Use 7z for extraction: unlike unzip 6.x it supports AES-256 (PK compat v5.1)
        // which is what PHP's ZipArchive uses when BACKUP_ARCHIVE_PASSWORD is set.
        $unzip = Process::timeout(120)->run("7z x {$passwordFlag}-o\"{$extractDir}\" \"{$archivePath}\" 2>&1");

        // Retry without password in case this particular archive was created without encryption.
        if ($unzip->failed() && $passwordFlag !== '') {
            File::deleteDirectory($extractDir);
            File::ensureDirectoryExists($extractDir);
            $unzip = Process::timeout(120)->run("7z x -o\"{$extractDir}\" \"{$archivePath}\" 2>&1");
        }

        if ($unzip->failed()) {
            File::deleteDirectory($extractDir);

            return response()->json([
                'success'    => false,
                'error_code' => 'EXTRACT_FAILED',
                'message'    => 'Failed to extract backup archive.',
                'detail'     => substr($unzip->output(), -1000),
            ], 500);
        }

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

        // ── Archive is valid — now signal all clients that restore is starting ─
        // Only set this flag AFTER the archive passes validation so that
        // pre-flight failures (file not found, bad zip, no SQL) never show
        // the maintenance modal. Stored in Redis so it survives schema drop.
        Cache::put('system.restore_in_progress', true, 1800);

        // ── Safety backup (only after archive is confirmed valid) ─────────────
        // Snapshot files BEFORE the backup runs so we can reliably identify the
        // newly created archive afterward — even when the restore target itself
        // has a recent mtime that could cause it to rank as "newest".
        $filesBeforeSafety = $this->getAllBackupFiles();

        $safetyExitCode = Artisan::call('backup:run', ['--only-db' => true]);
        if ($safetyExitCode !== 0) {
            File::deleteDirectory($extractDir);
            // DB is still intact — clear the flag so the modal is dismissed.
            Cache::forget('system.restore_in_progress');

            return response()->json([
                'success'    => false,
                'error_code' => 'SAFETY_BACKUP_FAILED',
                'message'    => 'Could not create a safety backup of the current database. Restore aborted to protect your data.',
                'detail'     => substr(Artisan::output(), -1000),
            ], 500);
        }

        // Rename the safety backup so it is clearly labelled.
        // Identify the newly created file by comparing before/after snapshots.
        // This is far more reliable than getAllBackupFiles()[0] because it is
        // immune to mtime collisions with the restore target (e.g. when restoring
        // a pre-restore/safety file whose mtime equals the new backup's mtime).
        $safetyPath       = null;
        $filesAfterSafety = $this->getAllBackupFiles();
        $newFiles         = array_values(array_diff($filesAfterSafety, $filesBeforeSafety));
        $newest           = $newFiles[0] ?? null;

        // Fallback: if the snapshot diff found nothing (unlikely — e.g. NFS mtime
        // granularity), pick the newest file that is NOT the restore target.
        if ($newest === null) {
            foreach ($filesAfterSafety as $candidate) {
                if (basename($candidate) !== basename($archivePath)) {
                    $newest = $candidate;
                    break;
                }
            }
        }

        if ($newest !== null) {
            $safetyTimestamp = now()->format('M-d-Y_H-i-s');
            $labelledName    = 'safety-'.$safetyTimestamp.'-before-restore.zip';
            $safetyPath      = dirname($newest).'/'.$labelledName;
            @rename($newest, $safetyPath);
        }

        // ── Notify all connected users that a restore is about to begin ───────
        // ShouldBroadcastNow fires synchronously (no queue), so the WebSocket
        // message reaches every browser before we wipe the DB.
        // The 2-second sleep gives Reverb time to push the event to all clients.
        // Wrapped in try/catch: a Reverb/Pusher HTTP error must never abort the
        // restore — the polling-based overlay handles the missing broadcast.
        try {
            event(new \App\Events\System\SystemRestoreStarting(
                filename:    $validated['filename'],
                initiatedBy: Auth::user()->email ?? 'unknown',
            ));
        } catch (\Throwable) {
            Log::warning('[BackupRestore] Could not broadcast SystemRestoreStarting — continuing restore.');
        }
        sleep(2);

        // ── Wipe production schema ────────────────────────────────────────────
        try {
            DB::statement('DROP SCHEMA public CASCADE;');
            DB::statement('CREATE SCHEMA public;');
            DB::statement("GRANT ALL ON SCHEMA public TO \"{$user}\";");
            DB::statement('GRANT ALL ON SCHEMA public TO public;');
        } catch (\Throwable $e) {
            File::deleteDirectory($extractDir);
            // DB is still intact (DROP failed) — clear the flag so the modal dismisses.
            Cache::forget('system.restore_in_progress');
            if ($safetyPath !== null && file_exists($safetyPath)) {
                $abortedName = str_replace('safety-', 'safety-aborted-', basename($safetyPath));
                @rename($safetyPath, dirname($safetyPath).'/'.$abortedName);
            }

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
            // Schema was already dropped — the DB is in a broken state.
            // Set the flag to 'done' so the overlay redirects everyone to login
            // (the app cannot function until the safety backup is restored).
            Cache::put('system.restore_in_progress', 'done', 30);

            return response()->json([
                'success'    => false,
                'error_code' => 'RESTORE_FAILED',
                'message'    => 'Database restore failed after the schema was reset. Use the safety backup to recover. All users have been directed to log in.',
                'detail'     => substr($restoreResult->output(), -2000),
            ], 500);
        }

        Log::warning('[BackupRestore] Production database restored via admin UI', [
            'by_user_id' => Auth::id(),
            'by_user'    => Auth::user()->email,
            'archive'    => $validated['filename'],
            'timestamp'  => now()->toIso8601String(),
        ]);

        // Flush every Redis session so all users (including the current admin)
        // are forced to log in again with the restored data.
        //
        // We MUST invalidate the current request session BEFORE the Redis scan,
        // because Laravel's session middleware re-saves the session after the
        // controller returns — so deleting it mid-request is not enough for the
        // current user. Invalidating here marks it as deleted; the middleware
        // will then write a brand-new, unauthenticated session instead.
        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable) {
            Log::warning('[BackupRestore] Could not invalidate current session after restore.');
        }

        // Delete all other sessions from Redis.
        // PhpRedis auto-applies the prefix, so keys() returns fully-prefixed
        // strings while del() expects the bare (un-prefixed) key names.
        try {
            $redisPrefix = config('database.redis.options.prefix', '');
            $allKeys     = \Illuminate\Support\Facades\Redis::keys('*');
            $stripped    = array_filter(
                array_map(fn (string $k) => substr($k, strlen($redisPrefix)), $allKeys),
                static fn (string $bare): bool =>
                    (bool) preg_match('/[a-zA-Z0-9]{40}$/', $bare) && ! str_contains($bare, ':'),
            );
            if (! empty($stripped)) {
                \Illuminate\Support\Facades\Redis::del(array_values($stripped));
            }
        } catch (\Throwable) {
            Log::warning('[BackupRestore] Could not flush Redis sessions after restore.');
        }

        // Keep the in-progress flag alive for 15 s after the restore completes so
        // that slow-polling clients (2 s interval) are guaranteed to catch the
        // 'done' state before the key expires.  The frontend detects the 'done'
        // value, transitions to a "Restore complete — redirecting" overlay, and
        // calls window.location.replace('/login') after a short countdown.
        // Using a short TTL (rather than Cache::forget) closes the race window
        // where the poll checked JUST before the flag was cleared and missed it.
        Cache::put('system.restore_in_progress', 'done', 15);

        // Notify all still-connected WebSocket clients to redirect to /login.
        // This covers idle users who would not otherwise get a 401 response.
        // ShouldBroadcastNow fires via Redis pub/sub (no DB required), so it
        // works correctly even after the schema was dropped and rebuilt.
        try {
            event(new \App\Events\System\SystemRestoreCompleted(
                filename: $validated['filename'],
            ));
        } catch (\Throwable) {
            Log::warning('[BackupRestore] Could not broadcast SystemRestoreCompleted.');
        }

        return response()->json([
            'success' => true,
            'message' => "Database successfully restored from '{$validated['filename']}'. All users have been logged out.",
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

    /**
     * Derives the backup type from the filename.
     *   'safety'  → created automatically before a restore operation
     *   'regular' → Spatie-scheduled or manual on-demand backup
     */
    private function getBackupType(string $filename): string
    {
        // New naming: safety-{timestamp}-before-restore.zip / safety-aborted-...
        if (str_starts_with($filename, 'safety-')) {
            return 'safety';
        }
        // Legacy naming from older versions: pre-restore--...zip / aborted-restore--...zip
        if (str_starts_with($filename, 'pre-restore--') || str_starts_with($filename, 'aborted-restore--')) {
            return 'safety';
        }

        return 'regular';
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
