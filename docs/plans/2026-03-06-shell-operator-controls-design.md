# Shell Operator Controls

**Date:** 2026-03-06
**Status:** Approved

## Problem

The terminal blocks all shell operators (pipes, redirection, chaining, expansion) unconditionally. Even with `allowAllCommands()`, users cannot run commands like `ls | grep foo`. There is no fluent API to control which operators are allowed.

## Solution

Add granular fluent methods to control which shell operator groups are allowed, plus a global toggle.

## Operator Groups

| Method | Operators Unblocked | Risk |
|--------|---------------------|------|
| `allowPipes()` | `\|` | Low |
| `allowRedirection()` | `>`, `<`, `>>`, `<<` | Medium |
| `allowChaining()` | `;`, `&&`, `\|\|` | Medium |
| `allowExpansion()` | `$`, `` ` ``, `$()`, `${}`, hex/octal escapes | High |
| `allowAllShellOperators()` | All of the above | High |

## Data Flow

```
Fluent API (Schema) → mount() params → Livewire properties → getSanitizer() → CommandSanitizer
```

## Files Changed

- `src/Security/CommandSanitizer.php` — group-aware filtering
- `src/Livewire/WebTerminal.php` — new mount params + properties
- `src/Schemas/Components/WebTerminal.php` — fluent methods
- `config/web-terminal.php` — `security.shell_operators` section
- `README.md` — documentation with risk explanations
- Tests (unit + E2E)

## Invariants (never relaxable)

- Null byte (`\x00`) blocking
- Newline/CR blocking
