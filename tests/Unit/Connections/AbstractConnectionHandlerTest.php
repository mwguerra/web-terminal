<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Connections\AbstractConnectionHandler;
use MWGuerra\WebTerminal\Contracts\ConnectionHandlerInterface;
use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;

// Create a concrete implementation for testing
class TestableConnectionHandler extends AbstractConnectionHandler
{
    public bool $shouldConnect = true;

    public function connect(ConnectionConfig $config): void
    {
        if ($this->shouldConnect) {
            $this->markConnected($config);
        }
    }

    public function execute(string $command, ?float $timeout = null): CommandResult
    {
        return CommandResult::success(
            stdout: "Executed: {$command}",
            executionTime: $this->getEffectiveTimeout($timeout),
            command: $command,
        );
    }

    public function disconnect(): void
    {
        $this->markDisconnected();
    }

    // Expose protected methods for testing
    public function exposeGetEffectiveTimeout(?float $timeout): float
    {
        return $this->getEffectiveTimeout($timeout);
    }

    public function exposeMarkConnected(ConnectionConfig $config): void
    {
        $this->markConnected($config);
    }

    public function exposeMarkDisconnected(): void
    {
        $this->markDisconnected();
    }
}

describe('AbstractConnectionHandler', function () {
    beforeEach(function () {
        $this->handler = new TestableConnectionHandler;
    });

    it('implements ConnectionHandlerInterface', function () {
        expect($this->handler)->toBeInstanceOf(ConnectionHandlerInterface::class);
    });

    it('starts disconnected', function () {
        expect($this->handler->isConnected())->toBeFalse();
        expect($this->handler->getConfig())->toBeNull();
    });

    it('has default timeout of 10 seconds', function () {
        expect($this->handler->getTimeout())->toBe(10.0);
    });

    it('has no default working directory', function () {
        expect($this->handler->getWorkingDirectory())->toBeNull();
    });

    describe('setTimeout', function () {
        it('sets timeout and returns self for chaining', function () {
            $result = $this->handler->setTimeout(30.0);

            expect($result)->toBe($this->handler);
            expect($this->handler->getTimeout())->toBe(30.0);
        });

        it('enforces minimum timeout of 0.1 seconds', function () {
            $this->handler->setTimeout(0.05);
            expect($this->handler->getTimeout())->toBe(0.1);

            $this->handler->setTimeout(-5);
            expect($this->handler->getTimeout())->toBe(0.1);
        });
    });

    describe('setWorkingDirectory', function () {
        it('sets working directory and returns self for chaining', function () {
            $result = $this->handler->setWorkingDirectory('/home/user');

            expect($result)->toBe($this->handler);
            expect($this->handler->getWorkingDirectory())->toBe('/home/user');
        });

        it('accepts null to clear working directory', function () {
            $this->handler->setWorkingDirectory('/home/user');
            $this->handler->setWorkingDirectory(null);

            expect($this->handler->getWorkingDirectory())->toBeNull();
        });
    });

    describe('getEffectiveTimeout', function () {
        it('returns provided timeout when given', function () {
            $this->handler->setTimeout(10.0);

            expect($this->handler->exposeGetEffectiveTimeout(30.0))->toBe(30.0);
        });

        it('returns default timeout when null', function () {
            $this->handler->setTimeout(15.0);

            expect($this->handler->exposeGetEffectiveTimeout(null))->toBe(15.0);
        });
    });

    describe('markConnected', function () {
        it('marks handler as connected with config', function () {
            $config = ConnectionConfig::local();
            $this->handler->exposeMarkConnected($config);

            expect($this->handler->isConnected())->toBeTrue();
            expect($this->handler->getConfig())->toBe($config);
        });
    });

    describe('markDisconnected', function () {
        it('marks handler as disconnected and clears config', function () {
            $config = ConnectionConfig::local();
            $this->handler->exposeMarkConnected($config);
            $this->handler->exposeMarkDisconnected();

            expect($this->handler->isConnected())->toBeFalse();
            expect($this->handler->getConfig())->toBeNull();
        });
    });

    describe('connect and disconnect flow', function () {
        it('properly tracks connection state', function () {
            $config = ConnectionConfig::local();

            expect($this->handler->isConnected())->toBeFalse();

            $this->handler->connect($config);
            expect($this->handler->isConnected())->toBeTrue();
            expect($this->handler->getConfig())->toBe($config);

            $this->handler->disconnect();
            expect($this->handler->isConnected())->toBeFalse();
            expect($this->handler->getConfig())->toBeNull();
        });
    });

    describe('execute', function () {
        it('uses effective timeout in command execution', function () {
            $config = ConnectionConfig::local();
            $this->handler->connect($config);
            $this->handler->setTimeout(5.0);

            // Test with default timeout
            $result = $this->handler->execute('test');
            expect($result->executionTime)->toBe(5.0);

            // Test with custom timeout
            $result = $this->handler->execute('test', 15.0);
            expect($result->executionTime)->toBe(15.0);
        });
    });
});
