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
        $dir = dirname($this->registryPath);
        if (! is_dir($dir)) { mkdir($dir, 0755, true); }
        file_put_contents($this->registryPath, json_encode([
            'old-session' => ['pid' => 99999, 'userId' => 1, 'createdAt' => time() - 7200],
            'new-session' => ['pid' => 88888, 'userId' => 1, 'createdAt' => time()],
        ]));

        $stale = $this->registry->cleanupStale(3600);
        expect($stale)->toHaveKey('old-session');
        expect($stale)->not->toHaveKey('new-session');
        expect($this->registry->find('old-session'))->toBeNull();
        expect($this->registry->find('new-session'))->not->toBeNull();
    });
});
