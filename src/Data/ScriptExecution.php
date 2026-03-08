<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Data;

use DateTimeImmutable;
use MWGuerra\WebTerminal\Enums\ScriptCommandStatus;

/**
 * DTO for tracking the execution state of a terminal script.
 *
 * Maintains the current state of a script execution including progress,
 * individual command statuses, and execution results.
 */
class ScriptExecution
{
    protected bool $isRunning = false;

    protected bool $isPaused = false;

    protected bool $isCancelled = false;

    protected int $currentCommandIndex = 0;

    protected ?string $scriptKey = null;

    protected ?string $scriptLabel = null;

    /** @var array<int, array<string, mixed>> */
    protected array $commands = [];

    protected ?DateTimeImmutable $startedAt = null;

    protected ?DateTimeImmutable $finishedAt = null;

    /**
     * Start a new script execution.
     *
     * @param  array<int, string>  $commands
     */
    public function start(string $scriptKey, string $scriptLabel, array $commands): static
    {
        $this->scriptKey = $scriptKey;
        $this->scriptLabel = $scriptLabel;
        $this->isRunning = true;
        $this->isPaused = false;
        $this->isCancelled = false;
        $this->currentCommandIndex = 0;
        $this->startedAt = new DateTimeImmutable;
        $this->finishedAt = null;

        $this->commands = [];
        foreach ($commands as $index => $command) {
            $this->commands[$index] = [
                'command' => $command,
                'status' => ScriptCommandStatus::Pending->value,
                'exitCode' => null,
                'output' => '',
                'executionTime' => null,
                'startedAt' => null,
                'finishedAt' => null,
            ];
        }

        return $this;
    }

    /**
     * Create an execution instance from an array (hydration).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $execution = new static;

        $execution->isRunning = $data['isRunning'] ?? false;
        $execution->isPaused = $data['isPaused'] ?? false;
        $execution->isCancelled = $data['isCancelled'] ?? false;
        $execution->currentCommandIndex = $data['currentCommandIndex'] ?? 0;
        $execution->scriptKey = $data['scriptKey'] ?? null;
        $execution->scriptLabel = $data['scriptLabel'] ?? null;
        $execution->commands = $data['commands'] ?? [];
        $execution->startedAt = isset($data['startedAt'])
            ? new DateTimeImmutable($data['startedAt'])
            : null;
        $execution->finishedAt = isset($data['finishedAt'])
            ? new DateTimeImmutable($data['finishedAt'])
            : null;

        return $execution;
    }

    /**
     * Mark the current command as running.
     */
    public function markCurrentAsRunning(): static
    {
        if (isset($this->commands[$this->currentCommandIndex])) {
            $this->commands[$this->currentCommandIndex]['status'] = ScriptCommandStatus::Running->value;
            $this->commands[$this->currentCommandIndex]['startedAt'] = (new DateTimeImmutable)->format('c');
        }

        return $this;
    }

    /**
     * Mark the current command as successful.
     */
    public function markCurrentAsSuccess(int $exitCode = 0, string $output = '', float $executionTime = 0.0): static
    {
        if (isset($this->commands[$this->currentCommandIndex])) {
            $this->commands[$this->currentCommandIndex]['status'] = ScriptCommandStatus::Success->value;
            $this->commands[$this->currentCommandIndex]['exitCode'] = $exitCode;
            $this->commands[$this->currentCommandIndex]['output'] = $output;
            $this->commands[$this->currentCommandIndex]['executionTime'] = $executionTime;
            $this->commands[$this->currentCommandIndex]['finishedAt'] = (new DateTimeImmutable)->format('c');
        }

        return $this;
    }

    /**
     * Mark the current command as failed.
     */
    public function markCurrentAsFailed(int $exitCode, string $output = '', float $executionTime = 0.0): static
    {
        if (isset($this->commands[$this->currentCommandIndex])) {
            $this->commands[$this->currentCommandIndex]['status'] = ScriptCommandStatus::Failed->value;
            $this->commands[$this->currentCommandIndex]['exitCode'] = $exitCode;
            $this->commands[$this->currentCommandIndex]['output'] = $output;
            $this->commands[$this->currentCommandIndex]['executionTime'] = $executionTime;
            $this->commands[$this->currentCommandIndex]['finishedAt'] = (new DateTimeImmutable)->format('c');
        }

        return $this;
    }

    /**
     * Mark all remaining pending commands as skipped.
     */
    public function markRemainingAsSkipped(): static
    {
        foreach ($this->commands as $index => $command) {
            if ($command['status'] === ScriptCommandStatus::Pending->value) {
                $this->commands[$index]['status'] = ScriptCommandStatus::Skipped->value;
            }
        }

        return $this;
    }

    /**
     * Advance to the next command.
     */
    public function advanceToNext(): static
    {
        $this->currentCommandIndex++;

        return $this;
    }

    /**
     * Check if there are more commands to execute.
     */
    public function hasMoreCommands(): bool
    {
        return $this->currentCommandIndex < count($this->commands);
    }

    /**
     * Get the current command string.
     */
    public function getCurrentCommand(): ?string
    {
        return $this->commands[$this->currentCommandIndex]['command'] ?? null;
    }

    /**
     * Get the current command index (0-based).
     */
    public function getCurrentCommandIndex(): int
    {
        return $this->currentCommandIndex;
    }

    /**
     * Get the total number of commands.
     */
    public function getTotalCommands(): int
    {
        return count($this->commands);
    }

    /**
     * Get progress percentage (0-100).
     */
    public function getProgressPercentage(): int
    {
        $total = count($this->commands);
        if ($total === 0) {
            return 100;
        }

        $completed = 0;
        foreach ($this->commands as $command) {
            $status = ScriptCommandStatus::from($command['status']);
            if ($status->isComplete()) {
                $completed++;
            }
        }

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Pause the execution.
     */
    public function pause(): static
    {
        $this->isPaused = true;

        return $this;
    }

    /**
     * Resume the execution.
     */
    public function resume(): static
    {
        $this->isPaused = false;

        return $this;
    }

    /**
     * Cancel the execution.
     */
    public function cancel(): static
    {
        $this->isCancelled = true;
        $this->isRunning = false;
        $this->markRemainingAsSkipped();
        $this->finishedAt = new DateTimeImmutable;

        return $this;
    }

    /**
     * Finish the execution.
     */
    public function finish(): static
    {
        $this->isRunning = false;
        $this->finishedAt = new DateTimeImmutable;

        return $this;
    }

    /**
     * Check if script is currently running.
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Check if script is paused.
     */
    public function isPaused(): bool
    {
        return $this->isPaused;
    }

    /**
     * Check if script was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    /**
     * Get the script key.
     */
    public function getScriptKey(): ?string
    {
        return $this->scriptKey;
    }

    /**
     * Get the script label.
     */
    public function getScriptLabel(): ?string
    {
        return $this->scriptLabel;
    }

    /**
     * Get all command states.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Check if the execution completed successfully (all commands succeeded).
     */
    public function isSuccessful(): bool
    {
        if ($this->isRunning || $this->isCancelled) {
            return false;
        }

        foreach ($this->commands as $command) {
            if ($command['status'] !== ScriptCommandStatus::Success->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any command failed.
     */
    public function hasFailed(): bool
    {
        foreach ($this->commands as $command) {
            if ($command['status'] === ScriptCommandStatus::Failed->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of completed commands.
     */
    public function getCompletedCount(): int
    {
        $completed = 0;
        foreach ($this->commands as $command) {
            $status = ScriptCommandStatus::from($command['status']);
            if ($status->isComplete()) {
                $completed++;
            }
        }

        return $completed;
    }

    /**
     * Get the number of successful commands.
     */
    public function getSuccessCount(): int
    {
        return count(array_filter(
            $this->commands,
            fn (array $cmd): bool => $cmd['status'] === ScriptCommandStatus::Success->value
        ));
    }

    /**
     * Get the number of failed commands.
     */
    public function getFailedCount(): int
    {
        return count(array_filter(
            $this->commands,
            fn (array $cmd): bool => $cmd['status'] === ScriptCommandStatus::Failed->value
        ));
    }

    /**
     * Get total execution time of all completed commands.
     */
    public function getTotalExecutionTime(): float
    {
        $total = 0.0;
        foreach ($this->commands as $command) {
            if ($command['executionTime'] !== null) {
                $total += $command['executionTime'];
            }
        }

        return $total;
    }

    /**
     * Check if execution has started.
     */
    public function hasStarted(): bool
    {
        return $this->scriptKey !== null;
    }

    /**
     * Reset the execution state.
     */
    public function reset(): static
    {
        $this->isRunning = false;
        $this->isPaused = false;
        $this->isCancelled = false;
        $this->currentCommandIndex = 0;
        $this->scriptKey = null;
        $this->scriptLabel = null;
        $this->commands = [];
        $this->startedAt = null;
        $this->finishedAt = null;

        return $this;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isRunning' => $this->isRunning,
            'isPaused' => $this->isPaused,
            'isCancelled' => $this->isCancelled,
            'currentCommandIndex' => $this->currentCommandIndex,
            'scriptKey' => $this->scriptKey,
            'scriptLabel' => $this->scriptLabel,
            'commands' => $this->commands,
            'startedAt' => $this->startedAt?->format('c'),
            'finishedAt' => $this->finishedAt?->format('c'),
            'progressPercentage' => $this->getProgressPercentage(),
            'totalCommands' => $this->getTotalCommands(),
            'completedCount' => $this->getCompletedCount(),
            'successCount' => $this->getSuccessCount(),
            'failedCount' => $this->getFailedCount(),
        ];
    }
}
