<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Security;

use Illuminate\Support\Facades\Crypt;
use MWGuerra\WebTerminal\Data\ConnectionConfig;

/**
 * Service for secure credential management.
 *
 * This service handles credential storage, retrieval, and redaction
 * to ensure sensitive data is never exposed to the frontend or logs.
 */
class CredentialManager
{
    /**
     * Fields that contain sensitive data and should never be exposed.
     *
     * @var array<string>
     */
    protected const SENSITIVE_FIELDS = [
        'password',
        'private_key',
        'privateKey',
        'passphrase',
        'api_token',
        'apiToken',
        'secret',
        'token',
    ];

    /**
     * Patterns that might indicate sensitive data.
     *
     * @var array<string>
     */
    protected const SENSITIVE_PATTERNS = [
        '/password/i',
        '/secret/i',
        '/token/i',
        '/key/i',
        '/credential/i',
        '/auth/i',
    ];

    /**
     * Create a CredentialManager instance.
     */
    public function __construct(
        protected bool $autoEncrypt = true,
    ) {}

    /**
     * Create a CredentialManager from configuration.
     */
    public static function fromConfig(): self
    {
        $config = config('web-terminal.security', []);

        return new self(
            autoEncrypt: $config['auto_encrypt_credentials'] ?? true,
        );
    }

    /**
     * Encrypt a credential value using Laravel's encryption.
     */
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt an encrypted credential value.
     */
    public function decrypt(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    /**
     * Wrap a credential in a SensitiveValue for safe handling.
     */
    public function wrap(string $value): SensitiveValue
    {
        return new SensitiveValue($value);
    }

    /**
     * Wrap a credential from an encrypted source.
     */
    public function wrapEncrypted(string $encrypted): SensitiveValue
    {
        return new SensitiveValue($this->decrypt($encrypted));
    }

    /**
     * Check if a field name appears to be sensitive.
     */
    public function isSensitiveField(string $fieldName): bool
    {
        // Check exact matches
        if (in_array($fieldName, self::SENSITIVE_FIELDS, true)) {
            return true;
        }

        // Check patterns
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact sensitive values from an array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);
            } elseif ($this->isSensitiveField($key)) {
                $redacted[$key] = '[REDACTED]';
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Sanitize a ConnectionConfig for safe frontend exposure.
     *
     * Returns only non-sensitive data that can be safely sent to frontend.
     *
     * @return array<string, mixed>
     */
    public function sanitizeConfig(ConnectionConfig $config): array
    {
        return [
            'type' => $config->type->value,
            'host' => $config->host,
            'username' => $config->username,
            'port' => $config->effectivePort(),
            'timeout' => $config->timeout,
            'uses_key_auth' => $config->usesKeyAuthentication(),
            'has_password' => $config->password !== null,
        ];
    }

    /**
     * Prepare credentials for storage by encrypting sensitive fields.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public function prepareForStorage(array $credentials): array
    {
        if (! $this->autoEncrypt) {
            return $credentials;
        }

        $prepared = [];

        foreach ($credentials as $key => $value) {
            if (is_string($value) && $this->isSensitiveField($key) && $value !== '') {
                $prepared[$key] = $this->encrypt($value);
                $prepared["{$key}_encrypted"] = true;
            } else {
                $prepared[$key] = $value;
            }
        }

        return $prepared;
    }

    /**
     * Restore credentials from storage by decrypting sensitive fields.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function restoreFromStorage(array $stored): array
    {
        $restored = [];

        foreach ($stored as $key => $value) {
            // Skip the encryption marker keys
            if (str_ends_with($key, '_encrypted')) {
                continue;
            }

            // Check if this field was encrypted
            if (isset($stored["{$key}_encrypted"]) && $stored["{$key}_encrypted"] === true) {
                $restored[$key] = $this->decrypt($value);
            } else {
                $restored[$key] = $value;
            }
        }

        return $restored;
    }

    /**
     * Create a ConnectionConfig from an array with encrypted credentials.
     *
     * @param  array<string, mixed>  $config
     */
    public function createConfigFromEncrypted(array $config): ConnectionConfig
    {
        return ConnectionConfig::fromArray(
            $this->restoreFromStorage($config)
        );
    }

    /**
     * Validate that credentials are not being logged or exposed.
     *
     * This can be called to check if any sensitive data might be
     * accidentally included in data intended for logging.
     *
     * @param  array<string, mixed>  $data
     * @return array<string> List of field names that contain sensitive data
     */
    public function detectSensitiveData(array $data): array
    {
        $sensitiveFields = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = $this->detectSensitiveData($value);
                foreach ($nested as $nestedKey) {
                    $sensitiveFields[] = "{$key}.{$nestedKey}";
                }
            } elseif ($this->isSensitiveField($key)) {
                $sensitiveFields[] = $key;
            }
        }

        return $sensitiveFields;
    }

    /**
     * Generate a masked version of a credential for display purposes.
     *
     * Shows only the first and last few characters, masking the rest.
     */
    public function mask(string $value, int $showChars = 3): string
    {
        $length = strlen($value);

        if ($length <= $showChars * 2) {
            return str_repeat('*', $length);
        }

        $start = substr($value, 0, $showChars);
        $end = substr($value, -$showChars);
        $masked = str_repeat('*', min($length - ($showChars * 2), 20));

        return "{$start}{$masked}{$end}";
    }

    /**
     * Securely compare two credential values.
     *
     * Uses timing-safe comparison to prevent timing attacks.
     */
    public function secureCompare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * Generate a secure random string for use as a token or secret.
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validate the format of an SSH private key.
     */
    public function isValidSshKey(string $key): bool
    {
        // Check for standard SSH key headers
        $validHeaders = [
            '-----BEGIN OPENSSH PRIVATE KEY-----',
            '-----BEGIN RSA PRIVATE KEY-----',
            '-----BEGIN DSA PRIVATE KEY-----',
            '-----BEGIN EC PRIVATE KEY-----',
            '-----BEGIN PRIVATE KEY-----',
        ];

        foreach ($validHeaders as $header) {
            if (str_contains($key, $header)) {
                return true;
            }
        }

        return false;
    }
}
