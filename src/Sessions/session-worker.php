<?php

declare(strict_types=1);

/**
 * Background session worker for FileSessionManager.
 *
 * Spawned as a detached process to manage an interactive PTY session,
 * relaying I/O through files in the session directory.
 *
 * Usage: php session-worker.php <session-dir> <command> [cwd] [env-json]
 *
 * Session directory files:
 *   pid        - Worker PID (written on start)
 *   stdout     - Accumulated PTY output (appended by worker)
 *   stdin      - Input from client (read incrementally by worker)
 *   exit_code  - Process exit code (written on termination)
 */

// --- Argument parsing ---

if ($argc < 3) {
    fwrite(STDERR, "Usage: php session-worker.php <session-dir> <command> [cwd] [env-json]\n");
    exit(1);
}

$sessionDir = $argv[1];
$command = $argv[2];
$cwd = ($argc > 3 && $argv[3] !== '') ? $argv[3] : null;
$env = null;

if ($argc > 4 && $argv[4] !== '') {
    $decoded = json_decode($argv[4], true);
    if (is_array($decoded)) {
        $env = $decoded;
    }
}

// --- Validate session directory ---

if (! is_dir($sessionDir)) {
    fwrite(STDERR, "Session directory does not exist: {$sessionDir}\n");
    exit(1);
}

// --- File paths ---

$pidFile = $sessionDir.'/pid';
$stdoutFile = $sessionDir.'/stdout';
$stdinFile = $sessionDir.'/stdin';
$exitCodeFile = $sessionDir.'/exit_code';

// --- Write PID ---

file_put_contents($pidFile, (string) getmypid());

// --- Initialize output and stdin files ---

if (! file_exists($stdoutFile)) {
    file_put_contents($stdoutFile, '');
}

if (! file_exists($stdinFile)) {
    file_put_contents($stdinFile, '');
}

// --- Signal handling ---

$shouldExit = false;

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    $signalHandler = function (int $_signal) use (&$shouldExit): void {
        $shouldExit = true;
    };

    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
}

// --- Open process with PTY ---

$descriptors = [
    0 => ['pty'],
    1 => ['pty'],
    2 => ['pty'],
];

// Merge provided env with current environment so PATH etc. are preserved.
// Disable Xdebug in child processes to avoid spurious warnings about
// renamed settings and JIT incompatibility in REPL output.
$baseEnv = getenv();
$baseEnv['XDEBUG_MODE'] = 'off';
$envVars = $env !== null ? array_merge($baseEnv, $env) : $baseEnv;
$process = proc_open($command, $descriptors, $pipes, $cwd, $envVars);

if (! is_resource($process)) {
    fwrite(STDERR, "Failed to open process: {$command}\n");
    file_put_contents($exitCodeFile, '-1');
    exit(1);
}

// Set PTY output pipes to non-blocking
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

// --- Main I/O loop ---

$stdinPosition = 0;
$exitCode = null;
$exitCodeCaptured = false;

while (! $shouldExit) {
    $status = proc_get_status($process);

    // Read PTY stdout
    $stdoutData = '';
    while (($chunk = fread($pipes[1], 8192)) !== false && $chunk !== '') {
        $stdoutData .= $chunk;
    }

    // Read PTY stderr
    while (($chunk = fread($pipes[2], 8192)) !== false && $chunk !== '') {
        $stdoutData .= $chunk;
    }

    // Append any output to stdout file
    if ($stdoutData !== '') {
        file_put_contents($stdoutFile, $stdoutData, FILE_APPEND);
    }

    // Read new stdin input (incremental)
    clearstatcache(true, $stdinFile);
    $currentSize = filesize($stdinFile);

    if ($currentSize !== false && $currentSize > $stdinPosition) {
        $handle = fopen($stdinFile, 'r');
        if ($handle !== false) {
            fseek($handle, $stdinPosition);
            $newInput = fread($handle, $currentSize - $stdinPosition);
            fclose($handle);

            if ($newInput !== false && $newInput !== '') {
                fwrite($pipes[0], $newInput);
                $stdinPosition = $currentSize;
            }
        }
    }

    // Capture exit code on first detection (only valid on first call after termination)
    if (! $status['running'] && ! $exitCodeCaptured) {
        $exitCode = $status['exitcode'];
        $exitCodeCaptured = true;
        break;
    }

    // Sleep ~10ms to avoid busy-waiting
    usleep(10000);
}

// --- Drain remaining output ---

if (isset($pipes[1]) && is_resource($pipes[1])) {
    while (($chunk = fread($pipes[1], 8192)) !== false && $chunk !== '') {
        file_put_contents($stdoutFile, $chunk, FILE_APPEND);
    }
}

if (isset($pipes[2]) && is_resource($pipes[2])) {
    while (($chunk = fread($pipes[2], 8192)) !== false && $chunk !== '') {
        file_put_contents($stdoutFile, $chunk, FILE_APPEND);
    }
}

// --- Graceful shutdown if signalled while process still running ---

if ($shouldExit && isset($status) && $status['running']) {
    // Try SIGTERM first
    proc_terminate($process, 15);
    usleep(100000); // 100ms grace

    $status = proc_get_status($process);
    if ($status['running']) {
        proc_terminate($process, 9); // SIGKILL
    }

    $exitCode = -1;
}

// --- Close pipes and process ---

foreach ($pipes as $pipe) {
    if (is_resource($pipe)) {
        fclose($pipe);
    }
}

if (! $exitCodeCaptured) {
    // If we broke out due to signal, get status one more time
    $finalStatus = proc_get_status($process);
    if (! $finalStatus['running'] && $exitCode === null) {
        $exitCode = $finalStatus['exitcode'];
    }
}

$closeCode = proc_close($process);

if ($exitCode === null) {
    $exitCode = $closeCode;
}

// --- Write exit code ---

file_put_contents($exitCodeFile, (string) $exitCode);
