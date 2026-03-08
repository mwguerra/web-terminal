<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Security;

use JsonSerializable;
use Stringable;

/**
 * Wrapper for sensitive values that prevents accidental exposure.
 *
 * This class wraps sensitive data (passwords, API tokens, keys) and
 * provides controlled access while preventing exposure in logs,
 * var_dumps, or JSON serialization.
 */
final class SensitiveValue implements JsonSerializable, Stringable
{
    /**
     * The sensitive value.
     */
    private readonly string $value;

    /**
     * Create a new sensitive value wrapper.
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Create from an encrypted value using Laravel's encryption.
     */
    public static function fromEncrypted(string $encrypted): self
    {
        return new self(decrypt($encrypted));
    }

    /**
     * Create from a base64-encoded value.
     */
    public static function fromBase64(string $base64): self
    {
        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 encoding');
        }

        return new self($decoded);
    }

    /**
     * Create from a file path (e.g., SSH key file).
     */
    public static function fromFile(string $path): self
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        if (! is_readable($path)) {
            throw new \InvalidArgumentException("File not readable: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Could not read file: {$path}");
        }

        return new self($content);
    }

    /**
     * Create from an environment variable.
     */
    public static function fromEnv(string $name): self
    {
        $value = getenv($name);

        if ($value === false) {
            throw new \InvalidArgumentException("Environment variable not set: {$name}");
        }

        return new self($value);
    }

    /**
     * Wrap a value only if it's not already a SensitiveValue.
     */
    public static function wrap(string|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return new self($value);
    }

    /**
     * Get the underlying value.
     *
     * This method should only be called when the value is actually needed,
     * such as when establishing a connection.
     */
    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * Get the value encrypted using Laravel's encryption.
     */
    public function toEncrypted(): string
    {
        return encrypt($this->value);
    }

    /**
     * Check if the value is empty.
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Check if the value is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the length of the underlying value.
     */
    public function length(): int
    {
        return strlen($this->value);
    }

    /**
     * Check if the value matches another value.
     */
    public function equals(string|self $other): bool
    {
        $otherValue = $other instanceof self ? $other->reveal() : $other;

        // Use hash_equals to prevent timing attacks
        return hash_equals($this->value, $otherValue);
    }

    /**
     * Prevent the value from being serialized in JSON.
     */
    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }

    /**
     * Prevent the value from being exposed when cast to string.
     */
    public function __toString(): string
    {
        return '[REDACTED]';
    }

    /**
     * Prevent the value from being exposed in var_dump.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'value' => '[REDACTED]',
            'length' => $this->length(),
        ];
    }

    /**
     * Prevent serialization of sensitive values.
     *
     * @return array<string>
     */
    public function __serialize(): array
    {
        throw new \RuntimeException(
            'SensitiveValue cannot be serialized. Use toEncrypted() to store the value securely.'
        );
    }

    /**
     * Prevent unserialization of sensitive values.
     *
     * @param  array<string>  $data
     */
    public function __unserialize(array $data): void
    {
        throw new \RuntimeException(
            'SensitiveValue cannot be unserialized. Use fromEncrypted() to restore the value.'
        );
    }

    /**
     * Prevent cloning to maintain a single reference to the sensitive data.
     */
    public function __clone(): void
    {
        throw new \RuntimeException('SensitiveValue cannot be cloned.');
    }
}
