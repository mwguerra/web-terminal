<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use MWGuerra\WebTerminal\Connections\ConnectionHandlerFactory;
use MWGuerra\WebTerminal\Contracts\ConnectionHandlerInterface;
use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Data\TerminalOutput;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Events\CommandExecutedEvent;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;
use MWGuerra\WebTerminal\Exceptions\RateLimitException;
use MWGuerra\WebTerminal\Exceptions\ValidationException;
use MWGuerra\WebTerminal\Models\TerminalLog;
use MWGuerra\WebTerminal\Security\CommandSanitizer;
use MWGuerra\WebTerminal\Services\TerminalLogger;
use MWGuerra\WebTerminal\Terminal\AnsiToHtml;
use MWGuerra\WebTerminal\Security\CommandValidator;
use MWGuerra\WebTerminal\Security\RateLimiter;

/**
 * Web Terminal Livewire Component.
 *
 * A secure, real-time terminal component for executing whitelisted
 * commands via Local or SSH connections.
 */
class WebTerminal extends Component
{
    /**
     * Current command input.
     */
    public string $command = '';

    /**
     * Terminal output lines.
     *
     * @var array<array<string, mixed>>
     */
    public array $output = [];

    /**
     * Command history for up/down navigation.
     *
     * @var array<string>
     */
    public array $history = [];

    /**
     * Current position in history.
     */
    public int $historyIndex = -1;

    /**
     * Whether the terminal is currently executing a command.
     */
    public bool $isExecuting = false;

    /**
     * Whether the terminal is connected (ready to accept commands).
     */
    public bool $isConnected = false;

    /**
     * Whether the terminal is in interactive mode (streaming output).
     */
    public bool $isInteractive = false;

    /**
     * Active interactive session ID.
     */
    public string $activeSessionId = '';

    /**
     * Output index where interactive session started (for full-screen replacement).
     */
    public int $interactiveOutputStart = 0;

    /**
     * The command being executed in interactive mode (for logging).
     */
    protected string $interactiveCommand = '';

    /**
     * Timestamp when interactive command started (for execution time logging).
     */
    protected float $interactiveStartTime = 0;

    /**
     * The terminal prompt string.
     */
    public string $prompt = '$ ';

    /**
     * Current working directory for display in prompt.
     */
    public string $currentDirectory = '';

    /**
     * Terminal height (CSS value).
     */
    public string $height = '350px';

    /**
     * Whether to auto-connect on mount.
     */
    #[Locked]
    public bool $startConnected = false;

    /**
     * The terminal title displayed in the header bar.
     */
    public string $title = 'Terminal';

    /**
     * Whether to show window control buttons (the three colored dots).
     */
    public bool $showWindowControls = true;

    /**
     * Get the connection type for display.
     */
    public function getConnectionType(): string
    {
        return ucfirst($this->connectionConfig['type'] ?? 'local');
    }

    /**
     * Maximum number of commands in history.
     */
    #[Locked]
    public int $historyLimit = 5;

    /**
     * Connection configuration (locked to prevent tampering).
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $connectionConfig = [];

    /**
     * Allowed commands (locked to prevent tampering).
     *
     * @var array<string>
     */
    #[Locked]
    public array $allowedCommands = [];

    /**
     * Whether to allow all commands (bypass whitelist).
     */
    #[Locked]
    public bool $allowAllCommands = false;

    /**
     * Environment variables for command execution.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $environment = [];

    /**
     * Whether to use a login shell (loads .bashrc/.bash_profile).
     */
    #[Locked]
    public bool $useLoginShell = false;

    /**
     * The shell to use for command execution.
     */
    #[Locked]
    public string $shell = '/bin/bash';

    /**
     * Timeout in seconds.
     */
    #[Locked]
    public int $timeout = 10;

    /**
     * Maximum number of output lines to retain.
     */
    #[Locked]
    public int $maxOutputLines = 1000;

    // ========================================
    // Logging Configuration
    // ========================================

    /**
     * Whether logging is enabled for this terminal.
     * Null means use config default.
     */
    #[Locked]
    public ?bool $loggingEnabled = null;

    /**
     * Whether to log connections/disconnections.
     * Null means use config default.
     */
    #[Locked]
    public ?bool $logConnections = null;

    /**
     * Whether to log commands.
     * Null means use config default.
     */
    #[Locked]
    public ?bool $logCommands = null;

    /**
     * Whether to log command output.
     * Null means use config default.
     */
    #[Locked]
    public ?bool $logOutput = null;

    /**
     * Custom identifier for this terminal in logs.
     */
    #[Locked]
    public ?string $logIdentifier = null;

    /**
     * Current terminal session ID (generated on connect).
     */
    public string $terminalSessionId = '';

    /**
     * The connection handler instance.
     */
    protected ?ConnectionHandlerInterface $handler = null;

    /**
     * Mount the component with configuration.
     *
     * @param  array<string, mixed>|ConnectionConfig|null  $connection
     * @param  array<string>|null  $allowedCommands
     */
    public function mount(
        array|ConnectionConfig|null $connection = null,
        ?array $allowedCommands = null,
        ?int $timeout = null,
        ?string $prompt = null,
        ?int $historyLimit = null,
        ?int $maxOutputLines = null,
        ?string $height = null,
        bool $allowAllCommands = false,
        array $environment = [],
        bool $useLoginShell = false,
        string $shell = '/bin/bash',
        bool $startConnected = false,
        ?string $title = null,
        bool $showWindowControls = true,
        ?bool $loggingEnabled = null,
        ?bool $logConnections = null,
        ?bool $logCommands = null,
        ?bool $logOutput = null,
        ?string $logIdentifier = null,
    ): void {
        // Set connection config
        if ($connection instanceof ConnectionConfig) {
            $this->connectionConfig = [
                'type' => $connection->type->value,
                'host' => $connection->host,
                'username' => $connection->username,
                'password' => $connection->password,
                'private_key' => $connection->privateKey,
                'passphrase' => $connection->passphrase,
                'port' => $connection->port,
                'timeout' => $connection->timeout,
                'working_directory' => $connection->workingDirectory,
                'environment' => $connection->environment,
            ];
        } elseif (is_array($connection)) {
            $this->connectionConfig = $connection;
        } else {
            // Default to local connection
            $this->connectionConfig = ['type' => 'local'];
        }

        // Set allowed commands (from config or parameter)
        $this->allowedCommands = $allowedCommands
            ?? config('web-terminal.allowed_commands', []);

        // Set timeout
        $this->timeout = $timeout
            ?? config('web-terminal.timeout', 10);

        // Set allow all commands flag
        $this->allowAllCommands = $allowAllCommands;

        // Set environment variables
        $this->environment = $environment;

        // Set shell configuration
        $this->useLoginShell = $useLoginShell;
        $this->shell = $shell;

        // Set optional parameters
        if ($prompt !== null) {
            $this->prompt = $prompt;
        }

        if ($historyLimit !== null) {
            $this->historyLimit = max(1, $historyLimit);
        }

        if ($maxOutputLines !== null) {
            $this->maxOutputLines = max(100, $maxOutputLines);
        }

        if ($height !== null) {
            $this->height = $height;
        }

        // Set UI configuration
        $this->startConnected = $startConnected;
        $this->showWindowControls = $showWindowControls;

        if ($title !== null) {
            $this->title = $title;
        }

        // Set logging configuration
        $this->loggingEnabled = $loggingEnabled;
        $this->logConnections = $logConnections;
        $this->logCommands = $logCommands;
        $this->logOutput = $logOutput;
        $this->logIdentifier = $logIdentifier;

        // Initialize current directory
        // For remote connections, don't use local getcwd()
        $configuredDir = $this->connectionConfig['working_directory'] ?? null;
        $connectionType = $this->connectionConfig['type'] ?? 'local';

        if ($configuredDir !== null) {
            $this->currentDirectory = $configuredDir;
        } elseif ($connectionType === 'local') {
            $this->currentDirectory = getcwd() ?: '/';
        } else {
            // For remote connections without a configured directory, default to home
            $this->currentDirectory = '~';
        }

        // Auto-connect if startConnected is true, otherwise show welcome message
        if ($this->startConnected) {
            $this->connect();
        } else {
            $this->addOutput(TerminalOutput::info('Terminal ready. Click "Connect" to start.'));
        }
    }

    /**
     * Get the formatted prompt with current directory.
     */
    public function getFormattedPrompt(): string
    {
        $dir = $this->getShortDirectoryName();

        return "{$dir} {$this->prompt}";
    }

    /**
     * Get shortened directory name for prompt display (just the folder name).
     */
    public function getShortDirectoryName(): string
    {
        if ($this->currentDirectory === '' || $this->currentDirectory === '/') {
            return '/';
        }

        // Just return the folder name (basename)
        return basename($this->currentDirectory) ?: '/';
    }

    /**
     * Execute the current command.
     */
    public function executeCommand(): void
    {
        // If in interactive mode, send input to the running process
        if ($this->isInteractive && $this->activeSessionId !== '') {
            $this->sendInput();

            return;
        }

        // Check if terminal is connected
        if (! $this->isConnected) {
            return;
        }

        $command = trim($this->command);
        $this->command = '';

        if ($command === '') {
            return;
        }

        // Handle built-in commands
        if ($this->handleBuiltInCommand($command)) {
            return;
        }

        // Add command to output and history
        $this->addOutput(TerminalOutput::command($this->getFormattedPrompt() . $command));
        $this->addToHistory($command);

        // Check rate limiting
        $rateLimiter = $this->getRateLimiter();
        if ($rateLimiter->isEnabled() && $rateLimiter->isLimited($this->getRateLimitKey())) {
            $this->addOutput(TerminalOutput::error(
                "Rate limited. Please wait {$rateLimiter->retryAfter($this->getRateLimitKey())} seconds."
            ));

            return;
        }

        // Validate command
        $validator = $this->getValidator();
        $validationResult = $validator->check($command);

        if (! $validationResult->valid) {
            $errorMessage = $validationResult->exception?->getUserMessage() ?? 'Command not allowed.';

            $this->addOutput(TerminalOutput::error($errorMessage));

            // Log blocked command (security event)
            $this->logBlockedCommand($command, $errorMessage);

            return;
        }

        // Execute with rate limiting
        $this->isExecuting = true;

        try {
            $rateLimiter->attempt(
                $this->getRateLimitKey(),
                fn () => $this->doExecuteCommand($command),
                fn ($retryAfter) => $this->addOutput(TerminalOutput::error(
                    "Rate limited. Please wait {$retryAfter} seconds."
                ))
            );
        } finally {
            // Only reset isExecuting if not in interactive mode
            // Interactive mode manages its own state via resetInteractiveState()
            if (! $this->isInteractive) {
                $this->isExecuting = false;
            }
        }
    }

    /**
     * Connect the terminal (enable command execution).
     *
     * This method actually tests the backend connection and shows detailed status.
     * If the connection fails, the terminal remains disconnected and the error is displayed.
     */
    public function connect(): void
    {
        if ($this->isConnected) {
            return;
        }

        // Build connection description
        $connectionDesc = $this->getConnectionDescription();
        $this->addOutput(TerminalOutput::info("Connecting to {$connectionDesc}..."));

        try {
            // Actually establish and test the connection
            $factory = new ConnectionHandlerFactory();
            $config = ConnectionConfig::fromArray($this->connectionConfig);
            $this->handler = $factory->createAndConnect($config);

            // Configure connection handler with environment
            $this->configureHandler($this->handler);

            $this->isConnected = true;
            $this->addOutput(TerminalOutput::info("Connected to {$connectionDesc} successfully."));

            // Log connection
            $logger = $this->getLogger();
            $this->terminalSessionId = $logger->generateSessionId();
            $logger->logConnection([
                'terminal_session_id' => $this->terminalSessionId,
                'terminal_identifier' => $this->logIdentifier,
                'connection_type' => $this->getConnectionTypeForLog(),
                'host' => $this->connectionConfig['host'] ?? null,
                'port' => $this->connectionConfig['port'] ?? null,
                'ssh_username' => $this->connectionConfig['username'] ?? null,
            ]);

        } catch (ConnectionException $e) {
            // Connection failed - stay disconnected and show error
            $this->handler = null;
            $this->addOutput(TerminalOutput::error('Connection failed: ' . $e->getUserMessage()));
        } catch (\Throwable $e) {
            // Unexpected error - stay disconnected and show error
            $this->handler = null;
            $this->addOutput(TerminalOutput::error('Connection error: ' . $e->getMessage()));
        }
    }

    /**
     * Get a human-readable description of the current connection.
     */
    protected function getConnectionDescription(): string
    {
        $type = $this->connectionConfig['type'] ?? 'local';
        $host = $this->connectionConfig['host'] ?? null;
        $port = $this->connectionConfig['port'] ?? null;

        $typeLabel = match ($type) {
            'local' => 'Local',
            'ssh' => 'SSH',
            default => ucfirst($type),
        };

        if ($type === 'local') {
            return "{$typeLabel} terminal";
        }

        $desc = "{$typeLabel}";

        if ($host !== null) {
            $desc .= " ({$host}";
            if ($port !== null) {
                $desc .= ":{$port}";
            }
            $desc .= ")";
        }

        return $desc;
    }

    /**
     * Disconnect the terminal (disable command execution).
     */
    public function disconnect(): void
    {
        if (! $this->isConnected) {
            return;
        }

        // Cancel any running process first
        if ($this->isInteractive && $this->activeSessionId !== '') {
            $this->cancelProcess();
        }

        // Clean up the handler
        if ($this->handler !== null) {
            try {
                if ($this->handler->isConnected()) {
                    $this->handler->disconnect();
                }
            } catch (\Throwable $e) {
                // Ignore disconnect errors
            }
            $this->handler = null;
        }

        // Log disconnection before resetting state
        if ($this->terminalSessionId !== '') {
            $logger = $this->getLogger();
            $logger->logDisconnection($this->terminalSessionId, [
                'connection_type' => $this->getConnectionTypeForLog(),
                'host' => $this->connectionConfig['host'] ?? null,
                'port' => $this->connectionConfig['port'] ?? null,
            ]);
        }

        $connectionDesc = $this->getConnectionDescription();
        $this->isConnected = false;
        $this->terminalSessionId = '';
        $this->addOutput(TerminalOutput::info("Disconnected from {$connectionDesc}."));
    }

    /**
     * Clear the terminal output.
     */
    public function clear(): void
    {
        $this->output = [];
        $this->addOutput(TerminalOutput::info('Terminal cleared.'));
    }

    /**
     * Navigate command history (up).
     */
    public function historyUp(): void
    {
        if (empty($this->history)) {
            return;
        }

        if ($this->historyIndex < count($this->history) - 1) {
            $this->historyIndex++;
            $this->command = $this->history[count($this->history) - 1 - $this->historyIndex];
        }
    }

    /**
     * Navigate command history (down).
     */
    public function historyDown(): void
    {
        if ($this->historyIndex > 0) {
            $this->historyIndex--;
            $this->command = $this->history[count($this->history) - 1 - $this->historyIndex];
        } elseif ($this->historyIndex === 0) {
            $this->historyIndex = -1;
            $this->command = '';
        }
    }

    /**
     * Reset history navigation when typing.
     */
    public function resetHistoryIndex(): void
    {
        $this->historyIndex = -1;
    }

    /**
     * Convert ANSI escape codes to HTML for display.
     *
     * @param  string  $content  Content that may contain ANSI escape codes
     * @return string  HTML-safe content with ANSI codes converted to styled spans
     */
    public function convertAnsiToHtml(string $content): string
    {
        return (new AnsiToHtml())->convert($content);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('web-terminal::terminal');
    }

    /**
     * Execute a command and return the result.
     */
    protected function doExecuteCommand(string $command): void
    {
        try {
            // Sanitize command
            $sanitizer = $this->getSanitizer();
            if (! $sanitizer->isSafe($command)) {
                $this->addOutput(TerminalOutput::error(
                    'Command contains potentially dangerous characters.'
                ));

                return;
            }

            // Handle cd command specially to update working directory
            if ($this->isCdCommand($command)) {
                $this->handleCdCommand($command);

                return;
            }

            // Use interactive mode when allowAllCommands is enabled
            if ($this->shouldUseInteractiveMode()) {
                $this->startInteractiveCommand($command);

                return;
            }

            // Get connection handler
            $handler = $this->getConnectionHandler();
            $handler->setWorkingDirectory($this->currentDirectory);

            // Execute command synchronously
            $result = $handler->execute($command);

            // Add output
            $this->addCommandResultOutput($result);

            // Log command execution
            if ($this->terminalSessionId !== '') {
                $logger = $this->getLogger();
                $logger->logCommand($this->terminalSessionId, $command, [
                    'connection_type' => $this->getConnectionTypeForLog(),
                    'exit_code' => $result->exitCode,
                    'execution_time_seconds' => (int) ceil($result->executionTime),
                ]);

                // Optionally log output
                $outputText = trim($result->stdout . "\n" . $result->stderr);
                if ($outputText !== '') {
                    $logger->logOutput($this->terminalSessionId, $outputText, [
                        'connection_type' => $this->getConnectionTypeForLog(),
                    ]);
                }
            }

            // Dispatch event for auditing
            $this->dispatchAuditEvent($command, $result);

        } catch (ConnectionException $e) {
            $errorMsg = 'Connection error: ' . $e->getUserMessage();
            $this->addOutput(TerminalOutput::error($errorMsg));
            $this->logError($command, $errorMsg);
        } catch (RateLimitException $e) {
            $this->addOutput(TerminalOutput::error($e->getUserMessage()));
            $this->logError($command, $e->getUserMessage());
        } catch (\Throwable $e) {
            $errorMsg = 'Error executing command: ' . $e->getMessage();
            $this->addOutput(TerminalOutput::error($errorMsg));
            $this->logError($command, $errorMsg);
        }
    }

    /**
     * Log an error during command execution.
     */
    protected function logError(string $command, string $error): void
    {
        if ($this->terminalSessionId === '') {
            return;
        }

        $logger = $this->getLogger();
        $logger->logError($this->terminalSessionId, $error, [
            'command' => $command,
            'connection_type' => $this->getConnectionTypeForLog(),
        ]);
    }

    /**
     * Log a blocked command attempt (security event).
     */
    protected function logBlockedCommand(string $command, string $reason): void
    {
        if ($this->terminalSessionId === '') {
            return;
        }

        $logger = $this->getLogger();
        $logger->logBlockedCommand($this->terminalSessionId, $command, $reason, [
            'connection_type' => $this->getConnectionTypeForLog(),
        ]);
    }

    /**
     * Check if command is a cd command.
     */
    protected function isCdCommand(string $command): bool
    {
        $trimmed = trim($command);

        return $trimmed === 'cd' || str_starts_with($trimmed, 'cd ');
    }

    /**
     * Handle cd command to change working directory.
     */
    protected function handleCdCommand(string $command): void
    {
        $parts = preg_split('/\s+/', trim($command), 2);
        $targetDir = $parts[1] ?? '';

        // For remote connections (SSH), execute on the remote server
        if ($this->isRemoteConnection()) {
            $this->handleRemoteCdCommand($command, $targetDir);

            return;
        }

        // Local connection handling
        $this->handleLocalCdCommand($targetDir, $parts[1] ?? '');
    }

    /**
     * Check if this is a remote connection (SSH).
     */
    protected function isRemoteConnection(): bool
    {
        $type = $this->connectionConfig['type'] ?? 'local';

        return $type === 'ssh';
    }

    /**
     * Handle cd command for remote connections by executing on the remote server.
     */
    protected function handleRemoteCdCommand(string $command, string $targetDir): void
    {
        try {
            $handler = $this->getConnectionHandler();

            // Build the cd command - if empty, go to home directory
            if ($targetDir === '' || $targetDir === '~') {
                $cdCommand = 'cd ~';
            } else {
                $cdCommand = 'cd ' . escapeshellarg($targetDir);
            }

            // Execute cd && pwd to change directory and get the new path
            $handler->setWorkingDirectory($this->currentDirectory);
            $result = $handler->execute($cdCommand . ' && pwd');

            if ($result->exitCode === 0 && $result->stdout !== '') {
                // Update current directory from pwd output
                $newDir = trim($result->stdout);
                if ($newDir !== '') {
                    $this->currentDirectory = $newDir;
                }
            } else {
                // Show error from remote server
                $errorMsg = trim($result->stderr) ?: "cd: no such directory: {$targetDir}";
                $this->addOutput(TerminalOutput::error($errorMsg));
            }
        } catch (\Throwable $e) {
            $this->addOutput(TerminalOutput::error('Error changing directory: ' . $e->getMessage()));
        }
    }

    /**
     * Handle cd command for local connections.
     */
    protected function handleLocalCdCommand(string $targetDir, string $originalTarget): void
    {
        // Handle empty cd (go to home)
        if ($targetDir === '' || $targetDir === '~') {
            $homeDir = getenv('HOME') ?: '/home/' . get_current_user();
            $this->currentDirectory = $homeDir;

            return;
        }

        // Handle ~ prefix
        if (str_starts_with($targetDir, '~/')) {
            $homeDir = getenv('HOME') ?: '/home/' . get_current_user();
            $targetDir = $homeDir . substr($targetDir, 1);
        }

        // Handle relative paths
        if (! str_starts_with($targetDir, '/')) {
            $targetDir = rtrim($this->currentDirectory, '/') . '/' . $targetDir;
        }

        // Resolve path (handle .. and .)
        $realPath = realpath($targetDir);

        if ($realPath === false || ! is_dir($realPath)) {
            $this->addOutput(TerminalOutput::error("cd: no such directory: {$originalTarget}"));

            return;
        }

        $this->currentDirectory = $realPath;
    }

    /**
     * Handle built-in commands.
     */
    protected function handleBuiltInCommand(string $command): bool
    {
        $lowerCommand = strtolower(trim($command));

        if ($lowerCommand === 'clear') {
            $this->clear();

            return true;
        }

        if ($lowerCommand === 'history') {
            $this->showHistory();

            return true;
        }

        if ($lowerCommand === 'help') {
            $this->showHelp();

            return true;
        }

        return false;
    }

    /**
     * Show command history.
     */
    protected function showHistory(): void
    {
        $this->addOutput(TerminalOutput::command($this->getFormattedPrompt() . 'history'));

        if (empty($this->history)) {
            $this->addOutput(TerminalOutput::info('No commands in history.'));

            return;
        }

        foreach ($this->history as $index => $cmd) {
            $this->addOutput(TerminalOutput::stdout(sprintf('%3d  %s', $index + 1, $cmd)));
        }
    }

    /**
     * Show help information.
     */
    protected function showHelp(): void
    {
        $this->addOutput(TerminalOutput::command($this->getFormattedPrompt() . 'help'));
        $this->addOutput(TerminalOutput::info('Built-in commands:'));
        $this->addOutput(TerminalOutput::stdout('  clear   - Clear terminal output'));
        $this->addOutput(TerminalOutput::stdout('  history - Show command history'));
        $this->addOutput(TerminalOutput::stdout('  help    - Show this help message'));
        $this->addOutput(TerminalOutput::info(''));
        $this->addOutput(TerminalOutput::info('Keyboard shortcuts:'));
        $this->addOutput(TerminalOutput::stdout('  Up/Down - Navigate command history'));
        $this->addOutput(TerminalOutput::stdout('  Enter   - Execute command'));
    }

    /**
     * Add a TerminalOutput to the output array.
     */
    protected function addOutput(TerminalOutput $output): void
    {
        $this->output[] = $output->toArray();

        // Trim output if exceeds max lines
        if (count($this->output) > $this->maxOutputLines) {
            $this->output = array_slice($this->output, -$this->maxOutputLines);
        }
    }

    /**
     * Add command result output.
     */
    protected function addCommandResultOutput(CommandResult $result): void
    {
        // Process stdout - trim trailing whitespace and add non-empty lines
        if ($result->stdout !== '') {
            $lines = $this->cleanOutputLines($result->stdout);
            foreach ($lines as $line) {
                $this->addOutput(TerminalOutput::stdout($line));
            }
        }

        // Process stderr - trim trailing whitespace and add non-empty lines
        if ($result->stderr !== '') {
            $lines = $this->cleanOutputLines($result->stderr);
            foreach ($lines as $line) {
                $this->addOutput(TerminalOutput::stderr($line));
            }
        }

        if ($result->isTimedOut()) {
            $this->addOutput(TerminalOutput::error('Command timed out.'));
        }
    }

    /**
     * Clean output lines by removing trailing blank lines and excessive whitespace.
     *
     * @return array<string>
     */
    protected function cleanOutputLines(string $output): array
    {
        // Split into lines
        $lines = explode("\n", $output);

        // Remove trailing empty lines
        while (! empty($lines) && trim(end($lines)) === '') {
            array_pop($lines);
        }

        // Remove leading empty lines
        while (! empty($lines) && trim(reset($lines)) === '') {
            array_shift($lines);
        }

        return $lines;
    }

    /**
     * Add command to history.
     */
    protected function addToHistory(string $command): void
    {
        // Don't add duplicates of the last command
        if (! empty($this->history) && end($this->history) === $command) {
            return;
        }

        $this->history[] = $command;

        // Trim history to limit
        if (count($this->history) > $this->historyLimit) {
            $this->history = array_slice($this->history, -$this->historyLimit);
        }

        $this->historyIndex = -1;
    }

    /**
     * Get the connection handler.
     *
     * The handler is lazily recreated on each request since Livewire cannot
     * persist the handler object between requests. The initial connect()
     * validates the connection works, and this method recreates it as needed.
     *
     * @throws ConnectionException If not connected (isConnected is false)
     */
    protected function getConnectionHandler(): ConnectionHandlerInterface
    {
        // Check if terminal is connected (state is persisted by Livewire)
        if (! $this->isConnected) {
            throw ConnectionException::notConnected();
        }

        // Recreate handler if needed (handlers can't be serialized by Livewire)
        if ($this->handler === null) {
            $factory = new ConnectionHandlerFactory();
            $config = ConnectionConfig::fromArray($this->connectionConfig);
            $this->handler = $factory->createAndConnect($config);

            // Configure connection handler with environment
            $this->configureHandler($this->handler);
        }

        return $this->handler;
    }

    /**
     * Configure connection handler with environment and shell settings.
     */
    protected function configureHandler(ConnectionHandlerInterface $handler): void
    {
        // Build environment with TERM for color support
        $environment = $this->environment;

        // Always set TERM for color support if not already set
        if (! isset($environment['TERM'])) {
            $environment['TERM'] = 'xterm-256color';
        }

        // Configure local connection handler
        if ($handler instanceof \MWGuerra\WebTerminal\Connections\LocalConnectionHandler) {
            if (! empty($environment)) {
                $handler->setEnvironment($environment);
            }
            if ($this->useLoginShell) {
                $handler->setUseLoginShell(true);
                $handler->setShell($this->shell);
            }
        }

        // Configure SSH connection handler
        if ($handler instanceof \MWGuerra\WebTerminal\Connections\SSHConnectionHandler) {
            if (! empty($environment)) {
                $handler->setEnvironment($environment);
            }
            // Enable PTY for color support when TERM is set
            if (isset($environment['TERM'])) {
                $handler->enablePty(true);
            }
        }
    }

    /**
     * Get the command validator.
     */
    protected function getValidator(): CommandValidator
    {
        return new CommandValidator(
            allowedCommands: $this->allowedCommands,
            blockedCharacters: [],
            allowAll: $this->allowAllCommands,
        );
    }

    /**
     * Get the command sanitizer.
     */
    protected function getSanitizer(): CommandSanitizer
    {
        $blockedChars = config('web-terminal.security.blocked_characters', []);

        return new CommandSanitizer($blockedChars);
    }

    /**
     * Get the rate limiter.
     */
    protected function getRateLimiter(): RateLimiter
    {
        return RateLimiter::fromConfig();
    }

    /**
     * Get the rate limit key for the current user.
     */
    protected function getRateLimitKey(): string
    {
        $userId = auth()->id() ?? session()->getId() ?? 'anonymous';

        return "user:{$userId}";
    }

    /**
     * Get the terminal logger with configured overrides.
     */
    protected function getLogger(): TerminalLogger
    {
        $overrides = [];

        if ($this->loggingEnabled !== null) {
            $overrides['enabled'] = $this->loggingEnabled;
        }

        if ($this->logConnections !== null) {
            $overrides['connections'] = $this->logConnections;
        }

        if ($this->logCommands !== null) {
            $overrides['commands'] = $this->logCommands;
        }

        if ($this->logOutput !== null) {
            $overrides['output'] = $this->logOutput;
        }

        if ($this->logIdentifier !== null) {
            $overrides['identifier'] = $this->logIdentifier;
        }

        return app(TerminalLogger::class)->withOverrides($overrides);
    }

    /**
     * Get the connection type string.
     */
    protected function getConnectionTypeForLog(): string
    {
        return $this->connectionConfig['type'] ?? 'local';
    }

    /**
     * Get session statistics from logs.
     * Used by the info panel to display session info.
     */
    public function getSessionStats(): ?array
    {
        if (! $this->isLoggingEnabled() || $this->terminalSessionId === '') {
            return null;
        }

        try {
            $logs = TerminalLog::forSession($this->terminalSessionId)->get();

            if ($logs->isEmpty()) {
                return null;
            }

            $connected = $logs->where('event_type', TerminalLog::EVENT_CONNECTED)->first();
            $commands = $logs->where('event_type', TerminalLog::EVENT_COMMAND);
            $errors = $commands->whereNotNull('exit_code')->where('exit_code', '!=', 0);

            return [
                'command_count' => $commands->count(),
                'error_count' => $errors->count(),
                'duration' => $connected
                    ? $connected->created_at->diffForHumans(now(), ['parts' => 2, 'short' => true])
                    : 'N/A',
            ];
        } catch (\Throwable $e) {
            // Table may not exist yet (migration not run)
            return null;
        }
    }

    /**
     * Check if logging is enabled for this terminal.
     */
    public function isLoggingEnabled(): bool
    {
        if ($this->loggingEnabled !== null) {
            return $this->loggingEnabled;
        }

        return (bool) config('web-terminal.logging.enabled', true);
    }

    /**
     * Dispatch the command executed event.
     */
    protected function dispatchAuditEvent(string $command, CommandResult $result): void
    {
        $config = ConnectionConfig::fromArray($this->connectionConfig);

        event(CommandExecutedEvent::fromExecution(
            command: $command,
            result: $result,
            config: $config,
            userId: auth()->id() ? (string) auth()->id() : null,
            sessionId: session()->getId(),
            ipAddress: request()->ip(),
        ));
    }

    /**
     * Clean up handler on component dehydration.
     *
     * Note: We don't cancel interactive sessions here because dehydrate()
     * is called on every Livewire request, not just on component destruction.
     * Interactive sessions are managed by ProcessSessionManager with TTL cleanup.
     */
    public function dehydrate(): void
    {
        // Only disconnect the handler, don't cancel running processes
        // The process session manager handles session cleanup via TTL
        if ($this->handler !== null && $this->handler->isConnected()) {
            $this->handler->disconnect();
            $this->handler = null;
        }
    }

    // ========================================
    // Interactive Mode Methods
    // ========================================

    /**
     * Poll for new output from an interactive session.
     *
     * Called by wire:poll when a process is running.
     */
    public function pollOutput(): void
    {
        if (! $this->isInteractive || $this->activeSessionId === '') {
            return;
        }

        try {
            $handler = $this->getConnectionHandler();

            // Read new output
            $output = $handler->readOutput($this->activeSessionId);

            if ($output !== null) {
                $isFullScreen = $output['full_screen'] ?? false;
                $hasContent = ! empty($output['stdout']) || ! empty($output['stderr']);

                if ($isFullScreen && $hasContent) {
                    // Full screen mode: replace output from session start
                    $this->replaceInteractiveOutput($output);
                } else {
                    // Incremental mode: append output
                    $this->appendInteractiveOutput($output);
                }
            }

            // Check if process has finished
            if (! $handler->isProcessRunning($this->activeSessionId)) {
                $exitCode = $handler->getProcessExitCode($this->activeSessionId);
                $this->finishInteractiveSession($exitCode);
            }
        } catch (\Throwable $e) {
            $this->addOutput(TerminalOutput::error('Error reading output: ' . $e->getMessage()));
            $this->resetInteractiveState();
        }
    }

    /**
     * Replace interactive output from session start (for full-screen applications).
     *
     * @param array{stdout: string, stderr: string, full_screen?: bool} $output
     */
    protected function replaceInteractiveOutput(array $output): void
    {
        // Trim output back to where the interactive session started
        $this->output = array_slice($this->output, 0, $this->interactiveOutputStart);

        // Add the new full-screen content
        $this->appendInteractiveOutput($output);
    }

    /**
     * Append interactive output incrementally.
     *
     * @param array{stdout: string, stderr: string, full_screen?: bool} $output
     */
    protected function appendInteractiveOutput(array $output): void
    {
        // Add stdout
        if (! empty($output['stdout'])) {
            $lines = $this->cleanOutputLines($output['stdout']);
            foreach ($lines as $line) {
                $this->addOutput(TerminalOutput::stdout($line));
            }
        }

        // Add stderr
        if (! empty($output['stderr'])) {
            $lines = $this->cleanOutputLines($output['stderr']);
            foreach ($lines as $line) {
                $this->addOutput(TerminalOutput::stderr($line));
            }
        }
    }

    /**
     * Send input to the running interactive process.
     */
    public function sendInput(): void
    {
        if (! $this->isInteractive || $this->activeSessionId === '') {
            // If not in interactive mode, execute as a regular command
            $this->executeCommand();

            return;
        }

        $input = trim($this->command);
        $this->command = '';

        try {
            $handler = $this->getConnectionHandler();

            // Echo the input to show what was sent
            $this->addOutput(TerminalOutput::command($input));

            // Send input to the process
            if (! $handler->writeInput($this->activeSessionId, $input)) {
                $this->addOutput(TerminalOutput::error('Failed to send input to process.'));
            }
        } catch (\Throwable $e) {
            $this->addOutput(TerminalOutput::error('Error sending input: ' . $e->getMessage()));
        }
    }

    /**
     * Send a special key to the running interactive process.
     *
     * Supports: up, down, left, right, tab, space, enter, escape, backspace
     */
    public function sendSpecialKey(string $key): void
    {
        if (! $this->isInteractive || $this->activeSessionId === '') {
            return;
        }

        // Map key names to ANSI escape sequences
        $keyMap = [
            'up' => "\e[A",
            'down' => "\e[B",
            'right' => "\e[C",
            'left' => "\e[D",
            'tab' => "\t",
            'space' => ' ',
            'enter' => "\n",
            'escape' => "\e",
            'backspace' => "\x7f",
            'home' => "\e[H",
            'end' => "\e[F",
            'pageup' => "\e[5~",
            'pagedown' => "\e[6~",
            'delete' => "\e[3~",
            // Function keys
            'f1' => "\eOP",
            'f2' => "\eOQ",
            'f3' => "\eOR",
            'f4' => "\eOS",
            'f5' => "\e[15~",
            'f10' => "\e[21~",
        ];

        $sequence = $keyMap[strtolower($key)] ?? null;

        if ($sequence === null) {
            return;
        }

        try {
            $handler = $this->getConnectionHandler();

            // Send the key sequence directly (no newline appended for special keys)
            if ($handler instanceof \MWGuerra\WebTerminal\Connections\LocalConnectionHandler) {
                // Use raw input for special keys (no newline appended)
                $handler->writeRawInput($this->activeSessionId, $sequence);
            } else {
                // For other handlers, use regular writeInput
                $handler->writeInput($this->activeSessionId, $sequence);
            }
        } catch (\Throwable $e) {
            // Silent fail for special keys
        }
    }

    /**
     * Cancel the running interactive process (Ctrl+C equivalent).
     */
    public function cancelProcess(): void
    {
        if (! $this->isInteractive || $this->activeSessionId === '') {
            return;
        }

        try {
            $handler = $this->getConnectionHandler();
            $handler->terminateProcess($this->activeSessionId);
            $this->addOutput(TerminalOutput::info('^C'));
        } catch (\Throwable $e) {
            $this->addOutput(TerminalOutput::error('Error cancelling process: ' . $e->getMessage()));
        } finally {
            // Log the cancelled command (exit code 130 = 128 + SIGINT)
            $this->logInteractiveCommand(130);
            $this->resetInteractiveState();
        }
    }

    /**
     * Start an interactive command session.
     */
    protected function startInteractiveCommand(string $command): void
    {
        try {
            $handler = $this->getConnectionHandler();
            $handler->setWorkingDirectory($this->currentDirectory);

            // Record where interactive output starts (for full-screen replacement)
            $this->interactiveOutputStart = count($this->output);

            // Store command and start time for logging
            $this->interactiveCommand = $command;
            $this->interactiveStartTime = microtime(true);

            $this->activeSessionId = $handler->startInteractive($command);
            $this->isInteractive = true;
            $this->isExecuting = true;

            // Poll multiple times to capture output from fast-completing commands.
            // Login shell mode requires more time (~500ms) due to environment setup.
            // We poll in intervals to capture output as soon as it's available.
            $maxPolls = $this->useLoginShell ? 12 : 4; // 600ms or 200ms max
            $pollInterval = 50000; // 50ms between polls

            for ($i = 0; $i < $maxPolls; $i++) {
                usleep($pollInterval);
                $this->doImmediatePoll($handler);

                // Stop polling if process has finished
                if (! $this->isInteractive) {
                    return;
                }
            }

            // Dispatch Livewire event to start polling (if still running)
            if ($this->isInteractive) {
                $this->dispatch('terminal-interactive-started');
            }
        } catch (\Throwable $e) {
            $this->addOutput(TerminalOutput::error('Error starting command: ' . $e->getMessage()));
            $this->resetInteractiveState();
        }
    }

    /**
     * Perform an immediate poll to capture output from fast commands.
     */
    protected function doImmediatePoll(ConnectionHandlerInterface $handler): void
    {
        try {
            // Read any available output
            $output = $handler->readOutput($this->activeSessionId);

            if ($output !== null) {
                $isFullScreen = $output['full_screen'] ?? false;
                $hasContent = ! empty($output['stdout']) || ! empty($output['stderr']);

                if ($isFullScreen && $hasContent) {
                    // Full screen mode: replace output from session start
                    $this->replaceInteractiveOutput($output);
                } else {
                    // Incremental mode: append output
                    $this->appendInteractiveOutput($output);
                }
            }

            // Check if process already finished
            if (! $handler->isProcessRunning($this->activeSessionId)) {
                $exitCode = $handler->getProcessExitCode($this->activeSessionId);
                $this->finishInteractiveSession($exitCode);
            }
        } catch (\Throwable $e) {
            // Don't fail the whole operation if immediate poll fails
            // Regular polling will pick up from here
        }
    }

    /**
     * Finish an interactive session and show final status.
     */
    protected function finishInteractiveSession(?int $exitCode): void
    {
        if ($exitCode !== null && $exitCode !== 0) {
            $this->addOutput(TerminalOutput::info("Process exited with code {$exitCode}"));
        }

        // Log the interactive command before resetting state
        $this->logInteractiveCommand($exitCode);

        $this->resetInteractiveState();

        // Dispatch Livewire event to stop polling
        $this->dispatch('terminal-interactive-finished');
    }

    /**
     * Log an interactive command execution.
     */
    protected function logInteractiveCommand(?int $exitCode): void
    {
        if ($this->terminalSessionId === '' || $this->interactiveCommand === '') {
            return;
        }

        $executionTime = $this->interactiveStartTime > 0
            ? microtime(true) - $this->interactiveStartTime
            : 0;

        $logger = $this->getLogger();
        $logger->logCommand($this->terminalSessionId, $this->interactiveCommand, [
            'connection_type' => $this->getConnectionTypeForLog(),
            'exit_code' => $exitCode,
            'execution_time_seconds' => (int) ceil($executionTime),
        ]);
    }

    /**
     * Reset interactive mode state.
     */
    protected function resetInteractiveState(): void
    {
        $this->isInteractive = false;
        $this->activeSessionId = '';
        $this->isExecuting = false;
        $this->interactiveOutputStart = 0;
        $this->interactiveCommand = '';
        $this->interactiveStartTime = 0;
    }

    /**
     * Check if interactive mode should be used.
     *
     * Interactive mode is used when allowAllCommands is true and the
     * connection handler supports it.
     */
    protected function shouldUseInteractiveMode(): bool
    {
        if (! $this->allowAllCommands) {
            return false;
        }

        try {
            $handler = $this->getConnectionHandler();

            return $handler->supportsInteractive();
        } catch (\Throwable) {
            return false;
        }
    }

    // ========================================
    // Static Factory Methods (Fluent API)
    // ========================================

    /**
     * Create a new terminal builder instance.
     */
    public static function make(): TerminalBuilder
    {
        return new TerminalBuilder();
    }
}
