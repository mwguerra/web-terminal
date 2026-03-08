# Design: allowInteractiveMode Flag

**Date:** 2026-03-08
**Branch:** fix/artisan-interactive-commands

## Problem

Laravel artisan commands like `tinker`, `reverb:start`, `queue:work`, and `horizon` don't work in the web terminal because:

1. **Whitelist mode** executes commands synchronously — no stdin, 10s timeout, no streaming
2. **`allowAllCommands` mode** enables interactive execution but disables the entire security whitelist
3. There's no middle ground: secure whitelist OR interactive execution, never both

## Solution

Add `allowInteractiveMode` boolean that decouples interactive execution from the `allowAllCommands` security bypass.

### Changes

1. **`WebTerminal.php`**: New `$allowInteractiveMode` property
2. **`WebTerminal.php`**: Update `shouldUseInteractiveMode()` to check both flags
3. **`TerminalBuilder.php`**: New `allowInteractiveMode()` builder method

### Execution Flow (After Fix)

```
User enters command
  → Validate against whitelist (unchanged)
  → Sanitize input (unchanged)
  → Rate limit check (unchanged)
  → shouldUseInteractiveMode()?
      YES if allowAllCommands=true OR allowInteractiveMode=true
      → Start interactive session (tmux/process)
      → Stream output, accept stdin
      NO → Execute synchronously (existing behavior)
```

### What Stays the Same

- Command whitelist validation
- Command sanitization
- Rate limiting
- TUI detection (still kills alternate screen apps)
- All existing tests

### Usage

```php
Terminal::local()
    ->allowedCommands(['php *', 'node *', 'npm *'])
    ->allowAllShellOperators()
    ->allowInteractiveMode()
    ->render();
```
