<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Sessions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Session manager using tmux for cross-worker persistence.
 *
 * Uses tmux to manage terminal sessions, which allows:
 * - PTY support for interactive applications (htop, vim, etc.)
 * - Session persistence across PHP-FPM workers
 * - Proper input/output handling with ANSI support
 *
 * Session metadata is stored in Laravel's cache for cross-worker access.
 *
 * Requirements: tmux must be installed on the system.
 * Install with: apt install tmux (Debian/Ubuntu) or brew install tmux (macOS)
 */
class TmuxSessionManager implements SessionManagerInterface
{
    /**
     * Cache key prefix for session data.
     */
    protected const CACHE_PREFIX = 'swt:session:';

    /**
     * Session name prefix for tmux.
     */
    protected const SESSION_PREFIX = 'swt_';

    /**
     * Maximum session lifetime in seconds.
     */
    protected int $maxSessionLifetime = 300; // 5 minutes

    /**
     * Maximum lines to capture from tmux pane.
     */
    protected int $maxCaptureLines = 2000;

    /**
     * Cached tmux binary path.
     */
    protected static ?string $tmuxPath = null;

    /**
     * Check if tmux is available on the system.
     */
    public static function isAvailable(): bool
    {
        return static::getTmuxPath() !== null;
    }

    /**
     * Get the path to the tmux binary.
     *
     * Checks common installation paths including Homebrew/Linuxbrew.
     */
    public static function getTmuxPath(): ?string
    {
        if (static::$tmuxPath !== null) {
            return static::$tmuxPath !== '' ? static::$tmuxPath : null;
        }

        // Common paths where tmux might be installed
        $paths = [
            '/usr/bin/tmux',
            '/usr/local/bin/tmux',
            '/opt/homebrew/bin/tmux',           // macOS Homebrew
            '/home/linuxbrew/.linuxbrew/bin/tmux', // Linuxbrew
        ];

        // Check each path
        foreach ($paths as $path) {
            if (is_executable($path)) {
                static::$tmuxPath = $path;

                return $path;
            }
        }

        // Fall back to which command
        $result = shell_exec('which tmux 2>/dev/null');
        if (! empty(trim($result ?? ''))) {
            static::$tmuxPath = trim($result);

            return static::$tmuxPath;
        }

        // Cache negative result
        static::$tmuxPath = '';

        return null;
    }

    /**
     * Clear the cached tmux path (for testing).
     *
     * @internal
     */
    public static function clearTmuxPathCache(): void
    {
        static::$tmuxPath = null;
    }

    /**
     * Get the tmux command string (path or 'tmux' as fallback).
     */
    protected function tmux(): string
    {
        return static::getTmuxPath() ?? 'tmux';
    }

    /**
     * Start a new interactive process session.
     *
     * @param  string  $command  The command to execute
     * @param  string|null  $cwd  Working directory
     * @param  array<string, string>|null  $env  Environment variables
     * @param  float|null  $timeout  Timeout in seconds (ignored for tmux sessions)
     * @return string The session ID
     */
    public function start(
        string $command,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
    ): string {
        $sessionId = $this->generateSessionId();
        $tmuxSession = self::SESSION_PREFIX.$sessionId;

        // Build the tmux command
        $tmuxCommand = $this->buildTmuxStartCommand($tmuxSession, $command, $cwd, $env);

        // Execute tmux to start the session
        $result = $this->executeCommand($tmuxCommand);

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Failed to start tmux session: '.$result['stderr']);
        }

        // Get the PID of the process inside tmux
        $pid = $this->getTmuxPanePid($tmuxSession);

        // Store session data in cache
        $sessionData = new SharedSessionData(
            sessionId: $sessionId,
            command: $command,
            pid: $pid,
            startedAt: time(),
            backend: 'tmux',
        );

        $this->saveSessionData($sessionData);

        return $sessionId;
    }

    /**
     * Get output from a process session.
     *
     * For tmux sessions, returns the full pane content to properly handle
     * interactive applications that redraw the screen (like Laravel installer).
     * The 'full_screen' flag indicates the output should replace previous content.
     *
     * @param  string  $sessionId  The session ID
     * @return array{stdout: string, stderr: string, full_screen?: bool}|null Output or null if session not found
     */
    public function getOutput(string $sessionId): ?array
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        $tmuxSession = self::SESSION_PREFIX.$sessionId;

        // Check if session still exists
        if (! $this->tmuxSessionExists($tmuxSession)) {
            // Session ended - mark as finished
            $sessionData->finished = true;
            $sessionData->exitCode = 0; // Can't get real exit code from tmux easily
            $this->saveSessionData($sessionData);

            return ['stdout' => '', 'stderr' => ''];
        }

        // Capture full pane content - interactive apps redraw the screen
        $output = $this->captureTmuxPane($tmuxSession);

        // Check if content has changed
        $lastHash = $sessionData->lastOutputHash ?? '';
        $currentHash = md5($output);

        if ($lastHash === $currentHash) {
            // No changes
            return ['stdout' => '', 'stderr' => '', 'full_screen' => true];
        }

        // Update tracking
        $sessionData->lastOutputHash = $currentHash;
        $sessionData->lastActivity = time();
        $this->saveSessionData($sessionData);

        return [
            'stdout' => $output,
            'stderr' => '', // tmux combines stdout/stderr
            'full_screen' => true, // Signal to replace, not append
        ];
    }

    /**
     * Send input to a running process.
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The input to send (newline appended automatically)
     * @return bool True if input was sent
     */
    public function sendInput(string $sessionId, string $input): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $tmuxSession = self::SESSION_PREFIX.$sessionId;

        if (! $this->tmuxSessionExists($tmuxSession)) {
            return false;
        }

        // Send keys with Enter
        $escapedInput = $this->escapeTmuxInput($input);
        $command = sprintf(
            '%s send-keys -t %s %s Enter 2>/dev/null',
            $this->tmux(),
            escapeshellarg($tmuxSession),
            $escapedInput
        );

        $result = $this->executeCommand($command);

        if ($result['exit_code'] === 0) {
            $sessionData->lastActivity = time();
            $this->saveSessionData($sessionData);

            return true;
        }

        return false;
    }

    /**
     * Send raw input without appending newline.
     *
     * @param  string  $sessionId  The session ID
     * @param  string  $input  The raw input to send
     * @return bool True if input was sent
     */
    public function sendRawInput(string $sessionId, string $input): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $tmuxSession = self::SESSION_PREFIX.$sessionId;

        if (! $this->tmuxSessionExists($tmuxSession)) {
            return false;
        }

        // Send raw keys without Enter
        $escapedInput = $this->escapeTmuxInput($input);
        $command = sprintf(
            '%s send-keys -t %s %s 2>/dev/null',
            $this->tmux(),
            escapeshellarg($tmuxSession),
            $escapedInput
        );

        $result = $this->executeCommand($command);

        if ($result['exit_code'] === 0) {
            $sessionData->lastActivity = time();
            $this->saveSessionData($sessionData);

            return true;
        }

        return false;
    }

    /**
     * Check if a process is still running.
     *
     * @param  string  $sessionId  The session ID
     * @return bool True if running
     */
    public function isRunning(string $sessionId): bool
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null || $sessionData->finished) {
            return false;
        }

        $tmuxSession = self::SESSION_PREFIX.$sessionId;

        if (! $this->tmuxSessionExists($tmuxSession)) {
            // Update cache to mark as finished
            $sessionData->finished = true;
            $this->saveSessionData($sessionData);

            return false;
        }

        // Check if the pane's process is still running (not just session exists)
        // With remain-on-exit, session persists but pane shows dead status
        if ($this->isPaneDead($tmuxSession)) {
            $sessionData->finished = true;
            $this->saveSessionData($sessionData);

            return false;
        }

        return true;
    }

    /**
     * Check if the pane in a tmux session is dead (command finished).
     */
    protected function isPaneDead(string $tmuxSession): bool
    {
        // Check pane_dead flag - returns 1 if pane is dead (command finished)
        $command = sprintf(
            '%s display-message -t %s -p "#{pane_dead}" 2>/dev/null',
            $this->tmux(),
            escapeshellarg($tmuxSession)
        );

        $result = $this->executeCommand($command);

        return trim($result['stdout']) === '1';
    }

    /**
     * Get the exit code of a finished process.
     *
     * @param  string  $sessionId  The session ID
     * @return int|null Exit code or null if still running/not found
     */
    public function getExitCode(string $sessionId): ?int
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        return $sessionData->exitCode;
    }

    /**
     * Terminate a running process.
     *
     * @param  string  $sessionId  The session ID
     * @return bool True if terminated
     */
    public function terminate(string $sessionId): bool
    {
        $sessionData = $this->getSessionData($sessionId);
        $tmuxSession = self::SESSION_PREFIX.$sessionId;

        // Kill tmux session if it exists
        if ($this->tmuxSessionExists($tmuxSession)) {
            $command = sprintf('%s kill-session -t %s 2>/dev/null', $this->tmux(), escapeshellarg($tmuxSession));
            $this->executeCommand($command);
        }

        // Remove from cache
        $this->removeSessionData($sessionId);

        return true;
    }

    /**
     * Check if a session exists.
     */
    public function hasSession(string $sessionId): bool
    {
        return $this->getSessionData($sessionId) !== null;
    }

    /**
     * Get session info for debugging.
     *
     * @return array<string, mixed>|null
     */
    public function getSessionInfo(string $sessionId): ?array
    {
        $sessionData = $this->getSessionData($sessionId);

        if ($sessionData === null) {
            return null;
        }

        $tmuxSession = self::SESSION_PREFIX.$sessionId;
        $isRunning = $this->tmuxSessionExists($tmuxSession);

        return [
            'started_at' => $sessionData->startedAt,
            'last_activity' => $sessionData->lastActivity,
            'is_running' => $isRunning,
            'exit_code' => $sessionData->exitCode,
            'pid' => $sessionData->pid,
            'backend' => 'tmux',
        ];
    }

    /**
     * Clean up expired or finished sessions.
     */
    public function cleanup(): void
    {
        // List all tmux sessions with our prefix
        $command = sprintf(
            '%s list-sessions -F "#{session_name}" 2>/dev/null | grep "^%s"',
            $this->tmux(),
            self::SESSION_PREFIX
        );

        $result = $this->executeCommand($command);
        $sessions = array_filter(explode("\n", trim($result['stdout'])));

        foreach ($sessions as $tmuxSession) {
            $sessionId = str_replace(self::SESSION_PREFIX, '', $tmuxSession);
            $sessionData = $this->getSessionData($sessionId);

            if ($sessionData === null) {
                // Orphaned tmux session - kill it
                $this->executeCommand(sprintf('%s kill-session -t %s 2>/dev/null', $this->tmux(), escapeshellarg($tmuxSession)));

                continue;
            }

            $expired = (time() - $sessionData->lastActivity) > $this->maxSessionLifetime;

            if ($expired) {
                $this->terminate($sessionId);
            }
        }
    }

    /**
     * Get count of active sessions.
     */
    public function getActiveSessionCount(): int
    {
        $command = sprintf(
            '%s list-sessions -F "#{session_name}" 2>/dev/null | grep -c "^%s" || echo 0',
            $this->tmux(),
            self::SESSION_PREFIX
        );

        $result = $this->executeCommand($command);

        return (int) trim($result['stdout']);
    }

    /**
     * Set maximum session lifetime.
     */
    public function setMaxSessionLifetime(int $seconds): static
    {
        $this->maxSessionLifetime = max(60, $seconds);

        return $this;
    }

    /**
     * Build the tmux command to start a new session.
     *
     * @param  array<string, string>|null  $env
     */
    protected function buildTmuxStartCommand(
        string $tmuxSession,
        string $command,
        ?string $cwd,
        ?array $env,
    ): string {
        $tmux = static::getTmuxPath() ?? 'tmux';
        $parts = [$tmux, 'new-session', '-d', '-s', escapeshellarg($tmuxSession)];

        // Set terminal size for better compatibility
        $parts[] = '-x';
        $parts[] = '120';
        $parts[] = '-y';
        $parts[] = '40';

        // Build command with environment and working directory
        $shellCommand = '';

        // Add environment variables
        if ($env !== null && count($env) > 0) {
            foreach ($env as $key => $value) {
                $shellCommand .= sprintf('export %s=%s; ', escapeshellarg($key), escapeshellarg($value));
            }
        }

        // Change to working directory
        if ($cwd !== null && $cwd !== '') {
            $shellCommand .= sprintf('cd %s; ', escapeshellarg($cwd));
        }

        // Add the actual command
        $shellCommand .= $command;

        $parts[] = escapeshellarg($shellCommand);

        $mainCommand = implode(' ', $parts);

        // After creating the session, set remain-on-exit so we can capture output
        // even after the command completes
        $setRemainOnExit = sprintf(
            '%s set-option -t %s remain-on-exit on 2>/dev/null',
            $tmux,
            escapeshellarg($tmuxSession)
        );

        return sprintf('%s && %s', $mainCommand, $setRemainOnExit);
    }

    /**
     * Check if a tmux session exists.
     */
    protected function tmuxSessionExists(string $tmuxSession): bool
    {
        $command = sprintf('%s has-session -t %s 2>/dev/null', $this->tmux(), escapeshellarg($tmuxSession));
        $result = $this->executeCommand($command);

        return $result['exit_code'] === 0;
    }

    /**
     * Capture content from a tmux pane.
     */
    protected function captureTmuxPane(string $tmuxSession): string
    {
        // Use capture-pane with -p to print to stdout
        // -e preserves escape sequences (ANSI colors)
        // -S - starts from beginning of scrollback
        $command = sprintf(
            '%s capture-pane -t %s -p -e -S -%d 2>/dev/null',
            $this->tmux(),
            escapeshellarg($tmuxSession),
            $this->maxCaptureLines
        );

        $result = $this->executeCommand($command);
        $output = $result['stdout'];

        // Remove "Pane is dead" status line that tmux adds when remain-on-exit is enabled
        $output = preg_replace('/^Pane is dead.*$/m', '', $output);

        return $output;
    }

    /**
     * Get the PID of the process running in a tmux pane.
     */
    protected function getTmuxPanePid(string $tmuxSession): int
    {
        $command = sprintf(
            '%s list-panes -t %s -F "#{pane_pid}" 2>/dev/null | head -1',
            $this->tmux(),
            escapeshellarg($tmuxSession)
        );

        $result = $this->executeCommand($command);

        return (int) trim($result['stdout']);
    }

    /**
     * Map of ANSI escape sequences to tmux key names.
     */
    protected const ANSI_TO_TMUX_KEYS = [
        "\e[A" => 'Up',
        "\x1b[A" => 'Up',
        "\e[B" => 'Down',
        "\x1b[B" => 'Down',
        "\e[C" => 'Right',
        "\x1b[C" => 'Right',
        "\e[D" => 'Left',
        "\x1b[D" => 'Left',
        "\e[H" => 'Home',
        "\x1b[H" => 'Home',
        "\e[F" => 'End',
        "\x1b[F" => 'End',
        "\e[5~" => 'PageUp',
        "\x1b[5~" => 'PageUp',
        "\e[6~" => 'PageDown',
        "\x1b[6~" => 'PageDown',
        "\e[3~" => 'DC',  // Delete
        "\x1b[3~" => 'DC',
        "\eOP" => 'F1',
        "\x1bOP" => 'F1',
        "\eOQ" => 'F2',
        "\x1bOQ" => 'F2',
        "\eOR" => 'F3',
        "\x1bOR" => 'F3',
        "\eOS" => 'F4',
        "\x1bOS" => 'F4',
        "\e[15~" => 'F5',
        "\x1b[15~" => 'F5',
        "\e[21~" => 'F10',
        "\x1b[21~" => 'F10',
        "\t" => 'Tab',
        "\n" => 'Enter',
        "\r" => 'Enter',
        "\e" => 'Escape',
        "\x1b" => 'Escape',
        "\x7f" => 'BSpace',  // Backspace
        ' ' => 'Space',
    ];

    /**
     * Escape input for tmux send-keys command.
     *
     * Converts ANSI escape sequences to tmux key names for proper handling.
     */
    protected function escapeTmuxInput(string $input): string
    {
        // Check if the input is a known special key sequence
        if (isset(self::ANSI_TO_TMUX_KEYS[$input])) {
            return self::ANSI_TO_TMUX_KEYS[$input];
        }

        // For regular text, escape for shell
        return escapeshellarg($input);
    }

    /**
     * Execute a shell command and return results.
     *
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    protected function executeCommand(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            return ['stdout' => '', 'stderr' => 'Failed to execute command', 'exit_code' => -1];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout ?: '',
            'stderr' => $stderr ?: '',
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Generate a unique session ID.
     */
    protected function generateSessionId(): string
    {
        // Use shorter ID for tmux session name compatibility
        return substr(Str::uuid()->toString(), 0, 8);
    }

    /**
     * Get session data from cache.
     */
    protected function getSessionData(string $sessionId): ?SharedSessionData
    {
        $data = Cache::get(self::CACHE_PREFIX.$sessionId);

        if ($data === null) {
            return null;
        }

        return SharedSessionData::fromArray($data);
    }

    /**
     * Save session data to cache.
     */
    protected function saveSessionData(SharedSessionData $sessionData): void
    {
        Cache::put(
            self::CACHE_PREFIX.$sessionData->sessionId,
            $sessionData->toArray(),
            $this->maxSessionLifetime
        );
    }

    /**
     * Remove session data from cache.
     */
    protected function removeSessionData(string $sessionId): void
    {
        Cache::forget(self::CACHE_PREFIX.$sessionId);
    }

    /**
     * Clear all sessions (for testing).
     *
     * @internal
     */
    public static function clearAllSessions(): void
    {
        $tmux = static::getTmuxPath() ?? 'tmux';

        // Kill all tmux sessions with our prefix
        $command = sprintf(
            '%s list-sessions -F "#{session_name}" 2>/dev/null | grep "^%s" | xargs -r -I{} %s kill-session -t {} 2>/dev/null',
            $tmux,
            self::SESSION_PREFIX,
            $tmux
        );

        shell_exec($command);

        // Clear cache entries (best effort)
        // Note: Can't easily iterate cache keys in all cache drivers
    }
}
