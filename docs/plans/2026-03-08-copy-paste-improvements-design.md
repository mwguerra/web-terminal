# Copy & Paste Improvements Design

**Date:** 2026-03-08
**Version:** 2.1.0
**Branch:** feature/copy-paste-improvements

## Overview

Add clipboard capabilities to the web terminal: copy-all button, per-block copy on hover, and multi-line paste with confirmation dialog.

## Features

### 1. Copy All Button (Header)

- Icon button in header bar, next to the info toggle button
- Copies all terminal output as plain text (HTML/ANSI stripped) to clipboard
- Brief "Copied!" tooltip feedback (~1.5s)
- Disabled when no output exists
- Uses `navigator.clipboard.writeText()` with legacy fallback

### 2. Per-Command-Block Copy Button

- Output lines grouped by command boundaries: a `command`-type line + all subsequent output lines until the next `command`-type line
- On hover over a block, a small copy icon appears in the top-right corner
- Clicking copies that block's plain text content to clipboard
- Icon changes to a checkmark for ~1.5s as feedback
- Block wrapper needs `position: relative` and `group` class for hover detection

### 3. Multi-line Paste with Confirmation

- Intercept `paste` event on the input field
- Single-line paste: no change, works as normal
- Multi-line paste (content contains `\n`):
  - Filter out comment lines (lines starting with `#` after trimming)
  - Filter out empty lines
  - Show a confirmation modal listing the commands to execute
  - User confirms → execute lines sequentially (each waits for previous to complete)
  - User cancels → discard, no action
- The confirmation dialog is an Alpine.js modal rendered within the terminal component

## Architecture

### Clipboard Operations

All clipboard interactions are client-side only (Alpine.js). No Livewire round-trips needed for copy operations. The paste confirmation triggers Livewire calls sequentially for execution.

### Output Block Grouping

The Blade template groups output lines into blocks at render time. A block starts when a `command`-type line appears and includes all subsequent non-command lines. Lines before the first command form their own block (system/info messages).

### Sequential Paste Execution

When confirmed, multi-line paste calls `$wire.executeCommand()` for each line. A queue mechanism in Alpine.js sends lines one at a time, waiting for the Livewire response before sending the next. This respects rate limiting and ensures proper ordering.

## Files to Modify

- `resources/views/partials/header.blade.php` — Copy All button
- `resources/views/partials/output.blade.php` — Block grouping + per-block copy button
- `resources/views/partials/input.blade.php` — Paste event interception
- `resources/views/terminal.blade.php` — Paste confirmation modal, Alpine.js clipboard helpers
- `resources/css/index.css` — Hover/transition styles for copy buttons
- `src/Livewire/WebTerminal.php` — Method to return plain-text output for copy-all
- Tests for the new functionality

## UI/UX Notes

- Copy buttons use a clipboard icon (heroicon `clipboard-document`) and switch to checkmark (`clipboard-document-check`) on success
- Paste confirmation modal matches the terminal's dark/light theme
- Modal shows numbered command list with syntax highlighting matching terminal colors
- Copy All button tooltip shows "Copy output" normally and "Copied!" after click
