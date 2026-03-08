<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Sessions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Session manager using file-based IPC for cross-worker persistence.
 *
 * Spawns a background worker process (session-worker.php) that manages a PTY
 * session, relaying I/O through files in a session directory. Session metadata
 * is stored in Laravel's cache for cross-worker access.
 *
 * This approach works with any PHP deployment (FPM, Octane, etc.) without
 * requiring tmux or screen.
 */
class FileSessionManager implements SessionManagerInterface
{
    protected const CACHE_PREFIX = 'swt:file:';

    protected int $maxSessionLifetime = 300; // 5 minutes

    protected string $sessionBaseDir;

    public function __construct()
    {
        $this->sessionBaseDir = storage_path('app/web-terminal/sessions');
    }

    public static function isAvailable(): bool
    {
        return Process::isPtySupported();
    }

    public function start(
        string $command,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
    ): string {
        $sessionId = substr(Str::uuid()->toString(), 0, 12);
        $sessionDir = $this->sessionBaseDir.'/'.$sessionId;

        // Create session directory
        if (! is_dir($sessionDir)) {
            mkdir($sessionDir, 0700, true);
        }

        // Create empty stdin and stdout files
        file_put_contents($sessionDir.'/stdin', '');
        chmod($sessionDir.'/stdin', 0600);
        file_put_contents($sessionDir.'/stdout', '');
        chmod($sessionDir.'/stdout', 0600);

        // Build worker command
        $workerScript = __DIR__.'/session-worker.php';
        $workerArgs = [
            $this->resolvePhpBinary(),
            $workerScript,
            $sessionDir,
            $command,
            $cwd ?? '',
            $env !== null ? json_encode($env) : '',
        ];

        $errorLog = $sessionDir.'/worker_error.log';
        $shellCommand = sprintf(
            'nohup %s %s %s %s %s %s > /dev/null 2>%s &',
            escapeshellarg($workerArgs[0]),
            escapeshellarg($workerArgs[1]),
            escapeshellarg($workerArgs[2]),
            escapeshellarg($workerArgs[3]),
            escapeshellarg($workerArgs[4]),
            escapeshellarg($workerArgs[5]),
            escapeshellarg($errorLog),
        );

        shell_exec($shellCommand);

        // Wait for PID file
        $pidFile = $sessionDir.'/pid';
        $pid = null;

        for ($i = 0; $i < 50; $i++) {
            clearstatcache(true, $pidFile);
            if (file_exists($pidFile)) {
                $content = trim((string) file_get_contents($pidFile));
                if ($content !== '') {
                    $pid = (int) $content;
                    break;
                }
            }
            usleep(20000); // 20ms
        }

        if ($pid === null) {
            // Read error log for debugging
            $errorOutput = '';
            if (file_exists($errorLog)) {
                $errorOutput = trim((string) file_get_contents($errorLog));
            }

            // Cleanup on failure
            $this->removeDirectory($sessionDir);

            throw new \RuntimeException(
                'Failed to start session worker: PID file not created.'
                .($errorOutput !== '' ? ' Worker error: '.$errorOutput : '')
            );
        }

        $sessionData = new SharedSessionData(
            sessionId: $sessionId,
            command: $command,
            pid: $pid,
            startedAt: time(),
            backend: 'file',
        );

        $this->saveSessionData($sessionData);

        return $sessionId;
    }

    public function getOutput(string $sessionId): ?array
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        $stdoutFile = $this->sessionBaseDir.'/'.$sessionId.'/stdout';

        if (! file_exists($stdoutFile)) {
            return ['stdout' => '', 'stderr' => ''];
        }

        clearstatcache(true, $stdoutFile);
        $fileSize = filesize($stdoutFile);

        if ($fileSize === false || $fileSize <= $sessionData->lastOutputPosition) {
            return ['stdout' => '', 'stderr' => ''];
        }

        $handle = fopen($stdoutFile, 'r');
        if ($handle === false) {
            return ['stdout' => '', 'stderr' => ''];
        }

        fseek($handle, $sessionData->lastOutputPosition);
        $newOutput = fread($handle, $fileSize - $sessionData->lastOutputPosition);
        fclose($handle);

        if ($newOutput === false) {
            $newOutput = '';
        }

        $sessionData->lastOutputPosition = $fileSize;
        $sessionData->lastActivity = time();
        $this->saveSessionData($sessionData);

        return ['stdout' => $newOutput, 'stderr' => ''];
    }

    public function sendInput(string $sessionId, string $input): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $stdinFile = $this->sessionBaseDir.'/'.$sessionId.'/stdin';

        if (! file_exists($stdinFile)) {
            return false;
        }

        $written = file_put_contents($stdinFile, $input."\n", FILE_APPEND | LOCK_EX);

        if ($written === false) {
            return false;
        }

        $sessionData->lastActivity = time();
        $this->saveSessionData($sessionData);

        return true;
    }

    public function sendRawInput(string $sessionId, string $input): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $stdinFile = $this->sessionBaseDir.'/'.$sessionId.'/stdin';

        if (! file_exists($stdinFile)) {
            return false;
        }

        $written = file_put_contents($stdinFile, $input, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            return false;
        }

        $sessionData->lastActivity = time();
        $this->saveSessionData($sessionData);

        return true;
    }

    public function isRunning(string $sessionId): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $sessionDir = $this->sessionBaseDir.'/'.$sessionId;
        $exitCodeFile = $sessionDir.'/exit_code';

        // Check if exit_code file exists (worker finished)
        clearstatcache(true, $exitCodeFile);
        if (file_exists($exitCodeFile)) {
            $exitCode = (int) trim((string) file_get_contents($exitCodeFile));
            $sessionData->finished = true;
            $sessionData->exitCode = $exitCode;
            $this->saveSessionData($sessionData);

            return false;
        }

        // Check if PID is alive
        $pid = $sessionData->pid;

        if ($pid > 0 && ! $this->isPidAlive($pid)) {
            $sessionData->finished = true;
            $sessionData->exitCode = -1;
            $this->saveSessionData($sessionData);

            return false;
        }

        return true;
    }

    public function getExitCode(string $sessionId): ?int
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        if ($sessionData->exitCode !== null) {
            return $sessionData->exitCode;
        }

        // Check exit_code file
        $exitCodeFile = $this->sessionBaseDir.'/'.$sessionId.'/exit_code';

        clearstatcache(true, $exitCodeFile);
        if (file_exists($exitCodeFile)) {
            $exitCode = (int) trim((string) file_get_contents($exitCodeFile));
            $sessionData->finished = true;
            $sessionData->exitCode = $exitCode;
            $this->saveSessionData($sessionData);

            return $exitCode;
        }

        return null;
    }

    public function terminate(string $sessionId): bool
    {
        $sessionData = $this->getSessionData($sessionId);
        $sessionDir = $this->sessionBaseDir.'/'.$sessionId;

        // Return false if session doesn't exist at all
        if ($sessionData === null && ! is_dir($sessionDir)) {
            return false;
        }

        if ($sessionData !== null && $sessionData->pid > 0) {
            $pid = $sessionData->pid;

            if ($this->isPidAlive($pid)) {
                posix_kill($pid, SIGTERM);
                usleep(100000); // 100ms

                if ($this->isPidAlive($pid)) {
                    posix_kill($pid, SIGKILL);
                    usleep(50000); // 50ms
                }
            }
        }

        // Remove session directory
        if (is_dir($sessionDir)) {
            $this->removeDirectory($sessionDir);
        }

        // Remove from cache
        $this->removeSessionData($sessionId);

        return true;
    }

    public function hasSession(string $sessionId): bool
    {
        return $this->getSessionData($sessionId) !== null;
    }

    public function getSessionInfo(string $sessionId): ?array
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        return [
            'started_at' => $sessionData->startedAt,
            'last_activity' => $sessionData->lastActivity,
            'is_running' => ! $sessionData->finished && $this->isPidAlive($sessionData->pid),
            'exit_code' => $sessionData->exitCode,
            'pid' => $sessionData->pid,
            'backend' => 'file',
        ];
    }

    public function cleanup(): void
    {
        if (! is_dir($this->sessionBaseDir)) {
            return;
        }

        $entries = scandir($this->sessionBaseDir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sessionDir = $this->sessionBaseDir.'/'.$entry;

            if (! is_dir($sessionDir)) {
                continue;
            }

            $sessionData = $this->getSessionData($entry);

            // Orphaned directory (no cache entry)
            if ($sessionData === null) {
                $this->killSessionProcessFromDir($sessionDir);
                $this->removeDirectory($sessionDir);

                continue;
            }

            // Expired session
            $expired = (time() - $sessionData->lastActivity) > $this->maxSessionLifetime;

            if ($expired) {
                $this->terminate($entry);
            }
        }
    }

    public function getActiveSessionCount(): int
    {
        if (! is_dir($this->sessionBaseDir)) {
            return 0;
        }

        $entries = scandir($this->sessionBaseDir);

        if ($entries === false) {
            return 0;
        }

        $count = 0;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($this->sessionBaseDir.'/'.$entry) && $this->getSessionData($entry) !== null) {
                $count++;
            }
        }

        return $count;
    }

    public function setMaxSessionLifetime(int $seconds): static
    {
        $this->maxSessionLifetime = max(60, $seconds);

        return $this;
    }

    public function setSessionBaseDir(string $dir): static
    {
        $this->sessionBaseDir = $dir;

        return $this;
    }

    public function getSessionBaseDir(): string
    {
        return $this->sessionBaseDir;
    }

    public static function clearAllSessions(): void
    {
        $baseDir = storage_path('app/web-terminal/sessions');

        if (! is_dir($baseDir)) {
            return;
        }

        $entries = scandir($baseDir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sessionDir = $baseDir.'/'.$entry;

            if (! is_dir($sessionDir)) {
                continue;
            }

            // Kill process if PID file exists
            $pidFile = $sessionDir.'/pid';
            if (file_exists($pidFile)) {
                $pid = (int) trim((string) file_get_contents($pidFile));
                if ($pid > 0) {
                    @posix_kill($pid, SIGKILL);
                }
            }

            // Remove directory
            static::removeDirectoryStatic($sessionDir);

            // Remove cache entry
            Cache::forget(static::CACHE_PREFIX.$entry);
        }
    }

    protected function getSessionData(string $sessionId): ?SharedSessionData
    {
        $data = Cache::get(self::CACHE_PREFIX.$sessionId);

        if ($data === null) {
            return null;
        }

        return SharedSessionData::fromArray($data);
    }

    protected function saveSessionData(SharedSessionData $sessionData): void
    {
        Cache::put(
            self::CACHE_PREFIX.$sessionData->sessionId,
            $sessionData->toArray(),
            $this->maxSessionLifetime
        );
    }

    protected function removeSessionData(string $sessionId): void
    {
        Cache::forget(self::CACHE_PREFIX.$sessionId);
    }

    protected function resolvePhpBinary(): string
    {
        // PHP_BINARY returns 'php-fpm' under FPM, which can't run CLI scripts.
        // Try to find the CLI binary in the same directory or common locations.
        $binary = PHP_BINARY;
        $dir = dirname($binary);

        // If already a CLI binary, use it
        if (! str_contains(basename($binary), 'fpm')) {
            return $binary;
        }

        // Look for 'php' CLI in the same directory as php-fpm
        $cliBinary = $dir.'/php';
        if (is_executable($cliBinary)) {
            return $cliBinary;
        }

        // Try common PHP CLI paths
        foreach (['/usr/local/bin/php', '/usr/bin/php', '/opt/homebrew/bin/php'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // Last resort: rely on PATH
        $which = trim((string) shell_exec('which php 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }

        return $binary;
    }

    protected function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (is_dir('/proc/'.$pid)) {
            return true;
        }

        return @posix_kill($pid, 0);
    }

    protected function removeDirectory(string $dir): void
    {
        static::removeDirectoryStatic($dir);
    }

    protected static function removeDirectoryStatic(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            if (is_dir($path)) {
                static::removeDirectoryStatic($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    protected function killSessionProcessFromDir(string $sessionDir): void
    {
        $pidFile = $sessionDir.'/pid';

        if (! file_exists($pidFile)) {
            return;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));

        if ($pid > 0 && $this->isPidAlive($pid)) {
            posix_kill($pid, SIGKILL);
        }
    }
}
