<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Data;

use DateTimeImmutable;

/**
 * Value object representing the result of a command execution.
 *
 * This readonly class encapsulates all information about a completed
 * command execution, including output streams and timing information.
 */
readonly class CommandResult
{
    /**
     * @param  string  $stdout  Standard output from the command
     * @param  string  $stderr  Standard error output from the command
     * @param  int  $exitCode  Exit code from the command (0 typically means success)
     * @param  float  $executionTime  Time taken to execute the command in seconds
     * @param  string  $command  The command that was executed
     * @param  DateTimeImmutable  $executedAt  When the command was executed
     */
    public function __construct(
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public float $executionTime,
        public string $command = '',
        public DateTimeImmutable $executedAt = new DateTimeImmutable,
    ) {}

    /**
     * Create a successful command result.
     */
    public static function success(
        string $stdout,
        float $executionTime,
        string $command = '',
    ): self {
        return new self(
            stdout: $stdout,
            stderr: '',
            exitCode: 0,
            executionTime: $executionTime,
            command: $command,
        );
    }

    /**
     * Create a failed command result.
     */
    public static function failure(
        string $stderr,
        int $exitCode,
        float $executionTime,
        string $command = '',
        string $stdout = '',
    ): self {
        return new self(
            stdout: $stdout,
            stderr: $stderr,
            exitCode: $exitCode,
            executionTime: $executionTime,
            command: $command,
        );
    }

    /**
     * Create a result for a timed-out command.
     */
    public static function timeout(
        float $timeoutSeconds,
        string $command = '',
        string $partialOutput = '',
    ): self {
        return new self(
            stdout: $partialOutput,
            stderr: "Command timed out after {$timeoutSeconds} seconds",
            exitCode: 124,
            executionTime: $timeoutSeconds,
            command: $command,
        );
    }

    /**
     * Check if the command was successful (exit code 0).
     */
    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Check if the command failed (non-zero exit code).
     */
    public function isFailed(): bool
    {
        return $this->exitCode !== 0;
    }

    /**
     * Check if the command timed out.
     */
    public function isTimedOut(): bool
    {
        return $this->exitCode === 124;
    }

    /**
     * Get the combined output (stdout + stderr).
     */
    public function output(): string
    {
        if ($this->stderr === '') {
            return $this->stdout;
        }

        if ($this->stdout === '') {
            return $this->stderr;
        }

        return $this->stdout."\n".$this->stderr;
    }

    /**
     * Check if there is any output.
     */
    public function hasOutput(): bool
    {
        return $this->stdout !== '' || $this->stderr !== '';
    }

    /**
     * Check if there is error output.
     */
    public function hasError(): bool
    {
        return $this->stderr !== '';
    }

    /**
     * Get the output as an array of lines.
     *
     * @return array<int, string>
     */
    public function outputLines(): array
    {
        $output = $this->output();

        if ($output === '') {
            return [];
        }

        return explode("\n", $output);
    }

    /**
     * Get stdout as an array of lines.
     *
     * @return array<int, string>
     */
    public function stdoutLines(): array
    {
        if ($this->stdout === '') {
            return [];
        }

        return explode("\n", $this->stdout);
    }

    /**
     * Get stderr as an array of lines.
     *
     * @return array<int, string>
     */
    public function stderrLines(): array
    {
        if ($this->stderr === '') {
            return [];
        }

        return explode("\n", $this->stderr);
    }

    /**
     * Get the execution time formatted as a human-readable string.
     */
    public function formattedExecutionTime(): string
    {
        if ($this->executionTime < 1) {
            return round($this->executionTime * 1000).'ms';
        }

        return round($this->executionTime, 2).'s';
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'exit_code' => $this->exitCode,
            'execution_time' => $this->executionTime,
            'executed_at' => $this->executedAt->format('c'),
            'is_successful' => $this->isSuccessful(),
        ];
    }
}
