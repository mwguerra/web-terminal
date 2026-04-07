<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Enums;

/**
 * Enum representing the execution status of a command within a script.
 */
enum ScriptCommandStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * Get the icon name for this status (Heroicon format).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-minus-circle',
            self::Running => 'heroicon-o-arrow-path',
            self::Success => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-x-circle',
            self::Skipped => 'heroicon-o-minus',
        };
    }

    /**
     * Get the CSS class for styling this status.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Pending => 'text-slate-400 dark:text-zinc-500',
            self::Running => 'text-blue-500',
            self::Success => 'text-emerald-500',
            self::Failed => 'text-red-500',
            self::Skipped => 'text-amber-500',
        };
    }

    /**
     * Get the text color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => '#94a3b8',
            self::Running => '#3b82f6',
            self::Success => '#10b981',
            self::Failed => '#ef4444',
            self::Skipped => '#f59e0b',
        };
    }

    /**
     * Check if this status represents a completed state.
     */
    public function isComplete(): bool
    {
        return match ($this) {
            self::Success, self::Failed, self::Skipped => true,
            default => false,
        };
    }

    /**
     * Check if this status represents a failure.
     */
    public function isFailure(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Check if this status allows the script to continue.
     */
    public function allowsContinuation(): bool
    {
        return match ($this) {
            self::Pending, self::Running, self::Success => true,
            default => false,
        };
    }
}
