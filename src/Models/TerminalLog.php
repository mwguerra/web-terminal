<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'terminal_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'terminal_session_id',
        'terminal_identifier',
        'event_type',
        'connection_type',
        'host',
        'port',
        'ssh_username',
        'command',
        'output',
        'exit_code',
        'execution_time_seconds',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'port' => 'integer',
        'exit_code' => 'integer',
        'execution_time_seconds' => 'integer',
    ];

    /**
     * Event type constants.
     */
    public const EVENT_CONNECTED = 'connected';

    public const EVENT_DISCONNECTED = 'disconnected';

    public const EVENT_COMMAND = 'command';

    public const EVENT_OUTPUT = 'output';

    public const EVENT_ERROR = 'error';

    public const EVENT_BLOCKED = 'blocked';

    /**
     * Connection type constants.
     */
    public const CONNECTION_LOCAL = 'local';

    public const CONNECTION_SSH = 'ssh';

    /**
     * Get the user that owns this log entry.
     */
    public function user(): BelongsTo
    {
        $userTable = config('web-terminal.logging.user_table', 'users');

        return $this->belongsTo(
            related: config('auth.providers.users.model', 'App\\Models\\User'),
            foreignKey: config('web-terminal.logging.user_foreign_key', 'user_id'),
        );
    }

    /**
     * Scope to filter by session ID.
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('terminal_session_id', $sessionId);
    }

    /**
     * Scope to filter by terminal identifier.
     */
    public function scopeForTerminal(Builder $query, string $identifier): Builder
    {
        return $query->where('terminal_identifier', $identifier);
    }

    /**
     * Scope to filter by user ID.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by tenant ID.
     */
    public function scopeForTenant(Builder $query, mixed $tenantId): Builder
    {
        $tenantColumn = config('web-terminal.logging.tenant_column');

        if (! $tenantColumn) {
            return $query;
        }

        return $query->where($tenantColumn, $tenantId);
    }

    /**
     * Scope to get connection events only.
     */
    public function scopeConnections(Builder $query): Builder
    {
        return $query->whereIn('event_type', [self::EVENT_CONNECTED, self::EVENT_DISCONNECTED]);
    }

    /**
     * Scope to get command events only.
     */
    public function scopeCommands(Builder $query): Builder
    {
        return $query->where('event_type', self::EVENT_COMMAND);
    }

    /**
     * Scope to get error events only.
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('event_type', self::EVENT_ERROR);
    }

    /**
     * Scope to get recent logs within specified hours.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to get logs older than specified days.
     */
    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Determine if this log entry represents a successful command.
     */
    public function isSuccess(): bool
    {
        return $this->event_type === self::EVENT_COMMAND && $this->exit_code === 0;
    }

    /**
     * Determine if this log entry represents a failed command.
     */
    public function isFailed(): bool
    {
        return $this->event_type === self::EVENT_COMMAND && $this->exit_code !== 0 && $this->exit_code !== null;
    }

    /**
     * Get the formatted execution time.
     */
    public function getFormattedExecutionTime(): string
    {
        if ($this->execution_time_seconds === null) {
            return 'N/A';
        }

        if ($this->execution_time_seconds < 60) {
            return $this->execution_time_seconds.'s';
        }

        $minutes = floor($this->execution_time_seconds / 60);
        $seconds = $this->execution_time_seconds % 60;

        return "{$minutes}m {$seconds}s";
    }

    /**
     * Get the event type badge color.
     */
    public function getEventTypeColor(): string
    {
        return match ($this->event_type) {
            self::EVENT_CONNECTED => 'success',
            self::EVENT_DISCONNECTED => 'warning',
            self::EVENT_COMMAND => 'info',
            self::EVENT_OUTPUT => 'gray',
            self::EVENT_ERROR => 'danger',
            self::EVENT_BLOCKED => 'danger',
            default => 'gray',
        };
    }
}
