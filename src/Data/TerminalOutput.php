<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Data;

use DateTimeImmutable;
use MWGuerra\WebTerminal\Enums\OutputType;

/**
 * Value object representing a single output line/block for terminal display.
 *
 * This readonly class represents individual output items that will be
 * displayed in the terminal UI, with proper typing for styling purposes.
 */
readonly class TerminalOutput
{
    /**
     * @param  OutputType  $type  The type of output (stdout, stderr, error, info, command)
     * @param  string  $content  The content to display
     * @param  DateTimeImmutable  $timestamp  When this output was generated
     */
    public function __construct(
        public OutputType $type,
        public string $content,
        public DateTimeImmutable $timestamp = new DateTimeImmutable,
    ) {}

    /**
     * Create a stdout output.
     */
    public static function stdout(string $content): self
    {
        return new self(OutputType::Stdout, $content);
    }

    /**
     * Create a stderr output.
     */
    public static function stderr(string $content): self
    {
        return new self(OutputType::Stderr, $content);
    }

    /**
     * Create an error message output.
     */
    public static function error(string $content): self
    {
        return new self(OutputType::Error, $content);
    }

    /**
     * Create an info message output.
     */
    public static function info(string $content): self
    {
        return new self(OutputType::Info, $content);
    }

    /**
     * Create a command echo output (shows the command that was entered).
     */
    public static function command(string $content): self
    {
        return new self(OutputType::Command, $content);
    }

    /**
     * Create a system message output.
     */
    public static function system(string $content): self
    {
        return new self(OutputType::System, $content);
    }

    /**
     * Create multiple outputs from a CommandResult.
     *
     * @return array<self>
     */
    public static function fromCommandResult(CommandResult $result): array
    {
        $outputs = [];

        if ($result->command !== '') {
            $outputs[] = self::command($result->command);
        }

        if ($result->stdout !== '') {
            $outputs[] = self::stdout($result->stdout);
        }

        if ($result->stderr !== '') {
            $outputs[] = self::stderr($result->stderr);
        }

        return $outputs;
    }

    /**
     * Check if this is an error-type output.
     */
    public function isError(): bool
    {
        return $this->type === OutputType::Error || $this->type === OutputType::Stderr;
    }

    /**
     * Check if this is a standard output.
     */
    public function isStdout(): bool
    {
        return $this->type === OutputType::Stdout;
    }

    /**
     * Check if this is informational output.
     */
    public function isInfo(): bool
    {
        return $this->type === OutputType::Info || $this->type === OutputType::System;
    }

    /**
     * Get CSS class for styling this output type.
     */
    public function cssClass(): string
    {
        return $this->type->cssClass();
    }

    /**
     * Get the formatted timestamp.
     */
    public function formattedTimestamp(): string
    {
        return $this->timestamp->format('H:i:s');
    }

    /**
     * Split content into lines.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        if ($this->content === '') {
            return [];
        }

        return explode("\n", $this->content);
    }

    /**
     * Check if the content is empty.
     */
    public function isEmpty(): bool
    {
        return $this->content === '';
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'content' => $this->content,
            'timestamp' => $this->timestamp->format('c'),
            'css_class' => $this->cssClass(),
        ];
    }
}
