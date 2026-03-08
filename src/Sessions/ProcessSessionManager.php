<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Sessions;

use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Manages interactive process sessions using in-memory storage.
 *
 * Stores running process references and provides methods to interact with them:
 * - Start new processes
 * - Read accumulated output
 * - Write input to stdin
 * - Check process status
 * - Terminate processes
 *
 * WARNING: This manager uses static PHP memory which is NOT shared across
 * PHP-FPM workers. Sessions will be lost if subsequent requests hit different
 * workers. For production use with PHP-FPM, use TmuxSessionManager instead.
 */
class ProcessSessionManager implements SessionManagerInterface
{
    /**
     * Active process sessions.
     * Stored in static array because Process objects can't be serialized to cache.
     *
     * @var array<string, ProcessSession>
     */
    protected static array $sessions = [];

    /**
     * Maximum session lifetime in seconds.
     */
    protected int $maxSessionLifetime = 300; // 5 minutes

    /**
     * Start a new interactive process session.
     *
     * @param  string  $command  The command to execute
     * @param  string|null  $cwd  Working directory
     * @param  array<string, string>|null  $env  Environment variables
     * @param  float|null  $timeout  Timeout in seconds (null for no timeout)
     * @return string The session ID
     */
    public function start(
        string $command,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
    ): string {
        $sessionId = $this->generateSessionId();

        $input = new InputStream;

        $process = Process::fromShellCommandline(
            command: $command,
            cwd: $cwd,
            env: $env,
            input: $input,
            timeout: $timeout,
        );

        // Enable PTY if supported for better terminal emulation
        if (Process::isPtySupported()) {
            $process->setPty(true);
        }

        $process->start();

        self::$sessions[$sessionId] = new ProcessSession(
            process: $process,
            inputStream: $input,
            startedAt: time(),
        );

        return $sessionId;
    }

    /**
     * Get incremental output from a process session.
     *
     * Returns only new output since last call (not cumulative).
     *
     * @param  string  $sessionId  The session ID
     * @return array{stdout: string, stderr: string}|null Output or null if session not found
     */
    public function getOutput(string $sessionId): ?array
    {
        $session = $this->getSession($sessionId);

        if ($session === null) {
            return null;
        }

        // Get incremental output (clears internal buffer)
        $stdout = $session->process->getIncrementalOutput();
        $stderr = $session->process->getIncrementalErrorOutput();

        // Update last activity
        $session->lastActivity = time();

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Send input to a running process.
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The input to send (newline appended automatically)
     * @return bool True if input was sent, false if session not found or process not running
     */
    public function sendInput(string $sessionId, string $input): bool
    {
        $session = $this->getSession($sessionId);

        if ($session === null || ! $session->process->isRunning()) {
            return false;
        }

        // Append newline if not present (simulates pressing Enter)
        if (! str_ends_with($input, "\n")) {
            $input .= "\n";
        }

        $session->inputStream->write($input);
        $session->lastActivity = time();

        return true;
    }

    /**
     * Send raw input to a running process without appending newline.
     *
     * Used for special keys (arrows, tab, escape sequences, etc.)
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The raw input to send
     * @return bool True if input was sent, false if session not found or process not running
     */
    public function sendRawInput(string $sessionId, string $input): bool
    {
        $session = $this->getSession($sessionId);

        if ($session === null || ! $session->process->isRunning()) {
            return false;
        }

        $session->inputStream->write($input);
        $session->lastActivity = time();

        return true;
    }

    /**
     * Check if a process is still running.
     *
     * @param  string  $sessionId  The session ID
     * @return bool True if running, false if not found or finished
     */
    public function isRunning(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);

        if ($session === null) {
            return false;
        }

        return $session->process->isRunning();
    }

    /**
     * Get the exit code of a finished process.
     *
     * @param  string  $sessionId  The session ID
     * @return int|null Exit code or null if still running/not found
     */
    public function getExitCode(string $sessionId): ?int
    {
        $session = $this->getSession($sessionId);

        if ($session === null) {
            return null;
        }

        return $session->process->getExitCode();
    }

    /**
     * Terminate a running process.
     *
     * @param  string  $sessionId  The session ID
     * @return bool True if terminated, false if not found
     */
    public function terminate(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);

        if ($session === null) {
            return false;
        }

        if ($session->process->isRunning()) {
            // Close input stream first
            $session->inputStream->close();

            // Send SIGTERM, then SIGKILL if needed
            $session->process->stop(3, SIGTERM);
        }

        $this->removeSession($sessionId);

        return true;
    }

    /**
     * Check if a session exists.
     *
     * @param  string  $sessionId  The session ID
     */
    public function hasSession(string $sessionId): bool
    {
        return isset(self::$sessions[$sessionId]);
    }

    /**
     * Get session info without process details (for debugging).
     *
     * @param  string  $sessionId  The session ID
     * @return array<string, mixed>|null
     */
    public function getSessionInfo(string $sessionId): ?array
    {
        $session = $this->getSession($sessionId);

        if ($session === null) {
            return null;
        }

        return [
            'started_at' => $session->startedAt,
            'last_activity' => $session->lastActivity,
            'is_running' => $session->process->isRunning(),
            'exit_code' => $session->process->getExitCode(),
            'pid' => $session->process->getPid(),
        ];
    }

    /**
     * Clean up expired or finished sessions.
     *
     * Should be called periodically to prevent memory leaks.
     */
    public function cleanup(): void
    {
        $now = time();

        foreach (self::$sessions as $sessionId => $session) {
            $expired = ($now - $session->lastActivity) > $this->maxSessionLifetime;
            $finished = ! $session->process->isRunning();

            if ($expired || $finished) {
                $this->terminate($sessionId);
            }
        }
    }

    /**
     * Get count of active sessions.
     */
    public function getActiveSessionCount(): int
    {
        return count(self::$sessions);
    }

    /**
     * Set maximum session lifetime.
     *
     * @param  int  $seconds  Lifetime in seconds
     */
    public function setMaxSessionLifetime(int $seconds): static
    {
        $this->maxSessionLifetime = max(60, $seconds); // Minimum 1 minute

        return $this;
    }

    /**
     * Get a session by ID.
     */
    protected function getSession(string $sessionId): ?ProcessSession
    {
        return self::$sessions[$sessionId] ?? null;
    }

    /**
     * Remove a session.
     */
    protected function removeSession(string $sessionId): void
    {
        unset(self::$sessions[$sessionId]);
    }

    /**
     * Generate a unique session ID.
     */
    protected function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Clear all sessions (for testing).
     *
     * @internal
     */
    public static function clearAllSessions(): void
    {
        foreach (self::$sessions as $sessionId => $session) {
            if ($session->process->isRunning()) {
                $session->inputStream->close();
                $session->process->stop(1, SIGKILL);
            }
        }

        self::$sessions = [];
    }
}
