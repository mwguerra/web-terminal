# Ghostty Terminal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a ghostty-web terminal mode to the web-terminal package with WebSocket PTY streaming, dual-mode toggle, and full feature parity.

**Architecture:** Dual Livewire component design — `GhosttyTerminal` (new) alongside `WebTerminal` (unchanged), wrapped by `TerminalContainer` when both are enabled. Ratchet WebSocket server bridges ghostty-web to a PTY process. See `docs/superpowers/specs/2026-03-30-ghostty-terminal-design.md` for full spec.

**Tech Stack:** PHP 8.2+, Laravel 12/13, Livewire 4, Alpine.js, ghostty-web (npm), cboden/ratchet, phpseclib3

**Spec:** `docs/superpowers/specs/2026-03-30-ghostty-terminal-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `src/Enums/TerminalMode.php` | Classic/Ghostty enum with labels |
| `src/WebSocket/WebSocketProviderInterface.php` | Contract for WebSocket providers |
| `src/WebSocket/TerminalPtyBridge.php` | Manages PTY process lifecycle (local + SSH) |
| `src/WebSocket/PtySessionRegistry.php` | PID registry for orphan cleanup |
| `src/WebSocket/RatchetProvider.php` | Ratchet implementation of WebSocket provider |
| `src/WebSocket/RatchetServer.php` | Ratchet IoServer + WsServer setup |
| `src/Console/Commands/TerminalServeCommand.php` | `php artisan terminal:serve` command |
| `src/Http/Controllers/TerminalWebSocketController.php` | Token generation endpoint |
| `src/Livewire/GhosttyTerminal.php` | Livewire component for ghostty mode |
| `src/Livewire/TerminalContainer.php` | Wrapper with toggle pill for dual-mode |
| `resources/views/ghostty-terminal.blade.php` | Ghostty terminal Blade view |
| `resources/views/terminal-container.blade.php` | Container Blade view |
| `resources/views/partials/toggle-pill.blade.php` | Mode switcher partial |
| `resources/js/ghostty-terminal.js` | ghostty-web init, WebSocket client, FitAddon |
| `tests/Unit/Enums/TerminalModeTest.php` | Enum tests |
| `tests/Unit/WebSocket/TerminalPtyBridgeTest.php` | PTY bridge tests |
| `tests/Unit/WebSocket/PtySessionRegistryTest.php` | PID registry tests |
| `tests/Unit/WebSocket/RatchetProviderTest.php` | Ratchet provider tests |
| `tests/Unit/Livewire/GhosttyTerminalTest.php` | Ghostty component tests |
| `tests/Unit/Livewire/TerminalContainerTest.php` | Container component tests |
| `tests/Unit/Livewire/TerminalBuilderGhosttyTest.php` | Builder ghostty method tests |

### Modified Files

| File | Changes |
|------|---------|
| `src/Livewire/TerminalBuilder.php` | Add `ghosttyTerminal()`, `classicTerminal()`, `defaultMode()`, `ghosttyTheme()`, update `render()` and `toHtml()` |
| `src/WebTerminalServiceProvider.php` | Register new Livewire components, route, command, conditional JS asset |
| `config/web-terminal.php` | Add `ghostty` config section |
| `composer.json` | Add `cboden/ratchet` to `suggest` |
| `package.json` | Add `ghostty-web` dependency, add Vite/esbuild build script for JS |
| `resources/css/index.css` | Add toggle pill and ghostty overlay styles |

---

## Task 1: TerminalMode Enum

**Files:**
- Create: `src/Enums/TerminalMode.php`
- Test: `tests/Unit/Enums/TerminalModeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalMode;

describe('TerminalMode', function () {
    it('has classic and ghostty cases', function () {
        expect(TerminalMode::cases())->toHaveCount(2);
        expect(TerminalMode::Classic->value)->toBe('classic');
        expect(TerminalMode::Ghostty->value)->toBe('ghostty');
    });

    it('returns labels', function () {
        expect(TerminalMode::Classic->label())->toBe('Classic');
        expect(TerminalMode::Ghostty->label())->toBe('Ghostty');
    });

    it('returns descriptions', function () {
        expect(TerminalMode::Classic->description())->toBeString()->not->toBeEmpty();
        expect(TerminalMode::Ghostty->description())->toBeString()->not->toBeEmpty();
    });

    it('provides options array', function () {
        $options = TerminalMode::options();
        expect($options)->toBeArray()->toHaveCount(2);
        expect($options['classic'])->toBe('Classic');
        expect($options['ghostty'])->toBe('Ghostty');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Enums/TerminalModeTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write the enum**

Create `src/Enums/TerminalMode.php` following the exact pattern of `src/Enums/ConnectionType.php`:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\Enums;

enum TerminalMode: string
{
    case Classic = 'classic';
    case Ghostty = 'ghostty';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Ghostty => 'Ghostty',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Command-by-command terminal via Livewire',
            self::Ghostty => 'Full interactive PTY terminal via WebSocket',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases())
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Enums/TerminalModeTest.php`
Expected: PASS (all 4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Enums/TerminalMode.php tests/Unit/Enums/TerminalModeTest.php
git commit -m "feat: add TerminalMode enum (Classic/Ghostty)"
```

---

## Task 2: Extend TerminalBuilder with Ghostty Methods

**Files:**
- Modify: `src/Livewire/TerminalBuilder.php`
- Test: `tests/Unit/Livewire/TerminalBuilderGhosttyTest.php`

**Reference:** Read the existing `TerminalBuilder.php` and `tests/Unit/Livewire/TerminalBuilderTest.php` for patterns.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Livewire/TerminalBuilderGhosttyTest.php`:

```php
<?php
declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalMode;
use MWGuerra\WebTerminal\Livewire\TerminalBuilder;

describe('TerminalBuilder Ghostty Methods', function () {
    describe('ghosttyTerminal()', function () {
        it('enables ghostty mode', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $params = $builder->getParameters();
            expect($params['ghosttyEnabled'])->toBeTrue();
        });

        it('disables ghostty mode explicitly', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal(false);
            $params = $builder->getParameters();
            expect($params['ghosttyEnabled'])->toBeFalse();
        });

        it('is disabled by default', function () {
            $builder = new TerminalBuilder;
            $builder->local();
            $params = $builder->getParameters();
            expect($params['ghosttyEnabled'])->toBeFalse();
        });
    });

    describe('classicTerminal()', function () {
        it('is enabled by default', function () {
            $builder = new TerminalBuilder;
            $builder->local();
            $params = $builder->getParameters();
            expect($params['classicEnabled'])->toBeTrue();
        });

        it('can be disabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal()->classicTerminal(false);
            $params = $builder->getParameters();
            expect($params['classicEnabled'])->toBeFalse();
        });
    });

    describe('defaultMode()', function () {
        it('defaults to classic', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $params = $builder->getParameters();
            expect($params['defaultMode'])->toBe('classic');
        });

        it('can be set to ghostty', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal()->defaultMode(TerminalMode::Ghostty);
            $params = $builder->getParameters();
            expect($params['defaultMode'])->toBe('ghostty');
        });
    });

    describe('ghosttyTheme()', function () {
        it('stores theme options', function () {
            $theme = ['background' => '#1a1b26', 'fontSize' => 14];
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal()->ghosttyTheme($theme);
            $params = $builder->getParameters();
            expect($params['ghosttyTheme'])->toBe($theme);
        });

        it('defaults to empty array', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $params = $builder->getParameters();
            expect($params['ghosttyTheme'])->toBe([]);
        });
    });

    describe('validation', function () {
        it('throws when both modes disabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->classicTerminal(false);
            expect(fn () => $builder->render())->toThrow(\InvalidArgumentException::class, 'At least one terminal mode must be enabled');
        });

        it('throws when defaultMode set to disabled mode', function () {
            $builder = new TerminalBuilder;
            $builder->local()->defaultMode(TerminalMode::Ghostty);
            expect(fn () => $builder->render())->toThrow(\InvalidArgumentException::class);
        });
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalBuilderGhosttyTest.php`
Expected: FAIL — methods not found

- [ ] **Step 3: Add ghostty properties and methods to TerminalBuilder**

Open `src/Livewire/TerminalBuilder.php`. Add new properties alongside existing ones:

```php
protected bool $ghosttyEnabled = false;
protected bool $classicEnabled = true;
protected TerminalMode $defaultMode = TerminalMode::Classic;
protected array $ghosttyTheme = [];
```

Add the fluent methods (following existing patterns like `allowAllCommands()`):

```php
public function ghosttyTerminal(bool $enabled = true): static
{
    $this->ghosttyEnabled = $enabled;
    return $this;
}

public function classicTerminal(bool $enabled = true): static
{
    $this->classicEnabled = $enabled;
    return $this;
}

public function defaultMode(TerminalMode $mode = TerminalMode::Classic): static
{
    $this->defaultMode = $mode;
    return $this;
}

public function ghosttyTheme(array $theme): static
{
    $this->ghosttyTheme = $theme;
    return $this;
}
```

Add the new keys to `getParameters()`. **Important:** Only include ghostty-related params when ghostty is actually enabled, to avoid passing unexpected properties to the classic `WebTerminal` component:

```php
// In getParameters(), add conditionally:
if ($this->ghosttyEnabled) {
    $params['ghosttyEnabled'] = true;
    $params['ghosttyTheme'] = $this->ghosttyTheme;
}
if (! $this->classicEnabled) {
    $params['classicEnabled'] = false;
}
$params['defaultMode'] = $this->defaultMode->value;
```

Add validation to `render()` before the existing rendering logic:

```php
if (! $this->classicEnabled && ! $this->ghosttyEnabled) {
    throw new \InvalidArgumentException('At least one terminal mode must be enabled');
}

if ($this->defaultMode === TerminalMode::Ghostty && ! $this->ghosttyEnabled) {
    throw new \InvalidArgumentException('Cannot set default mode to Ghostty when Ghostty is disabled');
}

if ($this->defaultMode === TerminalMode::Classic && ! $this->classicEnabled) {
    throw new \InvalidArgumentException('Cannot set default mode to Classic when Classic is disabled');
}
```

**Important**: Don't change the actual rendering logic yet (Task 8 handles routing to the correct component). Just add the properties, methods, validation, and parameter output.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalBuilderGhosttyTest.php`
Expected: PASS (all tests)

- [ ] **Step 5: Run existing TerminalBuilder tests to ensure no regressions**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalBuilderTest.php`
Expected: PASS (all existing tests still pass)

- [ ] **Step 6: Commit**

```bash
git add src/Livewire/TerminalBuilder.php tests/Unit/Livewire/TerminalBuilderGhosttyTest.php
git commit -m "feat: add ghostty fluent methods to TerminalBuilder"
```

---

## Task 3: Configuration and Dependencies

**Files:**
- Modify: `config/web-terminal.php`
- Modify: `composer.json`
- Modify: `package.json`

- [ ] **Step 1: Add ghostty config section**

Open `config/web-terminal.php`. Add at the end, before the closing `];`:

```php
/*
|--------------------------------------------------------------------------
| Ghostty Terminal Mode
|--------------------------------------------------------------------------
|
| Configuration for the ghostty-web terminal mode. This mode provides a
| full interactive PTY shell via WebSocket, using the ghostty-web WASM
| terminal emulator. Requires cboden/ratchet to be installed.
|
| See: docs/superpowers/specs/2026-03-30-ghostty-terminal-design.md
|
*/

'ghostty' => [
    'enabled' => env('WEB_TERMINAL_GHOSTTY_ENABLED', false),
    'websocket_provider' => 'ratchet',
    'ratchet_host' => env('WEB_TERMINAL_RATCHET_HOST', '127.0.0.1'),
    'ratchet_port' => env('WEB_TERMINAL_RATCHET_PORT', 8090),
    'pty_grace_period' => 30,
    'max_session_lifetime' => 3600,
    'signed_url_ttl' => 300,
    'allowed_origins' => [env('APP_URL', 'http://localhost')],
    'theme' => [
        'background' => '#1a1b26',
        'foreground' => '#a9b1d6',
        'fontSize' => 14,
    ],
],
```

- [ ] **Step 2: Add ratchet to composer.json suggest**

In `composer.json`, add to the `suggest` section:

```json
"suggest": {
    "filament/filament": "Required for Filament admin panel integration (^5.0)",
    "cboden/ratchet": "Required for Ghostty terminal mode - WebSocket PTY bridge (^0.4)"
}
```

- [ ] **Step 3: Add ghostty-web to package.json**

Update `package.json` to add ghostty-web and a JS build script:

```json
{
    "private": true,
    "scripts": {
        "build": "npx @tailwindcss/cli -i resources/css/index.css -o resources/dist/web-terminal.css --minify",
        "build:js": "npx esbuild resources/js/ghostty-terminal.js --bundle --minify --format=iife --outfile=resources/dist/ghostty-terminal.js",
        "build:all": "npm run build && npm run build:js"
    },
    "dependencies": {
        "ghostty-web": "^0.4.0"
    },
    "devDependencies": {
        "@tailwindcss/cli": "^4.1.0",
        "esbuild": "^0.25.0",
        "tailwindcss": "^4.1.0"
    }
}
```

- [ ] **Step 4: Install npm dependencies**

Run: `cd /home/guerra/projects/web-terminal && npm install`
Expected: ghostty-web and esbuild installed successfully

- [ ] **Step 5: Commit**

```bash
git add config/web-terminal.php composer.json package.json package-lock.json
git commit -m "feat: add ghostty config, ratchet suggest dep, ghostty-web npm dep"
```

---

## Task 4: PtySessionRegistry (PID tracking for orphan cleanup)

**Files:**
- Create: `src/WebSocket/PtySessionRegistry.php`
- Test: `tests/Unit/WebSocket/PtySessionRegistryTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
declare(strict_types=1);

use MWGuerra\WebTerminal\WebSocket\PtySessionRegistry;

beforeEach(function () {
    $this->registryPath = sys_get_temp_dir() . '/web-terminal-test-' . uniqid() . '/pty-sessions.json';
    $this->registry = new PtySessionRegistry(dirname($this->registryPath));
});

afterEach(function () {
    if (file_exists($this->registryPath)) {
        unlink($this->registryPath);
    }
    $dir = dirname($this->registryPath);
    if (is_dir($dir)) {
        rmdir($dir);
    }
});

describe('PtySessionRegistry', function () {
    it('registers a session with PID', function () {
        $this->registry->register('session-1', 12345, 1);
        $sessions = $this->registry->all();
        expect($sessions)->toHaveKey('session-1');
        expect($sessions['session-1']['pid'])->toBe(12345);
        expect($sessions['session-1']['userId'])->toBe(1);
    });

    it('unregisters a session', function () {
        $this->registry->register('session-1', 12345, 1);
        $this->registry->unregister('session-1');
        expect($this->registry->all())->not->toHaveKey('session-1');
    });

    it('finds a session by ID', function () {
        $this->registry->register('session-1', 12345, 1);
        $session = $this->registry->find('session-1');
        expect($session)->not->toBeNull();
        expect($session['pid'])->toBe(12345);
    });

    it('returns null for unknown session', function () {
        expect($this->registry->find('nonexistent'))->toBeNull();
    });

    it('creates directory if it does not exist', function () {
        $dir = dirname($this->registryPath);
        expect(is_dir($dir))->toBeFalse();
        $this->registry->register('session-1', 12345, 1);
        expect(is_dir($dir))->toBeTrue();
    });

    it('records created_at timestamp', function () {
        $before = time();
        $this->registry->register('session-1', 12345, 1);
        $session = $this->registry->find('session-1');
        expect($session['createdAt'])->toBeGreaterThanOrEqual($before);
    });

    it('cleans up stale sessions', function () {
        // Register with a past timestamp by writing directly
        $dir = dirname($this->registryPath);
        if (! is_dir($dir)) { mkdir($dir, 0755, true); }
        file_put_contents($this->registryPath, json_encode([
            'old-session' => ['pid' => 99999, 'userId' => 1, 'createdAt' => time() - 7200],
            'new-session' => ['pid' => 88888, 'userId' => 1, 'createdAt' => time()],
        ]));

        $stale = $this->registry->cleanupStale(3600); // 1 hour max
        expect($stale)->toHaveKey('old-session');
        expect($stale)->not->toHaveKey('new-session');
        expect($this->registry->find('old-session'))->toBeNull();
        expect($this->registry->find('new-session'))->not->toBeNull();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/WebSocket/PtySessionRegistryTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement PtySessionRegistry**

Create `src/WebSocket/PtySessionRegistry.php`:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

class PtySessionRegistry
{
    private string $registryPath;

    public function __construct(string $storagePath)
    {
        $this->registryPath = rtrim($storagePath, '/') . '/pty-sessions.json';
    }

    public function register(string $sessionId, int $pid, int $userId): void
    {
        $sessions = $this->all();
        $sessions[$sessionId] = [
            'pid' => $pid,
            'userId' => $userId,
            'createdAt' => time(),
        ];
        $this->save($sessions);
    }

    public function unregister(string $sessionId): void
    {
        $sessions = $this->all();
        unset($sessions[$sessionId]);
        $this->save($sessions);
    }

    public function find(string $sessionId): ?array
    {
        return $this->all()[$sessionId] ?? null;
    }

    public function all(): array
    {
        if (! file_exists($this->registryPath)) {
            return [];
        }

        $content = file_get_contents($this->registryPath);
        if ($content === false || $content === '') {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    public function cleanupStale(int $maxLifetimeSeconds): array
    {
        $sessions = $this->all();
        $stale = [];
        $now = time();

        foreach ($sessions as $sessionId => $session) {
            if ($now - $session['createdAt'] > $maxLifetimeSeconds) {
                $stale[$sessionId] = $session;
                unset($sessions[$sessionId]);
            }
        }

        $this->save($sessions);

        return $stale;
    }

    private function save(array $sessions): void
    {
        $dir = dirname($this->registryPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->registryPath,
            json_encode($sessions, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/WebSocket/PtySessionRegistryTest.php`
Expected: PASS (all 6 tests)

- [ ] **Step 5: Commit**

```bash
git add src/WebSocket/PtySessionRegistry.php tests/Unit/WebSocket/PtySessionRegistryTest.php
git commit -m "feat: add PtySessionRegistry for orphan process cleanup"
```

---

## Task 5: WebSocketProviderInterface and TerminalPtyBridge

**Files:**
- Create: `src/WebSocket/WebSocketProviderInterface.php`
- Create: `src/WebSocket/TerminalPtyBridge.php`
- Test: `tests/Unit/WebSocket/TerminalPtyBridgeTest.php`

- [ ] **Step 1: Create the WebSocketProviderInterface**

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

interface WebSocketProviderInterface
{
    public function start(string $host, int $port): void;

    public function stop(): void;

    public function sendToConnection(string $sessionId, string $data): void;
}
```

The `sendToConnection` method is the key extensibility point — it allows sending PTY output to a specific client session regardless of transport. `start`/`stop` manage the server lifecycle. The actual connection/message handling is done inside each provider via its transport's native interface (e.g., Ratchet's `MessageComponentInterface`).

- [ ] **Step 2: Write failing tests for TerminalPtyBridge**

Create `tests/Unit/WebSocket/TerminalPtyBridgeTest.php`:

```php
<?php
declare(strict_types=1);

use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\WebSocket\PtySessionRegistry;
use MWGuerra\WebTerminal\WebSocket\TerminalPtyBridge;

describe('TerminalPtyBridge', function () {
    describe('local connection', function () {
        it('creates a bridge from local config', function () {
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry(sys_get_temp_dir() . '/test-' . uniqid());
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            expect($bridge)->toBeInstanceOf(TerminalPtyBridge::class);
            expect($bridge->getSessionId())->toBe('test-session');
            expect($bridge->isRunning())->toBeFalse();
        });

        it('starts a PTY process', function () {
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry(sys_get_temp_dir() . '/test-' . uniqid());
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            $bridge->start('/bin/sh');
            expect($bridge->isRunning())->toBeTrue();
            $bridge->terminate();
        });

        it('reads output from PTY', function () {
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry(sys_get_temp_dir() . '/test-' . uniqid());
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            $bridge->start('/bin/sh');
            $bridge->write("echo hello-test-output\n");
            usleep(100000); // 100ms for process to respond
            $output = $bridge->read();
            expect($output)->toContain('hello-test-output');
            $bridge->terminate();
        });

        it('terminates the PTY process', function () {
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry(sys_get_temp_dir() . '/test-' . uniqid());
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            $bridge->start('/bin/sh');
            expect($bridge->isRunning())->toBeTrue();
            $bridge->terminate();
            expect($bridge->isRunning())->toBeFalse();
        });

        it('registers PID in session registry on start', function () {
            $registryPath = sys_get_temp_dir() . '/test-' . uniqid();
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry($registryPath);
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            $bridge->start('/bin/sh');
            $session = $registry->find('test-session');
            expect($session)->not->toBeNull();
            expect($session['pid'])->toBeInt()->toBeGreaterThan(0);
            $bridge->terminate();
        });

        it('unregisters from registry on terminate', function () {
            $registryPath = sys_get_temp_dir() . '/test-' . uniqid();
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry($registryPath);
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            $bridge->start('/bin/sh');
            $bridge->terminate();
            expect($registry->find('test-session'))->toBeNull();
        });
    });

    describe('resize', function () {
        it('accepts resize without error', function () {
            $config = ConnectionConfig::local(timeout: 10);
            $registry = new PtySessionRegistry(sys_get_temp_dir() . '/test-' . uniqid());
            $bridge = new TerminalPtyBridge($config, 'test-session', 1, $registry);
            $bridge->start('/bin/sh');
            // resize sends SIGWINCH — should not throw
            $bridge->resize(120, 40);
            expect(true)->toBeTrue();
            $bridge->terminate();
        });
    });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/WebSocket/TerminalPtyBridgeTest.php`
Expected: FAIL — class not found

- [ ] **Step 4: Implement TerminalPtyBridge**

Create `src/WebSocket/TerminalPtyBridge.php`. Reference the existing PTY pattern from `src/Sessions/session-worker.php` (lines 82-96 for `proc_open`, lines 110-160 for I/O loop):

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;

class TerminalPtyBridge
{
    private string $sessionId;
    private int $userId;
    private ConnectionConfig $config;
    private PtySessionRegistry $registry;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private ?object $sshShell = null;

    public function __construct(
        ConnectionConfig $config,
        string $sessionId,
        int $userId,
        PtySessionRegistry $registry,
    ) {
        $this->config = $config;
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->registry = $registry;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function start(string $shell = '/bin/bash'): void
    {
        if ($this->config->type === ConnectionType::SSH) {
            $this->startSsh();
            return;
        }

        $this->startLocal($shell);
    }

    private function startLocal(string $shell): void
    {
        $descriptors = [
            0 => ['pty'],
            1 => ['pty'],
            2 => ['pty'],
        ];

        $env = array_merge(getenv() ?: [], $this->config->environment, [
            'TERM' => 'xterm-256color',
            'XDEBUG_MODE' => 'off',
        ]);

        $this->process = proc_open(
            $shell,
            $descriptors,
            $this->pipes,
            $this->config->workingDirectory,
            $env
        );

        if (! is_resource($this->process)) {
            throw new \RuntimeException("Failed to start PTY process: {$shell}");
        }

        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        $status = proc_get_status($this->process);
        $this->registry->register($this->sessionId, $status['pid'], $this->userId);
    }

    private function startSsh(): void
    {
        $ssh = new \phpseclib3\Net\SSH2(
            $this->config->host,
            $this->config->port ?? 22,
            $this->config->timeout
        );

        if ($this->config->privateKey !== null) {
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(
                $this->config->privateKey,
                $this->config->passphrase ?? ''
            );
            if (! $ssh->login($this->config->username, $key)) {
                throw new \RuntimeException('SSH key authentication failed');
            }
        } else {
            if (! $ssh->login($this->config->username, $this->config->password)) {
                throw new \RuntimeException('SSH password authentication failed');
            }
        }

        // Set timeout to 0 for non-blocking reads (critical for ReactPHP event loop)
        $ssh->setTimeout(0);
        $ssh->enablePTY();
        $ssh->exec('');
        $this->sshShell = $ssh;
        // SSH sessions use pid -1 (sentinel) since there's no local process
        $this->registry->register($this->sessionId, -1, $this->userId);
    }

    public function write(string $data): void
    {
        if ($this->sshShell !== null) {
            $this->sshShell->write($data);
            return;
        }

        if (isset($this->pipes[0]) && is_resource($this->pipes[0])) {
            fwrite($this->pipes[0], $data);
        }
    }

    public function read(): string
    {
        if ($this->sshShell !== null) {
            // With setTimeout(0), this returns immediately with available data or empty string
            return $this->sshShell->read('') ?: '';
        }

        $output = '';

        if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
            while (($chunk = fread($this->pipes[1], 8192)) !== false && $chunk !== '') {
                $output .= $chunk;
            }
        }

        if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
            while (($chunk = fread($this->pipes[2], 8192)) !== false && $chunk !== '') {
                $output .= $chunk;
            }
        }

        return $output;
    }

    public function resize(int $cols, int $rows): void
    {
        if ($this->sshShell !== null) {
            $this->sshShell->setWindowSize($cols, $rows);
            return;
        }

        if ($this->process !== null && $this->isRunning()) {
            $status = proc_get_status($this->process);
            if ($status['pid'] > 0) {
                // Send SIGWINCH to the process group
                posix_kill(-$status['pid'], SIGWINCH);
            }
        }
    }

    public function isRunning(): bool
    {
        if ($this->sshShell !== null) {
            return $this->sshShell->isConnected();
        }

        if ($this->process === null) {
            return false;
        }

        $status = proc_get_status($this->process);
        return $status['running'];
    }

    public function terminate(): void
    {
        if ($this->sshShell !== null) {
            $this->sshShell->disconnect();
            $this->sshShell = null;
            $this->registry->unregister($this->sessionId);
            return;
        }

        if ($this->process !== null) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process, 15); // SIGTERM
                usleep(100000); // 100ms grace
                $status = proc_get_status($this->process);
                if ($status['running']) {
                    proc_terminate($this->process, 9); // SIGKILL
                }
            }

            proc_close($this->process);
            $this->process = null;
            $this->pipes = [];
        }

        $this->registry->unregister($this->sessionId);
    }

    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->terminate();
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/WebSocket/TerminalPtyBridgeTest.php`
Expected: PASS (all 7 tests)

- [ ] **Step 6: Commit**

```bash
git add src/WebSocket/WebSocketProviderInterface.php src/WebSocket/TerminalPtyBridge.php tests/Unit/WebSocket/TerminalPtyBridgeTest.php
git commit -m "feat: add WebSocketProviderInterface and TerminalPtyBridge"
```

---

## Task 6: RatchetProvider and RatchetServer

**Files:**
- Create: `src/WebSocket/RatchetProvider.php`
- Create: `src/WebSocket/RatchetServer.php`
- Test: `tests/Unit/WebSocket/RatchetProviderTest.php`

**Important:** This task requires `cboden/ratchet` to be installed for tests. Run: `cd /home/guerra/projects/web-terminal && composer require cboden/ratchet --dev`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/WebSocket/RatchetProviderTest.php`:

```php
<?php
declare(strict_types=1);

use MWGuerra\WebTerminal\WebSocket\RatchetProvider;
use MWGuerra\WebTerminal\WebSocket\WebSocketProviderInterface;

describe('RatchetProvider', function () {
    it('implements WebSocketProviderInterface', function () {
        $provider = new RatchetProvider(app());
        expect($provider)->toBeInstanceOf(WebSocketProviderInterface::class);
    });
});
```

Note: Full WebSocket tests are integration/E2E tests — unit tests only verify construction and interface compliance. The Ratchet event loop cannot be easily tested in unit tests.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/WebSocket/RatchetProviderTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement RatchetServer (the MessageComponentInterface)**

Create `src/WebSocket/RatchetServer.php`. This handles WebSocket connections and bridges them to PTY processes:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class RatchetServer implements MessageComponentInterface
{
    /** @var array<string, TerminalPtyBridge> */
    private array $bridges = [];

    /** @var array<int, string> */
    private array $connectionToSession = [];

    /** @var array<int, ConnectionInterface> */
    private array $connections = [];

    private PtySessionRegistry $registry;
    private Encrypter $encrypter;
    private array $config;

    public function __construct(
        PtySessionRegistry $registry,
        Encrypter $encrypter,
        array $config,
    ) {
        $this->registry = $registry;
        $this->encrypter = $encrypter;
        $this->config = $config;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $params);

        $token = $params['token'] ?? null;
        if ($token === null) {
            $conn->close();
            return;
        }

        try {
            $payload = json_decode($this->encrypter->decrypt($token), true);
        } catch (\Exception $e) {
            $conn->close();
            return;
        }

        if (! $payload || ($payload['exp'] ?? 0) < time()) {
            $conn->close();
            return;
        }

        $sessionId = $payload['sessionId'];
        $userId = $payload['userId'];

        // Retrieve connection config from cache
        $configData = Cache::pull("terminal-pty:{$sessionId}");
        if ($configData === null) {
            $conn->close();
            return;
        }

        $connectionConfig = ConnectionConfig::fromArray($configData);
        $shell = $this->config['shell'] ?? '/bin/bash';

        $bridge = new TerminalPtyBridge($connectionConfig, $sessionId, $userId, $this->registry);
        $bridge->start($shell);

        $this->bridges[$sessionId] = $bridge;
        $this->connectionToSession[$conn->resourceId] = $sessionId;
        $this->connections[$conn->resourceId] = $conn;
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $sessionId = $this->connectionToSession[$from->resourceId] ?? null;
        if ($sessionId === null) {
            return;
        }

        $bridge = $this->bridges[$sessionId] ?? null;
        if ($bridge === null) {
            return;
        }

        // Check for resize messages (JSON)
        $decoded = @json_decode($msg, true);
        if ($decoded !== null && ($decoded['type'] ?? null) === 'resize') {
            $bridge->resize((int) $decoded['cols'], (int) $decoded['rows']);
            return;
        }

        // Otherwise, raw terminal input
        $bridge->write($msg);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $sessionId = $this->connectionToSession[$conn->resourceId] ?? null;
        if ($sessionId === null) {
            return;
        }

        unset($this->connectionToSession[$conn->resourceId]);
        unset($this->connections[$conn->resourceId]);

        $bridge = $this->bridges[$sessionId] ?? null;
        if ($bridge !== null) {
            $bridge->terminate();
            unset($this->bridges[$sessionId]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }

    /**
     * Called periodically by the event loop to stream PTY output to clients.
     */
    public function tick(): void
    {
        foreach ($this->bridges as $sessionId => $bridge) {
            if (! $bridge->isRunning()) {
                continue;
            }

            $output = $bridge->read();
            if ($output === '') {
                continue;
            }

            // Find the connection for this session and send output
            foreach ($this->connectionToSession as $resourceId => $sid) {
                if ($sid === $sessionId && isset($this->connections[$resourceId])) {
                    $this->connections[$resourceId]->send($output);
                    break;
                }
            }
        }
    }

    public function sendToSession(string $sessionId, string $data): void
    {
        foreach ($this->connectionToSession as $resourceId => $sid) {
            if ($sid === $sessionId && isset($this->connections[$resourceId])) {
                $this->connections[$resourceId]->send($data);
                break;
            }
        }
    }

    public function getBridge(string $sessionId): ?TerminalPtyBridge
    {
        return $this->bridges[$sessionId] ?? null;
    }
}
```

- [ ] **Step 4: Implement RatchetProvider**

Create `src/WebSocket/RatchetProvider.php`:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

use Illuminate\Contracts\Foundation\Application;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

class RatchetProvider implements WebSocketProviderInterface
{
    private Application $app;
    private ?IoServer $server = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function start(string $host, int $port): void
    {
        $config = $this->app['config']->get('web-terminal.ghostty', []);

        $registry = new PtySessionRegistry(
            $this->app->storagePath('web-terminal')
        );

        // Cleanup orphaned PIDs from previous crashes
        $stale = $registry->cleanupStale($config['max_session_lifetime'] ?? 3600);
        foreach ($stale as $session) {
            // pid > 0 guard: SSH sessions use -1 (sentinel), skip them.
            // pid 0 would kill the entire process group — never allow that.
            if ($session['pid'] > 0 && posix_kill($session['pid'], 0)) {
                posix_kill($session['pid'], 9);
            }
        }

        $ratchetServer = new RatchetServer(
            $registry,
            $this->app['encrypter'],
            $config,
        );

        $loop = Loop::get();

        // Periodic PTY output streaming (every 10ms)
        $connections = new \SplObjectStorage;
        $wsServer = new WsServer($ratchetServer);

        $socket = new SocketServer("{$host}:{$port}", [], $loop);

        $httpServer = new HttpServer($wsServer);
        $this->server = new IoServer($httpServer, $socket, $loop);

        // Add periodic timer for output streaming and cleanup
        $loop->addPeriodicTimer(0.01, function () use ($ratchetServer) {
            $ratchetServer->tick();
        });

        $loop->addPeriodicTimer(60, function () use ($registry, $config) {
            $stale = $registry->cleanupStale($config['max_session_lifetime'] ?? 3600);
            foreach ($stale as $session) {
                if ($session['pid'] > 0 && posix_kill($session['pid'], 0)) {
                    posix_kill($session['pid'], 9);
                }
            }
        });

        $this->server->run();
    }

    public function stop(): void
    {
        if ($this->server !== null) {
            // IoServer doesn't have a stop method — the loop handles shutdown
            $this->server = null;
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/WebSocket/RatchetProviderTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/WebSocket/RatchetServer.php src/WebSocket/RatchetProvider.php tests/Unit/WebSocket/RatchetProviderTest.php
git commit -m "feat: add RatchetServer and RatchetProvider for WebSocket PTY bridge"
```

---

## Task 7: TerminalServeCommand (Artisan Command)

**Files:**
- Create: `src/Console/Commands/TerminalServeCommand.php`

- [ ] **Step 1: Implement the artisan command**

Follow the pattern from existing `src/Console/Commands/TerminalInstallCommand.php`:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\Console\Commands;

use Illuminate\Console\Command;
use MWGuerra\WebTerminal\WebSocket\RatchetProvider;

class TerminalServeCommand extends Command
{
    protected $signature = 'terminal:serve
                            {--host= : The host to bind to}
                            {--port= : The port to listen on}';

    protected $description = 'Start the WebSocket server for Ghostty terminal mode';

    public function handle(): int
    {
        if (! class_exists(\Ratchet\Server\IoServer::class)) {
            $this->error('Ratchet is not installed. Run: composer require cboden/ratchet');
            return self::FAILURE;
        }

        $host = $this->option('host') ?? config('web-terminal.ghostty.ratchet_host', '127.0.0.1');
        $port = $this->option('port') ?? config('web-terminal.ghostty.ratchet_port', 8090);

        $this->info("Starting WebSocket server on {$host}:{$port}...");
        $this->info('Press Ctrl+C to stop.');

        $provider = new RatchetProvider($this->laravel);
        $provider->start($host, (int) $port);

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Register the command in the service provider**

Open `src/WebTerminalServiceProvider.php`. In the `boot()` method, find the `$this->commands([...])` array and add `TerminalServeCommand::class`.

- [ ] **Step 3: Verify the command is registered**

Run: `cd /home/guerra/projects/web-terminal && php vendor/bin/testbench terminal:serve --help`
Expected: Shows help text for the command

- [ ] **Step 4: Commit**

```bash
git add src/Console/Commands/TerminalServeCommand.php src/WebTerminalServiceProvider.php
git commit -m "feat: add terminal:serve artisan command for WebSocket server"
```

---

## Task 8: Token Generation Controller and Route

**Files:**
- Create: `src/Http/Controllers/TerminalWebSocketController.php`
- Modify: `src/WebTerminalServiceProvider.php` (add route)

- [ ] **Step 1: Implement the controller**

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TerminalWebSocketController extends Controller
{
    public function generateToken(Request $request): JsonResponse
    {
        $sessionId = Str::uuid()->toString();
        $config = $request->input('connectionConfig', []);
        $ttl = config('web-terminal.ghostty.signed_url_ttl', 300);

        // Store connection config in cache (one-time retrieval)
        Cache::put("terminal-pty:{$sessionId}", $config, $ttl);

        $payload = json_encode([
            'userId' => $request->user()->id,
            'sessionId' => $sessionId,
            'exp' => time() + $ttl,
        ]);

        $token = app('encrypter')->encrypt($payload);

        $host = config('web-terminal.ghostty.ratchet_host', '127.0.0.1');
        $port = config('web-terminal.ghostty.ratchet_port', 8090);
        $protocol = $request->isSecure() ? 'wss' : 'ws';

        return response()->json([
            'token' => $token,
            'url' => "{$protocol}://{$host}:{$port}?token=" . urlencode($token),
            'sessionId' => $sessionId,
        ]);
    }
}
```

- [ ] **Step 2: Write token generation tests**

Create `tests/Unit/Http/TerminalWebSocketControllerTest.php`:

```php
<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

describe('TerminalWebSocketController', function () {
    it('generates an encrypted token with correct payload', function () {
        $user = new \Illuminate\Foundation\Auth\User;
        $user->id = 42;
        $this->actingAs($user);

        $response = $this->postJson(route('terminal.ws-token'), [
            'connectionConfig' => ['type' => 'local'],
        ]);

        $response->assertOk();
        $data = $response->json();
        expect($data)->toHaveKeys(['token', 'url', 'sessionId']);

        // Verify token can be decrypted
        $payload = json_decode(app('encrypter')->decrypt($data['token']), true);
        expect($payload['userId'])->toBe(42);
        expect($payload['sessionId'])->toBe($data['sessionId']);
        expect($payload['exp'])->toBeGreaterThan(time());
    });

    it('caches connection config for the session', function () {
        $user = new \Illuminate\Foundation\Auth\User;
        $user->id = 1;
        $this->actingAs($user);

        $response = $this->postJson(route('terminal.ws-token'), [
            'connectionConfig' => ['type' => 'local', 'timeout' => 30],
        ]);

        $sessionId = $response->json('sessionId');
        $cached = Cache::get("terminal-pty:{$sessionId}");
        expect($cached)->toBe(['type' => 'local', 'timeout' => 30]);
    });

    it('requires authentication', function () {
        $response = $this->postJson(route('terminal.ws-token'));
        $response->assertUnauthorized();
    });
});
```

- [ ] **Step 3: Register the route in the service provider**

In `src/WebTerminalServiceProvider.php`, add in `boot()` method after view loading:

```php
use Illuminate\Support\Facades\Route;

// In boot():
if (config('web-terminal.ghostty.enabled', false)) {
    Route::post('terminal/ws-token', [
        \MWGuerra\WebTerminal\Http\Controllers\TerminalWebSocketController::class,
        'generateToken',
    ])->name('terminal.ws-token')->middleware(['web', 'auth']);
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Http/Controllers/TerminalWebSocketController.php src/WebTerminalServiceProvider.php
git commit -m "feat: add WebSocket token generation controller and route"
```

---

## Task 9: GhosttyTerminal Livewire Component

**Files:**
- Create: `src/Livewire/GhosttyTerminal.php`
- Create: `resources/views/ghostty-terminal.blade.php`
- Test: `tests/Unit/Livewire/GhosttyTerminalTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Livewire/GhosttyTerminalTest.php`:

```php
<?php
declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Livewire\GhosttyTerminal;

describe('GhosttyTerminal', function () {
    it('can be mounted with default parameters', function () {
        Livewire::test(GhosttyTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'ghosttyTheme' => [],
            'showWindowControls' => true,
        ])->assertStatus(200);
    });

    it('has locked connection config', function () {
        $component = Livewire::test(GhosttyTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'ghosttyTheme' => [],
            'showWindowControls' => true,
        ]);

        // Locked properties cannot be set from frontend
        $component->assertStatus(200);
        expect($component->get('isConnected'))->toBeFalse();
    });

    it('renders the ghostty terminal view', function () {
        Livewire::test(GhosttyTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'ghosttyTheme' => [],
            'showWindowControls' => true,
        ])->assertViewIs('web-terminal::ghostty-terminal');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/GhosttyTerminalTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement GhosttyTerminal Livewire component**

Create `src/Livewire/GhosttyTerminal.php`. Model after `src/Livewire/WebTerminal.php` for patterns (mount, properties, Locked attributes), but much simpler since the heavy lifting is in JS:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GhosttyTerminal extends Component
{
    public bool $isConnected = false;
    public string $height = '400px';
    public string $title = 'Terminal';
    public bool $showWindowControls = true;

    #[Locked]
    public array $ghosttyTheme = [];

    #[Locked]
    public array $connectionConfig = [];

    #[Locked]
    public string $componentId = '';

    #[Locked]
    public array $scripts = [];

    public function mount(
        array $connectionConfig = [],
        string $height = '400px',
        string $title = 'Terminal',
        array $ghosttyTheme = [],
        bool $showWindowControls = true,
        array $scripts = [],
    ): void {
        $this->connectionConfig = $connectionConfig;
        $this->height = $height;
        $this->title = $title;
        $this->ghosttyTheme = $ghosttyTheme;
        $this->showWindowControls = $showWindowControls;
        $this->scripts = $scripts;
        $this->componentId = 'ghostty-' . Str::random(8);
    }

    public function getWebSocketUrl(): array
    {
        // Check authorization
        if (Gate::has('useGhosttyTerminal') && ! Gate::allows('useGhosttyTerminal')) {
            return ['error' => 'Unauthorized'];
        }

        $sessionId = Str::uuid()->toString();
        $ttl = config('web-terminal.ghostty.signed_url_ttl', 300);

        // Store connection config in cache
        Cache::put("terminal-pty:{$sessionId}", $this->connectionConfig, $ttl);

        $payload = json_encode([
            'userId' => auth()->id(),
            'sessionId' => $sessionId,
            'exp' => time() + $ttl,
        ]);

        $token = app('encrypter')->encrypt($payload);
        $host = config('web-terminal.ghostty.ratchet_host', '127.0.0.1');
        $port = config('web-terminal.ghostty.ratchet_port', 8090);

        return [
            'token' => $token,
            'url' => "ws://{$host}:{$port}?token=" . urlencode($token),
            'sessionId' => $sessionId,
        ];
    }

    public function connect(): void
    {
        $this->isConnected = true;
    }

    public function disconnect(): void
    {
        $this->isConnected = false;
    }

    public function getScriptsForExecution(string $key): array
    {
        foreach ($this->scripts as $script) {
            if (($script['key'] ?? '') === $key) {
                return $script['commands'] ?? [];
            }
        }

        return [];
    }

    public function render()
    {
        return view('web-terminal::ghostty-terminal');
    }
}
```

- [ ] **Step 4: Create the Blade view**

Create `resources/views/ghostty-terminal.blade.php`:

```blade
<div
    class="secure-web-terminal ghostty-mode relative font-mono text-[13px] leading-tight bg-gradient-to-b from-slate-100 to-white dark:from-[#1a1a2e] dark:to-[#16213e] text-zinc-800 dark:text-zinc-200 rounded-xl overflow-hidden flex flex-col shadow-2xl ring-1 ring-slate-200 dark:ring-white/5 text-left"
    style="height: {{ $height }}; min-height: 200px;"
    x-data="{
        isConnected: $wire.entangle('isConnected'),
        ws: null,
        terminal: null,
        fitAddon: null,
        ghosttyTheme: @js($ghosttyTheme),
        componentId: @js($componentId),
        showInfoPanel: false,
        copyFeedback: false,
        copyFeedbackTimeout: null,

        async initGhostty() {
            const { init, Terminal, FitAddon } = await import('/vendor/web-terminal/ghostty-terminal.js');
            await init();

            this.terminal = new Terminal({
                fontSize: this.ghosttyTheme.fontSize || 14,
                fontFamily: this.ghosttyTheme.fontFamily || 'monospace',
                theme: this.ghosttyTheme,
                cursorBlink: true,
                cursorStyle: this.ghosttyTheme.cursorStyle || 'block',
                scrollback: this.ghosttyTheme.scrollback || 5000,
            });

            this.fitAddon = new FitAddon();
            this.terminal.loadAddon(this.fitAddon);

            const container = this.$refs.ghosttyContainer;
            this.terminal.open(container);
            this.fitAddon.fit();
            this.fitAddon.observeResize();
        },

        async connect() {
            if (this.ws) return;

            const result = await $wire.getWebSocketUrl();
            if (result.error) {
                console.error('Ghostty auth error:', result.error);
                return;
            }

            if (!this.terminal) {
                await this.initGhostty();
            }

            this.ws = new WebSocket(result.url);

            this.ws.onopen = () => {
                $wire.connect();

                this.terminal.onData((data) => {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(data);
                    }
                });

                this.terminal.onResize((size) => {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(JSON.stringify({
                            type: 'resize',
                            cols: size.cols,
                            rows: size.rows,
                        }));
                    }
                });

                // Send initial size
                this.ws.send(JSON.stringify({
                    type: 'resize',
                    cols: this.terminal.cols,
                    rows: this.terminal.rows,
                }));

                this.terminal.focus();
            };

            this.ws.onmessage = (event) => {
                this.terminal.write(event.data);
            };

            this.ws.onclose = () => {
                $wire.disconnect();
                this.ws = null;
            };

            this.ws.onerror = (err) => {
                console.error('Ghostty WebSocket error:', err);
                this.ws = null;
                $wire.disconnect();
            };
        },

        disconnect() {
            if (this.ws) {
                this.ws.close();
                this.ws = null;
            }
            $wire.disconnect();
        },

        handleToggle() {
            if (this.isConnected) {
                this.disconnect();
            } else {
                this.connect();
            }
        },

        async copyAllOutput() {
            if (!this.terminal) return;
            const buffer = this.terminal.buffer.active;
            let text = '';
            for (let i = 0; i < buffer.length; i++) {
                const line = buffer.getLine(i);
                if (line) {
                    text += line.translateToString(true) + '\n';
                }
            }
            try {
                await navigator.clipboard.writeText(text.trim());
                this.copyFeedback = true;
                clearTimeout(this.copyFeedbackTimeout);
                this.copyFeedbackTimeout = setTimeout(() => { this.copyFeedback = false; }, 1500);
            } catch (e) { /* fallback omitted for brevity */ }
        },

        async runScript(key) {
            const commands = await $wire.getScriptsForExecution(key);
            if (!commands.length || !this.ws || this.ws.readyState !== WebSocket.OPEN) return;
            for (const cmd of commands) {
                this.ws.send(cmd + '\n');
                await new Promise(r => setTimeout(r, 300));
            }
        },

        destroy() {
            if (this.ws) { this.ws.close(); }
            if (this.terminal) { this.terminal.dispose(); }
        }
    }"
    x-init="$watch('isConnected', v => { if (!v && ws) { ws = null; } })"
    @beforeunload.window="destroy()"
>
    {{-- Header — reuses same structure as classic terminal --}}
    <div class="flex items-center px-4 py-3 bg-slate-200/80 dark:bg-black/30 border-b border-slate-300 dark:border-white/5">
        @if($showWindowControls)
        <div class="flex gap-2">
            <span class="w-3 h-3 rounded-full bg-[#ff5f56] hover:opacity-80 transition-opacity"></span>
            <span class="w-3 h-3 rounded-full bg-[#ffbd2e] hover:opacity-80 transition-opacity"></span>
            <span class="w-3 h-3 rounded-full bg-[#27c93f] hover:opacity-80 transition-opacity"></span>
        </div>
        @endif
        <div class="flex-1 text-center text-xs font-medium text-slate-500 dark:text-white/50 tracking-wide">{{ $title }}</div>
        <div class="flex items-center gap-2">
            {{-- Scripts dropdown (if scripts configured) --}}
            @if(!empty($scripts))
            <div class="relative" x-data="{ showScriptsDropdown: false }">
                <button type="button" @click="showScriptsDropdown = !showScriptsDropdown" @click.away="showScriptsDropdown = false"
                    class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200 bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60"
                    :disabled="!isConnected" title="Run scripts">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="showScriptsDropdown" x-transition class="absolute right-0 mt-2 min-w-60 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-black/5 dark:ring-white/10 z-50 overflow-hidden" @click.away="showScriptsDropdown = false">
                    <div class="px-3 py-2 border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-gray-800/50">
                        <p class="text-xs font-medium text-slate-500 dark:text-gray-400 uppercase tracking-wide">Scripts</p>
                    </div>
                    <div class="py-1">
                        @foreach($scripts as $script)
                        <button type="button" @click="runScript('{{ $script['key'] }}'); showScriptsDropdown = false"
                            class="w-full px-3 py-2.5 text-left hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                            <p class="text-sm font-medium text-slate-800 dark:text-gray-100">{{ $script['label'] }}</p>
                            @if($script['description'] ?? false)
                            <p class="text-xs text-slate-500 dark:text-gray-400">{{ $script['description'] }}</p>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Copy All --}}
            <button type="button" @click="copyAllOutput()"
                class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
                :class="copyFeedback ? 'bg-emerald-500/20 text-emerald-600 ring-1 ring-emerald-500/40 dark:bg-emerald-500/30 dark:text-emerald-400' : 'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60'"
                :title="copyFeedback ? 'Copied!' : 'Copy terminal output'">
                <svg x-show="!copyFeedback" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3A1.5 1.5 0 0 1 13 3.5H7ZM5.5 5A1.5 1.5 0 0 0 4 6.5v10A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-10A1.5 1.5 0 0 0 14.5 5h-9Z"/><path d="M8.5 1A2.5 2.5 0 0 0 6 3.5H4.5A2.5 2.5 0 0 0 2 6v10.5A2.5 2.5 0 0 0 4.5 19h9a2.5 2.5 0 0 0 2.5-2.5V6a2.5 2.5 0 0 0-2.5-2.5H12A2.5 2.5 0 0 0 9.5 1h-1Z"/></svg>
                <svg x-show="copyFeedback" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            </button>

            {{-- Info Panel --}}
            <button type="button" @click="showInfoPanel = !showInfoPanel"
                class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
                :class="showInfoPanel ? 'bg-blue-500/20 text-blue-600 ring-1 ring-blue-500/40 dark:bg-blue-500/30 dark:text-blue-400 dark:ring-blue-500/50' : 'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60'"
                title="Connection info">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" /></svg>
            </button>

            {{-- Connect/Disconnect --}}
            <button type="button" @click="handleToggle()"
                class="relative flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full transition-all duration-200 overflow-hidden"
                :class="isConnected ? 'bg-red-500/10 text-red-600 border border-red-500/40 hover:bg-red-500/20 dark:text-red-400 dark:border-red-500/30' : 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40 hover:bg-emerald-500/25 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30 dark:hover:bg-emerald-500/30'"
                :title="isConnected ? 'Disconnect terminal' : 'Connect terminal'">
                <span x-show="!isConnected">Connect</span>
                <span x-show="isConnected">Disconnect</span>
            </button>
        </div>
    </div>

    {{-- Info Panel Overlay --}}
    <div x-show="showInfoPanel" x-transition class="absolute inset-0 top-12 z-20 bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="text-xs space-y-2">
            <div class="text-white/70">
                <span class="font-semibold text-white">Mode:</span> Ghostty (PTY via WebSocket)
            </div>
            <div class="text-white/70">
                <span class="font-semibold text-white">Status:</span>
                <span :class="isConnected ? 'text-emerald-400' : 'text-red-400'" x-text="isConnected ? 'Connected' : 'Disconnected'"></span>
            </div>
            <div class="text-white/70">
                <span class="font-semibold text-white">WebSocket:</span>
                {{ config('web-terminal.ghostty.ratchet_host') }}:{{ config('web-terminal.ghostty.ratchet_port') }}
            </div>
        </div>
    </div>

    {{-- Ghostty Canvas Container --}}
    <div x-ref="ghosttyContainer" class="flex-1 overflow-hidden" style="min-height: 0;"></div>
</div>
```

- [ ] **Step 5: Register the Livewire component in the service provider**

In `src/WebTerminalServiceProvider.php`, in `boot()`, add after the existing `Livewire::component('web-terminal', ...)`:

```php
Livewire::component('ghostty-terminal', \MWGuerra\WebTerminal\Livewire\GhosttyTerminal::class);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/GhosttyTerminalTest.php`
Expected: PASS (all 3 tests)

- [ ] **Step 7: Commit**

```bash
git add src/Livewire/GhosttyTerminal.php resources/views/ghostty-terminal.blade.php tests/Unit/Livewire/GhosttyTerminalTest.php src/WebTerminalServiceProvider.php
git commit -m "feat: add GhosttyTerminal Livewire component and view"
```

---

## Task 10: TerminalContainer and Toggle Pill

**Files:**
- Create: `src/Livewire/TerminalContainer.php`
- Create: `resources/views/terminal-container.blade.php`
- Create: `resources/views/partials/toggle-pill.blade.php`
- Test: `tests/Unit/Livewire/TerminalContainerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Livewire/TerminalContainerTest.php`:

```php
<?php
declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Livewire\TerminalContainer;

describe('TerminalContainer', function () {
    it('can be mounted with both modes', function () {
        Livewire::test(TerminalContainer::class, [
            'classicParams' => ['allowedCommands' => ['ls']],
            'ghosttyParams' => ['ghosttyTheme' => []],
            'defaultMode' => 'classic',
            'height' => '400px',
            'title' => 'Terminal',
            'showWindowControls' => true,
        ])->assertStatus(200);
    });

    it('renders the container view', function () {
        Livewire::test(TerminalContainer::class, [
            'classicParams' => ['allowedCommands' => ['ls']],
            'ghosttyParams' => ['ghosttyTheme' => []],
            'defaultMode' => 'classic',
            'height' => '400px',
            'title' => 'Terminal',
            'showWindowControls' => true,
        ])->assertViewIs('web-terminal::terminal-container');
    });

    it('passes default mode to view', function () {
        Livewire::test(TerminalContainer::class, [
            'classicParams' => [],
            'ghosttyParams' => [],
            'defaultMode' => 'ghostty',
            'height' => '400px',
            'title' => 'Terminal',
            'showWindowControls' => true,
        ])->assertSet('defaultMode', 'ghostty');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalContainerTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement TerminalContainer**

Create `src/Livewire/TerminalContainer.php`:

```php
<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;

class TerminalContainer extends Component
{
    #[Locked]
    public array $classicParams = [];

    #[Locked]
    public array $ghosttyParams = [];

    #[Locked]
    public string $defaultMode = 'classic';

    public string $height = '400px';
    public string $title = 'Terminal';
    public bool $showWindowControls = true;

    public function mount(
        array $classicParams = [],
        array $ghosttyParams = [],
        string $defaultMode = 'classic',
        string $height = '400px',
        string $title = 'Terminal',
        bool $showWindowControls = true,
    ): void {
        $this->classicParams = $classicParams;
        $this->ghosttyParams = $ghosttyParams;
        $this->defaultMode = $defaultMode;
        $this->height = $height;
        $this->title = $title;
        $this->showWindowControls = $showWindowControls;
    }

    public function render()
    {
        return view('web-terminal::terminal-container');
    }
}
```

- [ ] **Step 4: Create the toggle pill partial**

Create `resources/views/partials/toggle-pill.blade.php`:

```blade
{{-- Terminal Mode Toggle Pill --}}
<div class="flex rounded-full bg-slate-300/50 dark:bg-white/[0.08] border border-slate-300 dark:border-white/10 overflow-hidden text-[10px] font-semibold tracking-wide">
    <button type="button"
        @click="activeMode = 'classic'"
        class="px-2.5 py-1 rounded-full transition-all duration-200"
        :class="activeMode === 'classic'
            ? 'bg-indigo-500/30 text-indigo-600 dark:text-indigo-300'
            : 'text-slate-400 dark:text-white/35 hover:text-slate-600 dark:hover:text-white/50'"
    >Classic</button>
    <button type="button"
        @click="activeMode = 'ghostty'"
        class="px-2.5 py-1 rounded-full transition-all duration-200"
        :class="activeMode === 'ghostty'
            ? 'bg-purple-500/30 text-purple-600 dark:text-purple-300'
            : 'text-slate-400 dark:text-white/35 hover:text-slate-600 dark:hover:text-white/50'"
    >Ghostty</button>
</div>
```

- [ ] **Step 5: Create the container view**

Create `resources/views/terminal-container.blade.php`:

```blade
<div
    x-data="{ activeMode: '{{ $defaultMode }}' }"
    style="height: {{ $height }}; min-height: 200px;"
    class="relative"
>
    {{-- Toggle Pill (floating above both terminals) --}}
    <div class="absolute top-2.5 left-1/2 -translate-x-1/2 z-30" style="pointer-events: auto;">
        @include('web-terminal::partials.toggle-pill')
    </div>

    {{-- Classic Terminal --}}
    <div x-show="activeMode === 'classic'" class="h-full">
        <livewire:web-terminal
            wire:key="classic-terminal"
            :$height
            :$title
            :$showWindowControls
            @foreach($classicParams as $key => $value)
                :{{ $key }}="$classicParams['{{ $key }}']"
            @endforeach
        />
    </div>

    {{-- Ghostty Terminal --}}
    <div x-show="activeMode === 'ghostty'" class="h-full">
        <livewire:ghostty-terminal
            wire:key="ghostty-terminal"
            :$height
            :$title
            :$showWindowControls
            :connectionConfig="$ghosttyParams['connectionConfig'] ?? []"
            :ghosttyTheme="$ghosttyParams['ghosttyTheme'] ?? []"
            :scripts="$ghosttyParams['scripts'] ?? []"
        />
    </div>
</div>
```

**Note:** The exact parameter passing between container and nested components will need refinement during implementation. The container view above is a starting point — the TerminalBuilder will prepare the correct parameter arrays for each nested component.

- [ ] **Step 6: Register the component in the service provider**

In `src/WebTerminalServiceProvider.php`, in `boot()`:

```php
Livewire::component('terminal-container', \MWGuerra\WebTerminal\Livewire\TerminalContainer::class);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalContainerTest.php`
Expected: PASS (all 3 tests)

- [ ] **Step 8: Commit**

```bash
git add src/Livewire/TerminalContainer.php resources/views/terminal-container.blade.php resources/views/partials/toggle-pill.blade.php tests/Unit/Livewire/TerminalContainerTest.php src/WebTerminalServiceProvider.php
git commit -m "feat: add TerminalContainer with toggle pill for dual-mode"
```

---

## Task 11: Update TerminalBuilder render() Routing

**Files:**
- Modify: `src/Livewire/TerminalBuilder.php` (update `render()` and `toHtml()`)
- Modify: `tests/Unit/Livewire/TerminalBuilderGhosttyTest.php` (add render routing tests)

- [ ] **Step 1: Add render routing tests**

Append to `tests/Unit/Livewire/TerminalBuilderGhosttyTest.php`:

```php
describe('render routing', function () {
    it('renders WebTerminal when only classic enabled', function () {
        $builder = new TerminalBuilder;
        $builder->local();
        $html = $builder->render();
        // The render() mounts 'web-terminal' Livewire component
        expect((string) $html)->toContain('secure-web-terminal');
    });

    it('renders GhosttyTerminal when only ghostty enabled', function () {
        $builder = new TerminalBuilder;
        $builder->local()->ghosttyTerminal()->classicTerminal(false);
        $html = $builder->render();
        expect((string) $html)->toContain('ghostty-mode');
    });

    it('renders TerminalContainer when both enabled', function () {
        $builder = new TerminalBuilder;
        $builder->local()->ghosttyTerminal();
        $html = $builder->render();
        // Container renders both
        expect((string) $html)->toContain('activeMode');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalBuilderGhosttyTest.php --filter="render routing"`
Expected: FAIL — render routing not yet implemented

- [ ] **Step 3: Update render() method**

In `src/Livewire/TerminalBuilder.php`, replace the `render()` method:

```php
public function render(): View|HtmlString
{
    // Validation
    if (! $this->classicEnabled && ! $this->ghosttyEnabled) {
        throw new \InvalidArgumentException('At least one terminal mode must be enabled');
    }

    if ($this->defaultMode === TerminalMode::Ghostty && ! $this->ghosttyEnabled) {
        throw new \InvalidArgumentException('Cannot set default mode to Ghostty when Ghostty is disabled');
    }

    if ($this->defaultMode === TerminalMode::Classic && ! $this->classicEnabled) {
        throw new \InvalidArgumentException('Cannot set default mode to Classic when Classic is disabled');
    }

    // Check Ratchet dependency if ghostty enabled
    if ($this->ghosttyEnabled && ! class_exists(\Ratchet\Server\IoServer::class)) {
        throw new \RuntimeException('Ghostty terminal requires cboden/ratchet. Install it: composer require cboden/ratchet');
    }

    $params = $this->getParameters();
    $key = $this->key;

    // Route to the correct component
    // Note: $this->connection holds the connection data (array or ConnectionConfig).
    // Extract it as an array for the ghostty component.
    $connectionArray = $this->connection instanceof ConnectionConfig
        ? $this->connection->toArray()
        : ($this->connection ?? []);

    if ($this->ghosttyEnabled && $this->classicEnabled) {
        $component = 'terminal-container';
        $mountParams = [
            'classicParams' => $params,
            'ghosttyParams' => [
                'connectionConfig' => $connectionArray,
                'ghosttyTheme' => $this->ghosttyTheme,
                'scripts' => $this->scripts ?? [],
            ],
            'defaultMode' => $this->defaultMode->value,
            'height' => $this->height,
            'title' => $this->title,
            'showWindowControls' => $this->showWindowControls,
        ];
    } elseif ($this->ghosttyEnabled) {
        $component = 'ghostty-terminal';
        $mountParams = [
            'connectionConfig' => $connectionArray,
            'height' => $this->height,
            'title' => $this->title,
            'ghosttyTheme' => $this->ghosttyTheme,
            'showWindowControls' => $this->showWindowControls,
            'scripts' => $this->scripts,
        ];
    } else {
        $component = 'web-terminal';
        $mountParams = $params;
    }

    if ($key !== null) {
        return new HtmlString(
            \Livewire\Livewire::mount($component, $mountParams, $key)->html()
        );
    }

    return new HtmlString(
        \Livewire\Livewire::mount($component, $mountParams)->html()
    );
}
```

**Note:** The `$this->connectionConfig` is a protected property — reference the existing code to find the exact property name and how it stores connection config. It may be stored differently (e.g., as session data). Adjust the parameter extraction accordingly.

- [ ] **Step 4: Run tests to verify**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/TerminalBuilderGhosttyTest.php`
Expected: PASS (all tests including render routing)

Then run the full test suite:
Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest`
Expected: PASS (all tests, no regressions)

- [ ] **Step 5: Commit**

```bash
git add src/Livewire/TerminalBuilder.php tests/Unit/Livewire/TerminalBuilderGhosttyTest.php
git commit -m "feat: route TerminalBuilder render to correct component based on mode"
```

---

## Task 12: JavaScript — ghostty-web Integration

**Files:**
- Create: `resources/js/ghostty-terminal.js`

- [ ] **Step 1: Create the ghostty-terminal.js module**

This is the frontend JS that initializes ghostty-web, manages the WebSocket, and exposes the API used by the Blade view's Alpine.js:

```javascript
// resources/js/ghostty-terminal.js
import { init as ghosttyInit, Terminal, FitAddon } from 'ghostty-web';

export { Terminal, FitAddon };

let initialized = false;

export async function init() {
    if (initialized) return;
    await ghosttyInit();
    initialized = true;
}
```

- [ ] **Step 2: Build the JS bundle**

Run: `cd /home/guerra/projects/web-terminal && npm run build:js`
Expected: `resources/dist/ghostty-terminal.js` created

- [ ] **Step 3: Register the JS asset in the service provider**

In `src/WebTerminalServiceProvider.php`, update `registerAssets()`:

```php
use Filament\Support\Assets\Js;

protected function registerAssets(): void
{
    if (class_exists(FilamentAsset::class)) {
        $assets = [
            Css::make('web-terminal', __DIR__.'/../resources/dist/web-terminal.css'),
        ];

        if (config('web-terminal.ghostty.enabled', false)) {
            $assets[] = Js::make('ghostty-terminal', __DIR__.'/../resources/dist/ghostty-terminal.js');
        }

        FilamentAsset::register($assets, 'mwguerra/web-terminal');
    }
}
```

- [ ] **Step 4: Update the Blade view to use the Filament asset path**

The ghostty-terminal.blade.php `import()` path needs to reference the Filament asset URL. Update the `initGhostty()` method in the Blade view to use the correct asset path. The exact path depends on Filament's asset publishing — it may be something like:

```javascript
// In the Alpine.js initGhostty method, replace the static import with:
const module = await import(Filament.getAssetUrl('ghostty-terminal', 'mwguerra/web-terminal'));
```

Or use the `@filamentAssets` approach. This will need to be verified during implementation by checking how Filament JS assets are loaded.

- [ ] **Step 5: Commit**

```bash
git add resources/js/ghostty-terminal.js resources/dist/ghostty-terminal.js src/WebTerminalServiceProvider.php
git commit -m "feat: add ghostty-web JS integration and Filament asset registration"
```

---

## Task 13: CSS Updates for Toggle Pill and Ghostty Mode

**Files:**
- Modify: `resources/css/index.css`

- [ ] **Step 1: Add ghostty-specific styles**

Append to `resources/css/index.css` (before any closing brackets):

```css
/* Ghostty Terminal Mode */
.secure-web-terminal.ghostty-mode .xterm {
    height: 100%;
}

.secure-web-terminal.ghostty-mode .xterm-screen {
    height: 100%;
}

/* Toggle pill transitions */
.toggle-pill-active {
    transition: background-color 200ms ease, color 200ms ease;
}
```

- [ ] **Step 2: Rebuild CSS**

Run: `cd /home/guerra/projects/web-terminal && npm run build`
Expected: `resources/dist/web-terminal.css` updated

- [ ] **Step 3: Commit**

```bash
git add resources/css/index.css resources/dist/web-terminal.css
git commit -m "feat: add ghostty mode and toggle pill CSS styles"
```

---

## Task 14: Service Provider Final Registration

**Files:**
- Modify: `src/WebTerminalServiceProvider.php`

- [ ] **Step 1: Review all registrations are in place**

Verify the service provider has all of these in `boot()`:
1. `Livewire::component('web-terminal', WebTerminal::class)` (existing)
2. `Livewire::component('ghostty-terminal', GhosttyTerminal::class)` (new)
3. `Livewire::component('terminal-container', TerminalContainer::class)` (new)
4. `TerminalServeCommand::class` in the commands array (new)
5. Route registration for `terminal.ws-token` (new, conditional)
6. JS asset registration (new, conditional)

- [ ] **Step 2: Run the full test suite**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest`
Expected: ALL tests pass

- [ ] **Step 3: Commit if any changes needed**

```bash
git add src/WebTerminalServiceProvider.php
git commit -m "feat: finalize service provider registrations for ghostty mode"
```

---

## Task 15: Integration Testing and Smoke Tests

**Files:**
- Run existing tests + manual verification

- [ ] **Step 1: Run the complete test suite**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest --parallel`
Expected: ALL tests pass, no regressions

- [ ] **Step 2: Verify classic mode is unaffected**

Run: `cd /home/guerra/projects/web-terminal && vendor/bin/pest tests/Unit/Livewire/WebTerminalTest.php`
Expected: ALL existing WebTerminal tests pass unchanged

- [ ] **Step 3: Verify config publishing works**

Run: `cd /home/guerra/projects/web-terminal && php vendor/bin/testbench vendor:publish --tag=web-terminal-config --force`
Expected: Config file published with ghostty section

- [ ] **Step 4: Commit test fixes if any**

Only if tests revealed issues that needed fixing.

---

## Task 16: E2E Testing with testapp_f5

**Reference:** Memory file for testapp_f5 setup (URL: `https://testapp-f5.test`, login: `admin@example.com` / `password`)

- [ ] **Step 1: Update testapp_f5 composer.json branch**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
# Update composer.json to: "mwguerra/web-terminal": "dev-feature/ghostty-terminal"
composer update mwguerra/web-terminal
```

- [ ] **Step 2: Install Ratchet in testapp**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
composer require cboden/ratchet
```

- [ ] **Step 3: Create a dual-mode test page**

Create a Filament page at `/home/guerra/projects/test_projects/testapp_f5/app/Filament/Pages/GhosttyTerminalPage.php` that extends `Terminal` and enables both modes with `ghosttyTerminal()` and `startConnected(true)`.

- [ ] **Step 4: Start the WebSocket server**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
php artisan terminal:serve &
```

- [ ] **Step 5: Run Playwright E2E tests**

Use Playwright MCP to:
1. Navigate to the ghostty terminal test page
2. Login if needed
3. Test toggle pill switches between modes
4. Test ghostty connect/disconnect
5. Execute readonly commands in ghostty mode (echo, ls, pwd)
6. Test copy-all functionality
7. Test info panel overlay
8. Take screenshots for visual verification

- [ ] **Step 6: Commit E2E test page**

```bash
cd /home/guerra/projects/test_projects/testapp_f5
git add -A
git commit -m "test: add dual-mode ghostty terminal test page"
```
