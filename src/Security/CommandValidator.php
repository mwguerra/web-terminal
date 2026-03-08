<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Security;

use MWGuerra\WebTerminal\Exceptions\ValidationException;

/**
 * Validates commands against a whitelist and security rules.
 *
 * This service implements command validation with:
 * - O(1) whitelist lookup using hash sets
 * - Command and argument splitting
 * - Blocked character detection
 * - Pattern-based command matching
 */
class CommandValidator
{
    /**
     * Hash set for O(1) exact match lookup.
     *
     * @var array<string, true>
     */
    protected array $exactMatches = [];

    /**
     * Commands that allow any arguments (pattern: 'command *').
     *
     * @var array<string, true>
     */
    protected array $wildcardCommands = [];

    /**
     * Maximum command length allowed.
     */
    protected int $maxLength = 1000;

    /**
     * Blocked characters for injection prevention.
     *
     * @var array<int, string>
     */
    protected array $blockedCharacters = [];

    /**
     * Whether to allow all commands (bypass whitelist).
     */
    protected bool $allowAll = false;

    /**
     * Create a new CommandValidator instance.
     *
     * @param  array<int, string>  $allowedCommands  List of allowed commands
     * @param  array<int, string>  $blockedCharacters  Characters to block
     * @param  bool  $allowAll  Whether to allow all commands
     */
    public function __construct(
        array $allowedCommands = [],
        array $blockedCharacters = [],
        bool $allowAll = false,
    ) {
        $this->setAllowedCommands($allowedCommands);
        $this->setBlockedCharacters($blockedCharacters);
        $this->allowAll = $allowAll;
    }

    /**
     * Create a CommandValidator from configuration.
     */
    public static function fromConfig(): self
    {
        return new self(
            allowedCommands: config('web-terminal.allowed_commands', []),
            blockedCharacters: config('web-terminal.blocked_characters', []),
        );
    }

    /**
     * Set the list of allowed commands.
     *
     * @param  array<int, string>  $commands  List of allowed commands
     * @return $this
     */
    public function setAllowedCommands(array $commands): static
    {
        $this->exactMatches = [];
        $this->wildcardCommands = [];

        foreach ($commands as $command) {
            $this->addAllowedCommand($command);
        }

        return $this;
    }

    /**
     * Add a single allowed command.
     *
     * @param  string  $command  Command to allow
     * @return $this
     */
    public function addAllowedCommand(string $command): static
    {
        $command = trim($command);

        if ($command === '') {
            return $this;
        }

        // Check for wildcard pattern: 'command *'
        if (str_ends_with($command, ' *')) {
            $binary = substr($command, 0, -2);
            $this->wildcardCommands[$binary] = true;
        } else {
            // Exact match command
            $this->exactMatches[$command] = true;
        }

        return $this;
    }

    /**
     * Remove an allowed command.
     *
     * @param  string  $command  Command to remove
     * @return $this
     */
    public function removeAllowedCommand(string $command): static
    {
        $command = trim($command);

        if (str_ends_with($command, ' *')) {
            $binary = substr($command, 0, -2);
            unset($this->wildcardCommands[$binary]);
        } else {
            unset($this->exactMatches[$command]);
        }

        return $this;
    }

    /**
     * Set blocked characters.
     *
     * @param  array<int, string>  $characters  Characters to block
     * @return $this
     */
    public function setBlockedCharacters(array $characters): static
    {
        $this->blockedCharacters = $characters;

        return $this;
    }

    /**
     * Add a blocked character.
     *
     * @param  string  $character  Character to block
     * @return $this
     */
    public function addBlockedCharacter(string $character): static
    {
        if (! in_array($character, $this->blockedCharacters, true)) {
            $this->blockedCharacters[] = $character;
        }

        return $this;
    }

    /**
     * Set the maximum command length.
     *
     * @param  int  $length  Maximum length in characters
     * @return $this
     */
    public function setMaxLength(int $length): static
    {
        $this->maxLength = max(1, $length);

        return $this;
    }

    /**
     * Get the maximum command length.
     */
    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Validate a command and throw on failure.
     *
     * @param  string  $command  Command to validate
     *
     * @throws ValidationException If validation fails
     */
    public function validate(string $command): void
    {
        $result = $this->check($command);

        if (! $result->isValid()) {
            throw $result->getException();
        }
    }

    /**
     * Check if a command is valid without throwing.
     *
     * @param  string  $command  Command to check
     */
    public function check(string $command): ValidationResult
    {
        // Check for empty command
        if (trim($command) === '') {
            return ValidationResult::failed(ValidationException::emptyCommand());
        }

        // Check command length
        if (strlen($command) > $this->maxLength) {
            return ValidationResult::failed(
                ValidationException::tooLong($command, $this->maxLength)
            );
        }

        // Check for blocked characters
        foreach ($this->blockedCharacters as $char) {
            if (str_contains($command, $char)) {
                return ValidationResult::failed(
                    ValidationException::blockedCharacters($command, $char)
                );
            }
        }

        // Check if command is allowed
        if (! $this->isCommandAllowed($command)) {
            return ValidationResult::failed(
                ValidationException::notAllowed($command)
            );
        }

        return ValidationResult::passed($command);
    }

    /**
     * Check if a specific command is allowed.
     *
     * @param  string  $command  Full command string
     */
    public function isAllowed(string $command): bool
    {
        return $this->check($command)->isValid();
    }

    /**
     * Check if a command matches the whitelist (without blocked char checks).
     *
     * @param  string  $command  Command to check
     */
    protected function isCommandAllowed(string $command): bool
    {
        // If allowAll is enabled, all commands are allowed
        if ($this->allowAll) {
            return true;
        }

        $command = trim($command);

        // Direct exact match - O(1)
        if (isset($this->exactMatches[$command])) {
            return true;
        }

        // Extract binary and check wildcard patterns
        $parsed = $this->parseCommand($command);
        $binary = $parsed['binary'];

        // Check if the binary is allowed with any arguments
        if (isset($this->wildcardCommands[$binary])) {
            return true;
        }

        // Check if just the binary is allowed (no arguments in whitelist)
        // This handles cases like 'ls' being allowed when user runs 'ls -la'
        if (isset($this->exactMatches[$binary])) {
            return true;
        }

        // Check multi-word wildcard prefixes (e.g., 'php artisan *' matches 'php artisan tinker')
        // Progressively build longer prefixes from command parts to find matches
        $parts = $this->splitCommand($command);
        if (count($parts) > 1) {
            $prefix = '';
            for ($i = 0; $i < count($parts) - 1; $i++) {
                $prefix = $prefix === '' ? $parts[$i] : $prefix.' '.$parts[$i];
                if (isset($this->wildcardCommands[$prefix])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Parse a command into binary and arguments.
     *
     * @param  string  $command  Command string to parse
     * @return array{binary: string, arguments: string, parts: array<int, string>}
     */
    public function parseCommand(string $command): array
    {
        $command = trim($command);

        // Handle empty command
        if ($command === '') {
            return [
                'binary' => '',
                'arguments' => '',
                'parts' => [],
            ];
        }

        // Split on whitespace, preserving quoted strings
        $parts = $this->splitCommand($command);
        $binary = array_shift($parts) ?? '';
        $arguments = implode(' ', $parts);

        return [
            'binary' => $binary,
            'arguments' => $arguments,
            'parts' => $parts,
        ];
    }

    /**
     * Split a command string into parts, respecting quotes.
     *
     * @param  string  $command  Command to split
     * @return array<int, string>
     */
    protected function splitCommand(string $command): array
    {
        $parts = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            // Handle quotes
            if (($char === '"' || $char === "'") && ! $inQuote) {
                $inQuote = true;
                $quoteChar = $char;

                continue;
            }

            if ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                $quoteChar = '';

                continue;
            }

            // Handle whitespace outside quotes
            if (! $inQuote && ctype_space($char)) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        // Add the last part
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Get the list of allowed commands.
     *
     * @return array<int, string>
     */
    public function getAllowedCommands(): array
    {
        $commands = array_keys($this->exactMatches);

        foreach (array_keys($this->wildcardCommands) as $binary) {
            $commands[] = $binary.' *';
        }

        return $commands;
    }

    /**
     * Get the list of blocked characters.
     *
     * @return array<int, string>
     */
    public function getBlockedCharacters(): array
    {
        return $this->blockedCharacters;
    }

    /**
     * Check if any commands are configured.
     */
    public function hasAllowedCommands(): bool
    {
        return ! empty($this->exactMatches) || ! empty($this->wildcardCommands);
    }

    /**
     * Clear all allowed commands.
     *
     * @return $this
     */
    public function clearAllowedCommands(): static
    {
        $this->exactMatches = [];
        $this->wildcardCommands = [];

        return $this;
    }

    /**
     * Enable or disable allow all mode.
     *
     * When enabled, all commands are allowed (whitelist is bypassed).
     * Blocked characters are still checked for security.
     *
     * @param  bool  $allowAll  Whether to allow all commands
     * @return $this
     */
    public function setAllowAll(bool $allowAll): static
    {
        $this->allowAll = $allowAll;

        return $this;
    }

    /**
     * Check if allow all mode is enabled.
     */
    public function isAllowAll(): bool
    {
        return $this->allowAll;
    }
}
