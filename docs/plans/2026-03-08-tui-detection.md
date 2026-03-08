# TUI Detection Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Detect full-screen TUI applications (vim, htop, less, etc.) and show a clear error message with non-interactive alternatives instead of rendering garbled output.

**Architecture:** A new `TuiDetector` class detects alternate screen buffer escape sequences in command output. It integrates at two points: (1) `addCommandResultOutput()` for synchronous execution, and (2) `pollOutput()` / `doImmediatePoll()` for interactive (tmux) execution. When detected, the process is killed and a red error with command-specific suggestions is shown.

**Tech Stack:** PHP 8.2, Pest 3, Laravel 12, Livewire 4

---

### Task 1: TuiDetector — Tests

**Files:**
- Create: `tests/Unit/Terminal/TuiDetectorTest.php`

**Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Terminal\TuiDetector;

describe('TuiDetector', function () {
    describe('containsTuiSequences', function () {
        it('detects standard alternate screen buffer sequence', function () {
            $output = "some text\x1b[?1049hmore text";

            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('detects older alternate screen buffer variant ?47h', function () {
            $output = "\x1b[?47h";

            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('detects older alternate screen buffer variant ?1047h', function () {
            $output = "\x1b[?1047h";

            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('detects leave alternate screen sequences', function () {
            $output = "\x1b[?1049l";

            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('detects octal escape variant', function () {
            $output = "\033[?1049h";

            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('does not false-positive on SGR color sequences', function () {
            $output = "\x1b[31mred text\x1b[0m normal text \x1b[1;32mgreen bold\x1b[0m";

            expect(TuiDetector::containsTuiSequences($output))->toBeFalse();
        });

        it('does not false-positive on cursor movement', function () {
            $output = "\x1b[H\x1b[2J\x1b[A\x1b[B\x1b[C\x1b[D";

            expect(TuiDetector::containsTuiSequences($output))->toBeFalse();
        });

        it('does not false-positive on cursor visibility', function () {
            $output = "\x1b[?25h\x1b[?25l";

            expect(TuiDetector::containsTuiSequences($output))->toBeFalse();
        });

        it('does not false-positive on line erase', function () {
            $output = "Loading...\x1b[K";

            expect(TuiDetector::containsTuiSequences($output))->toBeFalse();
        });

        it('returns false for plain text', function () {
            expect(TuiDetector::containsTuiSequences('hello world'))->toBeFalse();
        });

        it('returns false for empty string', function () {
            expect(TuiDetector::containsTuiSequences(''))->toBeFalse();
        });

        it('detects TUI sequence mixed with other ANSI codes', function () {
            $output = "\x1b[31mred\x1b[0m\x1b[?1049h\x1b[32mgreen\x1b[0m";

            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });
    });

    describe('getSuggestion', function () {
        it('suggests batch mode for top', function () {
            $suggestion = TuiDetector::getSuggestion('top');

            expect($suggestion)->toContain('top -b -n 1');
        });

        it('suggests batch mode for top with arguments', function () {
            $suggestion = TuiDetector::getSuggestion('top -u root');

            expect($suggestion)->toContain('top -b -n 1');
        });

        it('suggests top batch mode for htop', function () {
            $suggestion = TuiDetector::getSuggestion('htop');

            expect($suggestion)->toContain('top -b -n 1');
        });

        it('suggests cat for vim', function () {
            $suggestion = TuiDetector::getSuggestion('vim /etc/hosts');

            expect($suggestion)->toContain('cat /etc/hosts');
        });

        it('suggests cat for vi', function () {
            $suggestion = TuiDetector::getSuggestion('vi /etc/hosts');

            expect($suggestion)->toContain('cat /etc/hosts');
        });

        it('suggests cat for nano', function () {
            $suggestion = TuiDetector::getSuggestion('nano /etc/hosts');

            expect($suggestion)->toContain('cat /etc/hosts');
        });

        it('suggests cat for less', function () {
            $suggestion = TuiDetector::getSuggestion('less /var/log/syslog');

            expect($suggestion)->toContain('cat /var/log/syslog');
        });

        it('suggests cat for more', function () {
            $suggestion = TuiDetector::getSuggestion('more README.md');

            expect($suggestion)->toContain('cat README.md');
        });

        it('returns generic suggestion for man', function () {
            $suggestion = TuiDetector::getSuggestion('man ls');

            expect($suggestion)->not->toBeNull();
            expect($suggestion)->toContain('man');
        });

        it('returns null for unknown commands', function () {
            expect(TuiDetector::getSuggestion('ls -la'))->toBeNull();
            expect(TuiDetector::getSuggestion('echo hello'))->toBeNull();
        });

        it('handles commands with paths', function () {
            $suggestion = TuiDetector::getSuggestion('/usr/bin/vim /etc/hosts');

            expect($suggestion)->toContain('cat /etc/hosts');
        });

        it('handles sudo prefix', function () {
            $suggestion = TuiDetector::getSuggestion('sudo vim /etc/hosts');

            expect($suggestion)->toContain('cat /etc/hosts');
        });
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Terminal/TuiDetectorTest.php`
Expected: FAIL — class `TuiDetector` not found

**Step 3: Commit test file**

```bash
git add tests/Unit/Terminal/TuiDetectorTest.php
git commit -m "test: add TuiDetector tests for TUI sequence detection and suggestions"
```

---

### Task 2: TuiDetector — Implementation

**Files:**
- Create: `src/Terminal/TuiDetector.php`

**Step 1: Implement TuiDetector**

```php
<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Terminal;

/**
 * Detects full-screen TUI (Text User Interface) applications in command output.
 *
 * TUI apps like vim, htop, less, and nano use alternate screen buffer escape
 * sequences to take over the terminal. These sequences cannot be rendered in
 * a web terminal's line-based display. This class detects those sequences and
 * provides suggestions for non-interactive alternatives.
 */
class TuiDetector
{
    /**
     * Alternate screen buffer escape sequence patterns.
     *
     * These are the definitive signals that an application is trying to take
     * over the full terminal screen. Other escape sequences (cursor movement,
     * colors, line erase) are either handled by AnsiToHtml or degrade gracefully.
     */
    private const TUI_PATTERNS = [
        '/(?:\x1b|\033)\[\?1049[hl]/',  // Standard alternate screen buffer
        '/(?:\x1b|\033)\[\?47[hl]/',     // Older variant
        '/(?:\x1b|\033)\[\?1047[hl]/',   // Another older variant
    ];

    /**
     * Map of TUI commands to their non-interactive alternatives.
     *
     * Keys are base command names (without path or arguments).
     * Values are closures that receive the full command and return a suggestion string.
     *
     * @var array<string, \Closure(string): string>
     */
    private const SUGGESTION_COMMANDS = [
        'top' => 'batch',
        'htop' => 'batch',
        'vim' => 'pager',
        'vi' => 'pager',
        'nvim' => 'pager',
        'nano' => 'pager',
        'less' => 'pager',
        'more' => 'pager',
        'man' => 'man',
    ];

    /**
     * Check if the output contains TUI alternate screen buffer sequences.
     */
    public static function containsTuiSequences(string $output): bool
    {
        if ($output === '') {
            return false;
        }

        foreach (self::TUI_PATTERNS as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a suggestion for a non-interactive alternative to the given command.
     *
     * Returns a human-readable suggestion string, or null if no suggestion is known.
     */
    public static function getSuggestion(string $command): ?string
    {
        $baseCommand = self::extractBaseCommand($command);
        $args = self::extractArguments($command);
        $type = self::SUGGESTION_COMMANDS[$baseCommand] ?? null;

        if ($type === null) {
            return null;
        }

        return match ($type) {
            'batch' => "Try instead: top -b -n 1",
            'pager' => $args !== '' ? "Try instead: cat {$args}" : "This command requires a file argument to suggest an alternative.",
            'man' => $args !== '' ? "Try instead: {$command} | col -b (requires pipes enabled), or search online for \"{$baseCommand} {$args}\"" : null,
            default => null,
        };
    }

    /**
     * Get the error message for TUI detection.
     */
    public static function getErrorMessage(string $command): string
    {
        $message = 'This command requires a full-screen terminal (TUI) which is not supported in this web terminal.';
        $suggestion = self::getSuggestion($command);

        if ($suggestion !== null) {
            $message .= "\n{$suggestion}";
        }

        return $message;
    }

    /**
     * Extract the base command name from a full command string.
     *
     * Handles paths (/usr/bin/vim), sudo prefix, and arguments.
     */
    private static function extractBaseCommand(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return '';
        }

        $executable = $parts[0];

        // Skip sudo
        if ($executable === 'sudo' && isset($parts[1])) {
            $executable = $parts[1];
        }

        // Strip path (e.g., /usr/bin/vim -> vim)
        return basename($executable);
    }

    /**
     * Extract the arguments from a command string (everything after the base command).
     */
    private static function extractArguments(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) <= 1) {
            return '';
        }

        $startIndex = 1;

        // Skip sudo
        if ($parts[0] === 'sudo') {
            $startIndex = 2;
        }

        return implode(' ', array_slice($parts, $startIndex));
    }
}
```

**Step 2: Run tests to verify they pass**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Terminal/TuiDetectorTest.php`
Expected: All tests PASS

**Step 3: Commit**

```bash
git add src/Terminal/TuiDetector.php
git commit -m "feat: add TuiDetector for full-screen TUI application detection"
```

---

### Task 3: WebTerminal Integration — Synchronous Execution

**Files:**
- Modify: `src/Livewire/WebTerminal.php:1220-1241` (addCommandResultOutput method)
- Modify: `tests/Unit/Livewire/WebTerminalTest.php`

**Step 1: Write failing tests**

Add to `tests/Unit/Livewire/WebTerminalTest.php` inside the main `describe('WebTerminal', ...)` block, after the existing `getPlainTextOutput` describe:

```php
describe('TUI detection in synchronous output', function () {
    it('shows error when command output contains TUI sequences', function () {
        $component = Livewire::test(WebTerminal::class);
        $component->call('connect');
        $component->set('command', 'echo -e "\x1b[?1049h"');
        $component->call('executeCommand');

        $output = $component->get('output');
        $lastOutputs = array_slice($output, -2);
        $errorFound = false;
        foreach ($lastOutputs as $line) {
            if ($line['type'] === 'error' && str_contains($line['content'], 'full-screen terminal')) {
                $errorFound = true;
                break;
            }
        }

        expect($errorFound)->toBeTrue();
    });

    it('does not trigger TUI detection for normal command output', function () {
        $component = Livewire::test(WebTerminal::class);
        $component->call('connect');
        $component->set('command', 'echo "hello world"');
        $component->call('executeCommand');

        $output = $component->get('output');
        $hasError = false;
        foreach ($output as $line) {
            if ($line['type'] === 'error' && str_contains($line['content'], 'full-screen terminal')) {
                $hasError = true;
                break;
            }
        }

        expect($hasError)->toBeFalse();
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Livewire/WebTerminalTest.php --filter="TUI detection"`
Expected: FAIL — TUI detection not implemented yet

**Step 3: Modify `addCommandResultOutput` in `src/Livewire/WebTerminal.php`**

Add the `use` statement at the top of the file:
```php
use MWGuerra\WebTerminal\Terminal\TuiDetector;
```

Replace the `addCommandResultOutput` method (lines 1220-1241):

```php
protected function addCommandResultOutput(CommandResult $result): void
{
    // Check for TUI sequences in output before rendering
    $combinedOutput = $result->stdout . $result->stderr;
    if (TuiDetector::containsTuiSequences($combinedOutput)) {
        $this->addOutput(TerminalOutput::error(
            TuiDetector::getErrorMessage($result->command)
        ));

        return;
    }

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
```

**Step 4: Run tests to verify they pass**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Livewire/WebTerminalTest.php --filter="TUI detection"`
Expected: PASS

**Step 5: Run full test suite**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest`
Expected: All existing tests still pass

**Step 6: Commit**

```bash
git add src/Livewire/WebTerminal.php tests/Unit/Livewire/WebTerminalTest.php
git commit -m "feat: detect TUI sequences in synchronous command output"
```

---

### Task 4: WebTerminal Integration — Interactive Mode (tmux)

**Files:**
- Modify: `src/Livewire/WebTerminal.php:1528-1569` (pollOutput method)
- Modify: `src/Livewire/WebTerminal.php:1770-1798` (doImmediatePoll method)
- Modify: `src/Livewire/WebTerminal.php:2350-2390` (pollOutputForScript method)

**Step 1: Modify `pollOutput` method**

In the `pollOutput()` method (around line 1551), replace the full-screen handling block:

**Before:**
```php
if ($isFullScreen && $hasContent) {
    // Full screen mode: replace output from session start
    $this->replaceInteractiveOutput($output);
}
```

**After:**
```php
if ($isFullScreen && $hasContent) {
    // TUI detected — kill process and show error
    $this->handleTuiDetected($handler);
    return;
}
```

**Step 2: Modify `doImmediatePoll` method**

Same change in `doImmediatePoll()` (around line 1780):

**Before:**
```php
if ($isFullScreen && $hasContent) {
    // Full screen mode: replace output from session start
    $this->replaceInteractiveOutput($output);
}
```

**After:**
```php
if ($isFullScreen && $hasContent) {
    // TUI detected — kill process and show error
    $this->handleTuiDetected($handler);
    return;
}
```

**Step 3: Modify `pollOutputForScript` method**

Same change in `pollOutputForScript()` (around line 2369):

**Before:**
```php
if ($isFullScreen && $hasContent) {
    $this->replaceInteractiveOutput($output);
}
```

**After:**
```php
if ($isFullScreen && $hasContent) {
    // TUI detected — kill process and show error
    $this->handleTuiDetected($handler);
    return;
}
```

**Step 4: Add the `handleTuiDetected` method**

Add this new method near the other interactive helper methods (after `finishInteractiveSession`):

```php
/**
 * Handle detection of a TUI application in interactive mode.
 *
 * Kills the process, shows an error message with suggestions, and resets state.
 */
protected function handleTuiDetected(ConnectionHandlerInterface $handler): void
{
    // Get the command that was running for the suggestion
    $command = $this->interactiveCommand ?? '';

    // Kill the process
    try {
        $handler->terminateProcess($this->activeSessionId);
    } catch (\Throwable) {
        // Best-effort termination
    }

    // Clear any partial output from the TUI app
    if ($this->interactiveOutputStart > 0) {
        $this->output = array_slice($this->output, 0, $this->interactiveOutputStart);
    }

    // Show error message with suggestion
    $this->addOutput(TerminalOutput::error(
        TuiDetector::getErrorMessage($command)
    ));

    // Reset interactive state
    $this->resetInteractiveState();
}
```

**Step 5: Verify the `interactiveCommand` property exists**

Check if the component stores the interactive command. If not, add it. Search for `interactiveCommand` in WebTerminal.php. If it doesn't exist, add to the class properties:

```php
protected string $interactiveCommand = '';
```

And set it in `startInteractiveCommand()` before calling `handler->startInteractive()`:
```php
$this->interactiveCommand = $command;
```

And clear it in `resetInteractiveState()`:
```php
$this->interactiveCommand = '';
```

**Step 6: Run full test suite**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest`
Expected: All tests pass

**Step 7: Commit**

```bash
git add src/Livewire/WebTerminal.php
git commit -m "feat: detect and handle TUI apps in interactive mode"
```

---

### Task 5: Update Help Command and CHANGELOG

**Files:**
- Modify: `src/Livewire/WebTerminal.php` (showHelp method)
- Modify: `CHANGELOG.md`

**Step 1: Find and update `showHelp()`**

Search for the `showHelp` method in WebTerminal.php. Add a line about TUI detection to the help output:

```php
$this->addOutput(TerminalOutput::info('Full-screen apps (vim, htop, less) are detected and blocked with suggestions.'));
```

**Step 2: Update CHANGELOG.md**

Add a new section at the top for the upcoming release:

```markdown
## [Unreleased]

### Added

- TUI application detection — full-screen apps (vim, htop, less, nano, etc.) are detected via alternate screen buffer sequences and blocked with a clear error message and non-interactive alternative suggestions
- `TuiDetector` utility class for identifying TUI escape sequences and providing command suggestions
```

**Step 3: Commit**

```bash
git add src/Livewire/WebTerminal.php CHANGELOG.md
git commit -m "docs: update help command and CHANGELOG with TUI detection"
```

---

### Task 6: E2E Testing on testapp_f5

**Files:**
- Uses: `/home/guerra/projects/test_projects/testapp_f5/`

**Context:** The test app at `testapp_f5` has a CopyPasteTerminal page at `/copy-paste-terminal` that allows all commands and all shell operators. Use Playwright MCP to verify TUI detection works in the browser.

**Step 1: Update testapp_f5 composer dependency**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
# Update composer.json to point to the new branch
# Then run composer update
```

Update `composer.json` to use `"mwguerra/web-terminal": "dev-feature/tui-detection"` and run `composer update mwguerra/web-terminal`.

**Step 2: Navigate to the terminal page**

Use Playwright to navigate to `https://testapp_f5.test/admin/copy-paste-terminal`, log in if needed, and verify the terminal loads.

**Step 3: Test TUI detection**

Run a command that would trigger TUI detection. Since we can't safely run `vim` or `htop` on the user's machine, use `echo` to simulate the escape sequence:

```bash
echo -e "\033[?1049h"
```

Verify the red error message appears containing "full-screen terminal".

**Step 4: Test normal commands still work**

Run `ls`, `pwd`, `echo "hello"` and verify they produce normal output (no false-positive TUI detection).

**Step 5: Clean up**

No cleanup needed — keep the test app working.

**Step 6: Commit any test app changes**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
git add -A
git commit -m "test: update web-terminal to tui-detection branch"
```
