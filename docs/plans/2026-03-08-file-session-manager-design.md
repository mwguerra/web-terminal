# FileSessionManager Design

## Problem

Interactive commands (REPLs like `php artisan tinker`, long-running like `php artisan serve`) require bidirectional communication with a persistent process. The current `ProcessSessionManager` stores process handles in PHP static memory, which is not shared across PHP-FPM workers. The `TmuxSessionManager` solves this but requires tmux installed on the system.

## Solution

A new `FileSessionManager` that uses file-based IPC with a background PHP worker process per session. Zero external dependencies beyond PHP itself.

## Architecture

### Session Manager Priority (auto-detected)

```
1. TmuxSessionManager    — tmux installed (best, cross-worker, full PTY)
2. FileSessionManager    — pure PHP fallback (cross-worker, PTY via background process)
3. ProcessSessionManager — single-worker only (artisan serve / Octane)
```

### Components

**`FileSessionManager`** (implements `SessionManagerInterface`)
- Uses Laravel Cache for session metadata (respects CACHE_STORE: file, redis, database, etc.)
- Uses filesystem for I/O relay (stdin/stdout temp files)
- Spawns a background PHP worker per interactive session

**`session-worker.php`** (background PHP script, bundled in package)
- Spawned as a detached process via `proc_open` + `nohup`
- Owns the PTY via `proc_open` with PTY mode
- Polls stdin file for new input, relays to PTY
- Reads PTY output, appends to stdout file
- Writes exit_code file when command finishes

### Session Directory Structure

```
storage/app/web-terminal/sessions/{session-id}/
├── stdin       # Regular file, append-only (FPM workers write here)
├── stdout      # Regular file, append-only (background worker writes here)
├── pid         # Process ID of the background worker
└── exit_code   # Written when command finishes (absent = still running)
```

### Session Metadata (Laravel Cache)

```php
SharedSessionData {
    sessionId, command, pid, startedAt, lastActivity,
    lastOutputPosition,  // byte offset for incremental stdout reads
    backend: 'file',
    finished, exitCode
}
```

## Data Flow

### Starting a session

1. Create session directory in `storage/app/web-terminal/sessions/{id}/`
2. Create empty `stdin` and `stdout` files
3. Spawn detached background worker: `nohup php session-worker.php {id} {command} &`
4. Worker writes its PID to `pid` file
5. Store metadata in Laravel Cache
6. Return session ID

### Reading output (Livewire poll, ~500ms)

1. Get metadata from Cache (includes `lastOutputPosition`)
2. Open `stdout` file, seek to last position, read new bytes
3. Update position in Cache
4. Return incremental output

### Sending input

1. Append input + newline to `stdin` file using `FILE_APPEND | LOCK_EX`
2. Background worker detects new bytes, writes to PTY stdin

### Background worker I/O loop (~10ms)

```
while (process is running):
    read PTY stdout/stderr → append to stdout file
    read stdin file from last offset → write to PTY stdin
    usleep(10ms)
```

### Session termination

1. Send SIGTERM to worker PID
2. Worker catches signal, closes PTY, writes exit_code
3. Remove session directory
4. Remove metadata from Cache

## Integration

### LocalConnectionHandler update

```php
protected function getSessionManager(): SessionManagerInterface
{
    if ($this->sessionManager === null) {
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
```

### Availability check

`FileSessionManager::isAvailable()` returns true when `Process::isPtySupported()` is true (standard on Linux/macOS).

## Error Handling

- **Worker crash**: `isRunning()` checks both exit_code file and PID liveness via `/proc/{pid}`. Dead PID without exit_code = failed session (exit code -1).
- **Orphaned sessions**: `cleanup()` scans session directory, kills expired PIDs, removes directories.
- **File permissions**: Session directory `0700`, files `0600`.
- **Concurrent stdin writes**: `FILE_APPEND | LOCK_EX` ensures atomic appends.
- **Disk cleanup**: All session files removed on terminate/cleanup.

## Testing

- Unit tests for FileSessionManager (start, getOutput, sendInput, terminate, cleanup)
- Background worker I/O relay with simple commands
- REPL behavior (send input, read response)
- Long-running command streaming and cancellation
- Cross-worker simulation (start in one manager instance, read in another)
- SessionManagerInterface contract compliance
