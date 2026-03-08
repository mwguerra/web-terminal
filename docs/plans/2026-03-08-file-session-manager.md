# FileSessionManager Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a pure-PHP file-based session manager that enables interactive commands (REPLs, long-running processes) to work across PHP-FPM workers without requiring tmux.

**Architecture:** A background PHP worker process per session owns the PTY via `proc_open`. FPM workers communicate through the filesystem (stdin/stdout files). Session metadata is stored in Laravel's Cache (respects `CACHE_STORE`). The `FileSessionManager` implements the existing `SessionManagerInterface` and slots between `TmuxSessionManager` and `ProcessSessionManager` in the auto-detection chain.

**Tech Stack:** PHP 8.2+, Symfony Process (PTY), Laravel Cache, Pest testing

---

### Task 1: Create the session-worker.php background script

**Files:**
- Create: `src/Sessions/session-worker.php`

**Step 1: Write the background worker script**

This is a standalone PHP script spawned as a detached process. It owns the PTY and relays I/O through files.

```php
<?php

declare(strict_types=1);

/**
 * Background session worker for FileSessionManager.
 *
 * This script is spawned as a detached process to manage an interactive
 * PTY session. It relays I/O between temp files and the PTY:
 * - Reads stdin file → writes to PTY stdin
 * - Reads PTY stdout/stderr → writes to stdout file
 * - Writes exit_code file when the command finishes
 *
 * Usage: php session-worker.php <session-dir> <command> [cwd] [env-json]
 */

// Validate arguments
if ($argc < 3) {
    fwrite(STDERR, "Usage: php session-worker.php <session-dir> <command> [cwd] [env-json]\n");
    exit(1);
}

$sessionDir = $argv[1];
$command = $argv[2];
$cwd = ($argc >= 4 && $argv[3] !== '') ? $argv[3] : null;
$envJson = ($argc >= 5) ? $argv[4] : null;

// Parse environment variables
$env = null;
if ($envJson !== null && $envJson !== '') {
    $env = json_decode($envJson, true);
    if (!is_array($env)) {
        $env = null;
    }
}

// Validate session directory exists
if (!is_dir($sessionDir)) {
    fwrite(STDERR, "Session directory does not exist: {$sessionDir}\n");
    exit(1);
}

$stdinFile = $sessionDir . '/stdin';
$stdoutFile = $sessionDir . '/stdout';
$pidFile = $sessionDir . '/pid';
$exitCodeFile = $sessionDir . '/exit_code';

// Write our PID
file_put_contents($pidFile, (string) getmypid());

// Set up PTY process
$descriptors = [
    0 => ['pty'],
    1 => ['pty'],
    2 => ['pty'],
];

$process = proc_open($command, $descriptors, $pipes, $cwd, $env);

if (!is_resource($process)) {
    file_put_contents($exitCodeFile, '1');
    exit(1);
}

// Set PTY output pipes to non-blocking
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

// Open stdout file for appending
$stdoutHandle = fopen($stdoutFile, 'a');
if (!$stdoutHandle) {
    proc_terminate($process);
    file_put_contents($exitCodeFile, '1');
    exit(1);
}

// Track stdin file read position
$stdinPosition = 0;

// Ensure stdin file exists
if (!file_exists($stdinFile)) {
    touch($stdinFile);
}

// Install signal handler for graceful shutdown
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use (&$running) {
        $running = false;
    });
    pcntl_signal(SIGINT, function () use (&$running) {
        $running = false;
    });
}

// Main I/O relay loop
while ($running) {
    // Check if process is still alive
    $status = proc_get_status($process);
    if (!$status['running']) {
        // Read any remaining output
        $out = stream_get_contents($pipes[1]);
        if ($out !== false && $out !== '') {
            fwrite($stdoutHandle, $out);
            fflush($stdoutHandle);
        }
        $err = stream_get_contents($pipes[2]);
        if ($err !== false && $err !== '') {
            fwrite($stdoutHandle, $err);
            fflush($stdoutHandle);
        }
        break;
    }

    // Dispatch signals if available
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    // Read PTY stdout → append to stdout file
    $out = fread($pipes[1], 8192);
    if ($out !== false && $out !== '') {
        fwrite($stdoutHandle, $out);
        fflush($stdoutHandle);
    }

    // Read PTY stderr → append to stdout file
    $err = fread($pipes[2], 8192);
    if ($err !== false && $err !== '') {
        fwrite($stdoutHandle, $err);
        fflush($stdoutHandle);
    }

    // Read stdin file for new input → write to PTY stdin
    clearstatcache(true, $stdinFile);
    $fileSize = filesize($stdinFile);
    if ($fileSize !== false && $fileSize > $stdinPosition) {
        $stdinHandle = fopen($stdinFile, 'r');
        if ($stdinHandle) {
            fseek($stdinHandle, $stdinPosition);
            $input = fread($stdinHandle, $fileSize - $stdinPosition);
            fclose($stdinHandle);

            if ($input !== false && $input !== '') {
                fwrite($pipes[0], $input);
                fflush($pipes[0]);
                $stdinPosition = $fileSize;
            }
        }
    }

    // Small sleep to prevent CPU spinning
    usleep(10000); // 10ms
}

// Cleanup
fclose($stdoutHandle);
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);

// Get exit code
$exitCode = $status['exitcode'] ?? -1;
if ($exitCode === -1) {
    // proc_get_status only returns real exit code on first call after exit
    // Try proc_close as fallback
    $exitCode = proc_close($process);
} else {
    proc_close($process);
}

// Write exit code
file_put_contents($exitCodeFile, (string) $exitCode);
```

**Step 2: Verify the script is syntactically valid**

Run: `php -l src/Sessions/session-worker.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add src/Sessions/session-worker.php
git commit -m "feat: add session-worker.php background script for FileSessionManager"
```

---

### Task 2: Create FileSessionManager class

**Files:**
- Create: `src/Sessions/FileSessionManager.php`

**Step 1: Write the failing test**

Create `tests/Unit/Sessions/FileSessionManagerTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use MWGuerra\WebTerminal\Sessions\FileSessionManager;

beforeEach(function () {
    $this->manager = new FileSessionManager;
    // Use a unique session dir per test to avoid conflicts
    $this->manager->setSessionBaseDir(storage_path('app/web-terminal/test-sessions-'.uniqid()));
});

afterEach(function () {
    // Cleanup: terminate all sessions and remove test directory
    $baseDir = $this->manager->getSessionBaseDir();
    if (is_dir($baseDir)) {
        // Kill any running processes
        $dirs = glob($baseDir.'/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $pidFile = $dir.'/pid';
            if (file_exists($pidFile)) {
                $pid = (int) file_get_contents($pidFile);
                if ($pid > 0) {
                    @posix_kill($pid, SIGKILL);
                }
            }
        }
        // Wait briefly for processes to die
        usleep(100000);
        // Remove directory tree
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($baseDir);
    }
    Cache::flush();
});

describe('FileSessionManager', function () {
    describe('isAvailable', function () {
        it('reports availability based on PTY support', function () {
            $available = FileSessionManager::isAvailable();
            // On Linux/macOS with proc_open, PTY should be supported
            expect($available)->toBeBool();
        });
    });

    describe('start', function () {
        it('starts a process and returns a session ID', function () {
            $sessionId = $this->manager->start('echo "hello from file session"');

            expect($sessionId)->toBeString();
            expect($sessionId)->not->toBeEmpty();
            expect($this->manager->hasSession($sessionId))->toBeTrue();
        });

        it('creates session directory with required files', function () {
            $sessionId = $this->manager->start('echo "test"');
            $sessionDir = $this->manager->getSessionBaseDir().'/'.$sessionId;

            // Wait for worker to start
            usleep(200000);

            expect(is_dir($sessionDir))->toBeTrue();
            expect(file_exists($sessionDir.'/stdin'))->toBeTrue();
            expect(file_exists($sessionDir.'/stdout'))->toBeTrue();
            expect(file_exists($sessionDir.'/pid'))->toBeTrue();
        });

        it('starts process in specified working directory', function () {
            $sessionId = $this->manager->start('pwd', '/tmp');

            // Wait for output
            usleep(500000);

            $output = $this->manager->getOutput($sessionId);
            expect($output)->not->toBeNull();
            expect($output['stdout'])->toContain('/tmp');
        });
    });

    describe('getOutput', function () {
        it('returns incremental output', function () {
            $sessionId = $this->manager->start('echo "line1"; sleep 0.2; echo "line2"');

            // Wait for first echo
            usleep(300000);
            $output1 = $this->manager->getOutput($sessionId);
            expect($output1['stdout'])->toContain('line1');

            // Wait for second echo
            usleep(400000);
            $output2 = $this->manager->getOutput($sessionId);
            expect($output2['stdout'])->toContain('line2');

            // Output should be incremental (not repeat line1)
            expect($output2['stdout'])->not->toContain('line1');
        });

        it('returns null for nonexistent session', function () {
            expect($this->manager->getOutput('nonexistent'))->toBeNull();
        });
    });

    describe('sendInput', function () {
        it('sends input to a running process', function () {
            // Start a process that reads from stdin
            $sessionId = $this->manager->start('/bin/bash -c "read LINE; echo GOT:\$LINE"');

            usleep(300000);

            // Send input
            $result = $this->manager->sendInput($sessionId, 'hello');
            expect($result)->toBeTrue();

            // Wait for response
            usleep(500000);

            $output = $this->manager->getOutput($sessionId);
            expect($output['stdout'])->toContain('GOT:hello');
        });

        it('returns false for nonexistent session', function () {
            expect($this->manager->sendInput('nonexistent', 'test'))->toBeFalse();
        });
    });

    describe('sendRawInput', function () {
        it('sends raw input without appending newline', function () {
            $sessionId = $this->manager->start('/bin/bash');

            usleep(300000);

            // Send raw input (no auto-newline)
            $result = $this->manager->sendRawInput($sessionId, "echo raw\n");
            expect($result)->toBeTrue();

            usleep(500000);

            $output = $this->manager->getOutput($sessionId);
            expect($output['stdout'])->toContain('raw');
        });
    });

    describe('isRunning', function () {
        it('returns true for a running process', function () {
            $sessionId = $this->manager->start('sleep 5');

            usleep(300000);

            expect($this->manager->isRunning($sessionId))->toBeTrue();
        });

        it('returns false after process finishes', function () {
            $sessionId = $this->manager->start('echo "done"');

            // Wait for process to finish
            usleep(1000000);

            expect($this->manager->isRunning($sessionId))->toBeFalse();
        });

        it('returns false for nonexistent session', function () {
            expect($this->manager->isRunning('nonexistent'))->toBeFalse();
        });
    });

    describe('getExitCode', function () {
        it('returns exit code after process finishes', function () {
            $sessionId = $this->manager->start('echo "success"');

            // Wait for process to complete
            usleep(1000000);

            // Trigger running check to detect completion
            $this->manager->isRunning($sessionId);

            expect($this->manager->getExitCode($sessionId))->toBe(0);
        });

        it('returns non-zero exit code for failed commands', function () {
            $sessionId = $this->manager->start('exit 42');

            usleep(1000000);
            $this->manager->isRunning($sessionId);

            expect($this->manager->getExitCode($sessionId))->toBe(42);
        });
    });

    describe('terminate', function () {
        it('terminates a running process', function () {
            $sessionId = $this->manager->start('sleep 60');

            usleep(300000);
            expect($this->manager->isRunning($sessionId))->toBeTrue();

            $result = $this->manager->terminate($sessionId);
            expect($result)->toBeTrue();

            usleep(300000);

            expect($this->manager->isRunning($sessionId))->toBeFalse();
        });

        it('cleans up session directory', function () {
            $sessionId = $this->manager->start('sleep 60');
            $sessionDir = $this->manager->getSessionBaseDir().'/'.$sessionId;

            usleep(300000);

            $this->manager->terminate($sessionId);
            usleep(200000);

            expect(is_dir($sessionDir))->toBeFalse();
        });
    });

    describe('cleanup', function () {
        it('removes expired sessions', function () {
            $this->manager->setMaxSessionLifetime(1); // 1 second

            $sessionId = $this->manager->start('sleep 60');

            usleep(300000);
            expect($this->manager->hasSession($sessionId))->toBeTrue();

            // Wait for expiry
            sleep(2);

            $this->manager->cleanup();

            expect($this->manager->hasSession($sessionId))->toBeFalse();
        });
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Sessions/FileSessionManagerTest.php`
Expected: FAIL — class `FileSessionManager` not found

**Step 3: Write FileSessionManager implementation**

Create `src/Sessions/FileSessionManager.php`:

```php
<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Sessions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * File-based session manager for cross-worker interactive process persistence.
 *
 * Uses a background PHP worker process per session that owns the PTY.
 * FPM workers communicate through the filesystem:
 * - stdin file: FPM workers append input
 * - stdout file: background worker appends output
 * - Session metadata stored in Laravel Cache (respects CACHE_STORE)
 *
 * This manager requires no external dependencies (no tmux, no screen).
 * It works with any PHP SAPI: FPM, Octane, artisan serve.
 */
class FileSessionManager implements SessionManagerInterface
{
    protected const CACHE_PREFIX = 'swt:file:';

    protected string $sessionBaseDir;

    protected int $maxSessionLifetime = 300; // 5 minutes

    public function __construct()
    {
        $this->sessionBaseDir = storage_path('app/web-terminal/sessions');
    }

    /**
     * Check if file-based session management is available.
     *
     * Requires PTY support (proc_open + OS PTY) for terminal emulation.
     */
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
        $sessionId = $this->generateSessionId();
        $sessionDir = $this->getSessionDir($sessionId);

        // Create session directory
        if (! is_dir($sessionDir)) {
            mkdir($sessionDir, 0700, true);
        }

        // Create empty stdin and stdout files
        touch($sessionDir.'/stdin');
        chmod($sessionDir.'/stdin', 0600);
        touch($sessionDir.'/stdout');
        chmod($sessionDir.'/stdout', 0600);

        // Build the worker command
        $workerScript = __DIR__.'/session-worker.php';
        $phpBinary = PHP_BINARY;

        $workerArgs = [
            escapeshellarg($sessionDir),
            escapeshellarg($command),
            escapeshellarg($cwd ?? ''),
            escapeshellarg($env !== null ? json_encode($env) : ''),
        ];

        $workerCommand = sprintf(
            'nohup %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($workerScript),
            implode(' ', $workerArgs),
        );

        // Spawn the background worker
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($workerCommand, $descriptors, $pipes);

        if (is_resource($proc)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }

        // Wait briefly for the worker to write its PID
        $pidFile = $sessionDir.'/pid';
        $attempts = 0;
        while (! file_exists($pidFile) && $attempts < 50) {
            usleep(20000); // 20ms
            $attempts++;
        }

        $pid = file_exists($pidFile) ? (int) file_get_contents($pidFile) : 0;

        // Store session metadata in cache
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

        $stdoutFile = $this->getSessionDir($sessionId).'/stdout';

        if (! file_exists($stdoutFile)) {
            return ['stdout' => '', 'stderr' => ''];
        }

        // Read incrementally from last position
        clearstatcache(true, $stdoutFile);
        $fileSize = filesize($stdoutFile);

        if ($fileSize === false || $fileSize <= $sessionData->lastOutputPosition) {
            return ['stdout' => '', 'stderr' => ''];
        }

        $handle = fopen($stdoutFile, 'r');
        if (! $handle) {
            return ['stdout' => '', 'stderr' => ''];
        }

        fseek($handle, $sessionData->lastOutputPosition);
        $newOutput = fread($handle, $fileSize - $sessionData->lastOutputPosition);
        fclose($handle);

        // Update position in cache
        $sessionData->lastOutputPosition = $fileSize;
        $sessionData->lastActivity = time();
        $this->saveSessionData($sessionData);

        return [
            'stdout' => $newOutput ?: '',
            'stderr' => '', // PTY combines stdout/stderr
        ];
    }

    public function sendInput(string $sessionId, string $input): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $stdinFile = $this->getSessionDir($sessionId).'/stdin';

        if (! file_exists($stdinFile)) {
            return false;
        }

        // Append newline if not present
        if (! str_ends_with($input, "\n")) {
            $input .= "\n";
        }

        $result = file_put_contents($stdinFile, $input, FILE_APPEND | LOCK_EX);

        if ($result !== false) {
            $sessionData->lastActivity = time();
            $this->saveSessionData($sessionData);

            return true;
        }

        return false;
    }

    public function sendRawInput(string $sessionId, string $input): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $stdinFile = $this->getSessionDir($sessionId).'/stdin';

        if (! file_exists($stdinFile)) {
            return false;
        }

        $result = file_put_contents($stdinFile, $input, FILE_APPEND | LOCK_EX);

        if ($result !== false) {
            $sessionData->lastActivity = time();
            $this->saveSessionData($sessionData);

            return true;
        }

        return false;
    }

    public function isRunning(string $sessionId): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $sessionDir = $this->getSessionDir($sessionId);
        $exitCodeFile = $sessionDir.'/exit_code';

        // Check if exit_code file exists (worker writes this when process finishes)
        if (file_exists($exitCodeFile)) {
            $exitCode = (int) file_get_contents($exitCodeFile);
            $sessionData->finished = true;
            $sessionData->exitCode = $exitCode;
            $this->saveSessionData($sessionData);

            return false;
        }

        // Verify PID is still alive
        if ($sessionData->pid > 0) {
            if (! $this->isPidAlive($sessionData->pid)) {
                $sessionData->finished = true;
                $sessionData->exitCode = -1; // Unknown exit code
                $this->saveSessionData($sessionData);

                return false;
            }
        }

        return true;
    }

    public function getExitCode(string $sessionId): ?int
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        // If not marked finished yet, check exit_code file
        if (! $sessionData->finished) {
            $exitCodeFile = $this->getSessionDir($sessionId).'/exit_code';
            if (file_exists($exitCodeFile)) {
                $exitCode = (int) file_get_contents($exitCodeFile);
                $sessionData->finished = true;
                $sessionData->exitCode = $exitCode;
                $this->saveSessionData($sessionData);
            }
        }

        return $sessionData->exitCode;
    }

    public function terminate(string $sessionId): bool
    {
        $sessionData = $this->getSessionData($sessionId);
        $sessionDir = $this->getSessionDir($sessionId);

        // Kill the worker process
        if ($sessionData !== null && $sessionData->pid > 0) {
            if ($this->isPidAlive($sessionData->pid)) {
                posix_kill($sessionData->pid, SIGTERM);

                // Wait briefly, then force kill if needed
                usleep(100000); // 100ms
                if ($this->isPidAlive($sessionData->pid)) {
                    posix_kill($sessionData->pid, SIGKILL);
                }
            }
        }

        // Clean up session directory
        $this->removeSessionDir($sessionDir);

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
            'is_running' => $this->isRunning($sessionId),
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

        $dirs = glob($this->sessionBaseDir.'/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $sessionId = basename($dir);
            $sessionData = $this->getSessionData($sessionId);

            if ($sessionData === null) {
                // Orphaned directory - remove it
                $this->killSessionProcess($dir);
                $this->removeSessionDir($dir);

                continue;
            }

            $expired = (time() - $sessionData->lastActivity) > $this->maxSessionLifetime;

            if ($expired) {
                $this->terminate($sessionId);
            }
        }
    }

    public function getActiveSessionCount(): int
    {
        if (! is_dir($this->sessionBaseDir)) {
            return 0;
        }

        $dirs = glob($this->sessionBaseDir.'/*', GLOB_ONLYDIR);
        $count = 0;

        foreach ($dirs as $dir) {
            $sessionId = basename($dir);
            if ($this->isRunning($sessionId)) {
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

    // ========================================
    // Utility methods
    // ========================================

    public function setSessionBaseDir(string $dir): static
    {
        $this->sessionBaseDir = $dir;

        return $this;
    }

    public function getSessionBaseDir(): string
    {
        return $this->sessionBaseDir;
    }

    protected function getSessionDir(string $sessionId): string
    {
        return $this->sessionBaseDir.'/'.$sessionId;
    }

    protected function generateSessionId(): string
    {
        return substr(Str::uuid()->toString(), 0, 12);
    }

    protected function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Use /proc filesystem (Linux) or posix_kill signal 0 check
        if (is_dir("/proc/{$pid}")) {
            return true;
        }

        // Fallback: signal 0 checks process existence without sending a signal
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return false;
    }

    protected function killSessionProcess(string $sessionDir): void
    {
        $pidFile = $sessionDir.'/pid';
        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid > 0 && $this->isPidAlive($pid)) {
                posix_kill($pid, SIGKILL);
            }
        }
    }

    protected function removeSessionDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
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

    /**
     * Clear all sessions (for testing).
     *
     * @internal
     */
    public static function clearAllSessions(): void
    {
        $baseDir = storage_path('app/web-terminal/sessions');

        if (! is_dir($baseDir)) {
            return;
        }

        $dirs = glob($baseDir.'/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $pidFile = $dir.'/pid';
            if (file_exists($pidFile)) {
                $pid = (int) file_get_contents($pidFile);
                if ($pid > 0) {
                    @posix_kill($pid, SIGKILL);
                }
            }

            usleep(50000);

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($dir);
        }
    }
}
```

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/Sessions/FileSessionManagerTest.php`
Expected: All tests pass

**Step 5: Run Pint**

Run: `vendor/bin/pint src/Sessions/FileSessionManager.php src/Sessions/session-worker.php tests/Unit/Sessions/FileSessionManagerTest.php`

**Step 6: Commit**

```bash
git add src/Sessions/FileSessionManager.php tests/Unit/Sessions/FileSessionManagerTest.php
git commit -m "feat: add FileSessionManager with file-based IPC for cross-worker sessions"
```

---

### Task 3: Integrate FileSessionManager into LocalConnectionHandler

**Files:**
- Modify: `src/Connections/LocalConnectionHandler.php:437-449`

**Step 1: Write the failing test**

Add to `tests/Unit/Sessions/FileSessionManagerTest.php`:

```php
describe('LocalConnectionHandler integration', function () {
    it('selects FileSessionManager when tmux is unavailable', function () {
        // FileSessionManager should be available on this system (PTY supported)
        expect(FileSessionManager::isAvailable())->toBeTrue();

        $handler = new \MWGuerra\WebTerminal\Connections\LocalConnectionHandler;
        $handler->setPreferTmux(false); // Disable tmux preference

        // Connect the handler
        $config = new \MWGuerra\WebTerminal\Data\ConnectionConfig(
            type: \MWGuerra\WebTerminal\Enums\ConnectionType::Local,
        );
        $handler->connect($config);

        // The handler should use FileSessionManager (not ProcessSessionManager)
        // We can verify by checking isUsingTmux returns false and starting a session
        expect($handler->isUsingTmux())->toBeFalse();

        // Start an interactive session and verify it works across "simulated" workers
        $sessionId = $handler->startInteractive('echo "cross-worker test"');
        expect($sessionId)->toBeString();

        usleep(500000);

        $output = $handler->readOutput($sessionId);
        expect($output['stdout'])->toContain('cross-worker test');

        $handler->terminateProcess($sessionId);
    });
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Sessions/FileSessionManagerTest.php --filter="selects FileSessionManager"`
Expected: FAIL — still selects ProcessSessionManager

**Step 3: Update LocalConnectionHandler**

Modify `src/Connections/LocalConnectionHandler.php` at line 437, update `getSessionManager()`:

```php
protected function getSessionManager(): SessionManagerInterface
{
    if ($this->sessionManager === null) {
        // Priority: Tmux > File > Process
        if ($this->preferTmux && TmuxSessionManager::isAvailable()) {
            $this->sessionManager = new TmuxSessionManager;
        } elseif (FileSessionManager::isAvailable()) {
            $this->sessionManager = new FileSessionManager;
        } else {
            $this->sessionManager = new ProcessSessionManager;
        }
    }

    return $this->sessionManager;
}
```

Add the import at the top of the file:

```php
use MWGuerra\WebTerminal\Sessions\FileSessionManager;
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Sessions/FileSessionManagerTest.php --filter="selects FileSessionManager"`
Expected: PASS

**Step 5: Add isUsingFileSession helper**

Add to `LocalConnectionHandler.php`:

```php
/**
 * Check if file session manager is being used.
 */
public function isUsingFileSession(): bool
{
    return $this->getSessionManager() instanceof FileSessionManager;
}
```

**Step 6: Run full test suite**

Run: `vendor/bin/pest --parallel`
Expected: All existing tests still pass (no regressions)

**Step 7: Run Pint**

Run: `vendor/bin/pint src/Connections/LocalConnectionHandler.php`

**Step 8: Commit**

```bash
git add src/Connections/LocalConnectionHandler.php tests/Unit/Sessions/FileSessionManagerTest.php
git commit -m "feat: integrate FileSessionManager into LocalConnectionHandler priority chain"
```

---

### Task 4: Add REPL (stdin/stdout round-trip) test

**Files:**
- Modify: `tests/Unit/Sessions/FileSessionManagerTest.php`

**Step 1: Write the REPL integration test**

Add to the test file:

```php
describe('REPL interaction', function () {
    it('supports multiple rounds of stdin/stdout interaction', function () {
        // Start a simple REPL-like process (bash reading from stdin)
        $sessionId = $this->manager->start('/bin/bash');

        usleep(500000); // Wait for bash to start

        // Drain initial output (prompt, etc.)
        $this->manager->getOutput($sessionId);

        // Send a command
        $this->manager->sendInput($sessionId, 'echo "round1"');
        usleep(300000);

        $output = $this->manager->getOutput($sessionId);
        expect($output['stdout'])->toContain('round1');

        // Send another command
        $this->manager->sendInput($sessionId, 'echo "round2"');
        usleep(300000);

        $output = $this->manager->getOutput($sessionId);
        expect($output['stdout'])->toContain('round2');

        // Verify process is still running
        expect($this->manager->isRunning($sessionId))->toBeTrue();

        // Terminate
        $this->manager->terminate($sessionId);
        usleep(200000);
        expect($this->manager->isRunning($sessionId))->toBeFalse();
    });
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Unit/Sessions/FileSessionManagerTest.php --filter="REPL"`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Unit/Sessions/FileSessionManagerTest.php
git commit -m "test: add REPL round-trip interaction test for FileSessionManager"
```

---

### Task 5: Update documentation

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

**Step 1: Update README**

Add a section about session manager backends under the interactive mode documentation:

```markdown
### Session Manager Backends

The terminal automatically selects the best available backend for interactive sessions:

| Backend | Requirements | Cross-Worker | Best For |
|---------|-------------|--------------|----------|
| **Tmux** | `tmux` installed | Yes | Production with tmux |
| **File** | PHP PTY support (default on Linux/macOS) | Yes | Production without tmux |
| **Process** | None | No | `artisan serve` / Octane |

The file-based backend uses a background PHP worker process per session with file-based IPC. Session metadata is stored in Laravel's Cache, respecting your `CACHE_STORE` setting (file, Redis, database, etc.).

No additional configuration is needed — the package detects available backends automatically.
```

**Step 2: Update CHANGELOG**

Add to the unreleased section:

```markdown
- **FileSessionManager**: Pure PHP file-based session manager for interactive commands without tmux dependency
  - Background PHP worker per session with PTY support
  - File-based IPC (stdin/stdout temp files) for cross-worker persistence
  - Session metadata via Laravel Cache (respects `CACHE_STORE`: file, Redis, database)
  - Automatic cleanup of expired sessions
  - Auto-selected when tmux is unavailable
```

**Step 3: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: add FileSessionManager backend documentation"
```

---

### Task 6: Full test suite verification and push

**Step 1: Run full test suite**

Run: `vendor/bin/pest --parallel`
Expected: All tests pass (only pre-existing ImmediatePollTest flaky failure allowed)

**Step 2: Run Pint on all changed files**

Run: `vendor/bin/pint`

**Step 3: Push to origin**

```bash
git push origin fix/artisan-interactive-commands
```

---

### Task 7: Browser E2E verification

**Step 1: Update testapp_f5**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
composer update mwguerra/web-terminal
```

**Step 2: Test in browser with Playwright**

1. Navigate to `https://testapp-f5.test/admin/interactive-terminal`
2. Run `php artisan tinker` — verify it starts, shows Psy Shell prompt
3. Type `1 + 1` in tinker — verify it returns `= 2`
4. Run `echo "hello"` — verify standard command works
5. Take screenshots of working REPL interaction
