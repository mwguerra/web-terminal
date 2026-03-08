<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MWGuerra\WebTerminal\Models\TerminalLog;

class TerminalLogger
{
    /**
     * The configuration array.
     */
    protected array $config;

    /**
     * Per-terminal overrides.
     */
    protected array $overrides = [];

    /**
     * Create a new TerminalLogger instance.
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('web-terminal.logging', []);
    }

    /**
     * Set per-terminal configuration overrides.
     */
    public function withOverrides(array $overrides): self
    {
        $clone = clone $this;
        $clone->overrides = $overrides;

        return $clone;
    }

    /**
     * Check if logging is enabled.
     */
    public function isEnabled(): bool
    {
        if (array_key_exists('enabled', $this->overrides)) {
            return (bool) $this->overrides['enabled'];
        }

        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * Check if a specific log type should be logged.
     */
    public function shouldLog(string $type): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $key = "log_{$type}";
        $overrideKey = $type; // connections, commands, output, etc.

        if (array_key_exists($overrideKey, $this->overrides)) {
            return (bool) $this->overrides[$overrideKey];
        }

        return (bool) ($this->config[$key] ?? true);
    }

    /**
     * Check if a specific terminal should be logged.
     */
    public function shouldLogTerminal(?string $identifier): bool
    {
        $terminals = $this->config['terminals'] ?? [];

        // Empty array means log all terminals
        if (empty($terminals)) {
            return true;
        }

        return in_array($identifier, $terminals, true);
    }

    /**
     * Generate a new session ID (UUID v4).
     */
    public function generateSessionId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Get the current tenant ID using the configured resolver.
     */
    protected function getTenantId(): mixed
    {
        $resolver = $this->config['tenant_resolver'] ?? null;

        if ($resolver === null) {
            return null;
        }

        if (is_callable($resolver)) {
            return $resolver();
        }

        if (is_string($resolver) && class_exists($resolver)) {
            return app($resolver)();
        }

        return null;
    }

    /**
     * Get the current user ID.
     */
    protected function getUserId(): ?int
    {
        return auth()->id();
    }

    /**
     * Get the terminal identifier from overrides.
     */
    protected function getTerminalIdentifier(): ?string
    {
        return $this->overrides['identifier'] ?? null;
    }

    /**
     * Get the base metadata from overrides.
     *
     * @return array<string, mixed>
     */
    protected function getBaseMetadata(): array
    {
        return $this->overrides['metadata'] ?? [];
    }

    /**
     * Log a connection event.
     */
    public function logConnection(array $data): ?TerminalLog
    {
        if (! $this->shouldLog('connections')) {
            return null;
        }

        if (! $this->shouldLogTerminal($this->getTerminalIdentifier())) {
            return null;
        }

        return $this->createLog(TerminalLog::EVENT_CONNECTED, $data);
    }

    /**
     * Log a disconnection event.
     */
    public function logDisconnection(string $sessionId, array $data = []): ?TerminalLog
    {
        if (! $this->shouldLog('disconnections')) {
            return null;
        }

        $data['terminal_session_id'] = $sessionId;

        return $this->createLog(TerminalLog::EVENT_DISCONNECTED, $data);
    }

    /**
     * Log a command execution.
     *
     * If 'output' is provided in $data and output logging is enabled,
     * it will be included in the same log entry (truncated if needed).
     */
    public function logCommand(string $sessionId, string $command, array $data = []): ?TerminalLog
    {
        if (! $this->shouldLog('commands')) {
            return null;
        }

        $data['terminal_session_id'] = $sessionId;
        $data['command'] = $command;

        // Handle output in the same log entry (if provided and output logging enabled)
        if (isset($data['output']) && $data['output'] !== null) {
            if ($this->shouldLog('output')) {
                // Apply truncation to output
                $maxLength = (int) ($this->config['max_output_length'] ?? 10000);
                $truncate = (bool) ($this->config['truncate_output'] ?? true);

                if ($truncate && strlen($data['output']) > $maxLength) {
                    $data['output'] = substr($data['output'], 0, $maxLength)."\n... [truncated]";
                }
            } else {
                // Output logging disabled, don't include output
                unset($data['output']);
            }
        }

        return $this->createLog(TerminalLog::EVENT_COMMAND, $data);
    }

    /**
     * Log command output.
     */
    public function logOutput(string $sessionId, string $output, array $data = []): ?TerminalLog
    {
        if (! $this->shouldLog('output')) {
            return null;
        }

        $maxLength = (int) ($this->config['max_output_length'] ?? 10000);
        $truncate = (bool) ($this->config['truncate_output'] ?? true);

        if ($truncate && strlen($output) > $maxLength) {
            $output = substr($output, 0, $maxLength)."\n... [truncated]";
        }

        $data['terminal_session_id'] = $sessionId;
        $data['output'] = $output;

        return $this->createLog(TerminalLog::EVENT_OUTPUT, $data);
    }

    /**
     * Log an error event.
     */
    public function logError(string $sessionId, string $error, array $data = []): ?TerminalLog
    {
        if (! $this->shouldLog('errors')) {
            return null;
        }

        $data['terminal_session_id'] = $sessionId;
        $data['output'] = $error;

        return $this->createLog(TerminalLog::EVENT_ERROR, $data);
    }

    /**
     * Log a blocked command event.
     */
    public function logBlockedCommand(string $sessionId, string $command, string $reason, array $data = []): ?TerminalLog
    {
        // Blocked commands are always logged when logging is enabled (security event)
        if (! $this->isEnabled()) {
            return null;
        }

        $data['terminal_session_id'] = $sessionId;
        $data['command'] = $command;
        $data['output'] = $reason;

        return $this->createLog(TerminalLog::EVENT_BLOCKED, $data);
    }

    /**
     * Create a log entry.
     */
    protected function createLog(string $eventType, array $data): ?TerminalLog
    {
        try {
            $tenantColumn = $this->config['tenant_column'] ?? null;
            $tenantId = $tenantColumn ? $this->getTenantId() : null;

            // Merge base metadata (from terminal config) with per-call metadata
            // Per-call metadata takes precedence over base metadata
            $baseMetadata = $this->getBaseMetadata();
            $callMetadata = $data['metadata'] ?? [];
            $mergedMetadata = ! empty($baseMetadata) || ! empty($callMetadata)
                ? array_merge($baseMetadata, $callMetadata)
                : null;

            $logData = [
                'user_id' => $data['user_id'] ?? $this->getUserId(),
                'terminal_session_id' => $data['terminal_session_id'] ?? $this->generateSessionId(),
                'terminal_identifier' => $data['terminal_identifier'] ?? $this->getTerminalIdentifier(),
                'event_type' => $eventType,
                'connection_type' => $data['connection_type'] ?? TerminalLog::CONNECTION_LOCAL,
                'host' => $data['host'] ?? null,
                'port' => $data['port'] ?? null,
                'ssh_username' => $data['ssh_username'] ?? null,
                'command' => $data['command'] ?? null,
                'output' => $data['output'] ?? null,
                'exit_code' => $data['exit_code'] ?? null,
                'execution_time_seconds' => $data['execution_time_seconds'] ?? null,
                'ip_address' => $data['ip_address'] ?? request()?->ip(),
                'user_agent' => $data['user_agent'] ?? request()?->userAgent(),
                'metadata' => $mergedMetadata,
            ];

            if ($tenantColumn && $tenantId !== null) {
                $logData[$tenantColumn] = $tenantId;
            }

            return TerminalLog::create($logData);
        } catch (\Throwable) {
            // Table may not exist yet (migration not run)
            return null;
        }
    }

    /**
     * Get all logs for a session.
     */
    public function getSessionLogs(string $sessionId): Collection
    {
        try {
            return TerminalLog::forSession($sessionId)
                ->orderBy('created_at')
                ->get();
        } catch (\Throwable) {
            // Table may not exist yet (migration not run)
            return collect();
        }
    }

    /**
     * Get session summary statistics.
     */
    public function getSessionSummary(string $sessionId): array
    {
        $logs = $this->getSessionLogs($sessionId);

        if ($logs->isEmpty()) {
            return [
                'command_count' => 0,
                'error_count' => 0,
                'success_count' => 0,
                'duration' => null,
                'started_at' => null,
                'ended_at' => null,
            ];
        }

        $connected = $logs->where('event_type', TerminalLog::EVENT_CONNECTED)->first();
        $disconnected = $logs->where('event_type', TerminalLog::EVENT_DISCONNECTED)->first();
        $commands = $logs->where('event_type', TerminalLog::EVENT_COMMAND);
        $errors = $commands->whereNotNull('exit_code')->where('exit_code', '!=', 0);
        $successes = $commands->where('exit_code', 0);

        return [
            'command_count' => $commands->count(),
            'error_count' => $errors->count(),
            'success_count' => $successes->count(),
            'duration' => $connected
                ? $connected->created_at->diffForHumans(
                    $disconnected?->created_at ?? now(),
                    ['parts' => 2, 'short' => true]
                )
                : null,
            'started_at' => $connected?->created_at,
            'ended_at' => $disconnected?->created_at,
        ];
    }

    /**
     * Clean up old log entries.
     */
    public function cleanup(?int $days = null): int
    {
        try {
            $retentionDays = $days ?? (int) ($this->config['retention_days'] ?? 90);

            return TerminalLog::olderThan($retentionDays)->delete();
        } catch (\Throwable) {
            // Table may not exist yet (migration not run)
            return 0;
        }
    }
}
