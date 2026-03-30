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
            $bridge->resize(120, 40);
            expect(true)->toBeTrue();
            $bridge->terminate();
        });
    });
});
