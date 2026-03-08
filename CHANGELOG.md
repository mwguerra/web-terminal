# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [v2.1.2] - 2026-03-08

### Added

- `allowInteractiveMode()` flag — enables interactive execution (PTY/tmux) for whitelisted commands, supporting artisan tinker, reverb:start, queue:work, and other interactive/long-running Laravel commands
- `TerminalPermission` enum — declarative permission configuration via `allow([TerminalPermission::InteractiveMode, TerminalPermission::ShellOperators])`
- `allow(array)` method on both Schema Component and TerminalBuilder for enum-based permission control
- TerminalBuilder now has full parity with Schema Component: `allowAllCommands()`, `allowPipes()`, `allowRedirection()`, `allowChaining()`, `allowExpansion()`, `allowAllShellOperators()`, `allowInteractiveMode()`, `environment()`, `loginShell()`, `shell()`, `startConnected()`, `title()`, `windowControls()`, `height()`, `log()`, `scripts()`
- **FileSessionManager** — file-based IPC session manager for cross-worker interactive session persistence without tmux dependency. Uses background PHP worker per session with PTY, communicates via filesystem files, stores metadata in Laravel Cache (respects `CACHE_STORE` — file, Redis, database, memcached)
- Session manager auto-detection priority chain: Tmux > File > Process (FileSessionManager now default fallback when PTY is supported)

### Changed

- Default Filament Terminal page now enables interactive mode and shell operators for artisan/composer commands

### Fixed

- Multi-word wildcard patterns in `CommandValidator` now correctly match commands like `php artisan *` against `php artisan tinker`
- Interactive sessions (REPLs, long-running commands) now persist across PHP-FPM worker processes via FileSessionManager
- REPL output rendering: prompts and user input now display on the same line (`> 1 + 1`), input lines render in command color (green)
- ANSI escape sequence stripping for interactive output (private mode, OSC, character set, keypad sequences)
- PTY input echo deduplication — typed commands no longer appear twice in terminal output
- Xdebug/JIT startup warnings suppressed in interactive sessions via `XDEBUG_MODE=off` in child processes
- Stderr now properly displayed for failed commands in interactive mode

## [v2.1.1] - 2026-03-08

### Fixed

- TUI application detection — full-screen apps (vim, htop, less, nano, etc.) are now detected via alternate screen buffer and Device Status Report escape sequences, blocked with a clear error message and non-interactive alternative suggestions
- `TuiDetector` utility class for identifying TUI escape sequences and providing command suggestions

## [v2.1.0] - 2026-03-08

### Added

- Copy All button in header bar — copies entire terminal output to clipboard
- Per-command-block copy button — hover over a command block to reveal copy icon
- Multi-line paste with confirmation — pasting multiple lines shows a modal to review and execute commands sequentially (comment lines starting with `#` are filtered)
- `clearOutput()` method to programmatically clear terminal output
- `getPlainTextOutput()` method to retrieve terminal output as plain text
- Shell operator controls with granular fluent methods to selectively allow blocked shell operators:
  - `allowPipes()` - Allow pipe operators (`|`) for piping output between commands
  - `allowRedirection()` - Allow redirection operators (`>`, `<`, `>>`, `<<`) for file I/O
  - `allowChaining()` - Allow chaining operators (`;`, `&&`, `||`, `&`) for running multiple commands
  - `allowExpansion()` - Allow expansion operators (`$`, `` ` ``, `$()`, `${}`) for variable/command substitution
  - `allowAllShellOperators()` - Global toggle to allow all operator groups at once
- Shell operator flags supported across all layers: Schema fluent API, Livewire component, and CommandSanitizer
- `allowChaining` and `allowExpansion` params added to `ValidCommand` attribute for consistency
- Documentation for shell operator controls with risk levels and usage examples

### Fixed

- Prevent multi-line paste during interactive mode or script execution

## [v2.0.0] - 2026-03-04

### Changed

- Upgraded to v2.x targeting Filament 5, Laravel 12, Livewire 4
