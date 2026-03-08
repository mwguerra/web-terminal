<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Security\CredentialManager;
use MWGuerra\WebTerminal\Security\SensitiveValue;

describe('CredentialManager', function () {
    describe('encryption', function () {
        it('encrypts and decrypts values', function () {
            $manager = new CredentialManager;

            $encrypted = $manager->encrypt('my-secret');

            expect($encrypted)->not->toBe('my-secret');

            $decrypted = $manager->decrypt($encrypted);

            expect($decrypted)->toBe('my-secret');
        });

        it('produces different encrypted values each time', function () {
            $manager = new CredentialManager;

            $encrypted1 = $manager->encrypt('same-value');
            $encrypted2 = $manager->encrypt('same-value');

            expect($encrypted1)->not->toBe($encrypted2);
        });
    });

    describe('wrap', function () {
        it('wraps values in SensitiveValue', function () {
            $manager = new CredentialManager;

            $wrapped = $manager->wrap('secret');

            expect($wrapped)->toBeInstanceOf(SensitiveValue::class);
            expect($wrapped->reveal())->toBe('secret');
        });

        it('wraps encrypted values', function () {
            $manager = new CredentialManager;

            $encrypted = $manager->encrypt('secret');
            $wrapped = $manager->wrapEncrypted($encrypted);

            expect($wrapped)->toBeInstanceOf(SensitiveValue::class);
            expect($wrapped->reveal())->toBe('secret');
        });
    });

    describe('sensitive field detection', function () {
        it('detects known sensitive field names', function () {
            $manager = new CredentialManager;

            expect($manager->isSensitiveField('password'))->toBeTrue();
            expect($manager->isSensitiveField('private_key'))->toBeTrue();
            expect($manager->isSensitiveField('privateKey'))->toBeTrue();
            expect($manager->isSensitiveField('passphrase'))->toBeTrue();
            expect($manager->isSensitiveField('api_token'))->toBeTrue();
            expect($manager->isSensitiveField('apiToken'))->toBeTrue();
            expect($manager->isSensitiveField('secret'))->toBeTrue();
            expect($manager->isSensitiveField('token'))->toBeTrue();
        });

        it('detects sensitive patterns', function () {
            $manager = new CredentialManager;

            expect($manager->isSensitiveField('user_password'))->toBeTrue();
            expect($manager->isSensitiveField('db_secret'))->toBeTrue();
            expect($manager->isSensitiveField('api_auth'))->toBeTrue();
            expect($manager->isSensitiveField('ssh_key'))->toBeTrue();
            expect($manager->isSensitiveField('credential_store'))->toBeTrue();
        });

        it('identifies non-sensitive fields', function () {
            $manager = new CredentialManager;

            expect($manager->isSensitiveField('username'))->toBeFalse();
            expect($manager->isSensitiveField('host'))->toBeFalse();
            expect($manager->isSensitiveField('port'))->toBeFalse();
            expect($manager->isSensitiveField('timeout'))->toBeFalse();
        });
    });

    describe('redact', function () {
        it('redacts sensitive fields from array', function () {
            $manager = new CredentialManager;

            $data = [
                'username' => 'admin',
                'password' => 'secret123',
                'host' => 'example.com',
                'api_token' => 'tok_12345',
            ];

            $redacted = $manager->redact($data);

            expect($redacted['username'])->toBe('admin');
            expect($redacted['host'])->toBe('example.com');
            expect($redacted['password'])->toBe('[REDACTED]');
            expect($redacted['api_token'])->toBe('[REDACTED]');
        });

        it('handles nested arrays', function () {
            $manager = new CredentialManager;

            $data = [
                'connection' => [
                    'host' => 'example.com',
                    'credentials' => [
                        'username' => 'admin',
                        'password' => 'secret',
                    ],
                ],
            ];

            $redacted = $manager->redact($data);

            expect($redacted['connection']['host'])->toBe('example.com');
            expect($redacted['connection']['credentials']['username'])->toBe('admin');
            expect($redacted['connection']['credentials']['password'])->toBe('[REDACTED]');
        });
    });

    describe('sanitizeConfig', function () {
        it('returns safe config for frontend', function () {
            $manager = new CredentialManager;

            $config = ConnectionConfig::sshWithPassword(
                host: 'example.com',
                username: 'admin',
                password: 'super-secret',
                port: 22,
            );

            $sanitized = $manager->sanitizeConfig($config);

            expect($sanitized)->toBeArray();
            expect($sanitized['type'])->toBe('ssh');
            expect($sanitized['host'])->toBe('example.com');
            expect($sanitized['username'])->toBe('admin');
            expect($sanitized['port'])->toBe(22);
            expect($sanitized['has_password'])->toBeTrue();
            expect($sanitized)->not->toHaveKey('password');
        });

        it('indicates key authentication', function () {
            $manager = new CredentialManager;

            $config = ConnectionConfig::sshWithKey(
                host: 'example.com',
                username: 'admin',
                privateKey: '-----BEGIN RSA PRIVATE KEY-----...',
            );

            $sanitized = $manager->sanitizeConfig($config);

            expect($sanitized['uses_key_auth'])->toBeTrue();
            expect($sanitized)->not->toHaveKey('private_key');
            expect($sanitized)->not->toHaveKey('privateKey');
        });
    });

    describe('prepareForStorage', function () {
        it('encrypts sensitive fields when autoEncrypt is true', function () {
            $manager = new CredentialManager(autoEncrypt: true);

            $credentials = [
                'username' => 'admin',
                'password' => 'secret123',
            ];

            $prepared = $manager->prepareForStorage($credentials);

            expect($prepared['username'])->toBe('admin');
            expect($prepared['password'])->not->toBe('secret123');
            expect($prepared['password_encrypted'])->toBeTrue();
        });

        it('does not encrypt when autoEncrypt is false', function () {
            $manager = new CredentialManager(autoEncrypt: false);

            $credentials = [
                'username' => 'admin',
                'password' => 'secret123',
            ];

            $prepared = $manager->prepareForStorage($credentials);

            expect($prepared['username'])->toBe('admin');
            expect($prepared['password'])->toBe('secret123');
            expect($prepared)->not->toHaveKey('password_encrypted');
        });

        it('does not encrypt empty values', function () {
            $manager = new CredentialManager(autoEncrypt: true);

            $credentials = [
                'password' => '',
            ];

            $prepared = $manager->prepareForStorage($credentials);

            expect($prepared['password'])->toBe('');
            expect($prepared)->not->toHaveKey('password_encrypted');
        });
    });

    describe('restoreFromStorage', function () {
        it('decrypts encrypted fields', function () {
            $manager = new CredentialManager(autoEncrypt: true);

            $credentials = [
                'username' => 'admin',
                'password' => 'secret123',
            ];

            $prepared = $manager->prepareForStorage($credentials);
            $restored = $manager->restoreFromStorage($prepared);

            expect($restored['username'])->toBe('admin');
            expect($restored['password'])->toBe('secret123');
            expect($restored)->not->toHaveKey('password_encrypted');
        });

        it('leaves non-encrypted fields unchanged', function () {
            $manager = new CredentialManager;

            $stored = [
                'username' => 'admin',
                'host' => 'example.com',
            ];

            $restored = $manager->restoreFromStorage($stored);

            expect($restored['username'])->toBe('admin');
            expect($restored['host'])->toBe('example.com');
        });
    });

    describe('createConfigFromEncrypted', function () {
        it('creates config from encrypted credentials', function () {
            $manager = new CredentialManager(autoEncrypt: true);

            $original = [
                'type' => 'ssh',
                'host' => 'example.com',
                'username' => 'admin',
                'password' => 'secret123',
            ];

            $stored = $manager->prepareForStorage($original);
            $config = $manager->createConfigFromEncrypted($stored);

            expect($config)->toBeInstanceOf(ConnectionConfig::class);
            expect($config->type)->toBe(ConnectionType::SSH);
            expect($config->host)->toBe('example.com');
            expect($config->username)->toBe('admin');
            expect($config->password)->toBe('secret123');
        });
    });

    describe('detectSensitiveData', function () {
        it('detects sensitive fields in data', function () {
            $manager = new CredentialManager;

            $data = [
                'username' => 'admin',
                'password' => 'secret',
                'host' => 'example.com',
            ];

            $detected = $manager->detectSensitiveData($data);

            expect($detected)->toContain('password');
            expect($detected)->not->toContain('username');
            expect($detected)->not->toContain('host');
        });

        it('detects nested sensitive fields', function () {
            $manager = new CredentialManager;

            $data = [
                'connection' => [
                    'host' => 'example.com',
                    'password' => 'secret',
                ],
            ];

            $detected = $manager->detectSensitiveData($data);

            expect($detected)->toContain('connection.password');
        });
    });

    describe('mask', function () {
        it('masks the middle of a credential', function () {
            $manager = new CredentialManager;

            $masked = $manager->mask('secretpassword123');

            expect($masked)->toStartWith('sec');
            expect($masked)->toEndWith('123');
            expect($masked)->toContain('*');
            expect($masked)->not->toContain('etpassword');
        });

        it('fully masks short values', function () {
            $manager = new CredentialManager;

            $masked = $manager->mask('short', 3);

            expect($masked)->toBe('*****');
        });

        it('respects showChars parameter', function () {
            $manager = new CredentialManager;

            $masked = $manager->mask('password123456', 2);

            expect($masked)->toStartWith('pa');
            expect($masked)->toEndWith('56');
        });
    });

    describe('secureCompare', function () {
        it('compares equal values', function () {
            $manager = new CredentialManager;

            expect($manager->secureCompare('secret', 'secret'))->toBeTrue();
        });

        it('compares different values', function () {
            $manager = new CredentialManager;

            expect($manager->secureCompare('secret', 'other'))->toBeFalse();
        });
    });

    describe('generateSecureToken', function () {
        it('generates tokens of specified length', function () {
            $manager = new CredentialManager;

            $token = $manager->generateSecureToken(32);

            expect(strlen($token))->toBe(32);
        });

        it('generates unique tokens', function () {
            $manager = new CredentialManager;

            $token1 = $manager->generateSecureToken();
            $token2 = $manager->generateSecureToken();

            expect($token1)->not->toBe($token2);
        });

        it('generates hex characters only', function () {
            $manager = new CredentialManager;

            $token = $manager->generateSecureToken(64);

            expect($token)->toMatch('/^[0-9a-f]+$/');
        });
    });

    describe('isValidSshKey', function () {
        it('validates OpenSSH keys', function () {
            $manager = new CredentialManager;

            $key = "-----BEGIN OPENSSH PRIVATE KEY-----\nbase64content\n-----END OPENSSH PRIVATE KEY-----";

            expect($manager->isValidSshKey($key))->toBeTrue();
        });

        it('validates RSA keys', function () {
            $manager = new CredentialManager;

            $key = "-----BEGIN RSA PRIVATE KEY-----\nbase64content\n-----END RSA PRIVATE KEY-----";

            expect($manager->isValidSshKey($key))->toBeTrue();
        });

        it('validates EC keys', function () {
            $manager = new CredentialManager;

            $key = "-----BEGIN EC PRIVATE KEY-----\nbase64content\n-----END EC PRIVATE KEY-----";

            expect($manager->isValidSshKey($key))->toBeTrue();
        });

        it('rejects invalid key formats', function () {
            $manager = new CredentialManager;

            expect($manager->isValidSshKey('not-a-key'))->toBeFalse();
            expect($manager->isValidSshKey('-----BEGIN PUBLIC KEY-----'))->toBeFalse();
            expect($manager->isValidSshKey(''))->toBeFalse();
        });
    });
});
