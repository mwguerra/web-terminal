<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Connections;

use MWGuerra\WebTerminal\Contracts\ConnectionHandlerInterface;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;

/**
 * Abstract base class for connection handlers.
 *
 * Provides common functionality and state management for all connection
 * handler implementations.
 */
abstract class AbstractConnectionHandler implements ConnectionHandlerInterface
{
    /**
     * The current connection configuration.
     */
    protected ?ConnectionConfig $config = null;

    /**
     * Whether the handler is currently connected.
     */
    protected bool $connected = false;

    /**
     * Default timeout for command execution in seconds.
     */
    protected float $timeout = 10.0;

    /**
     * Working directory for command execution.
     */
    protected ?string $workingDirectory = null;

    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): ?ConnectionConfig
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * {@inheritDoc}
     */
    public function setTimeout(float $timeout): static
    {
        $this->timeout = max(0.1, $timeout);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    /**
     * {@inheritDoc}
     */
    public function setWorkingDirectory(?string $directory): static
    {
        $this->workingDirectory = $directory;

        return $this;
    }

    /**
     * Get the effective timeout for a command.
     *
     * @param  float|null  $timeout  The provided timeout, or null to use default
     */
    protected function getEffectiveTimeout(?float $timeout): float
    {
        return $timeout ?? $this->timeout;
    }

    /**
     * Mark the handler as connected with the given configuration.
     */
    protected function markConnected(ConnectionConfig $config): void
    {
        $this->config = $config;
        $this->connected = true;
    }

    /**
     * Mark the handler as disconnected and clear configuration.
     */
    protected function markDisconnected(): void
    {
        $this->config = null;
        $this->connected = false;
    }

    // ========================================
    // Interactive Mode Default Implementations
    // ========================================

    /**
     * {@inheritDoc}
     *
     * Default implementation returns false. Override in subclasses
     * that support interactive mode.
     */
    public function supportsInteractive(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * Default implementation throws exception. Override in subclasses
     * that support interactive mode.
     */
    public function startInteractive(string $command): string
    {
        throw ConnectionException::executionFailed(
            command: $command,
            reason: 'Interactive mode is not supported by this connection handler',
            type: $this->config?->type,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Default implementation returns null. Override in subclasses
     * that support interactive mode.
     */
    public function readOutput(string $sessionId): ?array
    {
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * Default implementation returns false. Override in subclasses
     * that support interactive mode.
     */
    public function writeInput(string $sessionId, string $input): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * Default implementation returns false. Override in subclasses
     * that support interactive mode.
     */
    public function isProcessRunning(string $sessionId): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * Default implementation returns null. Override in subclasses
     * that support interactive mode.
     */
    public function getProcessExitCode(string $sessionId): ?int
    {
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * Default implementation returns false. Override in subclasses
     * that support interactive mode.
     */
    public function terminateProcess(string $sessionId): bool
    {
        return false;
    }
}
