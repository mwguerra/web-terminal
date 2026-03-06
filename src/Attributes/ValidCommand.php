<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Attributes;

use Attribute;

/**
 * Validation attribute for command values.
 *
 * Validates that a command string is safe and doesn't contain
 * dangerous shell metacharacters or injection patterns.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
readonly class ValidCommand
{
    /**
     * Dangerous shell metacharacters that could enable command injection.
     */
    private const DANGEROUS_CHARS = [
        ';',   // Command separator
        '|',   // Pipe
        '&',   // Background/AND operator
        '`',   // Command substitution
        '$(',  // Command substitution
        '${',  // Variable expansion
        '||',  // OR operator
        '&&',  // AND operator
        '>',   // Output redirection
        '<',   // Input redirection
        '>>',  // Append redirection
        '<<',  // Here document
    ];

    public function __construct(
        public bool $allowPipes = false,
        public bool $allowRedirection = false,
        public bool $allowChaining = false,
        public bool $allowExpansion = false,
        public int $maxLength = 1000,
        public string $message = 'The command contains unsafe characters',
    ) {}

    /**
     * Validate the given command value.
     */
    public function validate(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        // Check length
        if (strlen($value) > $this->maxLength) {
            return false;
        }

        // Check for null bytes
        if (str_contains($value, "\0")) {
            return false;
        }

        // Check for dangerous characters
        return ! $this->containsDangerousCharacters($value);
    }

    /**
     * Check if the command contains dangerous characters.
     */
    private function containsDangerousCharacters(string $command): bool
    {
        // Check for newlines and carriage returns
        if (str_contains($command, "\n") || str_contains($command, "\r")) {
            return true;
        }

        $charsToCheck = self::DANGEROUS_CHARS;

        // Remove pipe from dangerous chars if allowed
        if ($this->allowPipes) {
            $charsToCheck = array_filter(
                $charsToCheck,
                fn ($char) => $char !== '|'
            );
        }

        // Remove redirection chars if allowed
        if ($this->allowRedirection) {
            $charsToCheck = array_filter(
                $charsToCheck,
                fn ($char) => ! in_array($char, ['>', '<', '>>', '<<'], true)
            );
        }

        // Remove chaining chars if allowed
        if ($this->allowChaining) {
            $charsToCheck = array_filter(
                $charsToCheck,
                fn ($char) => ! in_array($char, [';', '&', '||', '&&'], true)
            );
        }

        // Remove expansion chars if allowed
        if ($this->allowExpansion) {
            $charsToCheck = array_filter(
                $charsToCheck,
                fn ($char) => ! in_array($char, ['`', '$(', '${'], true)
            );
        }

        // Check multi-character patterns first to avoid false positives
        // with single-character substrings
        $multiChar = [];
        $singleChar = [];

        foreach ($charsToCheck as $char) {
            if (strlen($char) > 1) {
                $multiChar[] = $char;
            } else {
                $singleChar[] = $char;
            }
        }

        foreach ($multiChar as $char) {
            if (str_contains($command, $char)) {
                return true;
            }
        }

        // For single-char checks, remove occurrences that are part of
        // allowed multi-char operators to avoid false positives
        $cleanedCommand = $command;

        if ($this->allowChaining) {
            $cleanedCommand = str_replace(['||', '&&'], '', $cleanedCommand);
        }

        if ($this->allowRedirection) {
            $cleanedCommand = str_replace(['>>', '<<'], '', $cleanedCommand);
        }

        foreach ($singleChar as $char) {
            if (str_contains($cleanedCommand, $char)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of dangerous characters being checked.
     *
     * @return array<string>
     */
    public function getDangerousCharacters(): array
    {
        return self::DANGEROUS_CHARS;
    }

    /**
     * Extract the base command (first word) from a command string.
     */
    public static function extractBaseCommand(string $command): string
    {
        $trimmed = trim($command);
        $parts = preg_split('/\s+/', $trimmed, 2);

        return $parts[0] ?? '';
    }
}
