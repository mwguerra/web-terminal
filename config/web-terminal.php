<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection Type
    |--------------------------------------------------------------------------
    |
    | Specify the default connection type for the terminal component.
    | Supported: "local", "ssh"
    |
    */
    'default_connection' => env('WEB_TERMINAL_CONNECTION', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Command Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum time (in seconds) a command can run before being terminated.
    | This helps prevent runaway processes from consuming resources.
    |
    */
    'timeout' => env('WEB_TERMINAL_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent abuse. Commands are limited to the
    | specified number of executions per second per user.
    |
    */
    'rate_limit' => [
        'enabled' => env('WEB_TERMINAL_RATE_LIMIT', true),
        'max_attempts' => env('WEB_TERMINAL_RATE_LIMIT_MAX', 1),
        'decay_seconds' => env('WEB_TERMINAL_RATE_LIMIT_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Commands
    |--------------------------------------------------------------------------
    |
    | A whitelist of commands that can be executed through the terminal.
    | Commands not on this list will be rejected. Use '*' with caution
    | as it allows all commands, which may pose security risks.
    |
    | You can specify commands in several formats:
    | - Exact match: 'ls', 'pwd', 'whoami'
    | - With arguments: 'ls -la', 'git status'
    | - Patterns: 'ls *' (allows ls with any arguments)
    |
    */
    'allowed_commands' => [
        'ls',
        'pwd',
        'cd',
        'uname',
        'whoami',
        'date',
        'uptime',
        'df -h',
        'free -m',
        'cat',
        'head',
        'tail',
        'wc',
        'grep',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked Characters
    |--------------------------------------------------------------------------
    |
    | Characters that will be blocked in command input to prevent injection
    | attacks. These characters are commonly used in shell injection.
    |
    */
    'blocked_characters' => [
        ';',    // Command separator
        '|',    // Pipe
        '&',    // Background/And
        '$',    // Variable expansion
        '`',    // Backtick command substitution
        '\\',   // Escape character
        "\n",   // Newline
        "\r",   // Carriage return
        '$()',  // Command substitution
        '${',   // Variable expansion
    ],

    /*
    |--------------------------------------------------------------------------
    | SSH Connection Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration for SSH connections. These values can be overridden
    | when configuring individual terminal instances.
    |
    */
    'ssh' => [
        'host' => env('WEB_TERMINAL_SSH_HOST', 'localhost'),
        'port' => env('WEB_TERMINAL_SSH_PORT', 22),
        'username' => env('WEB_TERMINAL_SSH_USER', 'root'),
        'auth_type' => env('WEB_TERMINAL_SSH_AUTH', 'key'), // 'key' or 'password'
        'private_key_path' => env('WEB_TERMINAL_SSH_KEY', '~/.ssh/id_rsa'),
        'password' => env('WEB_TERMINAL_SSH_PASSWORD'),
        'fingerprint' => env('WEB_TERMINAL_SSH_FINGERPRINT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal UI Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the terminal user interface appearance and behavior.
    |
    */
    'ui' => [
        'prompt' => env('WEB_TERMINAL_PROMPT', '$ '),
        'theme' => env('WEB_TERMINAL_THEME', 'dark'),
        'max_history' => env('WEB_TERMINAL_MAX_HISTORY', 5),
        'show_timestamp' => env('WEB_TERMINAL_SHOW_TIMESTAMP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditing
    |--------------------------------------------------------------------------
    |
    | Enable or disable command execution auditing. When enabled, all commands
    | executed through the terminal will be logged for review.
    |
    */
    'auditing' => [
        'enabled' => env('WEB_TERMINAL_AUDIT', true),
        'channel' => env('WEB_TERMINAL_AUDIT_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure terminal session logging to database. This provides detailed
    | tracking of terminal sessions, connections, commands, and outputs.
    | These settings can be overridden per-terminal using the ->log() method.
    |
    */
    'logging' => [
        // Global toggle - can be overridden per-terminal
        'enabled' => env('WEB_TERMINAL_LOGGING', true),

        // What to log
        'log_connections' => env('WEB_TERMINAL_LOG_CONNECTIONS', true),
        'log_disconnections' => env('WEB_TERMINAL_LOG_DISCONNECTIONS', true),
        'log_commands' => env('WEB_TERMINAL_LOG_COMMANDS', true),
        'log_output' => env('WEB_TERMINAL_LOG_OUTPUT', false), // Can be verbose
        'log_errors' => env('WEB_TERMINAL_LOG_ERRORS', true),

        // Output handling (stored in same table, truncated if needed)
        'max_output_length' => env('WEB_TERMINAL_MAX_OUTPUT_LOG', 10000),
        'truncate_output' => true,

        // User configuration
        'user_table' => 'users',
        'user_foreign_key' => 'user_id',

        // Retention (cleanup via manual `terminal:cleanup` command)
        'retention_days' => env('WEB_TERMINAL_LOG_RETENTION', 90),

        // Specific terminals to log (empty array = all terminals)
        'terminals' => [],

        // Multi-tenant support
        // Set to 'tenant_id' or your tenant column name if using tenancy
        'tenant_column' => null,

        // Custom resolver callback - receives no args, should return tenant ID or null
        // Example: fn () => auth()->user()?->tenant_id
        // Example: TenantResolver::class (must implement __invoke)
        'tenant_resolver' => null,
    ],
];
