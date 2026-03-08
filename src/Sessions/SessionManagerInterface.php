<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Sessions;

/**
 * Interface for interactive process session managers.
 *
 * Implementations must provide methods to:
 * - Start interactive processes
 * - Read output
 * - Write input
 * - Check status
 * - Terminate processes
 */
interface SessionManagerInterface
{
    /**
     * Start a new interactive process session.
     *
     * @param  string  $command  The command to execute
     * @param  string|null  $cwd  Working directory
     * @param  array<string, string>|null  $env  Environment variables
     * @param  float|null  $timeout  Timeout in seconds (may be ignored by some implementations)
     * @return string The session ID
     */
    public function start(
        string $command,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
    ): string;

    /**
     * Get incremental output from a process session.
     *
     * Returns only new output since last call (not cumulative).
     *
     * @param  string  $sessionId  The session ID
     * @return array{stdout: string, stderr: string}|null Output or null if session not found
     */
    public function getOutput(string $sessionId): ?array;

    /**
     * Send input to a running process.
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The input to send (newline appended automatically)
     * @return bool True if input was sent
     */
    public function sendInput(string $sessionId, string $input): bool;

    /**
     * Send raw input without appending newline.
     *
     * Used for special keys (arrows, tab, escape sequences, etc.)
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The raw input to send
     * @return bool True if input was sent
     */
    public function sendRawInput(string $sessionId, string $input): bool;

    /**
     * Check if a process is still running.
     *
     * @param  string  $sessionId  The session ID
     * @return bool True if running
     */
    public function isRunning(string $sessionId): bool;

    /**
     * Get the exit code of a finished process.
     *
     * @param  string  $sessionId  The session ID
     * @return int|null Exit code or null if still running/not found
     */
    public function getExitCode(string $sessionId): ?int;

    /**
     * Terminate a running process.
     *
     * @param  string  $sessionId  The session ID
     * @return bool True if terminated
     */
    public function terminate(string $sessionId): bool;

    /**
     * Check if a session exists.
     *
     * @param  string  $sessionId  The session ID
     */
    public function hasSession(string $sessionId): bool;

    /**
     * Get session info for debugging.
     *
     * @param  string  $sessionId  The session ID
     * @return array<string, mixed>|null
     */
    public function getSessionInfo(string $sessionId): ?array;

    /**
     * Clean up expired or finished sessions.
     */
    public function cleanup(): void;

    /**
     * Get count of active sessions.
     */
    public function getActiveSessionCount(): int;

    /**
     * Set maximum session lifetime.
     *
     * @param  int  $seconds  Lifetime in seconds
     */
    public function setMaxSessionLifetime(int $seconds): static;
}
