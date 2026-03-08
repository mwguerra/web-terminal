<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use MWGuerra\WebTerminal\Sessions\FileSessionManager;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir().'/swt-test-'.uniqid().'-'.getmypid();
    mkdir($this->testDir, 0700, true);

    $this->manager = new FileSessionManager;
    $this->manager->setSessionBaseDir($this->testDir);

    $this->startedSessions = [];
});

afterEach(function () {
    // Kill any running sessions
    foreach ($this->startedSessions as $sessionId) {
        try {
            $this->manager->terminate($sessionId);
        } catch (\Throwable) {
            // Ignore errors during cleanup
        }
    }

    // Remove test directory
    if (is_dir($this->testDir)) {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($this->testDir);
    }

    // Flush cache
    Cache::flush();
});

function startTracked(FileSessionManager $manager, array &$sessions, string $command, ?string $cwd = null, ?array $env = null): string
{
    $sessionId = $manager->start($command, $cwd, $env);
    $sessions[] = $sessionId;

    return $sessionId;
}

describe('FileSessionManager', function () {
    describe('isAvailable', function () {
        it('returns a boolean', function () {
            expect(FileSessionManager::isAvailable())->toBeBool();
        });
    });

    describe('start', function () {
        it('starts a process and returns a session ID', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo "hello"');

            expect($sessionId)->toBeString();
            expect($sessionId)->toHaveLength(12);
            expect($this->manager->hasSession($sessionId))->toBeTrue();
        });

        it('generates unique session IDs', function () {
            $ids = [];
            for ($i = 0; $i < 5; $i++) {
                $ids[] = startTracked($this->manager, $this->startedSessions, 'echo "test"');
            }

            expect(array_unique($ids))->toHaveCount(5);
        });

        it('creates session directory with required files', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 2');

            $sessionDir = $this->testDir.'/'.$sessionId;

            expect(is_dir($sessionDir))->toBeTrue();
            expect(file_exists($sessionDir.'/pid'))->toBeTrue();
            expect(file_exists($sessionDir.'/stdout'))->toBeTrue();
            expect(file_exists($sessionDir.'/stdin'))->toBeTrue();
        });

        it('starts process in specified working directory', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'pwd', '/tmp');

            usleep(200000);

            $output = $this->manager->getOutput($sessionId);

            expect(trim($output['stdout'] ?? ''))->toContain('/tmp');
        });

        it('passes environment variables to process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo $MY_TEST_VAR', null, ['MY_TEST_VAR' => 'hello_from_env']);

            usleep(200000);

            $output = $this->manager->getOutput($sessionId);

            expect(trim($output['stdout'] ?? ''))->toContain('hello_from_env');
        });
    });

    describe('getOutput', function () {
        it('returns null for non-existent session', function () {
            $output = $this->manager->getOutput('non-existent-id');

            expect($output)->toBeNull();
        });

        it('returns stdout from process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo "hello world"');

            usleep(200000);

            $output = $this->manager->getOutput($sessionId);

            expect($output)->not->toBeNull();
            expect($output['stdout'])->toContain('hello world');
            expect($output['stderr'])->toBe('');
        });

        it('returns incremental output (not cumulative)', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo "first"; sleep 0.2; echo "second"');

            usleep(100000);
            $first = $this->manager->getOutput($sessionId);

            usleep(300000);
            $second = $this->manager->getOutput($sessionId);

            $combined = ($first['stdout'] ?? '').($second['stdout'] ?? '');
            expect($combined)->toContain('first');
            expect($combined)->toContain('second');

            // Verify second call does not repeat first output
            if (str_contains($first['stdout'] ?? '', 'first')) {
                // If first call got "first", second should not repeat it
                expect($second['stdout'] ?? '')->not->toContain('first');
            }
        });

        it('returns empty string when no new output', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo "once"');

            usleep(200000);

            $this->manager->getOutput($sessionId);

            // Second call with no new output
            $output = $this->manager->getOutput($sessionId);

            expect($output['stdout'])->toBe('');
        });
    });

    describe('sendInput', function () {
        it('returns false for non-existent session', function () {
            $result = $this->manager->sendInput('non-existent-id', 'test');

            expect($result)->toBeFalse();
        });

        it('sends input to running process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, '/bin/bash -c "read LINE; echo GOT:\$LINE"');

            usleep(200000);

            // Drain any prompt output
            $this->manager->getOutput($sessionId);

            $result = $this->manager->sendInput($sessionId, 'hello');

            expect($result)->toBeTrue();

            usleep(300000);

            $output = $this->manager->getOutput($sessionId);

            expect($output['stdout'])->toContain('GOT:hello');
        });
    });

    describe('sendRawInput', function () {
        it('returns false for non-existent session', function () {
            $result = $this->manager->sendRawInput('non-existent-id', 'test');

            expect($result)->toBeFalse();
        });

        it('sends raw input without newline', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, '/bin/bash -c "read LINE; echo GOT:\$LINE"');

            usleep(200000);

            // Drain prompt output
            $this->manager->getOutput($sessionId);

            // Send text without newline, then send newline separately
            $this->manager->sendRawInput($sessionId, 'raw');
            $this->manager->sendRawInput($sessionId, "\n");

            usleep(300000);

            $output = $this->manager->getOutput($sessionId);

            expect($output['stdout'])->toContain('GOT:raw');
        });
    });

    describe('isRunning', function () {
        it('returns false for non-existent session', function () {
            expect($this->manager->isRunning('non-existent-id'))->toBeFalse();
        });

        it('returns true for running process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 5');

            usleep(100000);

            expect($this->manager->isRunning($sessionId))->toBeTrue();
        });

        it('returns false for finished process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo "done"');

            usleep(500000);

            expect($this->manager->isRunning($sessionId))->toBeFalse();
        });
    });

    describe('getExitCode', function () {
        it('returns null for non-existent session', function () {
            expect($this->manager->getExitCode('non-existent-id'))->toBeNull();
        });

        it('returns 0 for successful command', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'echo "success"');

            usleep(500000);

            $exitCode = $this->manager->getExitCode($sessionId);

            expect($exitCode)->toBe(0);
        });

        it('returns non-zero for failed command', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'exit 42');

            usleep(500000);

            $exitCode = $this->manager->getExitCode($sessionId);

            expect($exitCode)->toBe(42);
        });

        it('returns null for running process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 10');

            usleep(100000);

            expect($this->manager->getExitCode($sessionId))->toBeNull();
        });
    });

    describe('terminate', function () {
        it('terminates running process', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 60');

            usleep(200000);

            expect($this->manager->isRunning($sessionId))->toBeTrue();

            $result = $this->manager->terminate($sessionId);

            expect($result)->toBeTrue();
            expect($this->manager->hasSession($sessionId))->toBeFalse();

            // Remove from tracked since already terminated
            $this->startedSessions = array_diff($this->startedSessions, [$sessionId]);
        });

        it('removes session directory after termination', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 60');

            usleep(200000);

            $sessionDir = $this->testDir.'/'.$sessionId;
            expect(is_dir($sessionDir))->toBeTrue();

            $this->manager->terminate($sessionId);

            expect(is_dir($sessionDir))->toBeFalse();

            $this->startedSessions = array_diff($this->startedSessions, [$sessionId]);
        });

        it('removes cache entry after termination', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 60');

            usleep(200000);

            $this->manager->terminate($sessionId);

            expect($this->manager->hasSession($sessionId))->toBeFalse();
            expect($this->manager->getActiveSessionCount())->toBe(0);

            $this->startedSessions = array_diff($this->startedSessions, [$sessionId]);
        });
    });

    describe('cleanup', function () {
        it('removes expired sessions', function () {
            $this->manager->setMaxSessionLifetime(60);

            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 60');

            usleep(200000);

            // Manually expire the session by updating lastActivity in cache
            $cacheKey = 'swt:file:'.$sessionId;
            $data = Cache::get($cacheKey);
            $data['last_activity'] = time() - 120; // 2 minutes ago
            Cache::put($cacheKey, $data, 300);

            $this->manager->cleanup();

            expect($this->manager->hasSession($sessionId))->toBeFalse();

            $this->startedSessions = array_diff($this->startedSessions, [$sessionId]);
        });

        it('removes orphaned directories without cache entry', function () {
            // Create an orphaned directory (no cache entry)
            $orphanDir = $this->testDir.'/orphan-session';
            mkdir($orphanDir, 0700, true);
            file_put_contents($orphanDir.'/stdout', 'old output');

            $this->manager->cleanup();

            expect(is_dir($orphanDir))->toBeFalse();
        });
    });

    describe('getActiveSessionCount', function () {
        it('returns 0 when no sessions', function () {
            expect($this->manager->getActiveSessionCount())->toBe(0);
        });

        it('returns correct count of sessions', function () {
            startTracked($this->manager, $this->startedSessions, 'sleep 5');
            startTracked($this->manager, $this->startedSessions, 'sleep 5');
            startTracked($this->manager, $this->startedSessions, 'sleep 5');

            expect($this->manager->getActiveSessionCount())->toBe(3);
        });
    });

    describe('hasSession', function () {
        it('returns false for non-existent session', function () {
            expect($this->manager->hasSession('non-existent-id'))->toBeFalse();
        });

        it('returns true for existing session', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 2');

            expect($this->manager->hasSession($sessionId))->toBeTrue();
        });
    });

    describe('getSessionInfo', function () {
        it('returns null for non-existent session', function () {
            expect($this->manager->getSessionInfo('non-existent-id'))->toBeNull();
        });

        it('returns session info array', function () {
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 5');

            usleep(100000);

            $info = $this->manager->getSessionInfo($sessionId);

            expect($info)->toBeArray();
            expect($info)->toHaveKeys(['started_at', 'last_activity', 'is_running', 'exit_code', 'pid', 'backend']);
            expect($info['is_running'])->toBeTrue();
            expect($info['started_at'])->toBeInt();
            expect($info['pid'])->toBeInt();
            expect($info['backend'])->toBe('file');
        });
    });

    describe('setMaxSessionLifetime', function () {
        it('enforces minimum lifetime of 60 seconds', function () {
            // Set to 10 (below minimum of 60)
            $this->manager->setMaxSessionLifetime(10);

            // Start a long-running process
            $sessionId = startTracked($this->manager, $this->startedSessions, 'sleep 120');
            usleep(300000);

            // After 2 seconds, session should NOT be expired (minimum is 60s, not 10s)
            sleep(2);
            $this->manager->cleanup();

            // Session should still exist because min lifetime is 60, not 10
            expect($this->manager->hasSession($sessionId))->toBeTrue();
            expect($this->manager->isRunning($sessionId))->toBeTrue();

            $this->manager->terminate($sessionId);
        });

        it('returns fluent interface', function () {
            $result = $this->manager->setMaxSessionLifetime(300);

            expect($result)->toBeInstanceOf(FileSessionManager::class);
        });
    });

    describe('setSessionBaseDir', function () {
        it('sets and gets session base directory', function () {
            $dir = '/tmp/custom-dir';
            $this->manager->setSessionBaseDir($dir);

            expect($this->manager->getSessionBaseDir())->toBe($dir);
        });
    });

    describe('REPL interaction', function () {
        it('supports multiple rounds of stdin/stdout interaction', function () {
            // Use a while-read loop as a simple REPL simulator
            // This reads lines from stdin and echoes them back prefixed with "GOT:"
            $sessionId = startTracked(
                $this->manager,
                $this->startedSessions,
                '/bin/bash -c \'while IFS= read -r line; do echo "GOT:$line"; done\''
            );

            usleep(300000); // Wait for process to start

            // Round 1
            $this->manager->sendInput($sessionId, 'round1');
            usleep(300000);

            $output = $this->manager->getOutput($sessionId);
            expect($output['stdout'])->toContain('GOT:round1');

            // Round 2
            $this->manager->sendInput($sessionId, 'round2');
            usleep(300000);

            $output = $this->manager->getOutput($sessionId);
            expect($output['stdout'])->toContain('GOT:round2');

            // Round 3
            $this->manager->sendInput($sessionId, 'round3');
            usleep(300000);

            $output = $this->manager->getOutput($sessionId);
            expect($output['stdout'])->toContain('GOT:round3');

            // Process should still be running (waiting for more input)
            expect($this->manager->isRunning($sessionId))->toBeTrue();

            // Terminate
            $this->manager->terminate($sessionId);
            usleep(300000);
            expect($this->manager->isRunning($sessionId))->toBeFalse();
        });
    });
});
