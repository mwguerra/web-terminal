<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;

/**
 * Fluent builder for WebTerminal component.
 *
 * Provides a clean, chainable API for configuring the terminal
 * before rendering it in a Blade view.
 */
class TerminalBuilder
{
    /**
     * Connection configuration.
     *
     * @var array<string, mixed>|ConnectionConfig|null
     */
    protected array|ConnectionConfig|null $connection = null;

    /**
     * Allowed commands list.
     *
     * @var array<string>|null
     */
    protected ?array $allowedCommands = null;

    /**
     * Command timeout in seconds.
     */
    protected ?int $timeout = null;

    /**
     * Terminal prompt string.
     */
    protected ?string $prompt = null;

    /**
     * History limit.
     */
    protected ?int $historyLimit = null;

    /**
     * Maximum output lines.
     */
    protected ?int $maxOutputLines = null;

    /**
     * Component key for Livewire.
     */
    protected ?string $key = null;

    /**
     * Custom metadata for logging.
     *
     * @var array<string, mixed>
     */
    protected array $logMetadata = [];

    /**
     * Whether to disconnect on page navigation.
     */
    protected ?bool $disconnectOnNavigate = null;

    /**
     * Inactivity timeout in seconds.
     */
    protected ?int $inactivityTimeout = null;

    /**
     * Set the connection configuration.
     *
     * @param  ConnectionType|string  $type
     * @param  array<string, mixed>  $config
     * @return $this
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
     * Configure a local connection.
     *
     * @param  array<string, mixed>  $options
     * @return $this
     */
    public function local(array $options = []): static
    {
        return $this->connection(ConnectionType::Local, $options);
    }

    /**
     * Configure an SSH connection with password authentication.
     *
     * @return $this
     */
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

    /**
     * Configure an SSH connection with key authentication.
     *
     * @return $this
     */
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

    /**
     * Set the connection using a ConnectionConfig object.
     *
     * @return $this
     */
    public function withConfig(ConnectionConfig $config): static
    {
        $this->connection = $config;

        return $this;
    }

    /**
     * Set the allowed commands.
     *
     * @param  array<string>  $commands
     * @return $this
     */
    public function allowedCommands(array $commands): static
    {
        $this->allowedCommands = $commands;

        return $this;
    }

    /**
     * Add additional allowed commands.
     *
     * @param  array<string>  $commands
     * @return $this
     */
    public function addAllowedCommands(array $commands): static
    {
        $existing = $this->allowedCommands ?? config('web-terminal.allowed_commands', []);
        $this->allowedCommands = array_unique(array_merge($existing, $commands));

        return $this;
    }

    /**
     * Set the command timeout.
     *
     * @return $this
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = max(1, $seconds);

        return $this;
    }

    /**
     * Set the terminal prompt.
     *
     * @return $this
     */
    public function prompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Set the history limit.
     *
     * @return $this
     */
    public function historyLimit(int $limit): static
    {
        $this->historyLimit = max(1, $limit);

        return $this;
    }

    /**
     * Set the maximum output lines.
     *
     * @return $this
     */
    public function maxOutputLines(int $lines): static
    {
        $this->maxOutputLines = max(100, $lines);

        return $this;
    }

    /**
     * Set a unique key for the Livewire component.
     *
     * @return $this
     */
    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set custom metadata to be included in all log entries.
     *
     * @param  array<string, mixed>  $metadata  Custom metadata key-value pairs
     * @return $this
     */
    public function logMetadata(array $metadata): static
    {
        $this->logMetadata = $metadata;

        return $this;
    }

    /**
     * Set whether to disconnect when navigating away or refreshing the page.
     *
     * @return $this
     */
    public function disconnectOnNavigate(bool $enabled = true): static
    {
        $this->disconnectOnNavigate = $enabled;

        return $this;
    }

    /**
     * Disable automatic disconnect on page navigation.
     *
     * @return $this
     */
    public function keepConnectedOnNavigate(): static
    {
        $this->disconnectOnNavigate = false;

        return $this;
    }

    /**
     * Set the inactivity timeout in seconds.
     * Set to 0 to disable auto-disconnect on inactivity.
     *
     * @return $this
     */
    public function inactivityTimeout(int $seconds): static
    {
        $this->inactivityTimeout = max(0, $seconds);

        return $this;
    }

    /**
     * Disable inactivity timeout (never auto-disconnect due to inactivity).
     *
     * @return $this
     */
    public function noInactivityTimeout(): static
    {
        $this->inactivityTimeout = 0;

        return $this;
    }

    /**
     * Get the parameters for the component.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        $params = array_filter([
            'connection' => $this->connection,
            'allowedCommands' => $this->allowedCommands,
            'timeout' => $this->timeout,
            'prompt' => $this->prompt,
            'historyLimit' => $this->historyLimit,
            'maxOutputLines' => $this->maxOutputLines,
            'disconnectOnNavigate' => $this->disconnectOnNavigate,
            'inactivityTimeout' => $this->inactivityTimeout,
        ], fn ($value) => $value !== null);

        // Always include logMetadata if set (even empty array is filtered above)
        if (! empty($this->logMetadata)) {
            $params['logMetadata'] = $this->logMetadata;
        }

        return $params;
    }

    /**
     * Render the terminal component.
     */
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

    /**
     * Get the Blade component tag.
     */
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

    /**
     * Magic method to convert to string.
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }
}
