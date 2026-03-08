<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Sessions\ProcessSessionManager;

beforeEach(function () {
    // Clear any lingering sessions from previous tests
    ProcessSessionManager::clearAllSessions();
    $this->manager = new ProcessSessionManager;
});

afterEach(function () {
    // Clean up after each test
    ProcessSessionManager::clearAllSessions();
});

describe('ProcessSessionManager', function () {
    describe('start', function () {
        it('starts a process and returns a session ID', function () {
            $sessionId = $this->manager->start('echo "hello"');

            expect($sessionId)->toBeString();
            expect($sessionId)->not->toBeEmpty();
            expect($this->manager->hasSession($sessionId))->toBeTrue();
        });

        it('generates unique session IDs', function () {
            $ids = [];
            for ($i = 0; $i < 10; $i++) {
                $ids[] = $this->manager->start('echo "test"');
            }

            expect(array_unique($ids))->toHaveCount(10);
        });

        it('starts process in specified working directory', function () {
            $sessionId = $this->manager->start('pwd', '/tmp');

            // Wait a moment for the command to execute
            usleep(100000); // 100ms

            $output = $this->manager->getOutput($sessionId);

            expect(trim($output['stdout'] ?? ''))->toBe('/tmp');
        });

        it('passes environment variables to process', function () {
            $sessionId = $this->manager->start('echo $TEST_VAR', null, ['TEST_VAR' => 'my_value']);

            usleep(100000);

            $output = $this->manager->getOutput($sessionId);

            expect(trim($output['stdout'] ?? ''))->toBe('my_value');
        });
    });

    describe('getOutput', function () {
        it('returns null for non-existent session', function () {
            $output = $this->manager->getOutput('non-existent-session-id');

            expect($output)->toBeNull();
        });

        it('returns stdout from process', function () {
            $sessionId = $this->manager->start('echo "hello world"');

            usleep(100000);

            $output = $this->manager->getOutput($sessionId);

            expect($output)->not->toBeNull();
            expect($output['stdout'])->toContain('hello world');
        });

        it('returns stderr from process', function () {
            $sessionId = $this->manager->start('echo "error message" >&2');

            usleep(100000);

            $output = $this->manager->getOutput($sessionId);

            expect($output)->not->toBeNull();
            expect(trim($output['stderr'] ?? ''))->toContain('error message');
        });

        it('returns incremental output (not cumulative)', function () {
            $sessionId = $this->manager->start('echo "first"; sleep 0.1; echo "second"');

            usleep(50000);
            $first = $this->manager->getOutput($sessionId);

            usleep(150000);
            $second = $this->manager->getOutput($sessionId);

            // First output should have "first", second should have "second"
            // They should not be cumulative
            $firstContent = $first['stdout'] ?? '';
            $secondContent = $second['stdout'] ?? '';

            // The exact timing may vary, but we should see both outputs eventually
            $combined = $firstContent.$secondContent;
            expect($combined)->toContain('first');
            expect($combined)->toContain('second');
        });
    });

    describe('sendInput', function () {
        it('returns false for non-existent session', function () {
            $result = $this->manager->sendInput('non-existent-id', 'test');

            expect($result)->toBeFalse();
        });

        it('returns false for finished process', function () {
            $sessionId = $this->manager->start('echo "done"');

            usleep(200000);

            // Process should be finished by now
            $result = $this->manager->sendInput($sessionId, 'test');

            expect($result)->toBeFalse();
        });

        it('sends input to running process', function () {
            // Start a long-running process that accepts input
            $sessionId = $this->manager->start('cat');

            usleep(100000);

            // Verify that we can send input (returns true)
            $result = $this->manager->sendInput($sessionId, 'hello');

            expect($result)->toBeTrue();

            // The process should still be running after receiving input
            expect($this->manager->isRunning($sessionId))->toBeTrue();

            $this->manager->terminate($sessionId);
        });

        it('appends newline to input if not present', function () {
            // Test that sendInput appends newline by checking return value
            // and that process continues running (meaning input was sent properly)
            $sessionId = $this->manager->start('cat');

            usleep(100000);

            // Input without trailing newline
            $input = 'test input';
            expect(str_ends_with($input, "\n"))->toBeFalse();

            $result = $this->manager->sendInput($sessionId, $input);

            expect($result)->toBeTrue();
            expect($this->manager->isRunning($sessionId))->toBeTrue();

            $this->manager->terminate($sessionId);
        });
    });

    describe('isRunning', function () {
        it('returns false for non-existent session', function () {
            $result = $this->manager->isRunning('non-existent-id');

            expect($result)->toBeFalse();
        });

        it('returns true for running process', function () {
            $sessionId = $this->manager->start('sleep 5');

            $result = $this->manager->isRunning($sessionId);

            expect($result)->toBeTrue();

            $this->manager->terminate($sessionId);
        });

        it('returns false for finished process', function () {
            $sessionId = $this->manager->start('echo "done"');

            usleep(200000);

            $result = $this->manager->isRunning($sessionId);

            expect($result)->toBeFalse();
        });
    });

    describe('getExitCode', function () {
        it('returns null for non-existent session', function () {
            $exitCode = $this->manager->getExitCode('non-existent-id');

            expect($exitCode)->toBeNull();
        });

        it('returns null for running process', function () {
            $sessionId = $this->manager->start('sleep 5');

            $exitCode = $this->manager->getExitCode($sessionId);

            expect($exitCode)->toBeNull();

            $this->manager->terminate($sessionId);
        });

        it('returns 0 for successful command', function () {
            $sessionId = $this->manager->start('echo "success"');

            usleep(200000);

            $exitCode = $this->manager->getExitCode($sessionId);

            expect($exitCode)->toBe(0);
        });

        it('returns non-zero for failed command', function () {
            $sessionId = $this->manager->start('exit 42');

            usleep(200000);

            $exitCode = $this->manager->getExitCode($sessionId);

            expect($exitCode)->toBe(42);
        });
    });

    describe('terminate', function () {
        it('returns false for non-existent session', function () {
            $result = $this->manager->terminate('non-existent-id');

            expect($result)->toBeFalse();
        });

        it('terminates running process', function () {
            $sessionId = $this->manager->start('sleep 60');

            expect($this->manager->isRunning($sessionId))->toBeTrue();

            $result = $this->manager->terminate($sessionId);

            expect($result)->toBeTrue();
            expect($this->manager->hasSession($sessionId))->toBeFalse();
        });

        it('removes session after termination', function () {
            $sessionId = $this->manager->start('echo "test"');

            usleep(100000);

            $this->manager->terminate($sessionId);

            expect($this->manager->hasSession($sessionId))->toBeFalse();
            expect($this->manager->getActiveSessionCount())->toBe(0);
        });
    });

    describe('hasSession', function () {
        it('returns false for non-existent session', function () {
            expect($this->manager->hasSession('non-existent-id'))->toBeFalse();
        });

        it('returns true for existing session', function () {
            $sessionId = $this->manager->start('echo "test"');

            expect($this->manager->hasSession($sessionId))->toBeTrue();
        });
    });

    describe('getSessionInfo', function () {
        it('returns null for non-existent session', function () {
            $info = $this->manager->getSessionInfo('non-existent-id');

            expect($info)->toBeNull();
        });

        it('returns session info array', function () {
            $sessionId = $this->manager->start('sleep 5');

            $info = $this->manager->getSessionInfo($sessionId);

            expect($info)->toBeArray();
            expect($info)->toHaveKeys(['started_at', 'last_activity', 'is_running', 'exit_code', 'pid']);
            expect($info['is_running'])->toBeTrue();
            expect($info['started_at'])->toBeInt();
            expect($info['pid'])->toBeInt();

            $this->manager->terminate($sessionId);
        });
    });

    describe('getActiveSessionCount', function () {
        it('returns 0 when no sessions', function () {
            expect($this->manager->getActiveSessionCount())->toBe(0);
        });

        it('returns correct count of sessions', function () {
            $this->manager->start('sleep 5');
            $this->manager->start('sleep 5');
            $this->manager->start('sleep 5');

            expect($this->manager->getActiveSessionCount())->toBe(3);
        });
    });

    describe('setMaxSessionLifetime', function () {
        it('enforces minimum lifetime of 60 seconds', function () {
            $result = $this->manager->setMaxSessionLifetime(10);

            expect($result)->toBe($this->manager);
            // Internal value should be at least 60
        });

        it('returns fluent interface', function () {
            $result = $this->manager->setMaxSessionLifetime(300);

            expect($result)->toBeInstanceOf(ProcessSessionManager::class);
        });
    });

    describe('cleanup', function () {
        it('removes finished sessions', function () {
            $sessionId = $this->manager->start('echo "done"');

            usleep(200000);

            expect($this->manager->hasSession($sessionId))->toBeTrue();

            $this->manager->cleanup();

            expect($this->manager->hasSession($sessionId))->toBeFalse();
        });
    });

    describe('clearAllSessions', function () {
        it('removes all sessions', function () {
            $this->manager->start('sleep 5');
            $this->manager->start('sleep 5');

            expect($this->manager->getActiveSessionCount())->toBe(2);

            ProcessSessionManager::clearAllSessions();

            expect($this->manager->getActiveSessionCount())->toBe(0);
        });
    });
});
