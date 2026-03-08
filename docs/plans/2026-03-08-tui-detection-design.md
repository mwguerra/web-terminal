# TUI Detection Design

## Problem

The web terminal cannot render full-screen terminal applications (TUI apps like `vim`, `htop`, `top`, `less`, `nano`). These apps use alternate screen buffer escape sequences to take over the terminal, which the web terminal's line-based rendering cannot display. This results in garbled output that confuses users and can freeze the terminal.

Two scenarios exist:
1. **Synchronous execution** (restricted `allowedCommands`) — TUI output is captured and rendered as garbled text
2. **Interactive mode** (tmux, `allowAllCommands`) — full-screen detection exists but renders a degraded, confusing view

## Solution

Detect alternate screen buffer escape sequences in command output and replace garbled output with a clear red error message. Suggest non-interactive alternatives when known.

## Architecture

### Detection

A `TuiDetector` class scans raw output for alternate screen buffer sequences — the definitive signal that an app is trying to take over the terminal:

- `ESC[?1049h` / `ESC[?1049l` — Standard alternate screen buffer
- `ESC[?47h` / `ESC[?47l` — Older variant
- `ESC[?1047h` / `ESC[?1047l` — Another older variant

**Not detected** (these are harmless or degrade gracefully):
- SGR sequences (`ESC[...m`) — Colors/styles, handled by `AnsiToHtml`
- Cursor movement (`ESC[A/B/C/D`, `ESC[H`) — Used by progress bars, `clear`
- Line erase (`ESC[K`) — Used by spinners/progress
- Cursor visibility (`ESC[?25h/l`) — Ignorable
- Screen clear (`ESC[2J`) — Used by `clear` command, harmless

### Components

**`TuiDetector`** (`src/Terminal/TuiDetector.php`):
- `containsTuiSequences(string $output): bool`
- `getSuggestion(string $command): ?string`
- Stateless, no dependencies

**Suggestion map:**

| Command | Suggestion |
|---------|-----------|
| `top` | `top -b -n 1` |
| `htop` | `top -b -n 1` |
| `vim`/`vi` | `cat <file>` |
| `nano` | `cat <file>` |
| `less` | `cat <file>` |
| `more` | `cat <file>` |
| `man` | Generic message (pipe alternative depends on shell operator config) |

**Error message format:**
```
⚠ This command requires a full-screen terminal (TUI) which is not supported in this web terminal.
→ Try instead: top -b -n 1
```

### Integration Points

- `WebTerminal::addCommandResultOutput()` — Check output before processing. If TUI detected, replace with error.
- `WebTerminal::pollOutput()` — When `full_screen === true`, kill process and show error with suggestion.
- `AnsiToHtml` — No changes. TUI output never reaches it.

### What This Doesn't Do

- No command blocklist — detection is output-based, not name-based
- No timeout-based detection — long-running commands are fine
- No changes to carriage return, cursor movement, or progress bar handling

## Testing

- Unit tests for `TuiDetector`: detection accuracy, no false positives, suggestion map
- Unit tests for `WebTerminal` integration: synchronous and interactive paths
- E2E test on testapp_f5 verifying the warning message appears
