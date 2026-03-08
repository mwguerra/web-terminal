<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Contracts;

use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;

/**
 * Interface for connection handlers implementing the Strategy pattern.
 *
 * This interface defines the contract for all connection handlers
 * (Local, SSH) to execute commands on different targets.
 */
interface ConnectionHandlerInterface
{
    /**
     * Establish a connection to the target.
     *
     * @param  ConnectionConfig  $config  The connection configuration
     *
     * @throws ConnectionException If connection cannot be established
     */
    public function connect(ConnectionConfig $config): void;

    /**
     * Execute a command on the connected target.
     *
     * @param  string  $command  The command to execute
     * @param  float|null  $timeout  Optional timeout in seconds (overrides default)
     *
     * @throws ConnectionException If not connected or execution fails
     */
    public function execute(string $command, ?float $timeout = null): CommandResult;

    /**
     * Disconnect from the target.
     *
     * This should gracefully close any open connections and release resources.
     * Safe to call even if not connected.
     */
    public function disconnect(): void;

    /**
     * Check if currently connected to the target.
     */
    public function isConnected(): bool;

    /**
     * Get the current connection configuration.
     *
     * Returns null if not connected.
     */
    public function getConfig(): ?ConnectionConfig;

    /**
     * Get the default timeout for command execution in seconds.
     */
    public function getTimeout(): float;

    /**
     * Set the default timeout for command execution.
     *
     * @param  float  $timeout  Timeout in seconds
     * @return $this
     */
    public function setTimeout(float $timeout): static;

    /**
     * Get the working directory for command execution.
     */
    public function getWorkingDirectory(): ?string;

    /**
     * Set the working directory for command execution.
     *
     * @param  string|null  $directory  The working directory path
     * @return $this
     */
    public function setWorkingDirectory(?string $directory): static;

    // ========================================
    // Interactive Mode Methods
    // ========================================

    /**
     * Check if this handler supports interactive mode.
     *
     * Interactive mode allows streaming output and sending input
     * to running processes (e.g., for commands that prompt for user input).
     */
    public function supportsInteractive(): bool;

    /**
     * Start an interactive command session.
     *
     * @param  string  $command  The command to execute
     * @return string The session ID for subsequent operations
     *
     * @throws ConnectionException If not connected or interactive mode not supported
     */
    public function startInteractive(string $command): string;

    /**
     * Read output from an interactive session.
     *
     * Returns incremental output since last read (not cumulative).
     *
     * @param  string  $sessionId  The session ID from startInteractive()
     * @return array{stdout: string, stderr: string}|null Output or null if session not found
     */
    public function readOutput(string $sessionId): ?array;

    /**
     * Send input to an interactive session.
     *
     * @param  string  $sessionId  The session ID from startInteractive()
     * @param  string  $input  The input to send (newline appended automatically)
     * @return bool True if sent successfully
     */
    public function writeInput(string $sessionId, string $input): bool;

    /**
     * Check if an interactive session is still running.
     *
     * @param  string  $sessionId  The session ID from startInteractive()
     */
    public function isProcessRunning(string $sessionId): bool;

    /**
     * Get the exit code of a finished interactive session.
     *
     * @param  string  $sessionId  The session ID from startInteractive()
     * @return int|null Exit code or null if still running/not found
     */
    public function getProcessExitCode(string $sessionId): ?int;

    /**
     * Terminate an interactive session.
     *
     * @param  string  $sessionId  The session ID from startInteractive()
     * @return bool True if terminated successfully
     */
    public function terminateProcess(string $sessionId): bool;
}
