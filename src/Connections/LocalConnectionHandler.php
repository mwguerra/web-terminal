<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Connections;

use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;
use MWGuerra\WebTerminal\Sessions\FileSessionManager;
use MWGuerra\WebTerminal\Sessions\ProcessSessionManager;
use MWGuerra\WebTerminal\Sessions\SessionManagerInterface;
use MWGuerra\WebTerminal\Sessions\TmuxSessionManager;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Connection handler for local command execution.
 *
 * Uses Symfony Process component to execute commands on the local machine
 * with proper timeout handling, output capture, and process cleanup.
 */
class LocalConnectionHandler extends AbstractConnectionHandler
{
    /**
     * Environment variables to pass to the process.
     *
     * @var array<string, string>
     */
    protected array $environment = [];

    /**
     * Whether to use a login shell (loads .bashrc/.bash_profile).
     */
    protected bool $useLoginShell = false;

    /**
     * The shell to use for command execution.
     */
    protected string $shell = '/bin/bash';

    /**
     * Session manager for interactive processes.
     */
    protected ?SessionManagerInterface $sessionManager = null;

    /**
     * Whether to prefer tmux for session management.
     * When true, uses TmuxSessionManager if tmux is available.
     */
    protected bool $preferTmux = true;

    /**
     * {@inheritDoc}
     */
    public function connect(ConnectionConfig $config): void
    {
        if ($config->type !== ConnectionType::Local) {
            throw ConnectionException::invalidConfig(
                reason: "LocalConnectionHandler only supports Local connections, got {$config->type->value}",
                type: $config->type,
            );
        }

        $this->markConnected($config);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $command, ?float $timeout = null): CommandResult
    {
        if (! $this->isConnected()) {
            throw ConnectionException::notConnected();
        }

        $effectiveTimeout = $this->getEffectiveTimeout($timeout);
        $startTime = microtime(true);

        try {
            $process = $this->createProcess($command, $effectiveTimeout);
            $process->run();

            $executionTime = microtime(true) - $startTime;

            return new CommandResult(
                stdout: $process->getOutput(),
                stderr: $process->getErrorOutput(),
                exitCode: $process->getExitCode() ?? -1,
                executionTime: $executionTime,
                command: $command,
            );
        } catch (ProcessTimedOutException $e) {
            $executionTime = microtime(true) - $startTime;

            return CommandResult::timeout(
                timeoutSeconds: $effectiveTimeout,
                command: $command,
                partialOutput: $e->getProcess()->getOutput(),
            );
        } catch (\Throwable $e) {
            throw ConnectionException::executionFailed(
                command: $command,
                reason: $e->getMessage(),
                type: ConnectionType::Local,
                previous: $e instanceof \Exception ? $e : null,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        $this->markDisconnected();
        $this->environment = [];
    }

    /**
     * Set environment variables for command execution.
     *
     * @param  array<string, string>  $environment  Environment variables
     * @return $this
     */
    public function setEnvironment(array $environment): static
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Add an environment variable for command execution.
     *
     * @param  string  $name  Variable name
     * @param  string  $value  Variable value
     * @return $this
     */
    public function addEnvironmentVariable(string $name, string $value): static
    {
        $this->environment[$name] = $value;

        return $this;
    }

    /**
     * Get the current environment variables.
     *
     * @return array<string, string>
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    /**
     * Enable or disable login shell mode.
     *
     * When enabled, commands are wrapped with `bash -l -c` which loads
     * .bashrc/.bash_profile and initializes the full user environment
     * (including NVM, rbenv, pyenv, etc.).
     *
     * @param  bool  $useLoginShell  Whether to use login shell
     * @return $this
     */
    public function setUseLoginShell(bool $useLoginShell): static
    {
        $this->useLoginShell = $useLoginShell;

        return $this;
    }

    /**
     * Check if login shell mode is enabled.
     */
    public function isUsingLoginShell(): bool
    {
        return $this->useLoginShell;
    }

    /**
     * Set the shell to use for command execution.
     *
     * @param  string  $shell  Path to shell (e.g., /bin/bash, /bin/zsh)
     * @return $this
     */
    public function setShell(string $shell): static
    {
        $this->shell = $shell;

        return $this;
    }

    /**
     * Get the shell used for command execution.
     */
    public function getShell(): string
    {
        return $this->shell;
    }

    /**
     * Create a Process instance for the given command.
     *
     * @param  string  $command  The command to execute
     * @param  float  $timeout  Timeout in seconds
     */
    protected function createProcess(string $command, float $timeout): Process
    {
        $effectiveCommand = $this->wrapCommand($command);
        $effectiveEnv = $this->getEffectiveEnvironment();

        $process = Process::fromShellCommandline(
            command: $effectiveCommand,
            cwd: $this->workingDirectory,
            env: $effectiveEnv ?: null,
            timeout: $timeout,
        );

        return $process;
    }

    /**
     * Get the effective environment for command execution.
     *
     * When using login shell, ensures HOME and USER are set properly
     * so that shell profile scripts work correctly.
     *
     * @return array<string, string>|null
     */
    protected function getEffectiveEnvironment(): ?array
    {
        $env = $this->environment;

        // Always set TERM for PTY support (required for ncurses apps like htop, vim, etc.)
        if (Process::isPtySupported() && ! isset($env['TERM'])) {
            $env['TERM'] = 'xterm-256color';
        }

        // When using login shell, ensure HOME and USER are set
        // These are required for .bashrc/.bash_profile to work correctly
        if ($this->useLoginShell) {
            if (! isset($env['HOME'])) {
                $env['HOME'] = getenv('HOME') ?: ($_SERVER['HOME'] ?? '/home/'.get_current_user());
            }
            if (! isset($env['USER'])) {
                $env['USER'] = getenv('USER') ?: ($_SERVER['USER'] ?? get_current_user());
            }
            if (! isset($env['SHELL'])) {
                $env['SHELL'] = $this->shell;
            }
        }

        return empty($env) ? null : $env;
    }

    /**
     * Wrap command for execution.
     *
     * If login shell mode is enabled, wraps the command to source common
     * environment setup scripts (linuxbrew, NVM, cargo, rbenv, pyenv, etc.).
     *
     * Note: We source specific tool scripts directly rather than .bashrc because
     * most .bashrc files have an interactive shell check that exits early.
     *
     * @param  string  $command  The original command
     * @return string The wrapped command
     */
    protected function wrapCommand(string $command): string
    {
        if (! $this->useLoginShell) {
            return $command;
        }

        // Escape single quotes in the command for safe wrapping
        $escapedCommand = str_replace("'", "'\\''", $command);

        // Build environment setup commands
        // We source specific tool scripts directly rather than .bashrc
        // because .bashrc typically has an interactive shell check that exits early
        $envSetup = $this->buildEnvironmentSetupScript();

        return sprintf("%s -c '%s %s'", $this->shell, $envSetup, $escapedCommand);
    }

    /**
     * Build the shell script to set up the environment.
     *
     * Sources common development tool scripts in a specific order to ensure
     * PATH and other variables are properly configured.
     *
     * @return string Shell commands to set up environment
     */
    protected function buildEnvironmentSetupScript(): string
    {
        $scripts = [];

        // Linuxbrew / Homebrew
        $scripts[] = '[ -x "/home/linuxbrew/.linuxbrew/bin/brew" ] && eval "$(/home/linuxbrew/.linuxbrew/bin/brew shellenv)"';
        $scripts[] = '[ -x "/opt/homebrew/bin/brew" ] && eval "$(/opt/homebrew/bin/brew shellenv)"';
        $scripts[] = '[ -x "/usr/local/bin/brew" ] && eval "$(/usr/local/bin/brew shellenv)"';

        // NVM (Node Version Manager)
        $scripts[] = 'export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"';
        $scripts[] = '[ -s "/home/linuxbrew/.linuxbrew/opt/nvm/nvm.sh" ] && . "/home/linuxbrew/.linuxbrew/opt/nvm/nvm.sh"';
        $scripts[] = '[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"';

        // Cargo (Rust)
        $scripts[] = '[ -f "$HOME/.cargo/env" ] && . "$HOME/.cargo/env"';

        // Pyenv (Python)
        $scripts[] = '[ -d "$HOME/.pyenv" ] && export PYENV_ROOT="$HOME/.pyenv" && export PATH="$PYENV_ROOT/bin:$PATH" && eval "$(pyenv init -)" 2>/dev/null';

        // Rbenv (Ruby)
        $scripts[] = '[ -d "$HOME/.rbenv" ] && export PATH="$HOME/.rbenv/bin:$PATH" && eval "$(rbenv init -)" 2>/dev/null';

        // SDKMAN (Java, Kotlin, etc.)
        $scripts[] = '[ -s "$HOME/.sdkman/bin/sdkman-init.sh" ] && . "$HOME/.sdkman/bin/sdkman-init.sh"';

        // Bun
        $scripts[] = '[ -d "$HOME/.bun" ] && export BUN_INSTALL="$HOME/.bun" && export PATH="$BUN_INSTALL/bin:$PATH"';

        // Deno
        $scripts[] = '[ -d "$HOME/.deno" ] && export DENO_INSTALL="$HOME/.deno" && export PATH="$DENO_INSTALL/bin:$PATH"';

        // Go
        $scripts[] = '[ -d "$HOME/go" ] && export GOPATH="$HOME/go" && export PATH="$GOPATH/bin:$PATH"';

        // Local bin directories
        $scripts[] = '[ -d "$HOME/.local/bin" ] && export PATH="$HOME/.local/bin:$PATH"';
        $scripts[] = '[ -d "$HOME/bin" ] && export PATH="$HOME/bin:$PATH"';

        // Composer (PHP) global bin
        $scripts[] = '[ -d "$HOME/.config/composer/vendor/bin" ] && export PATH="$HOME/.config/composer/vendor/bin:$PATH"';
        $scripts[] = '[ -d "$HOME/.composer/vendor/bin" ] && export PATH="$HOME/.composer/vendor/bin:$PATH"';

        return implode('; ', $scripts).';';
    }

    // ========================================
    // Interactive Mode Implementation
    // ========================================

    /**
     * {@inheritDoc}
     */
    public function supportsInteractive(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function startInteractive(string $command): string
    {
        if (! $this->isConnected()) {
            throw ConnectionException::notConnected();
        }

        $effectiveCommand = $this->wrapCommand($command);
        $effectiveEnv = $this->getEffectiveEnvironment();

        return $this->getSessionManager()->start(
            command: $effectiveCommand,
            cwd: $this->workingDirectory,
            env: $effectiveEnv,
            timeout: null, // No timeout for interactive processes
        );
    }

    /**
     * {@inheritDoc}
     */
    public function readOutput(string $sessionId): ?array
    {
        return $this->getSessionManager()->getOutput($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function writeInput(string $sessionId, string $input): bool
    {
        return $this->getSessionManager()->sendInput($sessionId, $input);
    }

    /**
     * Write raw input to a process without appending newline.
     *
     * Used for special keys (arrows, tab, escape sequences, etc.)
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The raw input to send
     * @return bool True if input was sent
     */
    public function writeRawInput(string $sessionId, string $input): bool
    {
        return $this->getSessionManager()->sendRawInput($sessionId, $input);
    }

    /**
     * {@inheritDoc}
     */
    public function isProcessRunning(string $sessionId): bool
    {
        return $this->getSessionManager()->isRunning($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function getProcessExitCode(string $sessionId): ?int
    {
        return $this->getSessionManager()->getExitCode($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function terminateProcess(string $sessionId): bool
    {
        return $this->getSessionManager()->terminate($sessionId);
    }

    /**
     * Get or create the session manager instance.
     *
     * Automatically selects the best available session manager:
     * - TmuxSessionManager: If tmux is available and preferred (default)
     * - ProcessSessionManager: Fallback for systems without tmux
     *
     * TmuxSessionManager is recommended for PHP-FPM environments as it
     * persists sessions across worker processes.
     */
    protected function getSessionManager(): SessionManagerInterface
    {
        if ($this->sessionManager === null) {
            // Priority: Tmux > File > Process
            if ($this->preferTmux && TmuxSessionManager::isAvailable()) {
                $this->sessionManager = new TmuxSessionManager;
            } elseif (FileSessionManager::isAvailable()) {
                $this->sessionManager = new FileSessionManager;
            } else {
                $this->sessionManager = new ProcessSessionManager;
            }
        }

        return $this->sessionManager;
    }

    /**
     * Set a custom session manager (for testing or custom implementations).
     */
    public function setSessionManager(SessionManagerInterface $manager): static
    {
        $this->sessionManager = $manager;

        return $this;
    }

    /**
     * Enable or disable preference for tmux session manager.
     *
     * When enabled (default), TmuxSessionManager is used if tmux is available.
     * When disabled, ProcessSessionManager is always used.
     *
     * @param  bool  $prefer  Whether to prefer tmux
     * @return $this
     */
    public function setPreferTmux(bool $prefer): static
    {
        $this->preferTmux = $prefer;

        // Reset session manager so it will be re-created with new preference
        if ($this->sessionManager !== null) {
            $this->sessionManager = null;
        }

        return $this;
    }

    /**
     * Check if tmux session manager is being used.
     */
    public function isUsingTmux(): bool
    {
        return $this->getSessionManager() instanceof TmuxSessionManager;
    }

    /**
     * Check if file session manager is being used.
     */
    public function isUsingFileSession(): bool
    {
        return $this->getSessionManager() instanceof FileSessionManager;
    }
}
