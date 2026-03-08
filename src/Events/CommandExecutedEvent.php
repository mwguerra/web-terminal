<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;

/**
 * Event dispatched after a command is executed.
 *
 * This event is fired for auditing purposes after each command
 * execution, regardless of success or failure.
 */
class CommandExecutedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $command,
        public readonly CommandResult $result,
        public readonly ConnectionType $connectionType,
        public readonly ?string $userId = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $ipAddress = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create an event from a command result and connection config.
     */
    public static function fromExecution(
        string $command,
        CommandResult $result,
        ConnectionConfig $config,
        ?string $userId = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        array $metadata = [],
    ): self {
        return new self(
            command: $command,
            result: $result,
            connectionType: $config->type,
            userId: $userId,
            sessionId: $sessionId,
            ipAddress: $ipAddress,
            metadata: array_merge($metadata, [
                'host' => $config->host,
                'username' => $config->username,
            ]),
        );
    }

    /**
     * Check if the command execution was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->result->isSuccessful();
    }

    /**
     * Check if the command execution failed.
     */
    public function wasFailed(): bool
    {
        return ! $this->wasSuccessful();
    }

    /**
     * Check if the command timed out.
     */
    public function wasTimeout(): bool
    {
        return $this->result->isTimedOut();
    }

    /**
     * Get the exit code of the command.
     */
    public function getExitCode(): int
    {
        return $this->result->exitCode;
    }

    /**
     * Get the execution time in seconds.
     */
    public function getExecutionTime(): float
    {
        return $this->result->executionTime;
    }

    /**
     * Get the event as an array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'connection_type' => $this->connectionType->value,
            'exit_code' => $this->result->exitCode,
            'execution_time' => $this->result->executionTime,
            'success' => $this->wasSuccessful(),
            'timeout' => $this->wasTimeout(),
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'ip_address' => $this->ipAddress,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get a sanitized version for logging (without sensitive output).
     *
     * @return array<string, mixed>
     */
    public function toSanitizedArray(): array
    {
        $data = $this->toArray();

        // Don't include actual command output in logs for security
        unset($data['stdout'], $data['stderr']);

        // Truncate command if too long
        if (strlen($data['command']) > 200) {
            $data['command'] = substr($data['command'], 0, 200).'...';
        }

        return $data;
    }
}
