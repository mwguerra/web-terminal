<?php

namespace MWGuerra\WebTerminal\Schemas\Components;

use Closure;
use Filament\Schemas\Components\Livewire;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Livewire\WebTerminal as WebTerminalComponent;

/**
 * Web Terminal component for use in Filament schemas/forms.
 *
 * This component embeds the terminal into any Filament form or page using fluent API.
 * Extends Filament's built-in Livewire component for proper component isolation.
 *
 * @example
 * WebTerminal::make()
 *     ->local()
 *     ->allowedCommands(['ls', 'pwd', 'cd'])
 *     ->height('400px')
 *     ->prompt('$ ')
 */
class WebTerminal extends Livewire
{
    protected string|Closure $height = '350px';

    protected array|Closure $connectionConfig = ['type' => 'local'];

    protected array|Closure $allowedCommands = [];

    protected int|Closure $timeout = 10;

    protected string|Closure $prompt = '$ ';

    protected int|Closure $historyLimit = 50;

    protected int|Closure $maxOutputLines = 1000;

    protected ?string $workingDirectory = null;

    protected bool|Closure $allowAll = false;

    protected array|Closure $environment = [];

    protected bool|Closure $useLoginShell = false;

    protected string|Closure $shell = '/bin/bash';

    protected bool|Closure $startConnected = false;

    protected string|Closure $title = 'Terminal';

    protected bool|Closure $showWindowControls = true;

    // Logging configuration
    protected bool|Closure|null $loggingEnabled = null;

    protected bool|Closure|null $logConnections = null;

    protected bool|Closure|null $logCommands = null;

    protected bool|Closure|null $logOutput = null;

    protected string|Closure|null $logIdentifier = null;

    protected array|Closure $logMetadata = [];

    public static function make(Closure|string $component = null, Closure|array $data = []): static
    {
        $static = app(static::class, [
            'component' => $component ?? WebTerminalComponent::class,
            'data' => $data,
        ]);
        $static->configure();
        $static->key('web-terminal');

        return $static;
    }

    /**
     * Get the properties to pass to the Livewire component.
     *
     * @return array<string, mixed>
     */
    public function getComponentProperties(): array
    {
        $config = $this->getConnectionConfig();

        // Add working directory if set
        if ($this->workingDirectory !== null) {
            $config['working_directory'] = $this->workingDirectory;
        }

        return [
            ...parent::getComponentProperties(),
            'connection' => $config,
            'allowedCommands' => $this->getAllowedCommands(),
            'allowAllCommands' => $this->getAllowAll(),
            'environment' => $this->getEnvironment(),
            'useLoginShell' => $this->getUseLoginShell(),
            'shell' => $this->getShell(),
            'timeout' => $this->getTimeout(),
            'prompt' => $this->getPrompt(),
            'historyLimit' => $this->getHistoryLimit(),
            'maxOutputLines' => $this->getMaxOutputLines(),
            'height' => $this->getHeight(),
            'startConnected' => $this->getStartConnected(),
            'title' => $this->getTitle(),
            'showWindowControls' => $this->getShowWindowControls(),
            'loggingEnabled' => $this->getLoggingEnabled(),
            'logConnections' => $this->getLogConnections(),
            'logCommands' => $this->getLogCommands(),
            'logOutput' => $this->getLogOutput(),
            'logIdentifier' => $this->getLogIdentifier(),
            'logMetadata' => $this->getLogMetadata(),
        ];
    }

    // ========================================
    // Height Configuration
    // ========================================

    /**
     * Set the height of the terminal.
     */
    public function height(string|Closure $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get the height of the terminal.
     */
    public function getHeight(): string
    {
        return $this->evaluate($this->height);
    }

    // ========================================
    // Connection Configuration
    // ========================================

    /**
     * Set the connection configuration.
     */
    public function connection(array|Closure|ConnectionConfig $config): static
    {
        if ($config instanceof ConnectionConfig) {
            $this->connectionConfig = [
                'type' => $config->type->value,
                'host' => $config->host,
                'username' => $config->username,
                'password' => $config->password,
                'private_key' => $config->privateKey,
                'passphrase' => $config->passphrase,
                'api_token' => $config->apiToken,
                'port' => $config->port,
                'timeout' => $config->timeout,
                'working_directory' => $config->workingDirectory,
                'environment' => $config->environment,
            ];
        } else {
            $this->connectionConfig = $config;
        }

        return $this;
    }

    /**
     * Configure for local connection.
     */
    public function local(): static
    {
        $this->connectionConfig = ['type' => 'local'];

        return $this;
    }

    /**
     * Configure for SSH connection.
     *
     * Supports both password and key-based authentication:
     * - Password auth: provide `password` parameter
     * - Key auth: provide `key` parameter with the private key content
     *
     * Can be called with named parameters or an array/Closure:
     *
     * @example Named parameters:
     * ->ssh(host: 'example.com', username: 'user', password: 'pass')
     *
     * @example Array configuration:
     * ->ssh(['host' => 'example.com', 'username' => 'user', 'password' => 'pass'])
     *
     * @example Closure (evaluated at render time):
     * ->ssh(fn () => [
     *     'host' => config('ssh.host'),
     *     'username' => config('ssh.username'),
     *     'private_key' => Storage::get('ssh/key'),
     * ])
     *
     * @param  array|Closure|string  $config  Array/Closure config, or SSH host when using named params
     * @param  string|null  $username  SSH username (when using named params)
     * @param  string|null  $password  Password for password-based auth
     * @param  string|null  $key  Private key content for key-based auth
     * @param  string|null  $passphrase  Passphrase for encrypted private keys
     * @param  int  $port  SSH port (default: 22)
     */
    public function ssh(
        array|Closure|string $config,
        ?string $username = null,
        ?string $password = null,
        ?string $key = null,
        ?string $passphrase = null,
        int $port = 22
    ): static {
        // If config is array or Closure, use it directly
        if (is_array($config) || $config instanceof Closure) {
            $this->connectionConfig = $config instanceof Closure
                ? fn () => array_merge(['type' => 'ssh'], $this->evaluate($config))
                : array_merge(['type' => 'ssh'], $config);

            return $this;
        }

        // Named parameters style (config is the host string)
        $this->connectionConfig = [
            'type' => 'ssh',
            'host' => $config,
            'username' => $username,
            'password' => $password,
            'private_key' => $key,
            'passphrase' => $passphrase,
            'port' => $port,
        ];

        return $this;
    }

    /**
     * Get the connection configuration.
     */
    public function getConnectionConfig(): array
    {
        return $this->evaluate($this->connectionConfig);
    }

    // ========================================
    // Command Configuration
    // ========================================

    /**
     * Set the allowed commands.
     */
    public function allowedCommands(array|Closure $commands): static
    {
        $this->allowedCommands = $commands;

        return $this;
    }

    /**
     * Get the allowed commands.
     */
    public function getAllowedCommands(): array
    {
        return $this->evaluate($this->allowedCommands);
    }

    /**
     * Allow all commands (bypass whitelist).
     *
     * WARNING: This allows any command to be executed. Use with caution.
     * Only use this for development/testing purposes or in trusted environments.
     */
    public function allowAllCommands(bool|Closure $allowAll = true): static
    {
        $this->allowAll = $allowAll;

        return $this;
    }

    /**
     * Get whether all commands are allowed.
     */
    public function getAllowAll(): bool
    {
        return $this->evaluate($this->allowAll);
    }

    // ========================================
    // Environment Configuration
    // ========================================

    /**
     * Set environment variables for command execution.
     *
     * @param  array<string, string>|Closure  $environment  Environment variables
     */
    public function environment(array|Closure $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Get the environment variables.
     *
     * @return array<string, string>
     */
    public function getEnvironment(): array
    {
        return $this->evaluate($this->environment);
    }

    /**
     * Set the PATH environment variable.
     *
     * This is useful for making commands like node, composer, etc. available
     * when they are installed in non-standard locations (NVM, homebrew, etc.).
     */
    public function path(string|Closure $path): static
    {
        $currentEnv = $this->evaluate($this->environment);
        $currentEnv['PATH'] = $this->evaluate($path);
        $this->environment = $currentEnv;

        return $this;
    }

    /**
     * Inherit PATH from the current shell environment.
     *
     * This reads the PATH from the server's environment and uses it.
     * Note: This may not include user-specific paths from .bashrc/.zshrc.
     */
    public function inheritPath(): static
    {
        $currentEnv = $this->evaluate($this->environment);
        $currentEnv['PATH'] = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        $this->environment = $currentEnv;

        return $this;
    }

    // ========================================
    // Shell Configuration
    // ========================================

    /**
     * Enable login shell mode.
     *
     * When enabled, commands are wrapped with `bash -l -i -c` which loads
     * .bashrc/.bash_profile and initializes the full user environment
     * (including NVM, rbenv, pyenv, homebrew, etc.).
     *
     * This is the recommended way to get a "real terminal" experience
     * where all your shell customizations are available.
     */
    public function loginShell(bool|Closure $useLoginShell = true): static
    {
        $this->useLoginShell = $useLoginShell;

        return $this;
    }

    /**
     * Get whether login shell mode is enabled.
     */
    public function getUseLoginShell(): bool
    {
        return $this->evaluate($this->useLoginShell);
    }

    /**
     * Set the shell to use for command execution.
     *
     * @param  string|Closure  $shell  Path to shell (e.g., /bin/bash, /bin/zsh)
     */
    public function shell(string|Closure $shell): static
    {
        $this->shell = $shell;

        return $this;
    }

    /**
     * Get the shell path.
     */
    public function getShell(): string
    {
        return $this->evaluate($this->shell);
    }

    // ========================================
    // Terminal Settings
    // ========================================

    /**
     * Set the command timeout in seconds.
     */
    public function timeout(int|Closure $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Get the timeout.
     */
    public function getTimeout(): int
    {
        return $this->evaluate($this->timeout);
    }

    /**
     * Set the terminal prompt.
     */
    public function prompt(string|Closure $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Get the prompt.
     */
    public function getPrompt(): string
    {
        return $this->evaluate($this->prompt);
    }

    /**
     * Set the command history limit.
     */
    public function historyLimit(int|Closure $limit): static
    {
        $this->historyLimit = $limit;

        return $this;
    }

    /**
     * Get the history limit.
     */
    public function getHistoryLimit(): int
    {
        return $this->evaluate($this->historyLimit);
    }

    /**
     * Set the maximum output lines to retain.
     */
    public function maxOutputLines(int|Closure $lines): static
    {
        $this->maxOutputLines = $lines;

        return $this;
    }

    /**
     * Get the max output lines.
     */
    public function getMaxOutputLines(): int
    {
        return $this->evaluate($this->maxOutputLines);
    }

    /**
     * Set the initial working directory.
     */
    public function workingDirectory(?string $directory): static
    {
        $this->workingDirectory = $directory;

        return $this;
    }

    /**
     * Get the working directory.
     */
    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    // ========================================
    // Preset Configurations
    // ========================================

    /**
     * Configure as a read-only terminal (only allows ls, pwd, cat, head, tail).
     */
    public function readOnly(): static
    {
        return $this->allowedCommands(['ls', 'pwd', 'cat', 'head', 'tail', 'find', 'grep']);
    }

    /**
     * Configure for file browsing (ls, pwd, cd, cat).
     */
    public function fileBrowser(): static
    {
        return $this->allowedCommands(['ls', 'pwd', 'cd', 'cat', 'head', 'tail', 'find']);
    }

    /**
     * Configure for git operations.
     */
    public function gitTerminal(): static
    {
        return $this->allowedCommands(['git', 'ls', 'pwd', 'cd', 'cat']);
    }

    /**
     * Configure for Docker operations.
     */
    public function dockerTerminal(): static
    {
        return $this->allowedCommands(['docker', 'docker-compose', 'ls', 'pwd', 'cd']);
    }

    /**
     * Configure for npm/node operations.
     */
    public function nodeTerminal(): static
    {
        return $this->allowedCommands(['npm', 'npx', 'node', 'yarn', 'ls', 'pwd', 'cd', 'cat']);
    }

    /**
     * Configure for artisan commands.
     */
    public function artisanTerminal(): static
    {
        return $this->allowedCommands(['php', 'composer', 'ls', 'pwd', 'cd', 'cat']);
    }

    // ========================================
    // UI Configuration
    // ========================================

    /**
     * Start the terminal already connected.
     *
     * When enabled, the terminal will automatically connect on load
     * instead of requiring the user to click the Connect button.
     */
    public function startConnected(bool|Closure $startConnected = true): static
    {
        $this->startConnected = $startConnected;

        return $this;
    }

    /**
     * Get whether to start connected.
     */
    public function getStartConnected(): bool
    {
        return $this->evaluate($this->startConnected);
    }

    /**
     * Set the terminal title shown in the header bar.
     *
     * @param  string|Closure  $title  The title to display (default: "Terminal")
     */
    public function title(string|Closure $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the terminal title.
     */
    public function getTitle(): string
    {
        return $this->evaluate($this->title);
    }

    /**
     * Show or hide the window control buttons (the three colored dots).
     *
     * @param  bool|Closure  $show  Whether to show the window controls (default: true)
     */
    public function windowControls(bool|Closure $show = true): static
    {
        $this->showWindowControls = $show;

        return $this;
    }

    /**
     * Get whether to show window controls.
     */
    public function getShowWindowControls(): bool
    {
        return $this->evaluate($this->showWindowControls);
    }

    // ========================================
    // Logging Configuration
    // ========================================

    /**
     * Configure logging for this terminal.
     *
     * All parameters have sensible defaults from config. When not specified,
     * values from config/web-terminal.php are used.
     *
     * Can be called with named parameters or an array/Closure:
     *
     * @example Named parameters:
     * ->log(enabled: true, commands: true, identifier: 'my-terminal')
     *
     * @example Array configuration:
     * ->log([
     *     'enabled' => true,
     *     'connections' => true,
     *     'commands' => true,
     *     'identifier' => 'my-terminal',
     *     'metadata' => ['context' => 'admin'],
     * ])
     *
     * @example Closure (evaluated at render time):
     * ->log(fn () => [
     *     'enabled' => true,
     *     'identifier' => 'terminal-' . auth()->id(),
     *     'metadata' => ['user_id' => auth()->id()],
     * ])
     *
     * @param  array|Closure|bool|null  $config  Array/Closure config, or enabled boolean when using named params
     * @param  bool|Closure|null  $connections  Log connection/disconnection events
     * @param  bool|Closure|null  $commands  Log command executions
     * @param  bool|Closure|null  $output  Log command output (can be verbose)
     * @param  string|Closure|null  $identifier  Custom identifier for filtering logs
     * @param  array|Closure|null  $metadata  Custom metadata for all log entries
     */
    public function log(
        array|Closure|bool|null $config = true,
        bool|Closure|null $connections = null,
        bool|Closure|null $commands = null,
        bool|Closure|null $output = null,
        string|Closure|null $identifier = null,
        array|Closure|null $metadata = null,
    ): static {
        // If config is array or Closure, extract values from it
        if (is_array($config) || $config instanceof Closure) {
            if ($config instanceof Closure) {
                // Store closure to be evaluated later - wrap individual values
                $configClosure = $config;
                $this->loggingEnabled = fn () => $this->evaluate($configClosure)['enabled'] ?? true;
                $this->logConnections = fn () => $this->evaluate($configClosure)['connections'] ?? null;
                $this->logCommands = fn () => $this->evaluate($configClosure)['commands'] ?? null;
                $this->logOutput = fn () => $this->evaluate($configClosure)['output'] ?? null;
                $this->logIdentifier = fn () => $this->evaluate($configClosure)['identifier'] ?? null;
                $this->logMetadata = fn () => $this->evaluate($configClosure)['metadata'] ?? [];
            } else {
                // Array config
                $this->loggingEnabled = $config['enabled'] ?? true;
                $this->logConnections = $config['connections'] ?? null;
                $this->logCommands = $config['commands'] ?? null;
                $this->logOutput = $config['output'] ?? null;
                $this->logIdentifier = $config['identifier'] ?? null;
                $this->logMetadata = $config['metadata'] ?? [];
            }

            return $this;
        }

        // Named parameters style (config is the enabled boolean)
        $this->loggingEnabled = $config;

        if ($connections !== null) {
            $this->logConnections = $connections;
        }

        if ($commands !== null) {
            $this->logCommands = $commands;
        }

        if ($output !== null) {
            $this->logOutput = $output;
        }

        if ($identifier !== null) {
            $this->logIdentifier = $identifier;
        }

        if ($metadata !== null) {
            $this->logMetadata = $metadata;
        }

        return $this;
    }

    /**
     * Get whether logging is enabled.
     *
     * Returns null if not explicitly set (uses config default).
     */
    public function getLoggingEnabled(): ?bool
    {
        $value = $this->loggingEnabled;

        if ($value === null) {
            return null;
        }

        return $this->evaluate($value);
    }

    /**
     * Get whether to log connections.
     *
     * Returns null if not explicitly set (uses config default).
     */
    public function getLogConnections(): ?bool
    {
        $value = $this->logConnections;

        if ($value === null) {
            return null;
        }

        return $this->evaluate($value);
    }

    /**
     * Get whether to log commands.
     *
     * Returns null if not explicitly set (uses config default).
     */
    public function getLogCommands(): ?bool
    {
        $value = $this->logCommands;

        if ($value === null) {
            return null;
        }

        return $this->evaluate($value);
    }

    /**
     * Get whether to log output.
     *
     * Returns null if not explicitly set (uses config default).
     */
    public function getLogOutput(): ?bool
    {
        $value = $this->logOutput;

        if ($value === null) {
            return null;
        }

        return $this->evaluate($value);
    }

    /**
     * Get the log identifier.
     */
    public function getLogIdentifier(): ?string
    {
        $value = $this->logIdentifier;

        if ($value === null) {
            return null;
        }

        return $this->evaluate($value);
    }

    /**
     * Set custom metadata to be included in all log entries for this terminal.
     *
     * This metadata is merged with any existing metadata on each log entry,
     * allowing you to add terminal-specific context like server name, environment,
     * project ID, or any other custom data useful for filtering and analysis.
     *
     * @param  array|Closure  $metadata  Custom metadata key-value pairs
     *
     * @example
     * WebTerminal::make()
     *     ->logMetadata([
     *         'server' => 'production-web-1',
     *         'environment' => 'production',
     *         'project_id' => 123,
     *     ])
     */
    public function logMetadata(array|Closure $metadata): static
    {
        $this->logMetadata = $metadata;

        return $this;
    }

    /**
     * Get the custom log metadata.
     */
    public function getLogMetadata(): array
    {
        return $this->evaluate($this->logMetadata);
    }
}

// Backward compatibility alias
class_alias(WebTerminal::class, 'MWGuerra\\WebTerminal\\Schemas\\Components\\WebTerminalEmbed');
