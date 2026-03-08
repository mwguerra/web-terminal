# Copy & Paste Improvements Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add clipboard capabilities to the web terminal — copy-all button, per-block copy on hover, and multi-line paste with confirmation dialog.

**Architecture:** All clipboard operations use the browser's `navigator.clipboard` API via Alpine.js — no Livewire round-trips for copy. Output lines are grouped into command blocks at template level. Multi-line paste shows an Alpine.js confirmation modal and executes commands sequentially through Livewire.

**Tech Stack:** Alpine.js (clipboard, modal, hover), Blade templates, Livewire (sequential paste execution), Tailwind CSS

---

### Task 1: Add `getPlainTextOutput()` method to WebTerminal component

This method returns all terminal output as plain text (ANSI stripped) for the Copy All feature.

**Files:**
- Modify: `src/Livewire/WebTerminal.php`
- Test: `tests/Unit/Livewire/WebTerminalTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Livewire/WebTerminalTest.php` inside the existing `describe('WebTerminal Component', ...)`:

```php
describe('getPlainTextOutput', function () {
    it('returns empty string when no output', function () {
        $component = Livewire::test(WebTerminal::class);

        // Clear the welcome message
        $component->call('clearOutput');

        expect($component->instance()->getPlainTextOutput())->toBe('');
    });

    it('returns plain text from output lines', function () {
        $component = Livewire::test(WebTerminal::class);
        $component->call('clearOutput');

        // Manually add output lines via reflection to test the method
        $instance = $component->instance();
        $instance->output = [
            ['type' => 'command', 'content' => '$ ls', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-command'],
            ['type' => 'stdout', 'content' => 'file1.txt', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
            ['type' => 'stdout', 'content' => 'file2.txt', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
        ];

        $result = $instance->getPlainTextOutput();
        expect($result)->toBe("$ ls\nfile1.txt\nfile2.txt");
    });

    it('strips ANSI codes from output', function () {
        $component = Livewire::test(WebTerminal::class);
        $instance = $component->instance();
        $instance->output = [
            ['type' => 'stdout', 'content' => "\x1b[31mred text\x1b[0m", 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
        ];

        $result = $instance->getPlainTextOutput();
        expect($result)->toBe('red text');
    });

    it('skips empty content lines', function () {
        $component = Livewire::test(WebTerminal::class);
        $instance = $component->instance();
        $instance->output = [
            ['type' => 'command', 'content' => '$ pwd', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-command'],
            ['type' => 'stdout', 'content' => '', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
            ['type' => 'stdout', 'content' => '/home/user', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
        ];

        $result = $instance->getPlainTextOutput();
        expect($result)->toBe("$ pwd\n/home/user");
    });
});
```

**Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Livewire/WebTerminalTest.php --filter="getPlainTextOutput"`
Expected: FAIL — `getPlainTextOutput` method does not exist, `clearOutput` method does not exist.

**Step 3: Write minimal implementation**

Add to `src/Livewire/WebTerminal.php` after the `convertAnsiToHtml()` method (around line 883):

```php
/**
 * Get all output as plain text (ANSI stripped).
 */
public function getPlainTextOutput(): string
{
    $lines = [];

    foreach ($this->output as $line) {
        $content = trim($line['content'] ?? '');
        if ($content === '') {
            continue;
        }

        $lines[] = AnsiToHtml::strip($content);
    }

    return implode("\n", $lines);
}

/**
 * Clear all terminal output.
 */
public function clearOutput(): void
{
    $this->output = [];
}
```

**Step 4: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest tests/Unit/Livewire/WebTerminalTest.php --filter="getPlainTextOutput"`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Livewire/WebTerminal.php tests/Unit/Livewire/WebTerminalTest.php
git commit -m "feat: add getPlainTextOutput and clearOutput methods"
```

---

### Task 2: Add Copy All button to header

Add a clipboard icon button to the header bar that copies all terminal output.

**Files:**
- Modify: `resources/views/terminal.blade.php` — add Alpine.js clipboard helpers
- Modify: `resources/views/partials/header.blade.php` — add Copy All button

**Step 1: Add clipboard helper methods to Alpine x-data**

In `resources/views/terminal.blade.php`, add these properties and methods to the `x-data` object (after the existing `scrollToBottom()` method around line 179):

```javascript
copyFeedback: false,
copyFeedbackTimeout: null,
async copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            return true;
        } catch {
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }
},
async copyAllOutput() {
    const text = await $wire.getPlainTextOutput();
    const success = await this.copyToClipboard(text);
    if (success) {
        this.copyFeedback = true;
        clearTimeout(this.copyFeedbackTimeout);
        this.copyFeedbackTimeout = setTimeout(() => {
            this.copyFeedback = false;
        }, 1500);
    }
},
```

**Step 2: Add Copy All button to header**

In `resources/views/partials/header.blade.php`, add the Copy All button before the Info Toggle Button (before line 99 `{{-- Info Toggle Button --}}`):

```blade
{{-- Copy All Button --}}
<button
    type="button"
    @click="copyAllOutput()"
    class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
    :class="{
        'bg-emerald-500/20 text-emerald-600 ring-1 ring-emerald-500/40 dark:bg-emerald-500/30 dark:text-emerald-400': copyFeedback,
        'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60': !copyFeedback
    }"
    :title="copyFeedback ? 'Copied!' : 'Copy all output'"
    :disabled="!$wire.output || $wire.output.length === 0"
>
    {{-- Clipboard icon (default) --}}
    <svg x-show="!copyFeedback" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
        <path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3A1.5 1.5 0 0 1 13 3.5H7ZM5.5 5A1.5 1.5 0 0 0 4 6.5v10A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-10A1.5 1.5 0 0 0 14.5 5h-9Z"/>
        <path d="M8.5 1A2.5 2.5 0 0 0 6 3.5H4.5A2.5 2.5 0 0 0 2 6v10.5A2.5 2.5 0 0 0 4.5 19h9a2.5 2.5 0 0 0 2.5-2.5V6a2.5 2.5 0 0 0-2.5-2.5H12A2.5 2.5 0 0 0 9.5 1h-1Z"/>
    </svg>
    {{-- Checkmark icon (after copy) --}}
    <svg x-show="copyFeedback" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
    </svg>
</button>
```

**Step 3: Visual test**

Verify the button renders correctly, shows clipboard icon by default, changes to checkmark on click, and reverts after 1.5s. Test with both light and dark themes.

**Step 4: Commit**

```bash
git add resources/views/terminal.blade.php resources/views/partials/header.blade.php
git commit -m "feat: add Copy All button to terminal header"
```

---

### Task 3: Group output into command blocks with per-block copy button

Restructure output.blade.php to group lines by command boundaries, with a hover copy button on each block.

**Files:**
- Modify: `resources/views/partials/output.blade.php` — block grouping + copy button
- Modify: `resources/css/index.css` — copy button hover styles

**Step 1: Rewrite output.blade.php with block grouping**

Replace the contents of `resources/views/partials/output.blade.php`:

```blade
{{-- Terminal Output Area --}}
<div
    class="terminal-output flex-1 overflow-y-auto p-3 scroll-smooth text-left"
    x-ref="output"
    role="log"
    aria-live="polite"
    aria-label="Terminal output"
>
    @php
        // Group output lines into command blocks
        $blocks = [];
        $currentBlock = [];

        foreach ($output as $line) {
            if (trim($line['content'] ?? '') === '') {
                continue;
            }

            if (($line['type'] ?? '') === 'command' && !empty($currentBlock)) {
                $blocks[] = $currentBlock;
                $currentBlock = [];
            }

            $currentBlock[] = $line;
        }

        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }
    @endphp

    @foreach($blocks as $blockIndex => $block)
        <div
            class="terminal-block group relative"
            x-data="{ blockCopied: false }"
        >
            {{-- Per-block copy button (visible on hover) --}}
            <button
                type="button"
                class="absolute top-1 right-1 z-10 flex items-center justify-center w-6 h-6 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                :class="blockCopied
                    ? 'bg-emerald-500/20 text-emerald-500'
                    : 'bg-slate-500/20 text-slate-400 hover:bg-slate-500/30 hover:text-slate-300'"
                :title="blockCopied ? 'Copied!' : 'Copy block'"
                @click="
                    const lines = $el.closest('.terminal-block').querySelectorAll('[data-line-content]');
                    const text = Array.from(lines).map(el => el.getAttribute('data-line-content')).join('\n');
                    copyToClipboard(text).then(success => {
                        if (success) {
                            blockCopied = true;
                            setTimeout(() => { blockCopied = false; }, 1500);
                        }
                    });
                "
            >
                <svg x-show="!blockCopied" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                    <path d="M5.5 3.5A1.5 1.5 0 0 1 7 2h2.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 1 .439 1.061V9.5A1.5 1.5 0 0 1 12 11V3.5H5.5Z"/>
                    <path d="M3.5 5A1.5 1.5 0 0 0 2 6.5v7A1.5 1.5 0 0 0 3.5 15h5A1.5 1.5 0 0 0 10 13.5v-7A1.5 1.5 0 0 0 8.5 5h-5Z"/>
                </svg>
                <svg x-show="blockCopied" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                    <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Block lines --}}
            @foreach($block as $line)
                <div
                    class="whitespace-pre-wrap break-words m-0 p-0 leading-snug text-left block w-full
                        @if(($line['type'] ?? '') === 'stdout') text-slate-700 dark:text-zinc-200
                        @elseif(($line['type'] ?? '') === 'stderr') text-red-600 dark:text-red-300
                        @elseif(($line['type'] ?? '') === 'error') text-red-700 dark:text-red-500 font-semibold
                        @elseif(($line['type'] ?? '') === 'info') text-blue-600 dark:text-blue-400
                        @elseif(($line['type'] ?? '') === 'command') text-emerald-600 dark:text-emerald-400 font-medium pt-1 pb-0.5
                        @elseif(($line['type'] ?? '') === 'system') text-slate-500 dark:text-gray-500 italic
                        @else text-slate-700 dark:text-zinc-200
                        @endif
                    "
                    data-line-content="{{ \MWGuerra\WebTerminal\Terminal\AnsiToHtml::strip($line['content']) }}"
                >{!! $this->convertAnsiToHtml($line['content']) !!}</div>
            @endforeach
        </div>
    @endforeach
</div>
```

**Step 2: Add block hover styles to CSS**

Add to `resources/css/index.css` (after the existing ANSI styles):

```css
/* Terminal block copy button hover */
.terminal-block {
    border-left: 2px solid transparent;
    padding-left: 4px;
    margin-left: -6px;
    transition: border-color 0.15s ease;
}

.terminal-block:hover {
    border-left-color: rgba(148, 163, 184, 0.2);
}

:is(.dark .terminal-block:hover) {
    border-left-color: rgba(255, 255, 255, 0.1);
}
```

**Step 3: Build CSS**

Run: `cd /home/guerra/projects/web-terminal && npm run build`
Expected: CSS builds successfully.

**Step 4: Visual test**

Verify blocks are visually grouped, the subtle left-border appears on hover, and the copy button appears in the top-right corner of each block.

**Step 5: Commit**

```bash
git add resources/views/partials/output.blade.php resources/css/index.css resources/dist/web-terminal.css
git commit -m "feat: add per-command-block copy button with hover reveal"
```

---

### Task 4: Add multi-line paste confirmation modal

Intercept paste events on the input, and for multi-line content show a confirmation dialog.

**Files:**
- Modify: `resources/views/terminal.blade.php` — paste handler + modal state
- Modify: `resources/views/partials/input.blade.php` — paste event listener
- Create: `resources/views/partials/paste-modal.blade.php` — confirmation dialog

**Step 1: Add paste state and methods to Alpine x-data**

In `resources/views/terminal.blade.php`, add these properties and methods to the `x-data` object (after the `copyAllOutput` method):

```javascript
showPasteModal: false,
pasteCommands: [],
pasteExecuting: false,
pasteCurrentIndex: 0,
handlePaste(event) {
    if (!this.isConnected) return;

    const text = (event.clipboardData || window.clipboardData).getData('text');
    if (!text || !text.includes('\n')) return;

    event.preventDefault();

    // Parse lines: filter comments (#) and empty lines
    const lines = text.split('\n')
        .map(line => line.trim())
        .filter(line => line !== '' && !line.startsWith('#'));

    if (lines.length === 0) return;

    if (lines.length === 1) {
        // Single effective line after filtering — just paste it
        $wire.set('command', lines[0]);
        return;
    }

    this.pasteCommands = lines;
    this.pasteCurrentIndex = 0;
    this.pasteExecuting = false;
    this.showPasteModal = true;
},
async executePastedCommands() {
    this.pasteExecuting = true;

    for (let i = 0; i < this.pasteCommands.length; i++) {
        this.pasteCurrentIndex = i;
        $wire.set('command', this.pasteCommands[i]);
        await $wire.executeCommand();
        // Small delay between commands for UI to update
        await new Promise(resolve => setTimeout(resolve, 200));
    }

    this.pasteExecuting = false;
    this.showPasteModal = false;
    this.pasteCommands = [];
},
cancelPaste() {
    this.showPasteModal = false;
    this.pasteCommands = [];
    this.pasteExecuting = false;
},
```

**Step 2: Add paste event to input field**

In `resources/views/partials/input.blade.php`, add the `@paste` handler to the `<input>` element (after the `@input` attribute):

```blade
@paste="handlePaste($event)"
```

**Step 3: Include paste modal partial**

In `resources/views/terminal.blade.php`, add after the `@include('web-terminal::partials.script-panel')` line:

```blade
@include('web-terminal::partials.paste-modal')
```

**Step 4: Create paste modal partial**

Create `resources/views/partials/paste-modal.blade.php`:

```blade
{{-- Multi-line Paste Confirmation Modal --}}
<div
    x-show="showPasteModal"
    x-cloak
    class="absolute inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm rounded-xl"
    @keydown.escape.window="if (showPasteModal && !pasteExecuting) cancelPaste()"
>
    <div
        class="w-full max-w-lg mx-4 bg-white dark:bg-[#1e1e2e] rounded-lg shadow-2xl ring-1 ring-slate-200 dark:ring-white/10 overflow-hidden"
        @click.away="if (!pasteExecuting) cancelPaste()"
    >
        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-slate-100 dark:bg-black/30 border-b border-slate-200 dark:border-white/10">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-amber-500">
                    <path d="M13.28 1.22a.75.75 0 0 1 0 1.06L6.56 9H17.75a.75.75 0 0 1 0 1.5H6.56l6.72 6.72a.75.75 0 1 1-1.06 1.06l-8-8a.75.75 0 0 1 0-1.06l8-8a.75.75 0 0 1 1.06 0Z"/>
                </svg>
                <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Paste Multiple Commands</span>
            </div>
            <span class="text-xs text-slate-500 dark:text-zinc-400" x-text="pasteCommands.length + ' command' + (pasteCommands.length !== 1 ? 's' : '')"></span>
        </div>

        {{-- Command List --}}
        <div class="max-h-60 overflow-y-auto p-2">
            <template x-for="(cmd, index) in pasteCommands" :key="index">
                <div
                    class="flex items-start gap-2 px-3 py-1.5 rounded text-sm font-mono transition-colors"
                    :class="{
                        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': pasteExecuting && index < pasteCurrentIndex,
                        'bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-1 ring-blue-500/30': pasteExecuting && index === pasteCurrentIndex,
                        'text-slate-600 dark:text-zinc-300': !pasteExecuting || index > pasteCurrentIndex
                    }"
                >
                    <span class="shrink-0 w-5 text-right text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5 select-none" x-text="index + 1"></span>
                    <span class="text-emerald-600 dark:text-emerald-400 select-none">$</span>
                    <span class="flex-1 break-all" x-text="cmd"></span>
                    <svg x-show="pasteExecuting && index < pasteCurrentIndex" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4 shrink-0 text-emerald-500 mt-0.5">
                        <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="pasteExecuting && index === pasteCurrentIndex" class="w-4 h-4 shrink-0 text-blue-500 animate-spin mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </template>
        </div>

        {{-- Modal Footer --}}
        <div class="flex items-center justify-end gap-2 px-4 py-3 bg-slate-50 dark:bg-black/20 border-t border-slate-200 dark:border-white/10">
            <button
                type="button"
                @click="cancelPaste()"
                :disabled="pasteExecuting"
                class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-zinc-400 bg-slate-200 dark:bg-white/10 rounded hover:bg-slate-300 dark:hover:bg-white/15 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >Cancel</button>
            <button
                type="button"
                @click="executePastedCommands()"
                :disabled="pasteExecuting"
                class="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span x-show="!pasteExecuting">Execute All</span>
                <span x-show="pasteExecuting" class="flex items-center gap-1">
                    <svg class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Running...
                </span>
            </button>
        </div>
    </div>
</div>
```

**Step 5: Visual test**

Test by pasting multi-line text into the input field. Verify:
- Single-line paste works normally
- Multi-line paste shows the modal
- Comment lines (starting with `#`) are filtered out
- Empty lines are filtered out
- Cancel dismisses the modal
- Execute All runs commands sequentially with progress indicators
- Escape key closes modal when not executing

**Step 6: Commit**

```bash
git add resources/views/terminal.blade.php resources/views/partials/input.blade.php resources/views/partials/paste-modal.blade.php
git commit -m "feat: add multi-line paste confirmation modal"
```

---

### Task 5: Build CSS and run full test suite

**Step 1: Build CSS**

Run: `cd /home/guerra/projects/web-terminal && npm run build`
Expected: CSS builds successfully with new classes included.

**Step 2: Run full test suite**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pest --parallel`
Expected: All tests pass.

**Step 3: Run code formatting**

Run: `cd /home/guerra/projects/web-terminal && ./vendor/bin/pint`

**Step 4: Commit if any formatting changes**

```bash
git add -A
git commit -m "chore: format code and build CSS"
```

---

### Task 6: Update CHANGELOG and help command

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `src/Livewire/WebTerminal.php` — update `help` command output

**Step 1: Update CHANGELOG.md**

Add a new section under the existing `## [2.1.0]` heading (or create `## [Unreleased]`):

```markdown
### Added
- Copy All button in header bar — copies entire terminal output to clipboard
- Per-command-block copy button — hover over a command block to reveal copy icon
- Multi-line paste with confirmation — pasting multiple lines shows a modal to review and execute commands sequentially (comment lines starting with `#` are filtered)
- `clearOutput()` method to programmatically clear terminal output
- `getPlainTextOutput()` method to retrieve terminal output as plain text
```

**Step 2: Update help command**

In `src/Livewire/WebTerminal.php`, find the `showHelpMessage()` method and add new keyboard shortcut info:

```php
$this->addOutput(TerminalOutput::stdout('  Ctrl+C  - Cancel running process or script'));
$this->addOutput(TerminalOutput::stdout('  Hover   - Copy button appears on command blocks'));
```

**Step 3: Commit**

```bash
git add CHANGELOG.md src/Livewire/WebTerminal.php
git commit -m "docs: update CHANGELOG and help command with copy/paste features"
```
