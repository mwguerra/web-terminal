<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;

class TerminalPtyBridge
{
    private string $sessionId;
    private int $userId;
    private ConnectionConfig $config;
    private PtySessionRegistry $registry;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private ?object $sshShell = null;

    public function __construct(
        ConnectionConfig $config,
        string $sessionId,
        int $userId,
        PtySessionRegistry $registry,
    ) {
        $this->config = $config;
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->registry = $registry;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function start(string $shell = '/bin/bash'): void
    {
        if ($this->config->type === ConnectionType::SSH) {
            $this->startSsh();
            return;
        }

        $this->startLocal($shell);
    }

    private function startLocal(string $shell): void
    {
        $descriptors = [
            0 => ['pty'],
            1 => ['pty'],
            2 => ['pty'],
        ];

        $env = array_merge(getenv() ?: [], $this->config->environment, [
            'TERM' => 'xterm-256color',
            'XDEBUG_MODE' => 'off',
        ]);

        $cwd = $this->config->workingDirectory;
        if ($cwd !== null && ! is_dir($cwd)) {
            $cwd = config('web-terminal.ghostty.working_directory') ?? getcwd();
        }

        $this->process = proc_open(
            "setsid {$shell} -il",
            $descriptors,
            $this->pipes,
            $cwd,
            $env
        );

        if (! is_resource($this->process)) {
            throw new \RuntimeException("Failed to start PTY process: {$shell}");
        }

        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        $status = proc_get_status($this->process);
        $this->registry->register($this->sessionId, $status['pid'], $this->userId);
    }

    private function startSsh(): void
    {
        $ssh = new \phpseclib3\Net\SSH2(
            $this->config->host,
            $this->config->port ?? 22,
            $this->config->timeout
        );

        if ($this->config->privateKey !== null) {
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(
                $this->config->privateKey,
                $this->config->passphrase ?? ''
            );
            if (! $ssh->login($this->config->username, $key)) {
                throw new \RuntimeException('SSH key authentication failed');
            }
        } else {
            if (! $ssh->login($this->config->username, $this->config->password)) {
                throw new \RuntimeException('SSH password authentication failed');
            }
        }

        // Set timeout to 0 for non-blocking reads (critical for ReactPHP event loop)
        $ssh->setTimeout(0);
        $ssh->enablePTY();
        $ssh->exec('');
        $this->sshShell = $ssh;
        // SSH sessions use pid -1 (sentinel) since there's no local process
        $this->registry->register($this->sessionId, -1, $this->userId);
    }

    public function write(string $data): void
    {
        if ($this->sshShell !== null) {
            $this->sshShell->write($data);
            return;
        }

        if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
            fwrite($this->pipes[0], $data);
        }
    }

    public function read(): string
    {
        if ($this->sshShell !== null) {
            // With setTimeout(0), this returns immediately with available data or empty string
            return $this->sshShell->read('') ?: '';
        }

        $output = '';

        if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
            while (($chunk = fread($this->pipes[1], 8192)) !== false && $chunk !== '') {
                $output .= $chunk;
            }
        }

        if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
            while (($chunk = fread($this->pipes[2], 8192)) !== false && $chunk !== '') {
                $output .= $chunk;
            }
        }

        return $output;
    }

    public function resize(int $cols, int $rows): void
    {
        if ($cols <= 0 || $rows <= 0) {
            return;
        }

        if ($this->sshShell !== null) {
            $this->sshShell->setWindowSize($cols, $rows);
            return;
        }

        if ($this->process === null || ! $this->isRunning()) {
            return;
        }

        $status = proc_get_status($this->process);
        $pid = $status['pid'] ?? 0;

        if ($pid <= 0) {
            return;
        }

        // Resize the PTY via the child's TTY device
        // /proc/<pid>/fd/0 is a symlink to the PTY slave device
        $ttyLink = "/proc/{$pid}/fd/0";
        $ttyDevice = @readlink($ttyLink);

        if ($ttyDevice !== false && str_starts_with($ttyDevice, '/dev/pts/')) {
            @exec("stty -F " . escapeshellarg($ttyDevice) . " rows {$rows} cols {$cols} 2>/dev/null");
        }

        // Send SIGWINCH to the process group so apps (vim, htop) re-read size
        posix_kill(-$pid, SIGWINCH);
    }

    public function isRunning(): bool
    {
        if ($this->sshShell !== null) {
            return $this->sshShell->isConnected();
        }

        if ($this->process === null) {
            return false;
        }

        $status = proc_get_status($this->process);
        return $status['running'];
    }

    public function terminate(): void
    {
        if ($this->sshShell !== null) {
            $this->sshShell->disconnect();
            $this->sshShell = null;
            $this->registry->unregister($this->sessionId);
            return;
        }

        if ($this->process !== null) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process, 15); // SIGTERM
                usleep(100000); // 100ms grace
                $status = proc_get_status($this->process);
                if ($status['running']) {
                    proc_terminate($this->process, 9); // SIGKILL
                }
            }

            proc_close($this->process);
            $this->process = null;
            $this->pipes = [];
        }

        $this->registry->unregister($this->sessionId);
    }

    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->terminate();
        }
    }
}
