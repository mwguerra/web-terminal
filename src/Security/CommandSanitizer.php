<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Security;

use MWGuerra\WebTerminal\Exceptions\ValidationException;

/**
 * Sanitizes command input to prevent shell injection attacks.
 *
 * This service implements multiple layers of protection:
 * - Character-level blocking and escaping
 * - Pattern-based injection detection
 * - Shell command escaping wrappers
 */
class CommandSanitizer
{
    /**
     * Characters that should be blocked entirely.
     *
     * @var array<int, string>
     */
    protected array $blockedCharacters = [
        ';',     // Command separator
        '|',     // Pipe
        '&',     // Background/And
        '$',     // Variable expansion
        '`',     // Backtick command substitution
        "\n",    // Newline
        "\r",    // Carriage return
        "\x00",  // Null byte
    ];

    /**
     * Patterns that indicate injection attempts.
     *
     * @var array<int, string>
     */
    protected array $injectionPatterns = [
        '/\$\(/',          // $(command)
        '/\$\{/',          // ${variable}
        '/\>\s*\>/',       // >> redirect
        '/\<\s*\</',       // << here-doc
        '/\|\s*\|/',       // || or
        '/\&\s*\&/',       // && and
        '/\>\s*\//',       // > /path redirect
        '/\<\s*\//',       // < /path input redirect
        '/\x00/',          // Null bytes
        '/\\\\x[0-9a-fA-F]{2}/', // Hex escape sequences
        '/\\\\[0-7]{1,3}/', // Octal escape sequences
    ];

    /**
     * Whether to escape arguments automatically.
     */
    protected bool $autoEscape = true;

    protected bool $allowPipes = false;

    protected bool $allowRedirection = false;

    protected bool $allowChaining = false;

    protected bool $allowExpansion = false;

    /**
     * Create a new CommandSanitizer instance.
     *
     * @param  array<int, string>  $blockedCharacters  Characters to block
     */
    public function __construct(
        array $blockedCharacters = [],
        bool $allowPipes = false,
        bool $allowRedirection = false,
        bool $allowChaining = false,
        bool $allowExpansion = false,
    ) {
        if (! empty($blockedCharacters)) {
            $this->blockedCharacters = $blockedCharacters;
        }
        $this->allowPipes = $allowPipes;
        $this->allowRedirection = $allowRedirection;
        $this->allowChaining = $allowChaining;
        $this->allowExpansion = $allowExpansion;
    }

    /**
     * Create a CommandSanitizer from configuration.
     */
    public static function fromConfig(): self
    {
        return new self(
            blockedCharacters: config('web-terminal.blocked_characters', []),
        );
    }

    /**
     * Sanitize a command and its arguments.
     *
     * @param  string  $command  The command to sanitize
     * @return string The sanitized command
     *
     * @throws ValidationException If dangerous patterns are detected
     */
    public function sanitize(string $command): string
    {
        // Check for blocked characters first
        $this->checkBlockedCharacters($command);

        // Check for injection patterns
        $this->checkInjectionPatterns($command);

        // If auto-escape is enabled, escape the command
        if ($this->autoEscape) {
            return $this->escapeCommand($command);
        }

        return $command;
    }

    /**
     * Sanitize without throwing exceptions, return null on failure.
     *
     * @param  string  $command  The command to sanitize
     * @return string|null The sanitized command or null on failure
     */
    public function sanitizeOrNull(string $command): ?string
    {
        try {
            return $this->sanitize($command);
        } catch (ValidationException $e) {
            return null;
        }
    }

    /**
     * Check if a command is safe without sanitizing.
     *
     * @param  string  $command  The command to check
     */
    public function isSafe(string $command): bool
    {
        return $this->sanitizeOrNull($command) !== null;
    }

    /**
     * Check for blocked characters in the command.
     *
     * @throws ValidationException If blocked characters are found
     */
    protected function checkBlockedCharacters(string $command): void
    {
        foreach ($this->getEffectiveBlockedCharacters() as $char) {
            if (str_contains($command, $char)) {
                throw ValidationException::blockedCharacters($command, $char);
            }
        }
    }

    /**
     * Check for injection patterns in the command.
     *
     * @throws ValidationException If injection patterns are detected
     */
    protected function checkInjectionPatterns(string $command): void
    {
        foreach ($this->getEffectiveInjectionPatterns() as $pattern) {
            if (preg_match($pattern, $command)) {
                throw ValidationException::injectionAttempt($command);
            }
        }
    }

    /**
     * Escape a shell command for safe execution.
     *
     * This is a safe wrapper around escapeshellcmd with additional
     * edge case handling.
     *
     * @param  string  $command  The command to escape
     * @return string The escaped command
     */
    public function escapeCommand(string $command): string
    {
        // Handle empty string
        if ($command === '') {
            return '';
        }

        // Split into binary and arguments to handle them separately
        $parts = $this->splitCommandParts($command);

        if (empty($parts)) {
            return '';
        }

        // The binary doesn't need escaping, but arguments do
        $binary = array_shift($parts);

        if (empty($parts)) {
            return $binary;
        }

        // Escape each argument individually for better safety
        $escapedArgs = array_map(fn ($arg) => $this->escapeArgument($arg), $parts);

        return $binary.' '.implode(' ', $escapedArgs);
    }

    /**
     * Escape a single argument for shell execution.
     *
     * This wraps escapeshellarg with additional handling.
     *
     * @param  string  $argument  The argument to escape
     * @return string The escaped argument
     */
    public function escapeArgument(string $argument): string
    {
        // Handle empty argument
        if ($argument === '') {
            return "''";
        }

        // If argument is already properly quoted, verify and return
        if ($this->isProperlyQuoted($argument)) {
            return $argument;
        }

        // Use escapeshellarg for standard escaping
        return escapeshellarg($argument);
    }

    /**
     * Check if an argument is already properly quoted.
     */
    protected function isProperlyQuoted(string $argument): bool
    {
        $length = strlen($argument);

        if ($length < 2) {
            return false;
        }

        // Check for single quotes
        if ($argument[0] === "'" && $argument[$length - 1] === "'") {
            // Ensure no unescaped single quotes inside
            $inner = substr($argument, 1, -1);

            return ! str_contains($inner, "'");
        }

        // Check for double quotes
        if ($argument[0] === '"' && $argument[$length - 1] === '"') {
            // More complex check needed for double quotes
            $inner = substr($argument, 1, -1);

            // Check for unescaped double quotes
            return ! preg_match('/(?<!\\\\)"/', $inner);
        }

        return false;
    }

    /**
     * Split a command into binary and argument parts.
     *
     * @param  string  $command  The command to split
     * @return array<int, string> Command parts
     */
    protected function splitCommandParts(string $command): array
    {
        $parts = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            // Handle escape sequences
            if ($char === '\\' && $i + 1 < $length) {
                $current .= $char.$command[$i + 1];
                $i++;

                continue;
            }

            // Handle quotes
            if (($char === '"' || $char === "'") && ! $inQuote) {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;

                continue;
            }

            if ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                $quoteChar = '';
                $current .= $char;

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
     * Strip dangerous patterns from a string.
     *
     * @param  string  $input  The input to clean
     * @return string The cleaned string
     */
    public function stripDangerous(string $input): string
    {
        $result = str_replace($this->getEffectiveBlockedCharacters(), '', $input);

        foreach ($this->getEffectiveInjectionPatterns() as $pattern) {
            $result = preg_replace($pattern, '', $result) ?? $result;
        }

        return $result;
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
     * Get blocked characters.
     *
     * @return array<int, string>
     */
    public function getBlockedCharacters(): array
    {
        return $this->blockedCharacters;
    }

    /**
     * Add an injection pattern.
     *
     * @param  string  $pattern  Regex pattern to detect
     * @return $this
     */
    public function addInjectionPattern(string $pattern): static
    {
        if (! in_array($pattern, $this->injectionPatterns, true)) {
            $this->injectionPatterns[] = $pattern;
        }

        return $this;
    }

    /**
     * Get injection patterns.
     *
     * @return array<int, string>
     */
    public function getInjectionPatterns(): array
    {
        return $this->injectionPatterns;
    }

    /**
     * Enable or disable auto-escaping.
     *
     * @param  bool  $enabled  Whether to enable auto-escaping
     * @return $this
     */
    public function setAutoEscape(bool $enabled): static
    {
        $this->autoEscape = $enabled;

        return $this;
    }

    /**
     * Check if auto-escaping is enabled.
     */
    public function isAutoEscapeEnabled(): bool
    {
        return $this->autoEscape;
    }

    public function allowPipes(bool $allow = true): static
    {
        $this->allowPipes = $allow;

        return $this;
    }

    public function allowRedirection(bool $allow = true): static
    {
        $this->allowRedirection = $allow;

        return $this;
    }

    public function allowChaining(bool $allow = true): static
    {
        $this->allowChaining = $allow;

        return $this;
    }

    public function allowExpansion(bool $allow = true): static
    {
        $this->allowExpansion = $allow;

        return $this;
    }

    public function allowAllShellOperators(bool $allow = true): static
    {
        $this->allowPipes = $allow;
        $this->allowRedirection = $allow;
        $this->allowChaining = $allow;
        $this->allowExpansion = $allow;

        return $this;
    }

    /**
     * Get the effective blocked characters, filtering out allowed operator groups.
     *
     * @return array<int, string>
     */
    protected function getEffectiveBlockedCharacters(): array
    {
        $chars = $this->blockedCharacters;

        if ($this->allowPipes) {
            $chars = array_filter($chars, fn ($c) => $c !== '|');
        }

        if ($this->allowChaining) {
            $chars = array_filter($chars, fn ($c) => ! in_array($c, [';', '&'], true));
        }

        if ($this->allowExpansion) {
            $chars = array_filter($chars, fn ($c) => ! in_array($c, ['$', '`'], true));
        }

        return array_values($chars);
    }

    /**
     * Get the effective injection patterns, filtering out allowed operator groups.
     *
     * @return array<int, string>
     */
    protected function getEffectiveInjectionPatterns(): array
    {
        $patterns = $this->injectionPatterns;

        if ($this->allowRedirection) {
            $patterns = array_filter($patterns, fn ($p) => ! in_array($p, [
                '/\>\s*\>/',
                '/\<\s*\</',
                '/\>\s*\//',
                '/\<\s*\//',
            ], true));
        }

        if ($this->allowChaining) {
            $patterns = array_filter($patterns, fn ($p) => ! in_array($p, [
                '/\|\s*\|/',
                '/\&\s*\&/',
            ], true));
        }

        if ($this->allowExpansion) {
            $patterns = array_filter($patterns, fn ($p) => ! in_array($p, [
                '/\$\(/',
                '/\$\{/',
                '/\\\\x[0-9a-fA-F]{2}/',
                '/\\\\[0-7]{1,3}/',
            ], true));
        }

        return array_values($patterns);
    }
}
