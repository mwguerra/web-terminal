# Ghostty Terminal Integration Design

**Date:** 2026-03-30
**Branch:** feature/ghostty-terminal
**Status:** Draft

## Overview

Add a new terminal mode to the web-terminal package using [ghostty-web](https://github.com/coder/ghostty-web) — a WASM-powered terminal emulator (xterm.js API-compatible drop-in replacement) that provides a full interactive PTY shell experience in the browser. This mode complements the existing "Classic" command-by-command terminal with a real-time, persistent shell session streamed over WebSocket.

Both modes can be enabled simultaneously with an in-header toggle pill for switching between them. Each mode maintains an independent session.

## Design Decisions

This section documents the reasoning behind each major decision. Kept up to date as the design evolves.

### Why ghostty-web instead of xterm.js?

[ghostty-web](https://github.com/coder/ghostty-web) is a WASM-compiled terminal emulator built from the same parser as the native [Ghostty](https://github.com/ghostty-org/ghostty) desktop terminal. It is designed as a **drop-in xterm.js replacement** — same API (`Terminal`, `FitAddon`, `ITerminalOptions`, `term.buffer`, etc.) but with a battle-tested WASM parser instead of a JavaScript reimplementation.

Advantages over xterm.js:
- Proper grapheme handling for complex scripts (Devanagari, Arabic)
- Full XTPUSHSGR/XTPOPSGR support
- Zero runtime dependencies, ~400KB WASM bundle
- MIT licensed, backed by Coder (enterprise backing)

Since the API is xterm.js-compatible, switching to xterm.js in the future would be a one-line import change.

### Why Ratchet and not Laravel Reverb?

This was initially planned to use Reverb as the primary provider. After analysis, Reverb was dropped because **it is architecturally incompatible with PTY streaming**:

1. **Reverb is pub/sub, not bidirectional streaming.** Reverb implements Laravel Broadcasting — it pushes server events to subscribed clients. Clients **cannot send arbitrary data back** through a Reverb channel. The client-to-server path in Reverb goes through HTTP (Livewire/AJAX), not the WebSocket.

2. **PTY I/O requires raw bidirectional byte streaming.** Every keystroke must travel client→server instantly, and every byte of PTY output must stream server→client in real-time. This is a persistent, bidirectional byte pipe — fundamentally different from pub/sub events.

3. **Latency and overhead.** Even if workarounds existed (e.g., sending keystrokes via Livewire HTTP and output via Reverb), the added latency would make the terminal unusable for interactive work (vim, htop, tab completion).

**Ratchet (cboden/ratchet)** is the correct tool because:
- It provides raw bidirectional WebSocket connections (exactly what PTY streaming needs)
- It's ReactPHP-based, so it runs as a persistent PHP process with an event loop
- It's the most mature PHP WebSocket library
- It runs as an Artisan command (`terminal:serve`), so it has full Laravel app access for auth, config, and credential retrieval
- It's a `suggest` dependency (not `require`), so it only needs to be installed when ghostty mode is used

**Future Reverb support:** If Laravel Reverb ever adds raw WebSocket channel support (beyond pub/sub), it could be added as a provider behind the existing `WebSocketProviderInterface`. The interface is designed for this extensibility.

### Why dual-component architecture instead of extending WebTerminal?

The existing `WebTerminal.php` is already 944 lines. Adding ghostty mode logic (WebSocket lifecycle, PTY bridge, different rendering) would push it well past 1,200 lines with deeply intertwined concerns. The dual-component approach:

- **Clean separation**: Each terminal mode owns its own state, lifecycle, and view
- **No risk to existing functionality**: `WebTerminal.php` is unchanged — zero regression risk
- **Independent testing**: Each component can be tested in isolation
- **Optional loading**: If only classic mode is used, `GhosttyTerminal` is never loaded

The `TerminalContainer` wrapper is intentionally thin — it only manages the toggle pill via Alpine.js.

### Why Alpine.js-only toggle (not Livewire state)?

Nested Livewire components have known edge cases with state hydration, `@entangle`, and DOM diffing. The toggle pill switches which terminal is visible — this is purely a UI concern that doesn't need server state.

Using Alpine.js `x-show` means:
- **Instant switching** — no server roundtrip
- **Both components stay mounted** — sessions persist independently
- **No hydration conflicts** — Livewire manages each nested component independently
- Each nested component gets a unique `wire:key` to prevent DOM diffing issues

### Why Ratchet as `suggest` and not `require`?

Most web-terminal users only need the Classic (Livewire) terminal. Requiring Ratchet for everyone would add an unnecessary dependency. By using `suggest`:
- Classic-only installations stay lightweight
- Ghostty mode users install Ratchet explicitly (`composer require cboden/ratchet`)
- A clear `RuntimeException` tells developers what to install if they enable ghostty without Ratchet

### Why encrypted tokens instead of signed URLs?

The initial design used `URL::signedRoute()` but the Ratchet server runs on a separate port, so Laravel's signed URL validation (which checks the route) doesn't work directly. Instead, we use Laravel's `Encrypter` to create self-contained tokens:

- Token contains: user ID, session ID, expiry timestamp
- Encrypted with the app key — only the same Laravel app can decrypt
- Validated entirely within the Ratchet process (which boots the Laravel app)
- SSH credentials are stored in Laravel Cache (not in the token) and retrieved by session ID

### Why a `useGhosttyTerminal` Gate?

Ghostty mode provides full PTY shell access — no command whitelisting, no sanitization, no rate limiting. In admin panels where some users shouldn't have unrestricted shell access, the Gate provides fine-grained control. If the Gate is not defined, it defaults to allowing authenticated users (same as Classic mode's connect).

### Why independent sessions per mode?

When switching between Classic and Ghostty:
- **Independent**: Each mode has its own connection. Switching doesn't kill the other. Both can be connected simultaneously. This matches how developers use multiple terminal tabs.
- The alternative (shared connection, destructive switch) would lose context on every switch — frustrating for users.

### Why FitAddon for resize?

The Classic terminal has a fixed CSS height. Ghostty mode uses ghostty-web's `FitAddon` to auto-resize the terminal to fill its container, and sends resize events (`{ type: 'resize', cols, rows }`) to the PTY via WebSocket so the shell adjusts its output formatting. This is standard for modern web terminals.

## Architecture

### Component Hierarchy

```
TerminalBuilder (fluent API)
  ├── ->ghosttyTerminal()          # enable ghostty mode
  ├── ->classicTerminal(false)     # disable classic (optional)
  ├── ->defaultMode(TerminalMode::Ghostty)
  ├── ->ghosttyTheme([...])        # ghostty-specific theme/options
  └── ->render()
        ├── [only classic]  → WebTerminal (existing, unchanged)
        ├── [only ghostty]  → GhosttyTerminal (new)
        ├── [both disabled] → throws InvalidArgumentException
        └── [both enabled]  → TerminalContainer
                               ├── Toggle pill (Alpine.js only, no Livewire state)
                               ├── WebTerminal (x-show, wire:key="classic-terminal")
                               └── GhosttyTerminal (x-show, wire:key="ghostty-terminal")
```

### New Components

1. **`TerminalContainer`** — Thin Livewire component wrapping both terminals. The active mode toggle is **Alpine.js state only** (no `@entangle`, no Livewire property) to avoid hydration issues with nested Livewire components. Each nested component gets a unique `wire:key` to prevent DOM diffing conflicts.

2. **`GhosttyTerminal`** — New Livewire component:
   - Generates signed WebSocket URLs for authentication
   - Manages WebSocket lifecycle (connect/disconnect)
   - Passes ghostty-web configuration to the frontend
   - Exposes script execution via WebSocket (writes commands to PTY stdin)
   - Provides copy-all via ghostty-web's buffer API (`term.buffer.active`)
   - Shows info panel overlay

3. **`TerminalPtyBridge`** — Core server-side class for managing PTY sessions over WebSocket. Builds on the existing `session-worker.php` PTY spawning pattern but designed for persistent WebSocket connections rather than Livewire polling:
   - **Local connections**: Spawns PTY process via `proc_open()` with PTY descriptors (same pattern as existing `session-worker.php` lines 82-96, but managed by the WebSocket server process instead of a separate PHP worker)
   - **SSH connections**: Uses phpseclib3's `SSH2::getShell()` for an interactive shell stream — same credentials from `ConnectionConfig`
   - Handles resize events (`{ type: 'resize', cols, rows }`)
   - Manages session lifecycle and cleanup

### WebSocket Bridge

**Why not Reverb:** Laravel Reverb is a pub/sub broadcasting system (server→client events). It is not designed for bidirectional raw byte streaming — clients cannot send arbitrary data to the server through Reverb channels. PTY I/O requires low-latency, bidirectional byte streaming which Reverb cannot provide without significant workarounds.

**Primary provider: Ratchet (raw WebSocket)**

Standalone PHP WebSocket server via `php artisan terminal:serve`:
- Uses `cboden/ratchet` (ReactPHP-based) for raw bidirectional WebSocket connections
- Each WebSocket connection maps to one PTY session
- Client sends keystrokes as raw bytes, server streams PTY output as raw bytes
- Configurable host/port (default: `127.0.0.1:8090`)

**Future provider option:** If Reverb adds raw WebSocket channel support, it can be added as a provider later behind the same `WebSocketProviderInterface`.

**`WebSocketProviderInterface` contract:**
```php
interface WebSocketProviderInterface
{
    public function start(string $host, int $port): void;
    public function stop(): void;
    public function onConnect(ConnectionInterface $conn, string $token): void;
    public function onMessage(ConnectionInterface $conn, string $data): void;
    public function onClose(ConnectionInterface $conn): void;
    public function sendToConnection(string $sessionId, string $data): void;
}
```

**CORS / Origin:** WebSocket connections from the browser to a different port on the same host are not subject to CORS (the `Origin` header is sent but the WebSocket protocol does not enforce same-origin policy). The Ratchet server will validate the `Origin` header against configured allowed origins as an extra security layer, defaulting to the app URL from `config('app.url')`.

**Route registration:**

The Ratchet server runs on its own port — no Laravel HTTP route is needed for the WebSocket connection itself. However, a Laravel route is registered for **generating signed connection tokens**:

```php
// Registered in WebTerminalServiceProvider::boot()
Route::post('terminal/ws-token', [TerminalWebSocketController::class, 'generateToken'])
    ->name('terminal.ws-token')
    ->middleware(['web', 'auth']);
```

The `GhosttyTerminal` Livewire component calls this endpoint (or exposes a `$wire.getWebSocketUrl()` method) to get a signed connection token. The token is then passed as a query parameter to the Ratchet WebSocket URL (`ws://127.0.0.1:8090?token=...`).

**Authentication flow:**
1. Livewire component generates a signed, time-limited token containing: user ID, session ID, connection config reference, and expiry timestamp. Token is signed with `app('encrypter')`.
2. Ghostty-web JS connects to `ws://{ratchet_host}:{ratchet_port}?token={signed_token}`
3. Ratchet server decrypts and validates the token on `onOpen()`. Since `terminal:serve` boots the full Laravel application (it's an Artisan command with access to the app container), it can use `app('encrypter')` for token validation.
4. Session bound to the authenticated user ID extracted from the token.

**SSH credential handoff:**

For SSH connections, the connection config (host, username, password/key) cannot be embedded in the client token. Instead:
1. When the Livewire component generates a token, it stores the `ConnectionConfig` in Laravel's cache (`Cache::put("terminal-pty:{$sessionId}", $config, $ttl)`) with the same TTL as the token.
2. The token contains only the `sessionId` reference.
3. When Ratchet receives the connection, it retrieves the config from cache using the session ID.
4. Cache entry is deleted after retrieval (one-time use).

This ensures credentials never leave the server and are never exposed in URLs or tokens.

**Provider detection:**
```php
// config/web-terminal.php
'ghostty' => [
    'enabled' => false,
    'websocket_provider' => 'ratchet',  // 'ratchet' (only option currently)
    'ratchet_host' => '127.0.0.1',
    'ratchet_port' => 8090,
],
```

### Security Model

**Ghostty mode provides unrestricted shell access.** Unlike Classic mode which has `CommandValidator`, `CommandSanitizer`, and `RateLimiter`, Ghostty mode opens a full interactive PTY — these layers are intentionally bypassed.

**Security controls:**

1. **Explicit opt-in required**: Ghostty mode is disabled by default. Developer must call `->ghosttyTerminal()` explicitly.
2. **Authorization gate**: The `GhosttyTerminal` Livewire component checks a `useGhosttyTerminal` Gate before rendering. Developers can define this gate to restrict which users get access:
   ```php
   Gate::define('useGhosttyTerminal', function (User $user) {
       return $user->is_admin;
   });
   ```
   If the gate is not defined, access defaults to the same check as Classic mode's `connect()` (authenticated user).
3. **Shell configuration**: The PTY shell can be configured via the fluent API (`->shell('/bin/rbash')`) to use a restricted shell if desired.
4. **Signed URLs**: WebSocket connections require valid signed URLs preventing unauthorized direct access to the WebSocket endpoint.

### Orphan PTY Process Cleanup

PTY processes spawned by the WebSocket server must be cleaned up in all scenarios:

1. **Normal disconnect**: PTY killed after configurable grace period (default 30s).
2. **WebSocket server crash/restart**: On server startup, `terminal:serve` ensures `storage_path('web-terminal/')` exists, then scans for orphaned PTY processes by checking a PID registry file (`storage_path('web-terminal/pty-sessions.json')`). Stale PIDs are killed. This directory is the same `storage/web-terminal/` used by the existing `FileSessionManager`.
3. **Periodic cleanup**: The Ratchet server runs a 60-second timer loop to check for:
   - PTY processes whose WebSocket connections have been closed past the grace period
   - PTY processes that have exceeded max session lifetime (configurable, default 1 hour)

## Enums

### TerminalMode

```php
namespace MWGuerra\WebTerminal\Enums;

enum TerminalMode: string
{
    case Classic = 'classic';
    case Ghostty = 'ghostty';
}
```

## Fluent API

```php
// Ghostty only
Terminal::make()
    ->connection(ConnectionType::SSH, [...])
    ->ghosttyTerminal()
    ->classicTerminal(false)
    ->height('500px')
    ->render();

// Both modes, ghostty default
Terminal::make()
    ->connection(ConnectionType::Local)
    ->ghosttyTerminal()
    ->defaultMode(TerminalMode::Ghostty)
    ->render();

// Classic only (unchanged, current behavior)
Terminal::make()
    ->connection(ConnectionType::Local)
    ->allowedCommands([...])
    ->render();

// Ghostty with theme
Terminal::make()
    ->connection(ConnectionType::SSH, [...])
    ->ghosttyTerminal()
    ->ghosttyTheme([
        'background' => '#1a1b26',
        'foreground' => '#a9b1d6',
        'fontSize' => 14,
        'fontFamily' => 'JetBrains Mono, monospace',
        'cursorStyle' => 'bar',
        'scrollback' => 5000,
    ])
    ->render();
```

**New methods on `TerminalBuilder`:**
- `ghosttyTerminal(bool $enabled = true)` — Enable ghostty mode
- `classicTerminal(bool $enabled = true)` — Enable/disable classic mode (default: true)
- `defaultMode(TerminalMode $mode = TerminalMode::Classic)` — Starting mode when both are enabled
- `ghosttyTheme(array $theme)` — Theme/options passed to ghostty-web's `ITerminalOptions`

**Edge cases:**
- Both disabled (`classicTerminal(false)` without `ghosttyTerminal()`): throws `InvalidArgumentException("At least one terminal mode must be enabled")`
- `defaultMode()` set to a disabled mode: throws `InvalidArgumentException`
- `ghosttyTerminal()` without Ratchet installed: throws `RuntimeException` with install instructions

## UI Design

### Toggle Pill

Placed in the header bar between the info button and connect/disconnect button. Only visible when both modes are enabled.

- **Classic active**: Indigo highlight (`rgba(99, 102, 241, 0.3)`)
- **Ghostty active**: Purple highlight (`rgba(168, 85, 247, 0.3)`)
- Switches via Alpine.js `x-show` — no Livewire roundtrip, instant
- Toggle state is pure Alpine.js (`x-data="{ activeMode: '{{ $defaultMode }}' }"`) — no `@entangle`

### Ghostty Terminal View

- **Header**: Shared with Classic mode — same window controls, title, action buttons
- **Content area**: ghostty-web canvas fills from header to bottom edge
- **No input bar**: Input goes directly to the PTY via the ghostty-web canvas
- **No interactive controls bar**: ghostty-web handles all keyboard input natively
- **No paste modal**: ghostty-web handles paste natively

### Action Buttons in Ghostty Mode

| Button | Behavior |
|--------|----------|
| Scripts | Opens dropdown, sends commands to PTY stdin via WebSocket |
| Copy | Extracts full scrollback buffer via ghostty-web's `term.buffer.active` API |
| Info | Shows info panel overlay positioned absolutely over the canvas |
| Connect/Disconnect | Manages WebSocket lifecycle |

## Feature Mapping

| Feature | Classic Mode | Ghostty Mode |
|---------|-------------|--------------|
| Connect/Disconnect | Livewire method | WebSocket lifecycle |
| Scripts | Execute via Livewire | Write commands to PTY via WebSocket |
| Copy output | Per-block + copy all | Full terminal buffer copy (no per-block) |
| Info panel | Overlay on output | Overlay on canvas |
| Command history | Livewire-managed | Native shell history (bash) |
| Paste | Multi-line modal | Native ghostty-web paste |
| Interactive controls | Arrow/function key UI | Not needed (native keyboard) |
| ANSI colors | Server-side AnsiToHtml | Native WASM rendering |
| Resize | Fixed CSS height | FitAddon auto-resize + resize events to PTY |
| Inactivity timeout | Livewire-managed | WebSocket ping/pong + server timer |

## Assets

ghostty-web distributed via NPM + Vite build:

- Add `ghostty-web` to `package.json` dependencies
- Create `resources/js/ghostty-terminal.js` — initializes WASM, creates Terminal, manages WebSocket connection
- Bundle via Vite into `resources/dist/ghostty-terminal.js`
- Register as Filament JS asset alongside existing CSS asset
- **WASM binary**: ghostty-web bundles the WASM inline (base64) in its JS distribution, so no separate WASM file needs to be published or served. If a future version requires a separate WASM file, it will be published to the consumer's `public/vendor/web-terminal/` directory.
- **Lazy loading**: The ghostty JS bundle is only loaded when ghostty mode is enabled (registered conditionally in the service provider based on config), so classic-only users pay no penalty.

## Error Handling

**Missing dependencies:**
- If `ghosttyTerminal()` called but `cboden/ratchet` not installed: throw `RuntimeException("Ghostty terminal requires cboden/ratchet. Install it: composer require cboden/ratchet")` at render time.

**WebSocket connection failures:**
- Retry with exponential backoff (3 attempts: 1s, 2s, 4s)
- After retries exhausted: show inline error in canvas area with message and instructions to check that `php artisan terminal:serve` is running

**Signed URL expiration:**
- URLs valid for 5 minutes (configurable via `signed_url_ttl`)
- On reconnect after expiry: Livewire generates a new signed URL via a `$wire.getWebSocketUrl()` call, then reconnects

**PTY session reconnection:**
- The 30s grace period means the PTY process stays alive after WebSocket close
- On reconnect within the grace period, the same PTY session is resumed (client reconnects to existing PTY output stream)
- Terminal scrollback buffer is NOT preserved across reconnections (ghostty-web's buffer is client-side; the PTY session continues but the client loses its buffer). This is acceptable — the shell session state persists, just not the visual history.

**PTY cleanup:**
- On WebSocket close: PTY process terminated after grace period
- On page unload (`beforeunload`): WebSocket close sent, immediate PTY cleanup
- On server startup: orphan scan via PID registry (see "Orphan PTY Process Cleanup" above)

**Dual-mode edge cases:**
- Switching modes while a script runs: script continues in original mode, modes are independent
- Both connected simultaneously: independent PTY/Livewire sessions, no conflicts
- Browser refresh: Classic can reconnect (if `keepConnectedOnNavigate`), Ghostty starts fresh (WebSocket doesn't survive page reload)

## Configuration

New keys in `config/web-terminal.php`:

```php
'ghostty' => [
    'enabled' => false,
    'websocket_provider' => 'ratchet',
    'ratchet_host' => '127.0.0.1',
    'ratchet_port' => 8090,
    'pty_grace_period' => 30,           // seconds before killing PTY after disconnect
    'max_session_lifetime' => 3600,     // max PTY session duration (1 hour)
    'signed_url_ttl' => 300,            // signed URL lifetime in seconds
    'theme' => [                        // default theme (overridden by ghosttyTheme())
        'background' => '#1a1b26',
        'foreground' => '#a9b1d6',
        'fontSize' => 14,
    ],
],
```

## Upgrade Path

For existing package users:
- **No breaking changes**: All new config keys are additive under the `ghostty` key
- **No migration needed**: Ghostty mode is disabled by default
- **Published configs**: Existing published `web-terminal.php` configs will work without changes. To use ghostty mode, users add the `ghostty` key manually or re-publish the config.

## Testing Strategy

### Unit Tests (Pest)

- **TerminalBuilder**: `ghosttyTerminal()`, `classicTerminal()`, `defaultMode()`, `ghosttyTheme()` fluent methods; correct component routing for all three render paths; edge cases (both disabled, mismatched default mode)
- **TerminalPtyBridge**: PTY spawn for local, SSH shell stream creation, resize handling, session cleanup, PID registry
- **GhosttyTerminal Livewire component**: `connect()` generates signed URL, `disconnect()` cleans up, locked properties, authorization gate check
- **TerminalMode enum**: value mapping

### Integration Tests (Pest)

- **TerminalContainer**: rendering with both modes, single mode, default mode selection, `wire:key` correctness
- **WebSocket auth**: signed URL generation, expired URL rejection
- **Note**: WebSocket integration tests mock the Ratchet server connection. Full WebSocket tests are deferred to E2E.

### E2E Tests (Playwright via testapp_f5)

- Create test page with both modes enabled
- Toggle pill switches between modes
- Ghostty canvas renders and accepts keyboard input
- Connect/disconnect lifecycle for ghostty mode
- Scripts execution in ghostty mode
- Copy-all extracts buffer content
- Info panel overlay on ghostty canvas
- **Prerequisite**: `php artisan terminal:serve` must be running during E2E tests

## New Files

```
src/
  Enums/
    TerminalMode.php               # Classic/Ghostty enum
  Livewire/
    GhosttyTerminal.php            # New Livewire component
    TerminalContainer.php          # Wrapper for dual-mode
  WebSocket/
    WebSocketProviderInterface.php # Provider contract
    TerminalPtyBridge.php          # Core PTY bridge (shared)
    RatchetProvider.php            # Ratchet WebSocket provider
    RatchetServer.php              # Standalone Ratchet server
    PtySessionRegistry.php         # PID registry for orphan cleanup
  Http/
    Controllers/
      TerminalWebSocketController.php  # Token generation endpoint (POST)
  Console/
    TerminalServeCommand.php       # php artisan terminal:serve
resources/
  views/
    ghostty-terminal.blade.php     # Ghostty terminal view
    terminal-container.blade.php   # Container with toggle pill
    partials/
      toggle-pill.blade.php        # Mode switcher partial
  js/
    ghostty-terminal.js            # ghostty-web init + WebSocket
  dist/
    ghostty-terminal.js            # Built JS bundle
config/
  web-terminal.php                 # Extended with ghostty section
```

## Dependencies

**Required (when ghostty mode enabled):**
- `ghostty-web` npm package (v0.4.0+) — xterm.js API-compatible terminal emulator
- `cboden/ratchet` composer package — PHP WebSocket server (added to `suggest` in `composer.json`, not `require`, since it's only needed when ghostty mode is enabled)

**Composer setup:**
```json
{
    "suggest": {
        "cboden/ratchet": "Required for Ghostty terminal mode (WebSocket PTY bridge)"
    }
}
```

Runtime check: if ghostty mode is enabled but Ratchet is not installed, throw `RuntimeException` with install instructions.

**Note:** ghostty-web is a drop-in xterm.js replacement. It exports `Terminal`, `FitAddon`, `init()` and uses the same `ITerminalOptions` interface. The `FitAddon` handles auto-resize, and `term.buffer.active` provides scrollback buffer access for copy operations. Required API surface: `init()`, `new Terminal(options)`, `term.open(el)`, `term.write(data)`, `term.onData(cb)`, `term.onResize(cb)`, `term.dispose()`, `FitAddon.fit()`, `FitAddon.observeResize()`, `term.buffer.active`.
