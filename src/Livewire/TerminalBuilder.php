<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Enums\TerminalPermission;

/**
 * Fluent builder for WebTerminal component.
 *
 * Provides a clean, chainable API for configuring the terminal
 * before rendering it in a Blade view.
 */
class TerminalBuilder
{
    /** @var array<string, mixed>|ConnectionConfig|null */
    protected array|ConnectionConfig|null $connection = null;

    /** @var array<string>|null */
    protected ?array $allowedCommands = null;

    protected ?int $timeout = null;

    protected ?string $prompt = null;

    protected ?int $historyLimit = null;

    protected ?int $maxOutputLines = null;

    protected ?string $height = null;

    protected ?string $key = null;

    // Permission flags
    protected bool $allowAllCommands = false;

    protected bool $allowPipes = false;

    protected bool $allowRedirection = false;

    protected bool $allowChaining = false;

    protected bool $allowExpansion = false;

    protected bool $allowAllShellOperators = false;

    protected bool $allowInteractiveMode = false;

    // Environment & shell
    /** @var array<string, string> */
    protected array $environment = [];

    protected bool $useLoginShell = false;

    protected string $shell = '/bin/bash';

    // UI options
    protected bool $startConnected = false;

    protected ?string $title = null;

    protected bool $showWindowControls = true;

    // Logging
    protected ?bool $loggingEnabled = null;

    protected ?bool $logConnections = null;

    protected ?bool $logCommands = null;

    protected ?bool $logOutput = null;

    protected ?string $logIdentifier = null;

    /** @var array<string, mixed> */
    protected array $logMetadata = [];

    // Session management
    protected ?bool $disconnectOnNavigate = null;

    protected ?int $inactivityTimeout = null;

    // Scripts
    /** @var array<mixed> */
    protected array $scripts = [];

    // ========================================
    // Connection Configuration
    // ========================================

    /**
     * @param  array<string, mixed>  $config
     */
    public function connection(ConnectionType|string $type, array $config = []): static
    {
        if (is_string($type)) {
            $type = ConnectionType::from($type);
        }

        $this->connection = array_merge(['type' => $type->value], $config);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function local(array $options = []): static
    {
        return $this->connection(ConnectionType::Local, $options);
    }

    public function sshWithPassword(
        string $host,
        string $username,
        string $password,
        ?int $port = null,
    ): static {
        return $this->connection(ConnectionType::SSH, [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'port' => $port,
        ]);
    }

    public function sshWithKey(
        string $host,
        string $username,
        string $privateKey,
        ?string $passphrase = null,
        ?int $port = null,
    ): static {
        return $this->connection(ConnectionType::SSH, [
            'host' => $host,
            'username' => $username,
            'private_key' => $privateKey,
            'passphrase' => $passphrase,
            'port' => $port,
        ]);
    }

    public function withConfig(ConnectionConfig $config): static
    {
        $this->connection = $config;

        return $this;
    }

    // ========================================
    // Command Configuration
    // ========================================

    /** @param  array<string>  $commands */
    public function allowedCommands(array $commands): static
    {
        $this->allowedCommands = $commands;

        return $this;
    }

    /** @param  array<string>  $commands */
    public function addAllowedCommands(array $commands): static
    {
        $existing = $this->allowedCommands ?? config('web-terminal.allowed_commands', []);
        $this->allowedCommands = array_unique(array_merge($existing, $commands));

        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = max(1, $seconds);

        return $this;
    }

    // ========================================
    // Permissions (enum-based)
    // ========================================

    /**
     * Set permissions using TerminalPermission enum values.
     *
     * @param  array<TerminalPermission>  $permissions
     */
    public function allow(array $permissions): static
    {
        $flags = TerminalPermission::resolveManyFlags($permissions);

        if ($flags['allowAllCommands'] ?? false) {
            $this->allowAllCommands = true;
        }
        if ($flags['allowPipes'] ?? false) {
            $this->allowPipes = true;
        }
        if ($flags['allowRedirection'] ?? false) {
            $this->allowRedirection = true;
        }
        if ($flags['allowChaining'] ?? false) {
            $this->allowChaining = true;
        }
        if ($flags['allowExpansion'] ?? false) {
            $this->allowExpansion = true;
        }
        if ($flags['allowAllShellOperators'] ?? false) {
            $this->allowAllShellOperators = true;
        }
        if ($flags['allowInteractiveMode'] ?? false) {
            $this->allowInteractiveMode = true;
        }

        return $this;
    }

    // ========================================
    // Permissions (individual methods)
    // ========================================

    public function allowAllCommands(bool $allow = true): static
    {
        $this->allowAllCommands = $allow;

        return $this;
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
        $this->allowAllShellOperators = $allow;
        $this->allowPipes = $allow;
        $this->allowRedirection = $allow;
        $this->allowChaining = $allow;
        $this->allowExpansion = $allow;

        return $this;
    }

    public function allowInteractiveMode(bool $allow = true): static
    {
        $this->allowInteractiveMode = $allow;

        return $this;
    }

    // ========================================
    // Environment & Shell
    // ========================================

    /** @param  array<string, string>  $environment */
    public function environment(array $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    public function loginShell(bool $useLoginShell = true): static
    {
        $this->useLoginShell = $useLoginShell;

        return $this;
    }

    public function shell(string $shell): static
    {
        $this->shell = $shell;

        return $this;
    }

    // ========================================
    // UI Configuration
    // ========================================

    public function prompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function historyLimit(int $limit): static
    {
        $this->historyLimit = max(1, $limit);

        return $this;
    }

    public function maxOutputLines(int $lines): static
    {
        $this->maxOutputLines = max(100, $lines);

        return $this;
    }

    public function height(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function startConnected(bool $connected = true): static
    {
        $this->startConnected = $connected;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function windowControls(bool $show = true): static
    {
        $this->showWindowControls = $show;

        return $this;
    }

    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    // ========================================
    // Logging Configuration
    // ========================================

    public function log(
        ?bool $enabled = true,
        ?bool $connections = null,
        ?bool $commands = null,
        ?bool $output = null,
        ?string $identifier = null,
    ): static {
        $this->loggingEnabled = $enabled;
        $this->logConnections = $connections;
        $this->logCommands = $commands;
        $this->logOutput = $output;
        $this->logIdentifier = $identifier;

        return $this;
    }

    /** @param  array<string, mixed>  $metadata */
    public function logMetadata(array $metadata): static
    {
        $this->logMetadata = $metadata;

        return $this;
    }

    // ========================================
    // Session Management
    // ========================================

    public function disconnectOnNavigate(bool $enabled = true): static
    {
        $this->disconnectOnNavigate = $enabled;

        return $this;
    }

    public function keepConnectedOnNavigate(): static
    {
        $this->disconnectOnNavigate = false;

        return $this;
    }

    public function inactivityTimeout(int $seconds): static
    {
        $this->inactivityTimeout = max(0, $seconds);

        return $this;
    }

    public function noInactivityTimeout(): static
    {
        $this->inactivityTimeout = 0;

        return $this;
    }

    // ========================================
    // Scripts
    // ========================================

    /** @param  array<mixed>  $scripts */
    public function scripts(array $scripts): static
    {
        $this->scripts = $scripts;

        return $this;
    }

    // ========================================
    // Build & Render
    // ========================================

    /** @return array<string, mixed> */
    public function getParameters(): array
    {
        $params = array_filter([
            'connection' => $this->connection,
            'allowedCommands' => $this->allowedCommands,
            'timeout' => $this->timeout,
            'prompt' => $this->prompt,
            'historyLimit' => $this->historyLimit,
            'maxOutputLines' => $this->maxOutputLines,
            'height' => $this->height,
            'disconnectOnNavigate' => $this->disconnectOnNavigate,
            'inactivityTimeout' => $this->inactivityTimeout,
            'loggingEnabled' => $this->loggingEnabled,
            'logConnections' => $this->logConnections,
            'logCommands' => $this->logCommands,
            'logOutput' => $this->logOutput,
            'logIdentifier' => $this->logIdentifier,
            'title' => $this->title,
        ], fn ($value) => $value !== null);

        // Boolean flags — include when true
        if ($this->allowAllCommands) {
            $params['allowAllCommands'] = true;
        }
        if ($this->allowPipes) {
            $params['allowPipes'] = true;
        }
        if ($this->allowRedirection) {
            $params['allowRedirection'] = true;
        }
        if ($this->allowChaining) {
            $params['allowChaining'] = true;
        }
        if ($this->allowExpansion) {
            $params['allowExpansion'] = true;
        }
        if ($this->allowAllShellOperators) {
            $params['allowAllShellOperators'] = true;
        }
        if ($this->allowInteractiveMode) {
            $params['allowInteractiveMode'] = true;
        }
        if ($this->startConnected) {
            $params['startConnected'] = true;
        }
        if (! $this->showWindowControls) {
            $params['showWindowControls'] = false;
        }
        if ($this->useLoginShell) {
            $params['useLoginShell'] = true;
        }
        if ($this->shell !== '/bin/bash') {
            $params['shell'] = $this->shell;
        }
        if (! empty($this->environment)) {
            $params['environment'] = $this->environment;
        }
        if (! empty($this->logMetadata)) {
            $params['logMetadata'] = $this->logMetadata;
        }
        if (! empty($this->scripts)) {
            $params['scripts'] = $this->scripts;
        }

        return $params;
    }

    public function render(): View|HtmlString
    {
        $params = $this->getParameters();
        $key = $this->key;

        if ($key !== null) {
            return new HtmlString(
                \Livewire\Livewire::mount('web-terminal', $params, $key)->html()
            );
        }

        return new HtmlString(
            \Livewire\Livewire::mount('web-terminal', $params)->html()
        );
    }

    public function toHtml(): string
    {
        $params = [];

        if ($this->connection !== null) {
            if ($this->connection instanceof ConnectionConfig) {
                $params[':connection'] = '$connection';
            } else {
                $params[':connection'] = json_encode($this->connection);
            }
        }

        if ($this->allowedCommands !== null) {
            $params[':allowed-commands'] = json_encode($this->allowedCommands);
        }

        if ($this->timeout !== null) {
            $params[':timeout'] = $this->timeout;
        }

        if ($this->prompt !== null) {
            $params['prompt'] = $this->prompt;
        }

        if ($this->historyLimit !== null) {
            $params[':history-limit'] = $this->historyLimit;
        }

        if ($this->maxOutputLines !== null) {
            $params[':max-output-lines'] = $this->maxOutputLines;
        }

        if ($this->height !== null) {
            $params['height'] = $this->height;
        }

        if ($this->allowAllCommands) {
            $params[':allow-all-commands'] = 'true';
        }

        if ($this->allowAllShellOperators) {
            $params[':allow-all-shell-operators'] = 'true';
        }

        if ($this->allowInteractiveMode) {
            $params[':allow-interactive-mode'] = 'true';
        }

        if ($this->startConnected) {
            $params[':start-connected'] = 'true';
        }

        if ($this->disconnectOnNavigate !== null) {
            $params[':disconnect-on-navigate'] = $this->disconnectOnNavigate ? 'true' : 'false';
        }

        if ($this->inactivityTimeout !== null) {
            $params[':inactivity-timeout'] = $this->inactivityTimeout;
        }

        $paramsString = collect($params)
            ->map(fn ($value, $key) => "{$key}=\"{$value}\"")
            ->implode(' ');

        $keyAttr = $this->key ? " wire:key=\"{$this->key}\"" : '';

        return "<livewire:web-terminal {$paramsString}{$keyAttr} />";
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
