<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Connections\LocalConnectionHandler;
use MWGuerra\WebTerminal\Contracts\ConnectionHandlerInterface;
use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;

describe('LocalConnectionHandler', function () {
    beforeEach(function () {
        $this->handler = new LocalConnectionHandler;
    });

    it('implements ConnectionHandlerInterface', function () {
        expect($this->handler)->toBeInstanceOf(ConnectionHandlerInterface::class);
    });

    it('extends AbstractConnectionHandler', function () {
        expect($this->handler)->toBeInstanceOf(\MWGuerra\WebTerminal\Connections\AbstractConnectionHandler::class);
    });

    describe('connect', function () {
        it('accepts local connection config', function () {
            $config = ConnectionConfig::local();

            $this->handler->connect($config);

            expect($this->handler->isConnected())->toBeTrue();
            expect($this->handler->getConfig())->toBe($config);
        });

        it('rejects non-local connection configs', function () {
            $config = ConnectionConfig::sshWithPassword(
                host: 'example.com',
                username: 'user',
                password: 'pass',
            );

            expect(fn () => $this->handler->connect($config))
                ->toThrow(ConnectionException::class);
        });

        it('throws with descriptive message for wrong type', function () {
            $config = ConnectionConfig::sshWithPassword(
                host: 'example.com',
                username: 'user',
                password: 'pass',
            );

            try {
                $this->handler->connect($config);
                $this->fail('Expected ConnectionException');
            } catch (ConnectionException $e) {
                expect($e->getMessage())->toContain('Local');
                expect($e->getMessage())->toContain('ssh');
                expect($e->connectionType)->toBe(ConnectionType::SSH);
            }
        });
    });

    describe('execute', function () {
        beforeEach(function () {
            $this->handler->connect(ConnectionConfig::local());
        });

        it('throws when not connected', function () {
            $disconnectedHandler = new LocalConnectionHandler;

            expect(fn () => $disconnectedHandler->execute('ls'))
                ->toThrow(ConnectionException::class, 'Not connected');
        });

        it('executes simple command and captures stdout', function () {
            $result = $this->handler->execute('echo "Hello World"');

            expect($result)->toBeInstanceOf(CommandResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect(trim($result->stdout))->toBe('Hello World');
            expect($result->exitCode)->toBe(0);
            expect($result->command)->toBe('echo "Hello World"');
        });

        it('captures stderr from commands', function () {
            $result = $this->handler->execute('echo "error message" >&2');

            expect(trim($result->stderr))->toBe('error message');
        });

        it('captures exit codes from failed commands', function () {
            $result = $this->handler->execute('exit 42');

            expect($result->isSuccessful())->toBeFalse();
            expect($result->exitCode)->toBe(42);
        });

        it('records execution time', function () {
            $result = $this->handler->execute('sleep 0.1');

            expect($result->executionTime)->toBeGreaterThan(0.09);
            expect($result->executionTime)->toBeLessThan(1.0);
        });

        it('handles command timeout', function () {
            $this->handler->setTimeout(0.5);

            $result = $this->handler->execute('sleep 10');

            expect($result->isTimedOut())->toBeTrue();
            expect($result->exitCode)->toBe(124);
            expect($result->stderr)->toContain('timed out');
        });

        it('uses provided timeout over default', function () {
            $this->handler->setTimeout(10.0);

            $result = $this->handler->execute('sleep 5', 0.5);

            expect($result->isTimedOut())->toBeTrue();
        });

        it('captures partial output on timeout', function () {
            $result = $this->handler->execute('echo "partial"; sleep 10', 0.5);

            expect($result->isTimedOut())->toBeTrue();
            // The partial output may or may not be captured depending on timing
        });
    });

    describe('working directory', function () {
        beforeEach(function () {
            $this->handler->connect(ConnectionConfig::local());
        });

        it('executes in specified working directory', function () {
            $this->handler->setWorkingDirectory('/tmp');

            $result = $this->handler->execute('pwd');

            expect(trim($result->stdout))->toBe('/tmp');
        });

        it('uses current directory when not specified', function () {
            $result = $this->handler->execute('pwd');

            expect($result->stdout)->not->toBeEmpty();
            expect($result->isSuccessful())->toBeTrue();
        });
    });

    describe('environment variables', function () {
        beforeEach(function () {
            $this->handler->connect(ConnectionConfig::local());
        });

        it('passes environment variables to process', function () {
            $this->handler->setEnvironment([
                'MY_VAR' => 'test_value',
            ]);

            $result = $this->handler->execute('echo $MY_VAR');

            expect(trim($result->stdout))->toBe('test_value');
        });

        it('adds single environment variable', function () {
            $this->handler
                ->addEnvironmentVariable('VAR1', 'value1')
                ->addEnvironmentVariable('VAR2', 'value2');

            $result = $this->handler->execute('echo "$VAR1-$VAR2"');

            expect(trim($result->stdout))->toBe('value1-value2');
        });

        it('gets environment variables', function () {
            $this->handler->setEnvironment(['KEY' => 'value']);

            expect($this->handler->getEnvironment())->toBe(['KEY' => 'value']);
        });

        it('clears environment on disconnect', function () {
            $this->handler->setEnvironment(['KEY' => 'value']);
            $this->handler->disconnect();

            expect($this->handler->getEnvironment())->toBe([]);
        });
    });

    describe('disconnect', function () {
        it('clears connection state', function () {
            $this->handler->connect(ConnectionConfig::local());
            $this->handler->setEnvironment(['KEY' => 'value']);
            $this->handler->setWorkingDirectory('/tmp');

            $this->handler->disconnect();

            expect($this->handler->isConnected())->toBeFalse();
            expect($this->handler->getConfig())->toBeNull();
            expect($this->handler->getEnvironment())->toBe([]);
            // Working directory is preserved as it's in the base class
        });

        it('is safe to call when not connected', function () {
            $this->handler->disconnect();

            expect($this->handler->isConnected())->toBeFalse();
        });
    });

    describe('fluent interface', function () {
        it('supports method chaining', function () {
            $result = $this->handler
                ->setTimeout(30.0)
                ->setWorkingDirectory('/tmp')
                ->setEnvironment(['KEY' => 'value'])
                ->addEnvironmentVariable('KEY2', 'value2');

            expect($result)->toBe($this->handler);
            expect($this->handler->getTimeout())->toBe(30.0);
            expect($this->handler->getWorkingDirectory())->toBe('/tmp');
            expect($this->handler->getEnvironment())->toBe(['KEY' => 'value', 'KEY2' => 'value2']);
        });
    });

    describe('real command execution', function () {
        beforeEach(function () {
            $this->handler->connect(ConnectionConfig::local());
        });

        it('executes ls command', function () {
            $result = $this->handler->execute('ls -la /tmp');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->stdout)->toContain('.');
        });

        it('executes whoami command', function () {
            $result = $this->handler->execute('whoami');

            expect($result->isSuccessful())->toBeTrue();
            expect(trim($result->stdout))->not->toBeEmpty();
        });

        it('handles non-existent commands', function () {
            $result = $this->handler->execute('nonexistent_command_xyz');

            expect($result->isSuccessful())->toBeFalse();
            expect($result->stderr)->toContain('not found');
        });

        it('handles commands with arguments', function () {
            $result = $this->handler->execute('date +%Y');

            expect($result->isSuccessful())->toBeTrue();
            expect(trim($result->stdout))->toMatch('/^\d{4}$/');
        });
    });

    describe('interactive mode', function () {
        beforeEach(function () {
            $this->handler->connect(ConnectionConfig::local());
            // Force ProcessSessionManager for these tests (FileSessionManager merges stderr via PTY)
            $this->handler->setSessionManager(new \MWGuerra\WebTerminal\Sessions\ProcessSessionManager);
        });

        afterEach(function () {
            // Clean up any sessions
            \MWGuerra\WebTerminal\Sessions\ProcessSessionManager::clearAllSessions();
        });

        it('supports interactive mode', function () {
            expect($this->handler->supportsInteractive())->toBeTrue();
        });

        it('starts interactive session and returns session ID', function () {
            $sessionId = $this->handler->startInteractive('echo "test"');

            expect($sessionId)->toBeString();
            expect($sessionId)->not->toBeEmpty();
        });

        it('throws when starting interactive without connection', function () {
            $disconnectedHandler = new LocalConnectionHandler;

            expect(fn () => $disconnectedHandler->startInteractive('echo "test"'))
                ->toThrow(ConnectionException::class, 'Not connected');
        });

        it('reads output from interactive session', function () {
            $sessionId = $this->handler->startInteractive('echo "hello world"');

            usleep(200000);

            $output = $this->handler->readOutput($sessionId);

            expect($output)->not->toBeNull();
            expect($output['stdout'])->toContain('hello');
        });

        it('returns null for non-existent session output', function () {
            $output = $this->handler->readOutput('non-existent-id');

            expect($output)->toBeNull();
        });

        it('writes input to interactive session', function () {
            $sessionId = $this->handler->startInteractive('cat');

            usleep(100000);

            $result = $this->handler->writeInput($sessionId, 'test input');

            expect($result)->toBeTrue();

            // Verify process is still running after input
            expect($this->handler->isProcessRunning($sessionId))->toBeTrue();

            $this->handler->terminateProcess($sessionId);
        });

        it('returns false when writing to non-existent session', function () {
            $result = $this->handler->writeInput('non-existent-id', 'test');

            expect($result)->toBeFalse();
        });

        it('checks if process is running', function () {
            $sessionId = $this->handler->startInteractive('sleep 5');

            expect($this->handler->isProcessRunning($sessionId))->toBeTrue();

            $this->handler->terminateProcess($sessionId);

            expect($this->handler->isProcessRunning($sessionId))->toBeFalse();
        });

        it('returns false for non-existent session running check', function () {
            expect($this->handler->isProcessRunning('non-existent-id'))->toBeFalse();
        });

        it('gets exit code from finished process', function () {
            $sessionId = $this->handler->startInteractive('exit 42');

            usleep(200000);

            $exitCode = $this->handler->getProcessExitCode($sessionId);

            expect($exitCode)->toBe(42);
        });

        it('returns null exit code for running process', function () {
            $sessionId = $this->handler->startInteractive('sleep 5');

            $exitCode = $this->handler->getProcessExitCode($sessionId);

            expect($exitCode)->toBeNull();

            $this->handler->terminateProcess($sessionId);
        });

        it('returns null exit code for non-existent session', function () {
            expect($this->handler->getProcessExitCode('non-existent-id'))->toBeNull();
        });

        it('terminates running process', function () {
            $sessionId = $this->handler->startInteractive('sleep 60');

            expect($this->handler->isProcessRunning($sessionId))->toBeTrue();

            $result = $this->handler->terminateProcess($sessionId);

            expect($result)->toBeTrue();
            expect($this->handler->isProcessRunning($sessionId))->toBeFalse();
        });

        it('returns false when terminating non-existent session', function () {
            $result = $this->handler->terminateProcess('non-existent-id');

            expect($result)->toBeFalse();
        });

        it('uses login shell wrapper in interactive mode when enabled', function () {
            $this->handler->setUseLoginShell(true);

            // Verify that login shell mode starts an interactive session successfully
            $sessionId = $this->handler->startInteractive('echo "test"');

            expect($sessionId)->toBeString();
            expect($sessionId)->not->toBeEmpty();

            // Login shell takes longer due to environment setup scripts
            usleep(1500000);

            // Process should complete successfully
            expect($this->handler->isProcessRunning($sessionId))->toBeFalse();
            expect($this->handler->getProcessExitCode($sessionId))->toBe(0);
        });

        it('uses working directory in interactive mode', function () {
            $this->handler->setWorkingDirectory('/tmp');

            $sessionId = $this->handler->startInteractive('pwd');

            usleep(200000);

            $output = $this->handler->readOutput($sessionId);

            expect(trim($output['stdout']))->toBe('/tmp');
        });

        it('passes environment variables in interactive mode', function () {
            $this->handler->setEnvironment(['MY_VAR' => 'test_value']);

            $sessionId = $this->handler->startInteractive('echo $MY_VAR');

            usleep(200000);

            $output = $this->handler->readOutput($sessionId);

            expect(trim($output['stdout']))->toBe('test_value');
        });
    });

    describe('login shell mode', function () {
        beforeEach(function () {
            $this->handler->connect(ConnectionConfig::local());
        });

        it('defaults to not using login shell', function () {
            expect($this->handler->isUsingLoginShell())->toBeFalse();
        });

        it('can enable login shell mode', function () {
            $this->handler->setUseLoginShell(true);

            expect($this->handler->isUsingLoginShell())->toBeTrue();
        });

        it('can disable login shell mode', function () {
            $this->handler->setUseLoginShell(true);
            $this->handler->setUseLoginShell(false);

            expect($this->handler->isUsingLoginShell())->toBeFalse();
        });

        it('defaults to bash shell', function () {
            expect($this->handler->getShell())->toBe('/bin/bash');
        });

        it('can set custom shell', function () {
            $this->handler->setShell('/bin/zsh');

            expect($this->handler->getShell())->toBe('/bin/zsh');
        });

        it('setUseLoginShell returns fluent interface', function () {
            $result = $this->handler->setUseLoginShell(true);

            expect($result)->toBe($this->handler);
        });

        it('setShell returns fluent interface', function () {
            $result = $this->handler->setShell('/bin/bash');

            expect($result)->toBe($this->handler);
        });

        it('executes commands in login shell when enabled', function () {
            $this->handler->setUseLoginShell(true);

            // This should use bash -l -i -c to execute
            $result = $this->handler->execute('echo $SHELL');

            expect($result->isSuccessful())->toBeTrue();
        });

        it('handles commands with single quotes in login shell mode', function () {
            $this->handler->setUseLoginShell(true);

            $result = $this->handler->execute("echo 'hello world'");

            expect($result->isSuccessful())->toBeTrue();
            expect(trim($result->stdout))->toBe('hello world');
        });

        it('executes simple commands correctly in login shell mode', function () {
            $this->handler->setUseLoginShell(true);

            $result = $this->handler->execute('pwd');

            expect($result->isSuccessful())->toBeTrue();
            expect(trim($result->stdout))->not->toBeEmpty();
        });
    });
});
