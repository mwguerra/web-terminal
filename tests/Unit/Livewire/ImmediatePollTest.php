<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Connections\LocalConnectionHandler;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Sessions\ProcessSessionManager;

beforeEach(function () {
    ProcessSessionManager::clearAllSessions();
});

afterEach(function () {
    ProcessSessionManager::clearAllSessions();
});

describe('Immediate Poll Behavior', function () {
    it('captures output from fast commands with immediate poll', function () {
        $handler = new LocalConnectionHandler;
        $handler->connect(ConnectionConfig::local());
        $handler->setPreferTmux(false);

        // Start a fast command
        $sessionId = $handler->startInteractive('echo "hello world"');

        // Wait a brief moment (like the Livewire component does)
        usleep(100000); // 100ms

        // Immediate poll should capture the output
        $output = $handler->readOutput($sessionId);

        expect($output)->not->toBeNull();
        expect($output['stdout'])->toContain('hello');

        // Process should be finished
        usleep(200000);
        expect($handler->isProcessRunning($sessionId))->toBeFalse();
        expect($handler->getProcessExitCode($sessionId))->toBe(0);
    });

    it('captures output from ls command', function () {
        $handler = new LocalConnectionHandler;
        $handler->connect(ConnectionConfig::local());
        $handler->setPreferTmux(false);
        $handler->setWorkingDirectory('/tmp');

        // Start ls command
        $sessionId = $handler->startInteractive('ls -la');

        // Poll multiple times to capture output (like Livewire component does)
        $stdout = '';
        for ($i = 0; $i < 10; $i++) {
            usleep(50000); // 50ms per poll
            $output = $handler->readOutput($sessionId);
            if ($output !== null && ! empty($output['stdout'])) {
                $stdout .= $output['stdout'];
            }
            if (! $handler->isProcessRunning($sessionId)) {
                break;
            }
        }

        expect($stdout)->not->toBeEmpty();
        // /tmp should have at least "." and ".." entries
        expect($stdout)->toContain('.');

        // Process should be finished
        usleep(200000);
        expect($handler->isProcessRunning($sessionId))->toBeFalse();
    });

    it('captures output from pwd command', function () {
        $handler = new LocalConnectionHandler;
        $handler->connect(ConnectionConfig::local());
        $handler->setPreferTmux(false);
        $handler->setWorkingDirectory('/tmp');

        // Start pwd command
        $sessionId = $handler->startInteractive('pwd');

        // Wait a brief moment
        usleep(200000);

        // Immediate poll
        $output = $handler->readOutput($sessionId);

        expect($output)->not->toBeNull();
        expect($output['stdout'])->toContain('/tmp');

        // Process should be finished
        usleep(200000);
        expect($handler->isProcessRunning($sessionId))->toBeFalse();
    });

    it('captures stderr from failed commands', function () {
        $handler = new LocalConnectionHandler;
        $handler->connect(ConnectionConfig::local());
        // Force ProcessSessionManager — FileSessionManager merges stderr into stdout via PTY
        $handler->setSessionManager(new \MWGuerra\WebTerminal\Sessions\ProcessSessionManager);

        // Start a command that produces stderr
        $sessionId = $handler->startInteractive('ls /nonexistent_directory_xyz');

        // Wait a brief moment
        usleep(100000);

        // Immediate poll
        $output = $handler->readOutput($sessionId);

        expect($output)->not->toBeNull();
        // Should have error message about directory not existing
        expect($output['stderr'])->not->toBeEmpty();

        // Process should be finished with non-zero exit
        expect($handler->isProcessRunning($sessionId))->toBeFalse();
        expect($handler->getProcessExitCode($sessionId))->not->toBe(0);
    });
});
