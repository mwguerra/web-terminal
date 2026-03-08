<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Connections;

use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * Connection handler for SSH-based remote command execution.
 *
 * Uses phpseclib3 for secure SSH connections with support for
 * password-based and key-based authentication.
 */
class SSHConnectionHandler extends AbstractConnectionHandler
{
    /**
     * The SSH connection instance.
     */
    protected ?SSH2 $ssh = null;

    /**
     * Environment variables to pass to commands.
     *
     * @var array<string, string>
     */
    protected array $environment = [];

    /**
     * Maximum number of reconnection attempts.
     */
    protected int $maxReconnectAttempts = 3;

    /**
     * Delay between reconnection attempts in seconds.
     */
    protected float $reconnectDelay = 1.0;

    /**
     * {@inheritDoc}
     */
    public function connect(ConnectionConfig $config): void
    {
        if ($config->type !== ConnectionType::SSH) {
            throw ConnectionException::invalidConfig(
                reason: "SSHConnectionHandler only supports SSH connections, got {$config->type->value}",
                type: $config->type,
            );
        }

        if (empty($config->host)) {
            throw ConnectionException::invalidConfig(
                reason: 'SSH connection requires a host',
                type: ConnectionType::SSH,
            );
        }

        if (empty($config->username)) {
            throw ConnectionException::invalidConfig(
                reason: 'SSH connection requires a username',
                type: ConnectionType::SSH,
            );
        }

        try {
            $this->ssh = $this->createSSHConnection($config);
            $this->authenticate($config);
            $this->markConnected($config);
        } catch (ConnectionException $e) {
            $this->ssh = null;
            throw $e;
        } catch (\Throwable $e) {
            $this->ssh = null;
            throw ConnectionException::connectionFailed(
                config: $config,
                reason: $e->getMessage(),
                previous: $e instanceof \Exception ? $e : null,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $command, ?float $timeout = null): CommandResult
    {
        if (! $this->isConnected() || $this->ssh === null) {
            throw ConnectionException::notConnected();
        }

        $effectiveTimeout = $this->getEffectiveTimeout($timeout);
        $startTime = microtime(true);

        // Build command with environment variables if set
        $fullCommand = $this->buildCommandWithEnvironment($command);

        // Set working directory if specified
        if ($this->workingDirectory !== null) {
            $fullCommand = 'cd '.escapeshellarg($this->workingDirectory).' && '.$fullCommand;
        }

        try {
            $this->ssh->setTimeout($effectiveTimeout);

            // Enable quiet mode to capture both stdout and stderr
            $this->ssh->enableQuietMode();

            $stdout = $this->ssh->exec($fullCommand);
            $stderr = $this->ssh->getStdError();
            $exitCode = $this->ssh->getExitStatus();

            $executionTime = microtime(true) - $startTime;

            // Check if we got a timeout
            if ($this->ssh->isTimeout()) {
                return CommandResult::timeout(
                    timeoutSeconds: $effectiveTimeout,
                    command: $command,
                    partialOutput: $stdout ?: '',
                );
            }

            return new CommandResult(
                stdout: $stdout ?: '',
                stderr: $stderr ?: '',
                exitCode: $exitCode !== false ? $exitCode : -1,
                executionTime: $executionTime,
                command: $command,
            );
        } catch (\Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            // Check if this is a connection issue that might be recoverable
            if ($this->isConnectionError($e)) {
                // Try to reconnect and re-execute
                if ($this->tryReconnect()) {
                    return $this->execute($command, $timeout);
                }
            }

            throw ConnectionException::executionFailed(
                command: $command,
                reason: $e->getMessage(),
                type: ConnectionType::SSH,
                previous: $e instanceof \Exception ? $e : null,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        if ($this->ssh !== null) {
            try {
                $this->ssh->disconnect();
            } catch (\Throwable $e) {
                // Ignore disconnect errors
            }
            $this->ssh = null;
        }

        $this->markDisconnected();
        $this->environment = [];
    }

    /**
     * Set environment variables for command execution.
     *
     * @param  array<string, string>  $environment  Environment variables
     * @return $this
     */
    public function setEnvironment(array $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Add an environment variable for command execution.
     *
     * @param  string  $name  Variable name
     * @param  string  $value  Variable value
     * @return $this
     */
    public function addEnvironmentVariable(string $name, string $value): static
    {
        $this->environment[$name] = $value;

        return $this;
    }

    /**
     * Get the current environment variables.
     *
     * @return array<string, string>
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    /**
     * Set the maximum number of reconnection attempts.
     *
     * @param  int  $attempts  Maximum attempts
     * @return $this
     */
    public function setMaxReconnectAttempts(int $attempts): static
    {
        $this->maxReconnectAttempts = max(0, $attempts);

        return $this;
    }

    /**
     * Get the maximum number of reconnection attempts.
     */
    public function getMaxReconnectAttempts(): int
    {
        return $this->maxReconnectAttempts;
    }

    /**
     * Set the delay between reconnection attempts.
     *
     * @param  float  $seconds  Delay in seconds
     * @return $this
     */
    public function setReconnectDelay(float $seconds): static
    {
        $this->reconnectDelay = max(0.1, $seconds);

        return $this;
    }

    /**
     * Get the delay between reconnection attempts.
     */
    public function getReconnectDelay(): float
    {
        return $this->reconnectDelay;
    }

    /**
     * Get the underlying SSH connection for advanced usage.
     *
     * @internal This method is primarily for testing.
     */
    public function getSSHConnection(): ?SSH2
    {
        return $this->ssh;
    }

    /**
     * Create SSH connection instance.
     */
    protected function createSSHConnection(ConnectionConfig $config): SSH2
    {
        $port = $config->port ?? 22;

        $ssh = new SSH2($config->host, $port);

        // Set connection timeout
        $ssh->setTimeout($this->timeout);

        return $ssh;
    }

    /**
     * Authenticate with the SSH server.
     */
    protected function authenticate(ConnectionConfig $config): void
    {
        $authenticated = false;

        // Try key-based authentication first if key is provided
        if (! empty($config->privateKey)) {
            $authenticated = $this->authenticateWithKey($config);
        }

        // Fall back to password authentication if key auth failed or not configured
        if (! $authenticated && ! empty($config->password)) {
            $authenticated = $this->authenticateWithPassword($config);
        }

        if (! $authenticated) {
            throw ConnectionException::authenticationFailed($config);
        }
    }

    /**
     * Authenticate using SSH key.
     */
    protected function authenticateWithKey(ConnectionConfig $config): bool
    {
        try {
            $passphrase = $config->passphrase;
            $key = PublicKeyLoader::load(
                $config->privateKey,
                $passphrase !== null && $passphrase !== '' ? $passphrase : false
            );

            return $this->ssh->login($config->username, $key);
        } catch (\Throwable $e) {
            // Key authentication failed, will try password next
            return false;
        }
    }

    /**
     * Authenticate using password.
     */
    protected function authenticateWithPassword(ConnectionConfig $config): bool
    {
        try {
            return $this->ssh->login($config->username, $config->password);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether to use PTY emulation for color support.
     */
    protected bool $usePty = false;

    /**
     * Enable PTY emulation for color support.
     *
     * When enabled, commands are wrapped with `script` to allocate a PTY,
     * which allows programs that check isatty() to output colors.
     *
     * @return $this
     */
    public function enablePty(bool $enable = true): static
    {
        $this->usePty = $enable;

        return $this;
    }

    /**
     * Check if PTY emulation is enabled.
     */
    public function isPtyEnabled(): bool
    {
        return $this->usePty;
    }

    /**
     * Build command string with environment variables prepended.
     */
    protected function buildCommandWithEnvironment(string $command): string
    {
        $exports = [];
        foreach ($this->environment as $name => $value) {
            // Sanitize variable name (alphanumeric and underscore only)
            $safeName = preg_replace('/[^A-Za-z0-9_]/', '', $name);
            if ($safeName === '') {
                continue;
            }
            // Only escape the value, not the variable name
            $exports[] = sprintf('export %s=%s', $safeName, escapeshellarg($value));
        }

        // Build the base command with environment
        $fullCommand = $command;
        if (! empty($exports)) {
            $fullCommand = implode('; ', $exports).'; '.$command;
        }

        // Wrap with script for PTY emulation if enabled (for color support)
        if ($this->usePty) {
            // Use script to allocate a PTY, making isatty() return true
            // -q: quiet mode, -c: command to run
            // /dev/null: discard the typescript file
            $fullCommand = sprintf('script -qc %s /dev/null', escapeshellarg($fullCommand));
        }

        return $fullCommand;
    }

    /**
     * Check if an exception indicates a connection error.
     */
    protected function isConnectionError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'connection') ||
               str_contains($message, 'socket') ||
               str_contains($message, 'broken pipe') ||
               str_contains($message, 'reset by peer');
    }

    /**
     * Attempt to reconnect to the SSH server.
     */
    protected function tryReconnect(): bool
    {
        $config = $this->getConfig();

        if ($config === null) {
            return false;
        }

        for ($attempt = 1; $attempt <= $this->maxReconnectAttempts; $attempt++) {
            try {
                // Clean up existing connection
                if ($this->ssh !== null) {
                    try {
                        $this->ssh->disconnect();
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                    $this->ssh = null;
                }

                // Wait before reconnecting
                if ($attempt > 1) {
                    usleep((int) ($this->reconnectDelay * 1000000));
                }

                // Try to reconnect
                $this->ssh = $this->createSSHConnection($config);
                $this->authenticate($config);

                return true;
            } catch (\Throwable $e) {
                // Continue to next attempt
            }
        }

        return false;
    }
}
