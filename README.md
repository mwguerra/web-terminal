# Web Terminal

A secure web terminal package for Laravel with Filament integration. Execute allowed commands on local systems or SSH servers.

> **Warning**
>
> This package provides real shell access to real servers. Commands executed through this terminal can modify files, change configurations, and affect running services.
>
> **Before using this package:**
> - Restrict access to technical personnel only — do not expose terminal pages to general users
> - Review and limit the allowed commands whitelist for each terminal instance
> - Enable logging to maintain an audit trail of all executed commands
> - Test configurations in a safe environment before deploying to production
>
> **Use at your own risk.** The authors are not responsible for any damage, data loss, or security incidents resulting from the use of this package. Always ensure proper access controls and review your security configuration.

## Installation

```bash
composer require mwguerra/web-terminal
```

### Interactive Setup

Run the install command for guided configuration:

```bash
php artisan terminal:install
```

The installer will:
- Publish configuration file
- Publish database migration (with optional tenant support)
- Optionally publish Blade views for customization
- Optionally run the migration

#### Install Options

```bash
# Standard installation (interactive)
php artisan terminal:install

# Multi-tenant installation (adds tenant_id column)
php artisan terminal:install --with-tenant

# Standard installation (explicitly no tenant)
php artisan terminal:install --no-tenant

# Force overwrite existing files
php artisan terminal:install --force
```

#### Non-Interactive Installation

Use `--no-interaction` (or `-n`) with specific flags to skip prompts:

```bash
# Install only config file
php artisan terminal:install --config -n

# Install only migration
php artisan terminal:install --migration -n

# Install config, migration, and run migrate
php artisan terminal:install --config --migration --migrate -n

# Install page only (requires --panel for multi-panel apps)
php artisan terminal:install --page --panel=admin -n

# Install everything non-interactively
php artisan terminal:install --config --migration --views --page --resource --migrate --panel=admin -n
```

| Flag | Description |
|------|-------------|
| `--config` | Publish the configuration file |
| `--migration` | Publish the database migration |
| `--views` | Publish Blade views for customization |
| `--migrate` | Run migration after publishing |
| `--page` | Generate a custom Terminal page |
| `--resource` | Generate a custom TerminalLogs resource |
| `--panel=` | Specify the Filament panel (required for page/resource in multi-panel apps) |

**Note:** When using `-n` without any flags, the installer defaults to `--config --migration`.

#### Generate Custom Pages

Generate customizable Terminal page and TerminalLogs resource in your application instead of using the plugin defaults:

```bash
# Generate a custom Terminal page
php artisan terminal:install --page

# Generate a custom TerminalLogs resource
php artisan terminal:install --resource

# Generate both page and resource
php artisan terminal:install --page --resource

# Generate for a specific panel (multi-panel apps)
php artisan terminal:install --page --panel=admin
```

The generated files are placed in directories configured in your panel provider via `->discoverPages()` and `->discoverResources()`. For example:

| Generated File | Location |
|----------------|----------|
| Terminal page | `app/Filament/Pages/Terminal.php` |
| TerminalLogResource | `app/Filament/Resources/TerminalLogResource.php` |
| ListTerminalLogs | `app/Filament/Resources/TerminalLogResource/Pages/ListTerminalLogs.php` |
| ViewTerminalLog | `app/Filament/Resources/TerminalLogResource/Pages/ViewTerminalLog.php` |

> **Important:** When using custom generated pages with the plugin, you **must** disable the corresponding plugin defaults to avoid duplicate pages in your navigation.

```php
// If you generated only the Terminal page
WebTerminalPlugin::make()
    ->withoutTerminalPage()

// If you generated only the TerminalLogs resource
WebTerminalPlugin::make()
    ->withoutTerminalLogs()

// If you generated both, disable both plugin defaults
WebTerminalPlugin::make()
    ->withoutTerminalPage()
    ->withoutTerminalLogs()

// Or use only() to keep plugin services without any default pages
WebTerminalPlugin::make()
    ->only([])
```

If you don't need the plugin at all (using only custom pages), you can remove `WebTerminalPlugin::make()` from your panel provider entirely.

#### Terminal Command Permissions

When generating a custom Terminal page, you can specify command permissions:

```bash
# Default - safe readonly commands (ls, pwd, cd, cat, grep, etc.)
php artisan terminal:install --page --allow-secure-commands

# Allow all commands (dangerous - use with caution)
php artisan terminal:install --page --allow-all-commands

# No commands - configure manually in the generated file
php artisan terminal:install --page --allow-no-commands
```

| Option | Generated Configuration | Description |
|--------|------------------------|-------------|
| `--allow-secure-commands` (default) | `->allowedCommands([...])` | Safe readonly commands |
| `--allow-all-commands` | `->allowAllCommands()` | All commands allowed (dangerous) |
| `--allow-no-commands` | `->allowedCommands([])` | No commands (configure manually) |

#### Create Terminal Page Command

For more control over page generation, use the dedicated `terminal:make-page` command:

```bash
# Interactive mode - prompts for all options
php artisan terminal:make-page

# Create a page with a specific name
php artisan terminal:make-page ServerTerminal

# Specify panel and terminal key
php artisan terminal:make-page ServerTerminal --panel=admin --key=server-term

# Set command permissions
php artisan terminal:make-page AdminTerminal --allow-all-commands
php artisan terminal:make-page ViewerTerminal --allow-no-commands

# Non-interactive with defaults
php artisan terminal:make-page --no-interaction

# Overwrite existing file
php artisan terminal:make-page Terminal --force
```

| Option | Description |
|--------|-------------|
| `name` | The page class name (default: `Terminal`) |
| `--panel=` | The Filament panel to create the page for |
| `--key=` | The terminal identifier key for logging |
| `--allow-secure-commands` | Safe readonly commands (default) |
| `--allow-all-commands` | All commands allowed (dangerous) |
| `--allow-no-commands` | No commands (configure manually) |
| `--force` | Overwrite existing file |

**Interactive prompts (when not using `--no-interaction`):**
1. **Panel selection** - if multiple panels exist
2. **Page class name** - defaults to "Terminal"
3. **Terminal key** - auto-generated from name (e.g., `server-terminal-terminal`)
4. **Command permissions** - secure/none/all options

### Manual Setup

If you prefer manual setup:

```bash
# Publish configuration
php artisan vendor:publish --tag=web-terminal-config

# Publish views (optional)
php artisan vendor:publish --tag=web-terminal-views
```

## Filament Integration

The package provides a Filament plugin that adds:
- **Terminal Page**: A demo page with local terminal functionality
- **Terminal Logs Resource**: Browse and search terminal session logs with stats widgets

### Register the Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use MWGuerra\WebTerminal\WebTerminalPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                WebTerminalPlugin::make(),
            ]);
    }
}
```

### Plugin Configuration

#### Customize Navigation

```php
WebTerminalPlugin::make()
    // Configure Terminal page navigation
    ->terminalNavigation(
        icon: 'heroicon-o-command-line',
        label: 'Terminal',
        sort: 100,
        group: 'Tools',
    )
    // Configure Terminal Logs resource navigation
    ->terminalLogsNavigation(
        icon: 'heroicon-o-clipboard-document-list',
        label: 'Terminal Logs',
        sort: 101,
        group: 'Tools',
    )
```

#### Disable Components

```php
// Disable Terminal page (only show logs)
WebTerminalPlugin::make()
    ->withoutTerminalPage()

// Disable Terminal Logs resource (only show terminal)
WebTerminalPlugin::make()
    ->withoutTerminalLogs()

// Register only specific components
WebTerminalPlugin::make()
    ->only([
        \MWGuerra\WebTerminal\Filament\Pages\Terminal::class,
    ])
```

#### Access Plugin Configuration

```php
use MWGuerra\WebTerminal\WebTerminalPlugin;

// Get current plugin instance
$plugin = WebTerminalPlugin::get();

// Check if components are enabled
$plugin->isTerminalPageEnabled();
$plugin->isTerminalLogsEnabled();

// Get navigation configuration
$plugin->getTerminalNavigationIcon();
$plugin->getTerminalNavigationLabel();
$plugin->getTerminalNavigationSort();
$plugin->getTerminalNavigationGroup();
```

### Customizing the Plugin's Terminal Page

The plugin provides a ready-to-use Terminal page at `/admin/terminal`. To customize its terminal configuration (allowed commands, working directory, etc.), create your own page that extends the base:

```php
<?php

namespace App\Filament\Pages;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MWGuerra\WebTerminal\Filament\Pages\Terminal as BaseTerminal;
use MWGuerra\WebTerminal\Schemas\Components\WebTerminal;

class Terminal extends BaseTerminal
{
    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server Terminal')
                    ->description('Execute commands on the server.')
                    ->icon('heroicon-o-command-line')
                    ->schema([
                        WebTerminal::make()
                            ->key('custom-terminal')
                            ->local()
                            // Customize allowed commands
                            ->allowedCommands([
                                'ls', 'pwd', 'cd', 'cat', 'head', 'tail',
                                'git *',
                                'php artisan *',
                                'composer *',
                                'npm *', 'yarn *',
                            ])
                            // Set working directory
                            ->workingDirectory(base_path())
                            // Enable login shell for full environment
                            ->loginShell()
                            // UI customization
                            ->timeout(60)
                            ->height('500px')
                            ->title('Development Terminal')
                            ->windowControls(true)
                            ->startConnected(false)
                            // Logging
                            ->log(
                                enabled: true,
                                commands: true,
                                identifier: 'dev-terminal',
                            ),
                    ]),
            ]);
    }
}
```

Then disable the plugin's default page and register your custom one:

```php
// In your PanelProvider
WebTerminalPlugin::make()
    ->withoutTerminalPage()  // Disable default Terminal page
```

```php
// Register your custom page
public function panel(Panel $panel): Panel
{
    return $panel
        ->pages([
            \App\Filament\Pages\Terminal::class,
        ])
        ->plugins([
            WebTerminalPlugin::make()
                ->withoutTerminalPage(),
        ]);
}
```

## Usage

### Option 1: Direct Livewire Component

Embed the terminal directly in any Blade view:

```blade
<livewire:web-terminal
    :connection="['type' => 'local']"
    :allowed-commands="['ls', 'pwd', 'cd', 'cat', 'head', 'tail']"
    :timeout="10"
    prompt="$ "
    :history-limit="50"
    height="400px"
/>
```

### Option 2: Filament Schema Component (Recommended)

Use the schema component for Filament pages with fluent configuration:

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use MWGuerra\WebTerminal\Schemas\Components\WebTerminal;

class Terminal extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.terminal';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Terminal')
                    ->description('Execute allowed commands.')
                    ->icon('heroicon-o-command-line')
                    ->collapsible()
                    ->schema([
                        WebTerminal::make()
                            ->local()
                            ->allowedCommands(['ls', 'pwd', 'cd'])
                            ->timeout(10)
                            ->prompt('$ ')
                            ->historyLimit(50)
                            ->height('350px'),
                    ]),
            ]);
    }
}
```

View file (`resources/views/filament/pages/terminal.blade.php`):

```blade
<x-filament-panels::page>
    {{ $this->schema }}
</x-filament-panels::page>
```

## Default Allowed Commands

The package uses command whitelisting for security. Only commands in the whitelist can be executed.

### Config File Defaults

The `config/web-terminal.php` file includes these default allowed commands:

```php
'allowed_commands' => [
    'ls', 'ls *',
    'pwd',
    'cd', 'cd *',
    'uname', 'uname *',
    'whoami',
    'date',
    'uptime',
    'df -h',
    'free -m',
    'cat *',
    'head *',
    'tail *',
    'wc *',
    'grep *',
],
```

**Note:** The `*` wildcard allows the command with any arguments (e.g., `cd /var/log`, `uname -a`, `grep pattern file.txt`).

### Plugin Terminal Page Defaults

The Filament plugin's Terminal page (`/admin/terminal`) uses an extended command set suitable for Laravel development:

| Command | Description |
|---------|-------------|
| `ls`, `ls *` | List directory contents |
| `pwd` | Print working directory |
| `cd`, `cd *` | Change directory |
| `uname`, `uname *` | System information |
| `whoami` | Display current user |
| `date` | Show current date/time |
| `uptime` | System uptime |
| `cat *`, `head *`, `tail *` | View file contents |
| `wc *` | Word/line count |
| `grep *` | Search file contents |
| `php artisan *` | Laravel Artisan commands |
| `composer *` | Composer package manager |

**Note:** The `*` wildcard allows the command with any arguments (e.g., `php artisan migrate`, `composer require package/name`).

### Customizing Commands

Override the defaults per-terminal using `allowedCommands()`:

```php
WebTerminal::make()
    ->allowedCommands([
        'ls', 'pwd', 'cd',           // Basic navigation
        'git *',                      // All git commands
        'npm *', 'yarn *',           // Package managers
        'php artisan migrate',        // Specific artisan command only
    ])
```

Or bypass the whitelist entirely (use with caution):

```php
WebTerminal::make()
    ->allowAllCommands()
```

## Configuration Options

### Dynamic Configuration with Closures

All configuration methods accept Closures for dynamic resolution at runtime. This is useful for:
- Resolving values based on the authenticated user
- Loading configuration from the database or external sources
- Conditional logic based on current state

```php
WebTerminal::make()
    ->key(fn () => 'terminal-' . auth()->id())
    ->allowedCommands(fn () => auth()->user()->isAdmin()
        ? ['*']
        : ['ls', 'pwd', 'cat'])
    ->ssh(fn () => [
        'host' => auth()->user()->server->host,
        'username' => auth()->user()->server->username,
        'key' => auth()->user()->server->private_key,
    ])
    ->log(fn () => [
        'enabled' => true,
        'commands' => true,
        'identifier' => 'user-' . auth()->id(),
        'metadata' => [
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'tenant_id' => filament()->getTenant()?->id,
        ],
    ])
```

### Connection Types

#### Local Connection

```php
WebTerminal::make()
    ->local()
    ->allowedCommands(['ls', 'pwd', 'cd'])
```

#### SSH Connection with Password

```php
WebTerminal::make()
    ->ssh(
        host: 'server.example.com',
        username: 'deploy',
        password: 'your-password',
        port: 22
    )
```

#### SSH Connection with Array Configuration

Use an array to configure SSH connections, useful when loading configuration dynamically:

```php
WebTerminal::make()
    ->ssh([
        'host' => 'server.example.com',
        'username' => 'deploy',
        'password' => 'your-password',
        'port' => 22,
    ])

// With key authentication
WebTerminal::make()
    ->ssh([
        'host' => 'server.example.com',
        'username' => 'deploy',
        'key' => file_get_contents('/path/to/private_key'),
        'passphrase' => 'optional-passphrase',
    ])

// With Closure for dynamic resolution
WebTerminal::make()
    ->ssh(fn () => [
        'host' => auth()->user()->server->host,
        'username' => auth()->user()->server->username,
        'key' => auth()->user()->server->private_key,
    ])
```

#### SSH Connection with Key

The `key` parameter accepts the private key content directly. Load it using your preferred method:

```php
// Key from file
WebTerminal::make()
    ->ssh(
        host: 'localhost',
        username: 'root',
        key: file_get_contents('/path/to/private_key'),
        port: 2222
    )

// Key from environment variable
WebTerminal::make()
    ->ssh(
        host: 'localhost',
        username: 'root',
        key: env('SSH_PRIVATE_KEY'),
        port: 22
    )

// Key from Laravel Storage
use Illuminate\Support\Facades\Storage;

WebTerminal::make()
    ->ssh(
        host: 'localhost',
        username: 'root',
        key: Storage::disk('local')->get('ssh/private_key'),
        port: 2222
    )

// Key with passphrase
WebTerminal::make()
    ->ssh(
        host: 'localhost',
        username: 'root',
        key: file_get_contents('/path/to/encrypted_key'),
        passphrase: 'key-passphrase',
        port: 22
    )
```

#### Generic Connection (Advanced)

For complex configurations, use the `connection()` method with a config array or `ConnectionConfig` object:

```php
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;

// Using array
WebTerminal::make()
    ->connection([
        'type' => 'ssh',
        'host' => 'server.example.com',
        'username' => 'deploy',
        'password' => 'secret',
        'port' => 22,
    ])

// Using ConnectionConfig object
$config = new ConnectionConfig(
    type: ConnectionType::SSH,
    host: 'server.example.com',
    username: 'deploy',
    password: 'secret',
    port: 22,
);

WebTerminal::make()->connection($config)
```

### Terminal Settings

| Method | Description | Default |
|--------|-------------|---------|
| `key(string)` | Unique identifier for the terminal instance | `'web-terminal'` |
| `allowedCommands(array)` | Commands users can execute | `[]` |
| `allowAllCommands()` | Bypass command whitelist (use with caution) | `false` |
| `loginShell()` | Use login shell for full environment (loads .bashrc) | `false` |
| `timeout(int)` | Command timeout in seconds | `10` |
| `prompt(string)` | Terminal prompt symbol | `'$ '` |
| `historyLimit(int)` | Command history size | `50` |
| `maxOutputLines(int)` | Max output lines to display | `1000` |
| `height(string)` | Terminal height | `'350px'` |
| `workingDirectory(string)` | Initial working directory | `null` |
| `environment(array)` | Environment variables for commands | `[]` |
| `shell(string)` | Shell to use for execution | `'/bin/bash'` |

### UI Settings

| Method | Description | Default |
|--------|-------------|---------|
| `title(string)` | Title shown in terminal header bar | `'Terminal'` |
| `windowControls(bool)` | Show macOS-style window control dots | `true` |
| `startConnected(bool)` | Auto-connect on page load | `false` |

### Environment Helpers

| Method | Description |
|--------|-------------|
| `path(string)` | Set custom PATH environment variable |
| `inheritPath()` | Inherit PATH from server's environment |

```php
// Example: Make NVM-installed node available
WebTerminal::make()
    ->local()
    ->path('/home/user/.nvm/versions/node/v18.0.0/bin:/usr/local/bin:/usr/bin:/bin')
    ->allowedCommands(['node', 'npm', 'npx'])

// Or inherit the server's PATH
WebTerminal::make()
    ->local()
    ->inheritPath()
    ->loginShell()  // Also loads shell profile for full environment
```

### Preset Configurations

Quick presets that configure allowed commands for common use cases:

| Preset | Allowed Commands | Use Case |
|--------|------------------|----------|
| `readOnly()` | `ls`, `pwd`, `cat`, `head`, `tail`, `find`, `grep` | View-only file access |
| `fileBrowser()` | `ls`, `pwd`, `cd`, `cat`, `head`, `tail`, `find` | Navigate and view files |
| `gitTerminal()` | `git`, `ls`, `pwd`, `cd`, `cat` | Git operations |
| `dockerTerminal()` | `docker`, `docker-compose`, `ls`, `pwd`, `cd` | Container management |
| `nodeTerminal()` | `npm`, `npx`, `node`, `yarn`, `ls`, `pwd`, `cd`, `cat` | Node.js development |
| `artisanTerminal()` | `php`, `composer`, `ls`, `pwd`, `cd`, `cat` | Laravel development |

```php
// Read-only file browser
WebTerminal::make()->readOnly()

// File browser with navigation
WebTerminal::make()->fileBrowser()

// Git operations
WebTerminal::make()->gitTerminal()

// Docker management
WebTerminal::make()->dockerTerminal()

// Node.js development
WebTerminal::make()->nodeTerminal()

// Laravel Artisan commands
WebTerminal::make()->artisanTerminal()
```

**Note:** Presets can be combined with other methods:

```php
WebTerminal::make()
    ->gitTerminal()                    // Use git preset
    ->workingDirectory(base_path())    // Set working directory
    ->loginShell()                     // Enable full shell environment
    ->title('Git Terminal')            // Custom title
    ->height('400px')
```

## Logging

The package includes comprehensive logging for terminal sessions, connections, and command execution.

### Configuration

Logging is configured in `config/web-terminal.php`:

```php
'logging' => [
    // Global toggle - can be overridden per-terminal
    'enabled' => env('WEB_TERMINAL_LOGGING', true),

    // What to log
    'log_connections' => env('WEB_TERMINAL_LOG_CONNECTIONS', true),
    'log_disconnections' => env('WEB_TERMINAL_LOG_DISCONNECTIONS', true),
    'log_commands' => env('WEB_TERMINAL_LOG_COMMANDS', true),
    'log_output' => env('WEB_TERMINAL_LOG_OUTPUT', false), // Can be verbose
    'log_errors' => env('WEB_TERMINAL_LOG_ERRORS', true),

    // Output handling
    'max_output_length' => env('WEB_TERMINAL_MAX_OUTPUT_LOG', 10000),
    'truncate_output' => true,

    // User configuration
    'user_table' => 'users',
    'user_foreign_key' => 'user_id',

    // Retention (cleanup via manual `terminal:cleanup` command)
    'retention_days' => env('WEB_TERMINAL_LOG_RETENTION', 90),

    // Terminals to log (empty = all)
    'terminals' => [], // ['local-terminal', 'ssh-terminal']

    // Multi-tenant support
    'tenant_column' => null, // 'tenant_id' if using tenancy
    'tenant_resolver' => null, // fn () => auth()->user()?->tenant_id
],
```

### Fluent API

Override logging settings per-terminal using the `log()` method:

```php
// Enable logging with all defaults from config
WebTerminal::make()
    ->local()
    ->log()

// Enable logging with custom settings (named parameters)
WebTerminal::make()
    ->key('server-terminal')
    ->ssh(host: 'server.com', username: 'admin', password: 'secret')
    ->log(
        enabled: true,                    // Enable logging
        connections: true,                // Log connect/disconnect
        commands: true,                   // Log all commands
        output: false,                    // Don't log output (verbose)
        identifier: 'production-ssh',     // Custom identifier for filtering
    )

// Disable logging for a specific terminal
WebTerminal::make()
    ->key('debug-terminal')
    ->local()
    ->log(enabled: false)

// Full audit logging (including output)
WebTerminal::make()
    ->key('audit-terminal')
    ->local()
    ->log(
        enabled: true,
        output: true,
        identifier: 'full-audit-terminal',
    )
```

#### Array and Closure Configuration

The `log()` method also accepts an array or Closure, which is useful for dynamic configuration and adding custom metadata:

```php
// Array configuration with metadata
WebTerminal::make()
    ->local()
    ->log([
        'enabled' => true,
        'connections' => true,
        'commands' => true,
        'identifier' => 'admin-terminal',
        'metadata' => [
            'context' => 'admin_panel',
            'feature' => 'server_maintenance',
        ],
    ])

// Closure for dynamic resolution (recommended for user-specific data)
WebTerminal::make()
    ->local()
    ->log(fn () => [
        'enabled' => true,
        'commands' => true,
        'identifier' => 'user-terminal',
        'metadata' => [
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'tenant_id' => filament()->getTenant()?->id,
            'session_started_at' => now()->toIso8601String(),
        ],
    ])
```

#### Log Configuration Options

| Key | Type | Description |
|-----|------|-------------|
| `enabled` | `bool` | Enable/disable logging |
| `connections` | `bool` | Log connect/disconnect events |
| `commands` | `bool` | Log command executions |
| `output` | `bool` | Log command output (can be verbose) |
| `identifier` | `string` | Custom identifier for filtering logs |
| `metadata` | `array` | Custom metadata stored with each log entry |

### Event Types

The logger tracks these event types:
- `connected` - Terminal session started
- `disconnected` - Terminal session ended
- `command` - Command executed
- `output` - Command output (when enabled)
- `error` - Error occurred

### Session Info Panel

When logging is enabled, the terminal info panel displays session statistics:
- Session ID
- Commands Run
- Session Duration
- Error Count

### Querying Logs

```php
use MWGuerra\WebTerminal\Models\TerminalLog;

// Get all commands for a user today
TerminalLog::forUser(auth()->id())
    ->commands()
    ->whereDate('created_at', today())
    ->get();

// Get session summary
$logger = app(TerminalLogger::class);
$summary = $logger->getSessionSummary($sessionId);

// Get logs for a specific terminal
TerminalLog::forTerminal('production-ssh')
    ->recent(24) // Last 24 hours
    ->get();

// Get recent errors
TerminalLog::errors()
    ->recent(72)
    ->get();

// Get connection history
TerminalLog::connections()
    ->forUser($userId)
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();
```

### Available Scopes

| Scope | Description |
|-------|-------------|
| `forSession(string $sessionId)` | Filter by session ID |
| `forTerminal(string $identifier)` | Filter by terminal identifier |
| `forUser(int $userId)` | Filter by user ID |
| `forTenant(mixed $tenantId)` | Filter by tenant ID |
| `connections()` | Only connection events |
| `commands()` | Only command events |
| `errors()` | Only error events |
| `recent(int $hours)` | Logs from the last N hours |
| `olderThan(int $days)` | Logs older than N days |

### Log Cleanup

Clean up old log entries manually:

```bash
# Clean logs older than retention period (default 90 days)
php artisan terminal:cleanup

# Clean logs older than 30 days
php artisan terminal:cleanup --days=30

# Preview what would be deleted (dry run)
php artisan terminal:cleanup --dry-run
```

### Multi-Tenant Support

For multi-tenant applications, configure the tenant resolver:

```php
// In config/web-terminal.php
'logging' => [
    'tenant_column' => 'tenant_id',
    'tenant_resolver' => fn () => auth()->user()?->tenant_id,
],
```

Or use a class-based resolver:

```php
'tenant_resolver' => App\Services\TenantResolver::class,
```

The resolver class must implement `__invoke()`:

```php
class TenantResolver
{
    public function __invoke(): ?int
    {
        return session('current_tenant_id');
    }
}
```

## Built-in Commands

The terminal includes these built-in commands:

- `help` - Show available commands
- `clear` - Clear terminal output
- `history` - Show command history

## Security

- Only whitelisted commands can be executed (unless `allowAllCommands()` is used)
- Command arguments are sanitized
- Sessions are isolated per user
- Rate limiting protection
- All commands can be logged for auditing

### Rate Limiting

Configure rate limiting in the config file:

```php
'rate_limit' => [
    'enabled' => env('WEB_TERMINAL_RATE_LIMIT', true),
    'max_attempts' => env('WEB_TERMINAL_RATE_LIMIT_MAX', 1),   // Commands per decay period
    'decay_seconds' => env('WEB_TERMINAL_RATE_LIMIT_DECAY', 1), // Decay window in seconds
],
```

The default settings allow 1 command per second per user. Adjust based on your use case:

```php
// More permissive: 10 commands per 10 seconds
'rate_limit' => [
    'enabled' => true,
    'max_attempts' => 10,
    'decay_seconds' => 10,
],
```

## Events

The package dispatches events you can listen to:

- `MWGuerra\WebTerminal\Events\CommandExecutedEvent` - After command execution
- `MWGuerra\WebTerminal\Events\TerminalConnectedEvent` - When terminal connects
- `MWGuerra\WebTerminal\Events\TerminalDisconnectedEvent` - When terminal disconnects

Example listener:

```php
use MWGuerra\WebTerminal\Events\CommandExecutedEvent;

class LogDangerousCommands
{
    public function handle(CommandExecutedEvent $event): void
    {
        if (str_contains($event->command, 'rm ')) {
            Log::warning('Dangerous command executed', $event->toArray());
        }
    }
}
```

## Example Use Cases

### Server Monitoring Dashboard

```php
WebTerminal::make()
    ->key('server-monitor')
    ->ssh(
        host: config('servers.production.host'),
        username: config('servers.production.user'),
        key: Storage::get('ssh/production.key'),
    )
    ->allowedCommands([
        'htop', 'top', 'df', 'free', 'uptime',
        'ps', 'netstat', 'iostat', 'vmstat',
    ])
    ->timeout(30)
    ->log(identifier: 'production-monitor')
    ->title('Production Server')
    ->height('500px')
```

### Developer Sandbox

```php
WebTerminal::make()
    ->key('dev-sandbox')
    ->local()
    ->workingDirectory(base_path())
    ->loginShell()
    ->allowedCommands([
        'php', 'composer', 'npm', 'node', 'yarn',
        'git', 'artisan', 'pest', 'phpunit',
    ])
    ->environment([
        'PATH' => '/usr/local/bin:/usr/bin:/bin:' . base_path('vendor/bin'),
    ])
    ->log(
        commands: true,
        output: true, // Full audit trail
        identifier: 'developer-sandbox',
    )
    ->title('Development Terminal')
```

### Docker Container Management

```php
WebTerminal::make()
    ->key('docker-terminal')
    ->local()
    ->dockerTerminal() // Preset with docker commands
    ->log(identifier: 'docker-ops')
    ->title('Docker Management')
```

### Database Admin (Read-Only)

```php
WebTerminal::make()
    ->key('db-readonly')
    ->local()
    ->allowedCommands(['mysql', 'psql', 'redis-cli'])
    ->timeout(60)
    ->log(
        commands: true,
        identifier: 'database-readonly',
    )
    ->title('Database Console')
```

### Multi-Tenant SaaS

```php
WebTerminal::make()
    ->key('tenant-terminal-' . auth()->user()->tenant_id)
    ->local()
    ->workingDirectory('/var/www/tenants/' . auth()->user()->tenant_id)
    ->allowedCommands(['ls', 'cat', 'tail', 'grep'])
    ->log(identifier: 'tenant-' . auth()->user()->tenant_id)
    ->title('Tenant Files')
```

## Localization

The package includes translations for English (en) and Brazilian Portuguese (pt_BR). All UI labels, messages, and Filament resource strings are translatable.

### Publishing Language Files

To customize translations, publish the language files:

```bash
php artisan vendor:publish --tag=web-terminal-lang
```

This will copy the language files to `lang/vendor/web-terminal/`.

### Using Translations

Translations use the `web-terminal::terminal` namespace:

```php
// In PHP
__('web-terminal::terminal.navigation.terminal')
__('web-terminal::terminal.resource.label')

// In Blade
{{ __('web-terminal::terminal.terminal.connect') }}
```

### Available Translation Keys

| Group | Keys |
|-------|------|
| `terminal.*` | Terminal UI strings (connect, disconnect, session info, etc.) |
| `navigation.*` | Navigation labels |
| `pages.*` | Page titles and descriptions |
| `resource.*` | Resource labels |
| `table.*` | Table column labels |
| `filters.*` | Filter labels |
| `events.*` | Event type labels |
| `connection_types.*` | Connection type labels |
| `infolist.*` | View page section and field labels |
| `widgets.*` | Stats widget labels |

### Adding New Languages

Create a new directory in `lang/` with your locale code and copy the structure from `en/terminal.php`:

```bash
mkdir -p lang/es
cp lang/en/terminal.php lang/es/terminal.php
# Edit lang/es/terminal.php with Spanish translations
```

## License

MIT License
