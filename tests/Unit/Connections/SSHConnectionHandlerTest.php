<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Connections\SSHConnectionHandler;
use MWGuerra\WebTerminal\Contracts\ConnectionHandlerInterface;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;

describe('SSHConnectionHandler', function () {
    beforeEach(function () {
        $this->handler = new SSHConnectionHandler;
    });

    it('implements ConnectionHandlerInterface', function () {
        expect($this->handler)->toBeInstanceOf(ConnectionHandlerInterface::class);
    });

    it('extends AbstractConnectionHandler', function () {
        expect($this->handler)->toBeInstanceOf(\MWGuerra\WebTerminal\Connections\AbstractConnectionHandler::class);
    });

    describe('connect', function () {
        it('rejects non-SSH connection configs', function () {
            $config = ConnectionConfig::local();

            expect(fn () => $this->handler->connect($config))
                ->toThrow(ConnectionException::class);
        });

        it('throws with descriptive message for wrong type', function () {
            $config = ConnectionConfig::local();

            try {
                $this->handler->connect($config);
                $this->fail('Expected ConnectionException');
            } catch (ConnectionException $e) {
                expect($e->getMessage())->toContain('SSH');
                expect($e->getMessage())->toContain('local');
                expect($e->connectionType)->toBe(ConnectionType::Local);
            }
        });

        it('requires a host', function () {
            // ConnectionConfig validates at construction time
            expect(fn () => new ConnectionConfig(
                type: ConnectionType::SSH,
                host: '',
                username: 'user',
                password: 'pass',
            ))->toThrow(InvalidArgumentException::class, 'Host is required');
        });

        it('requires a username', function () {
            // ConnectionConfig validates at construction time
            expect(fn () => new ConnectionConfig(
                type: ConnectionType::SSH,
                host: 'example.com',
                username: '',
                password: 'pass',
            ))->toThrow(InvalidArgumentException::class, 'Username is required');
        });

        it('handles connection failure gracefully', function () {
            // This will fail because the host doesn't exist
            $config = ConnectionConfig::sshWithPassword(
                host: 'nonexistent.invalid.host.example',
                username: 'user',
                password: 'pass',
            );

            expect(fn () => $this->handler->connect($config))
                ->toThrow(ConnectionException::class);

            expect($this->handler->isConnected())->toBeFalse();
        });
    });

    describe('execute', function () {
        it('throws when not connected', function () {
            expect(fn () => $this->handler->execute('ls'))
                ->toThrow(ConnectionException::class, 'Not connected');
        });
    });

    describe('environment variables', function () {
        it('sets environment variables', function () {
            $this->handler->setEnvironment(['KEY' => 'value']);

            expect($this->handler->getEnvironment())->toBe(['KEY' => 'value']);
        });

        it('adds single environment variable', function () {
            $this->handler
                ->addEnvironmentVariable('VAR1', 'value1')
                ->addEnvironmentVariable('VAR2', 'value2');

            expect($this->handler->getEnvironment())->toBe([
                'VAR1' => 'value1',
                'VAR2' => 'value2',
            ]);
        });

        it('clears environment on disconnect', function () {
            $this->handler->setEnvironment(['KEY' => 'value']);
            $this->handler->disconnect();

            expect($this->handler->getEnvironment())->toBe([]);
        });
    });

    describe('reconnection settings', function () {
        it('has default reconnection attempts', function () {
            expect($this->handler->getMaxReconnectAttempts())->toBe(3);
        });

        it('sets max reconnection attempts', function () {
            $result = $this->handler->setMaxReconnectAttempts(5);

            expect($result)->toBe($this->handler);
            expect($this->handler->getMaxReconnectAttempts())->toBe(5);
        });

        it('enforces minimum of 0 for reconnection attempts', function () {
            $this->handler->setMaxReconnectAttempts(-5);

            expect($this->handler->getMaxReconnectAttempts())->toBe(0);
        });

        it('has default reconnect delay', function () {
            expect($this->handler->getReconnectDelay())->toBe(1.0);
        });

        it('sets reconnect delay', function () {
            $result = $this->handler->setReconnectDelay(2.5);

            expect($result)->toBe($this->handler);
            expect($this->handler->getReconnectDelay())->toBe(2.5);
        });

        it('enforces minimum reconnect delay of 0.1 seconds', function () {
            $this->handler->setReconnectDelay(0.01);

            expect($this->handler->getReconnectDelay())->toBe(0.1);
        });
    });

    describe('disconnect', function () {
        it('clears connection state', function () {
            $this->handler->setEnvironment(['KEY' => 'value']);

            $this->handler->disconnect();

            expect($this->handler->isConnected())->toBeFalse();
            expect($this->handler->getConfig())->toBeNull();
            expect($this->handler->getEnvironment())->toBe([]);
        });

        it('is safe to call when not connected', function () {
            $this->handler->disconnect();

            expect($this->handler->isConnected())->toBeFalse();
        });

        it('can be called multiple times', function () {
            $this->handler->disconnect();
            $this->handler->disconnect();
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
                ->addEnvironmentVariable('KEY2', 'value2')
                ->setMaxReconnectAttempts(5)
                ->setReconnectDelay(2.0);

            expect($result)->toBe($this->handler);
            expect($this->handler->getTimeout())->toBe(30.0);
            expect($this->handler->getWorkingDirectory())->toBe('/tmp');
            expect($this->handler->getEnvironment())->toBe(['KEY' => 'value', 'KEY2' => 'value2']);
            expect($this->handler->getMaxReconnectAttempts())->toBe(5);
            expect($this->handler->getReconnectDelay())->toBe(2.0);
        });
    });

    describe('getSSHConnection', function () {
        it('returns null when not connected', function () {
            expect($this->handler->getSSHConnection())->toBeNull();
        });
    });
});

describe('SSHConnectionHandler buildCommandWithEnvironment', function () {
    it('returns command unchanged when no environment variables', function () {
        $handler = new class extends SSHConnectionHandler
        {
            public function exposeBuildCommandWithEnvironment(string $command): string
            {
                return $this->buildCommandWithEnvironment($command);
            }
        };

        $result = $handler->exposeBuildCommandWithEnvironment('ls -la');

        expect($result)->toBe('ls -la');
    });

    it('prepends environment exports to command', function () {
        $handler = new class extends SSHConnectionHandler
        {
            public function exposeBuildCommandWithEnvironment(string $command): string
            {
                return $this->buildCommandWithEnvironment($command);
            }
        };

        $handler->setEnvironment(['MY_VAR' => 'test_value']);

        $result = $handler->exposeBuildCommandWithEnvironment('echo $MY_VAR');

        expect($result)->toContain('export');
        expect($result)->toContain('MY_VAR');
        expect($result)->toContain('test_value');
        expect($result)->toContain('echo $MY_VAR');
    });

    it('handles multiple environment variables', function () {
        $handler = new class extends SSHConnectionHandler
        {
            public function exposeBuildCommandWithEnvironment(string $command): string
            {
                return $this->buildCommandWithEnvironment($command);
            }
        };

        $handler->setEnvironment([
            'VAR1' => 'value1',
            'VAR2' => 'value2',
        ]);

        $result = $handler->exposeBuildCommandWithEnvironment('test');

        expect($result)->toContain('VAR1');
        expect($result)->toContain('VAR2');
        expect($result)->toContain('; test');
    });
});

describe('SSHConnectionHandler isConnectionError', function () {
    it('detects connection errors', function () {
        $handler = new class extends SSHConnectionHandler
        {
            public function exposeIsConnectionError(\Throwable $e): bool
            {
                return $this->isConnectionError($e);
            }
        };

        expect($handler->exposeIsConnectionError(new \Exception('Connection reset')))->toBeTrue();
        expect($handler->exposeIsConnectionError(new \Exception('Socket error')))->toBeTrue();
        expect($handler->exposeIsConnectionError(new \Exception('Broken pipe')))->toBeTrue();
        expect($handler->exposeIsConnectionError(new \Exception('Reset by peer')))->toBeTrue();
        expect($handler->exposeIsConnectionError(new \Exception('Command not found')))->toBeFalse();
        expect($handler->exposeIsConnectionError(new \Exception('Permission denied')))->toBeFalse();
    });
});
