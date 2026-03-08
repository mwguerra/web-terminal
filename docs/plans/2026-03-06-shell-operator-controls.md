# Shell Operator Controls Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add granular fluent methods to control which shell operator groups (pipes, redirection, chaining, expansion) are allowed, plus a global toggle `allowAllShellOperators()`.

**Architecture:** The CommandSanitizer already has `$blockedCharacters` and `$injectionPatterns` arrays. We add 4 boolean flags (one per operator group) that filter out the relevant chars/patterns when enabled. The flags flow from Schema fluent API -> Livewire mount params -> getSanitizer() construction. The global `allowAllShellOperators()` sets all 4 flags at once.

**Tech Stack:** PHP 8.2, Laravel, Livewire 4, Filament 5, Pest 3

---

### Task 1: Create feature branch

**Step 1: Create and checkout feature branch**

Run: `git checkout -b feature/shell-operator-controls`
Expected: Switched to a new branch 'feature/shell-operator-controls'

---

### Task 2: Add operator group flags to CommandSanitizer

**Files:**
- Modify: `src/Security/CommandSanitizer.php`
- Test: `tests/Unit/Security/CommandSanitizerTest.php`

**Step 1: Write failing tests for operator group flags**

Add these tests at the end of `tests/Unit/Security/CommandSanitizerTest.php`, before the closing `});`:

```php
describe('shell operator controls', function () {
    describe('allowPipes', function () {
        it('blocks pipes by default', function () {
            $sanitizer = new CommandSanitizer();

            expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
        });

        it('allows pipes when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowPipes();

            expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
        });

        it('still blocks other operators when only pipes allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowPipes();

            expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
            expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
            expect($sanitizer->isSafe('echo > file'))->toBeFalse();
        });
    });

    describe('allowRedirection', function () {
        it('blocks redirection by default', function () {
            $sanitizer = new CommandSanitizer();

            expect($sanitizer->isSafe('echo test > file.txt'))->toBeFalse();
        });

        it('allows output redirection when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowRedirection();

            expect($sanitizer->isSafe('echo test > file.txt'))->toBeTrue();
        });

        it('allows input redirection when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowRedirection();

            expect($sanitizer->isSafe('cat < input.txt'))->toBeTrue();
        });

        it('allows append redirection when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowRedirection();

            expect($sanitizer->isSafe('echo test >> file.txt'))->toBeTrue();
        });

        it('allows here-doc when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowRedirection();

            expect($sanitizer->isSafe('cat << EOF'))->toBeTrue();
        });

        it('still blocks other operators when only redirection allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowRedirection();

            expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
            expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
        });
    });

    describe('allowChaining', function () {
        it('blocks chaining by default', function () {
            $sanitizer = new CommandSanitizer();

            expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
        });

        it('allows semicolon when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowChaining();

            expect($sanitizer->isSafe('ls; pwd'))->toBeTrue();
        });

        it('allows && when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowChaining();

            expect($sanitizer->isSafe('ls && pwd'))->toBeTrue();
        });

        it('allows || when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowChaining();

            expect($sanitizer->isSafe('ls || pwd'))->toBeTrue();
        });

        it('allows & (background) when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowChaining();

            expect($sanitizer->isSafe('sleep 10 &'))->toBeTrue();
        });

        it('still blocks other operators when only chaining allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowChaining();

            expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
            expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
        });
    });

    describe('allowExpansion', function () {
        it('blocks expansion by default', function () {
            $sanitizer = new CommandSanitizer();

            expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
        });

        it('allows dollar sign when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowExpansion();

            expect($sanitizer->isSafe('echo $HOME'))->toBeTrue();
        });

        it('allows backticks when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowExpansion();

            expect($sanitizer->isSafe('echo `whoami`'))->toBeTrue();
        });

        it('allows $() command substitution when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowExpansion();

            expect($sanitizer->isSafe('echo $(whoami)'))->toBeTrue();
        });

        it('allows ${} variable expansion when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowExpansion();

            expect($sanitizer->isSafe('echo ${HOME}'))->toBeTrue();
        });

        it('still blocks other operators when only expansion allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowExpansion();

            expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
            expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
        });
    });

    describe('allowAllShellOperators', function () {
        it('allows all operators when enabled', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowAllShellOperators();

            expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
            expect($sanitizer->isSafe('echo test > file.txt'))->toBeTrue();
            expect($sanitizer->isSafe('ls; pwd'))->toBeTrue();
            expect($sanitizer->isSafe('echo $HOME'))->toBeTrue();
            expect($sanitizer->isSafe('echo `whoami`'))->toBeTrue();
        });

        it('still blocks null bytes even when all operators allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowAllShellOperators();

            expect($sanitizer->isSafe("echo hello\x00malicious"))->toBeFalse();
        });

        it('still blocks newlines even when all operators allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowAllShellOperators();

            expect($sanitizer->isSafe("echo hello\nrm -rf /"))->toBeFalse();
        });

        it('still blocks carriage returns even when all operators allowed', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowAllShellOperators();

            expect($sanitizer->isSafe("echo hello\rrm -rf /"))->toBeFalse();
        });
    });

    describe('combining groups', function () {
        it('allows pipes and redirection together', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowPipes();
            $sanitizer->allowRedirection();

            expect($sanitizer->isSafe('ls | sort > output.txt'))->toBeTrue();
            expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
        });

        it('allows pipes and chaining together', function () {
            $sanitizer = new CommandSanitizer();
            $sanitizer->allowPipes();
            $sanitizer->allowChaining();

            expect($sanitizer->isSafe('ls | grep foo && echo done'))->toBeTrue();
            expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
        });
    });

    describe('constructor flags', function () {
        it('accepts operator flags in constructor', function () {
            $sanitizer = new CommandSanitizer(
                allowPipes: true,
                allowRedirection: true,
            );

            expect($sanitizer->isSafe('ls | sort > output.txt'))->toBeTrue();
            expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
        });
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Security/CommandSanitizerTest.php --filter="shell operator controls"`
Expected: FAIL - methods allowPipes, allowRedirection, etc. do not exist

**Step 3: Implement operator group flags in CommandSanitizer**

In `src/Security/CommandSanitizer.php`:

1. Add 4 boolean properties after `$autoEscape` (line 57):
```php
protected bool $allowPipes = false;
protected bool $allowRedirection = false;
protected bool $allowChaining = false;
protected bool $allowExpansion = false;
```

2. Update constructor (line 64) to accept the new flags:
```php
public function __construct(
    array $blockedCharacters = [],
    bool $allowPipes = false,
    bool $allowRedirection = false,
    bool $allowChaining = false,
    bool $allowExpansion = false,
)
{
    if (! empty($blockedCharacters)) {
        $this->blockedCharacters = $blockedCharacters;
    }
    $this->allowPipes = $allowPipes;
    $this->allowRedirection = $allowRedirection;
    $this->allowChaining = $allowChaining;
    $this->allowExpansion = $allowExpansion;
}
```

3. Add fluent setter methods after `setAutoEscape` (line ~405):
```php
public function allowPipes(bool $allow = true): static
{
    $this->allowPipes = $allow;
    return $this;
}

public function allowRedirection(bool $allow = true): static
{
    $this->allowRedirection = $allow;
    return $this;
}

public function allowChaining(bool $allow = true): static
{
    $this->allowChaining = $allow;
    return $this;
}

public function allowExpansion(bool $allow = true): static
{
    $this->allowExpansion = $allow;
    return $this;
}

public function allowAllShellOperators(bool $allow = true): static
{
    $this->allowPipes = $allow;
    $this->allowRedirection = $allow;
    $this->allowChaining = $allow;
    $this->allowExpansion = $allow;
    return $this;
}
```

4. Replace `checkBlockedCharacters` method (line 135) to filter by group:
```php
protected function checkBlockedCharacters(string $command): void
{
    foreach ($this->getEffectiveBlockedCharacters() as $char) {
        if (str_contains($command, $char)) {
            throw ValidationException::blockedCharacters($command, $char);
        }
    }
}
```

5. Replace `checkInjectionPatterns` method (line 149) to filter by group:
```php
protected function checkInjectionPatterns(string $command): void
{
    foreach ($this->getEffectiveInjectionPatterns() as $pattern) {
        if (preg_match($pattern, $command)) {
            throw ValidationException::injectionAttempt($command);
        }
    }
}
```

6. Add the two filtering methods:
```php
protected function getEffectiveBlockedCharacters(): array
{
    $chars = $this->blockedCharacters;

    if ($this->allowPipes) {
        $chars = array_filter($chars, fn ($c) => $c !== '|');
    }

    if ($this->allowChaining) {
        $chars = array_filter($chars, fn ($c) => ! in_array($c, [';', '&'], true));
    }

    if ($this->allowExpansion) {
        $chars = array_filter($chars, fn ($c) => ! in_array($c, ['$', '`'], true));
    }

    return array_values($chars);
}

protected function getEffectiveInjectionPatterns(): array
{
    $patterns = $this->injectionPatterns;

    if ($this->allowRedirection) {
        $patterns = array_filter($patterns, fn ($p) => ! in_array($p, [
            '/\>\s*\>/',       // >> redirect
            '/\<\s*\</',       // << here-doc
            '/\>\s*\//',       // > /path redirect
            '/\<\s*\//',       // < /path input redirect
        ], true));
    }

    if ($this->allowChaining) {
        $patterns = array_filter($patterns, fn ($p) => ! in_array($p, [
            '/\|\s*\|/',       // || or
            '/\&\s*\&/',       // && and
        ], true));
    }

    if ($this->allowExpansion) {
        $patterns = array_filter($patterns, fn ($p) => ! in_array($p, [
            '/\$\(/',          // $(command)
            '/\$\{/',          // ${variable}
            '/\\\\x[0-9a-fA-F]{2}/', // Hex escape sequences
            '/\\\\[0-7]{1,3}/', // Octal escape sequences
        ], true));
    }

    return array_values($patterns);
}
```

7. Also update `stripDangerous` method (line 318) to use the effective lists:
```php
public function stripDangerous(string $input): string
{
    $result = str_replace($this->getEffectiveBlockedCharacters(), '', $input);

    foreach ($this->getEffectiveInjectionPatterns() as $pattern) {
        $result = preg_replace($pattern, '', $result) ?? $result;
    }

    return $result;
}
```

**Step 4: Run tests to verify they pass**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Security/CommandSanitizerTest.php`
Expected: All tests PASS

**Step 5: Commit**

```bash
git add src/Security/CommandSanitizer.php tests/Unit/Security/CommandSanitizerTest.php
git commit -m "feat: add shell operator group controls to CommandSanitizer"
```

---

### Task 3: Wire operator flags through Livewire WebTerminal

**Files:**
- Modify: `src/Livewire/WebTerminal.php:240-280` (properties), `:413-437` (mount), `:1310-1315` (getSanitizer)

**Step 1: Add properties to Livewire component**

In `src/Livewire/WebTerminal.php`, add after line 264 (`$allowAllCommands`):
```php
#[Locked]
public bool $allowPipes = false;

#[Locked]
public bool $allowRedirection = false;

#[Locked]
public bool $allowChaining = false;

#[Locked]
public bool $allowExpansion = false;

#[Locked]
public bool $allowAllShellOperators = false;
```

**Step 2: Add mount parameters**

In the `mount()` method signature (line 413), add after `bool $allowAllCommands = false,`:
```php
bool $allowPipes = false,
bool $allowRedirection = false,
bool $allowChaining = false,
bool $allowExpansion = false,
bool $allowAllShellOperators = false,
```

In the mount body, add after line 474 (`$this->allowAllCommands = $allowAllCommands;`):
```php
$this->allowPipes = $allowPipes || $allowAllShellOperators;
$this->allowRedirection = $allowRedirection || $allowAllShellOperators;
$this->allowChaining = $allowChaining || $allowAllShellOperators;
$this->allowExpansion = $allowExpansion || $allowAllShellOperators;
$this->allowAllShellOperators = $allowAllShellOperators;
```

**Step 3: Update getSanitizer to pass flags**

Replace the `getSanitizer()` method (line 1310):
```php
protected function getSanitizer(): CommandSanitizer
{
    $blockedChars = config('web-terminal.security.blocked_characters', []);

    return new CommandSanitizer(
        blockedCharacters: $blockedChars,
        allowPipes: $this->allowPipes,
        allowRedirection: $this->allowRedirection,
        allowChaining: $this->allowChaining,
        allowExpansion: $this->allowExpansion,
    );
}
```

**Step 4: Run existing tests**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Livewire/WebTerminalTest.php`
Expected: All existing tests PASS

**Step 5: Commit**

```bash
git add src/Livewire/WebTerminal.php
git commit -m "feat: wire shell operator flags through Livewire mount"
```

---

### Task 4: Add fluent methods to Schema WebTerminal component

**Files:**
- Modify: `src/Schemas/Components/WebTerminal.php:25-77` (properties), `:96-131` (getComponentProperties), and add new methods after line 304 (allowAllCommands section)
- Test: `tests/Unit/Schemas/Components/WebTerminalTest.php`

**Step 1: Write failing tests**

Add these tests at the end of `tests/Unit/Schemas/Components/WebTerminalTest.php`, before the closing (no closing — just add at end):

```php
describe('allowPipes', function () {
    it('does not allow pipes by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowPipes())->toBeFalse();
    });

    it('enables pipes', function () {
        $component = WebTerminal::make()->allowPipes();

        expect($component->getAllowPipes())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowPipes())->toBe($component);
    });
});

describe('allowRedirection', function () {
    it('does not allow redirection by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowRedirection())->toBeFalse();
    });

    it('enables redirection', function () {
        $component = WebTerminal::make()->allowRedirection();

        expect($component->getAllowRedirection())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowRedirection())->toBe($component);
    });
});

describe('allowChaining', function () {
    it('does not allow chaining by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowChaining())->toBeFalse();
    });

    it('enables chaining', function () {
        $component = WebTerminal::make()->allowChaining();

        expect($component->getAllowChaining())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowChaining())->toBe($component);
    });
});

describe('allowExpansion', function () {
    it('does not allow expansion by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowExpansion())->toBeFalse();
    });

    it('enables expansion', function () {
        $component = WebTerminal::make()->allowExpansion();

        expect($component->getAllowExpansion())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowExpansion())->toBe($component);
    });
});

describe('allowAllShellOperators', function () {
    it('does not allow all shell operators by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowAllShellOperators())->toBeFalse();
    });

    it('enables all shell operators', function () {
        $component = WebTerminal::make()->allowAllShellOperators();

        expect($component->getAllowAllShellOperators())->toBeTrue();
        expect($component->getAllowPipes())->toBeTrue();
        expect($component->getAllowRedirection())->toBeTrue();
        expect($component->getAllowChaining())->toBeTrue();
        expect($component->getAllowExpansion())->toBeTrue();
    });

    it('can disable all shell operators', function () {
        $component = WebTerminal::make()
            ->allowAllShellOperators()
            ->allowAllShellOperators(false);

        expect($component->getAllowAllShellOperators())->toBeFalse();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowAllShellOperators())->toBe($component);
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Schemas/Components/WebTerminalTest.php --filter="allowPipes|allowRedirection|allowChaining|allowExpansion|allowAllShellOperators"`
Expected: FAIL - methods do not exist

**Step 3: Implement fluent methods**

In `src/Schemas/Components/WebTerminal.php`:

1. Add properties after line 43 (`protected bool|Closure $allowAll = false;`):
```php
protected bool|Closure $allowPipes = false;

protected bool|Closure $allowRedirection = false;

protected bool|Closure $allowChaining = false;

protected bool|Closure $allowExpansion = false;

protected bool|Closure $allowAllShellOperators = false;
```

2. Add to `getComponentProperties()` array (after line 110 `'allowAllCommands'`):
```php
'allowPipes' => $this->getAllowPipes(),
'allowRedirection' => $this->getAllowRedirection(),
'allowChaining' => $this->getAllowChaining(),
'allowExpansion' => $this->getAllowExpansion(),
'allowAllShellOperators' => $this->getAllowAllShellOperators(),
```

3. Add fluent methods after the `getAllowAll()` method (after line 304, before the Environment Configuration section):
```php
/**
 * Allow pipe operators (|) in commands.
 *
 * Enables piping output between commands (e.g., `ls | grep foo`).
 * Risk: Low - pipes pass data between processes.
 */
public function allowPipes(bool|Closure $allow = true): static
{
    $this->allowPipes = $allow;

    return $this;
}

public function getAllowPipes(): bool
{
    return $this->evaluate($this->allowPipes);
}

/**
 * Allow redirection operators (>, <, >>, <<) in commands.
 *
 * Enables file redirection (e.g., `echo test > file.txt`).
 * Risk: Medium - can overwrite or read files.
 */
public function allowRedirection(bool|Closure $allow = true): static
{
    $this->allowRedirection = $allow;

    return $this;
}

public function getAllowRedirection(): bool
{
    return $this->evaluate($this->allowRedirection);
}

/**
 * Allow chaining operators (;, &&, ||, &) in commands.
 *
 * Enables running multiple commands (e.g., `ls && pwd`).
 * Risk: Medium - allows executing multiple commands sequentially.
 */
public function allowChaining(bool|Closure $allow = true): static
{
    $this->allowChaining = $allow;

    return $this;
}

public function getAllowChaining(): bool
{
    return $this->evaluate($this->allowChaining);
}

/**
 * Allow expansion operators ($, `, $(), ${}) in commands.
 *
 * Enables variable and command substitution (e.g., `echo $HOME`).
 * Risk: High - allows arbitrary command execution via substitution.
 */
public function allowExpansion(bool|Closure $allow = true): static
{
    $this->allowExpansion = $allow;

    return $this;
}

public function getAllowExpansion(): bool
{
    return $this->evaluate($this->allowExpansion);
}

/**
 * Allow all shell operators (pipes, redirection, chaining, expansion).
 *
 * WARNING: This disables all operator filtering. Use with caution.
 * Only use in trusted environments where users need full shell access.
 */
public function allowAllShellOperators(bool|Closure $allow = true): static
{
    $this->allowAllShellOperators = $allow;
    $this->allowPipes = $allow;
    $this->allowRedirection = $allow;
    $this->allowChaining = $allow;
    $this->allowExpansion = $allow;

    return $this;
}

public function getAllowAllShellOperators(): bool
{
    return $this->evaluate($this->allowAllShellOperators);
}
```

**Step 4: Run tests to verify they pass**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Schemas/Components/WebTerminalTest.php`
Expected: All tests PASS

**Step 5: Commit**

```bash
git add src/Schemas/Components/WebTerminal.php tests/Unit/Schemas/Components/WebTerminalTest.php
git commit -m "feat: add shell operator fluent methods to Schema component"
```

---

### Task 5: Update ValidCommand attribute to align with operator groups

**Files:**
- Modify: `src/Attributes/ValidCommand.php`
- Test: `tests/Unit/Attributes/ValidCommandTest.php`

**Step 1: Read and update ValidCommand**

The `ValidCommand` attribute already has `allowPipes` and `allowRedirection`. Add `allowChaining` and `allowExpansion` to align with the new groups. Update `DANGEROUS_CHARS` to use the same group logic.

Add constructor params `allowChaining` and `allowExpansion`, and update `containsDangerousCharacters()` to filter by group (same logic as CommandSanitizer).

**Step 2: Run existing ValidCommand tests**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Attributes/ValidCommandTest.php`
Expected: All tests PASS

**Step 3: Commit**

```bash
git add src/Attributes/ValidCommand.php tests/Unit/Attributes/ValidCommandTest.php
git commit -m "feat: align ValidCommand attribute with shell operator groups"
```

---

### Task 6: Update README documentation

**Files:**
- Modify: `README.md`

**Step 1: Add Shell Operator Controls section**

Find the security/configuration section of the README and add a comprehensive section documenting:

1. What shell operators are and why they're blocked by default
2. The 4 operator groups with their characters and risk levels
3. Fluent API usage examples for each method
4. The global toggle `allowAllShellOperators()`
5. Security invariants (null bytes, newlines always blocked)
6. A risk reference table

Example content to add:

```markdown
### Shell Operator Controls

By default, the terminal blocks shell operators to prevent command injection attacks. You can selectively enable operator groups based on your security requirements.

#### Operator Groups

| Method | Operators | Risk Level | Description |
|--------|-----------|------------|-------------|
| `allowPipes()` | `\|` | Low | Enables piping output between commands. Pipes pass stdout of one command to stdin of another. Example: `ls \| grep foo` |
| `allowRedirection()` | `>` `<` `>>` `<<` | Medium | Enables file I/O redirection. Output redirection (`>`) can overwrite files. Input redirection (`<`) reads from files. Append (`>>`) adds to files. Here-documents (`<<`) allow multi-line input. |
| `allowChaining()` | `;` `&&` `\|\|` `&` | Medium | Enables running multiple commands. Semicolon (`;`) runs sequentially. AND (`&&`) runs next only on success. OR (`\|\|`) runs next only on failure. Background (`&`) runs asynchronously. |
| `allowExpansion()` | `$` `` ` `` `$()` `${}` | High | Enables variable and command substitution. Dollar sign (`$VAR`) expands variables. Backticks and `$()` execute commands and substitute their output. `${}` enables advanced parameter expansion. **This is the most powerful and potentially dangerous group.** |
| `allowAllShellOperators()` | All above | High | Enables all operator groups at once. Only use in trusted environments. |

#### Usage Examples

```php
// Allow only piping (low risk)
WebTerminal::make()
    ->allowedCommands(['ls', 'grep', 'sort', 'wc'])
    ->allowPipes()

// Allow piping and redirection
WebTerminal::make()
    ->allowedCommands(['ls', 'grep', 'cat', 'echo'])
    ->allowPipes()
    ->allowRedirection()

// Full shell access (high risk - trusted environments only)
WebTerminal::make()
    ->allowAllCommands()
    ->allowAllShellOperators()
```

#### Security Invariants

The following characters are **always blocked** regardless of operator settings:
- **Null bytes** (`\x00`) - Can truncate strings and bypass security checks
- **Newlines** (`\n`) - Can inject additional commands on new lines
- **Carriage returns** (`\r`) - Can be used for command injection via line manipulation
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add shell operator controls documentation"
```

---

### Task 7: Install web-terminal in test project and run E2E tests

**Files:**
- Test project: `/home/guerra/projects/test_projects/testapp_f5/`

**Step 1: Install web-terminal package in test project**

Run:
```bash
cd /home/guerra/projects/test_projects/testapp_f5
composer require mwguerra/web-terminal:dev-feature/shell-operator-controls --no-interaction
```

If the package is local/symlinked, add the repository path first if needed.

**Step 2: Set up a terminal page with operator controls**

Create a Filament page that tests the different operator configurations. Use read-only, non-destructive commands (ls, cat, grep, echo) for testing.

**Step 3: Run E2E tests with Playwright**

Use Playwright MCP to:
1. Navigate to the terminal page
2. Connect to the terminal
3. Test that blocked operators show error message
4. Test that allowed operators work correctly
5. Test combinations of operator groups
6. Verify no unexpected errors in the console

All test commands should be non-destructive: `ls | grep`, `cat file`, `ls && pwd`, `echo $HOME`, etc.

**Step 4: Commit test page if created**

---

### Task 8: Run full test suite and merge

**Step 1: Run all unit tests**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest`
Expected: All tests PASS

**Step 2: Merge to main**

```bash
git checkout main
git merge feature/shell-operator-controls
git push origin main
```
