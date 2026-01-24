<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Data;

use InvalidArgumentException;

/**
 * Fluent builder and DTO for terminal script configuration.
 *
 * Represents a script that can be executed in the terminal, consisting of
 * one or more commands with optional configuration for error handling,
 * confirmation dialogs, privileges, and disconnection handling.
 */
class Script
{
    protected string $key;

    protected string $label;

    /** @var array<int, string> */
    protected array $commands = [];

    protected ?string $description = null;

    protected string $icon = 'heroicon-o-command-line';

    protected bool $stopOnError = true;

    protected bool $confirmBeforeRun = false;

    protected bool $elevated = false;

    /** @var array<int, string> */
    protected array $requiredCommands = [];

    protected bool $willDisconnect = false;

    protected ?string $beforeMessage = null;

    protected ?string $disconnectMessage = null;

    /**
     * Private constructor - use make() or fromArray() factory methods.
     */
    private function __construct(string $key)
    {
        $this->key = $key;
        $this->label = $key;
    }

    /**
     * Create a new script instance with the given key.
     */
    public static function make(string $key): static
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('Script key cannot be empty.');
        }

        return new static($key);
    }

    /**
     * Create a script from an array configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        if (! isset($data['key']) || trim((string) $data['key']) === '') {
            throw new InvalidArgumentException('Script array must contain a non-empty "key".');
        }

        $script = static::make($data['key']);

        if (isset($data['label'])) {
            $script->label($data['label']);
        }

        if (isset($data['commands'])) {
            $script->commands($data['commands']);
        }

        if (isset($data['description'])) {
            $script->description($data['description']);
        }

        if (isset($data['icon'])) {
            $script->icon($data['icon']);
        }

        if (isset($data['stopOnError'])) {
            $script->stopOnError((bool) $data['stopOnError']);
        }

        if (isset($data['confirmBeforeRun'])) {
            $script->confirmBeforeRun((bool) $data['confirmBeforeRun']);
        }

        if (isset($data['elevated'])) {
            $script->elevated((bool) $data['elevated']);
        }

        if (isset($data['requiredCommands'])) {
            $script->requiresCommands($data['requiredCommands']);
        }

        if (isset($data['willDisconnect'])) {
            $script->willDisconnect((bool) $data['willDisconnect']);
        }

        if (isset($data['beforeMessage'])) {
            $script->beforeMessage($data['beforeMessage']);
        }

        if (isset($data['disconnectMessage'])) {
            $script->disconnectMessage($data['disconnectMessage']);
        }

        return $script;
    }

    /**
     * Set the display label for the script.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set an optional description for the script.
     */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the icon for the script (Heroicon format).
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the commands to execute.
     *
     * @param  array<int, string>  $commands
     */
    public function commands(array $commands): static
    {
        $this->commands = array_values(array_filter(
            array_map('trim', $commands),
            fn (string $cmd): bool => $cmd !== ''
        ));

        return $this;
    }

    /**
     * Configure to stop execution on first command failure (default behavior).
     */
    public function stopOnError(bool $stop = true): static
    {
        $this->stopOnError = $stop;

        return $this;
    }

    /**
     * Configure to continue execution even if a command fails.
     */
    public function continueOnError(): static
    {
        $this->stopOnError = false;

        return $this;
    }

    /**
     * Require user confirmation before running the script.
     */
    public function confirmBeforeRun(bool $confirm = true): static
    {
        $this->confirmBeforeRun = $confirm;

        return $this;
    }

    /**
     * Mark this script as elevated (can run commands not in allowed list).
     */
    public function elevated(bool $elevated = true): static
    {
        $this->elevated = $elevated;

        return $this;
    }

    /**
     * Specify commands this script requires permission for (for validation display).
     *
     * @param  array<int, string>  $commands
     */
    public function requiresCommands(array $commands): static
    {
        $this->requiredCommands = $commands;

        return $this;
    }

    /**
     * Mark this script as one that will cause disconnection (e.g., reboot).
     */
    public function willDisconnect(bool $will = true): static
    {
        $this->willDisconnect = $will;

        return $this;
    }

    /**
     * Set the message shown before script starts (for confirmation).
     */
    public function beforeMessage(string $message): static
    {
        $this->beforeMessage = $message;

        return $this;
    }

    /**
     * Set the message shown when disconnection happens.
     */
    public function disconnectMessage(string $message): static
    {
        $this->disconnectMessage = $message;

        return $this;
    }

    /**
     * Get the script key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the display label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the commands.
     *
     * @return array<int, string>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get the description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the icon.
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Check if script should stop on error.
     */
    public function shouldStopOnError(): bool
    {
        return $this->stopOnError;
    }

    /**
     * Check if confirmation is required before run.
     */
    public function requiresConfirmation(): bool
    {
        return $this->confirmBeforeRun;
    }

    /**
     * Check if script has elevated privileges.
     */
    public function isElevated(): bool
    {
        return $this->elevated;
    }

    /**
     * Check if script will cause disconnection.
     */
    public function causesDisconnection(): bool
    {
        return $this->willDisconnect;
    }

    /**
     * Get the before message.
     */
    public function getBeforeMessage(): ?string
    {
        return $this->beforeMessage;
    }

    /**
     * Get the disconnect message.
     */
    public function getDisconnectMessage(): ?string
    {
        return $this->disconnectMessage;
    }

    /**
     * Get the number of commands in the script.
     */
    public function commandCount(): int
    {
        return count($this->commands);
    }

    /**
     * Get commands that are not authorized given the allowed commands list.
     *
     * @param  array<int, string>  $allowedCommands
     * @return array<int, string>
     */
    public function getUnauthorizedCommands(array $allowedCommands): array
    {
        if ($this->elevated || $allowedCommands === []) {
            return [];
        }

        $unauthorized = [];

        foreach ($this->commands as $command) {
            $baseCommand = $this->extractBaseCommand($command);

            if (! $this->isCommandAllowed($baseCommand, $allowedCommands)) {
                $unauthorized[] = $command;
            }
        }

        return $unauthorized;
    }

    /**
     * Check if script can run with the given allowed commands.
     *
     * @param  array<int, string>  $allowedCommands
     */
    public function canRunWithAllowedCommands(array $allowedCommands): bool
    {
        return $this->getUnauthorizedCommands($allowedCommands) === [];
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'commands' => $this->commands,
            'description' => $this->description,
            'icon' => $this->icon,
            'stopOnError' => $this->stopOnError,
            'confirmBeforeRun' => $this->confirmBeforeRun,
            'elevated' => $this->elevated,
            'requiredCommands' => $this->requiredCommands,
            'willDisconnect' => $this->willDisconnect,
            'beforeMessage' => $this->beforeMessage,
            'disconnectMessage' => $this->disconnectMessage,
            'commandCount' => $this->commandCount(),
        ];
    }

    /**
     * Extract the base command from a full command string.
     */
    protected function extractBaseCommand(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command), 2);

        return $parts[0] ?? '';
    }

    /**
     * Check if a command is allowed.
     *
     * @param  array<int, string>  $allowedCommands
     */
    protected function isCommandAllowed(string $command, array $allowedCommands): bool
    {
        foreach ($allowedCommands as $allowed) {
            // Exact match
            if ($command === $allowed) {
                return true;
            }

            // Wildcard match (e.g., "git*" matches "git", "git-*", etc.)
            if (str_ends_with($allowed, '*')) {
                $prefix = substr($allowed, 0, -1);
                if (str_starts_with($command, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
