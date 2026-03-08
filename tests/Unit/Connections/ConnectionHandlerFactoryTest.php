<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Connections\ConnectionHandlerFactory;
use MWGuerra\WebTerminal\Connections\LocalConnectionHandler;
use MWGuerra\WebTerminal\Connections\SSHConnectionHandler;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;

describe('ConnectionHandlerFactory', function () {
    beforeEach(function () {
        $this->factory = new ConnectionHandlerFactory;
    });

    describe('createHandler', function () {
        it('creates LocalConnectionHandler for Local type', function () {
            $handler = $this->factory->createHandler(ConnectionType::Local);

            expect($handler)->toBeInstanceOf(LocalConnectionHandler::class);
        });

        it('creates SSHConnectionHandler for SSH type', function () {
            $handler = $this->factory->createHandler(ConnectionType::SSH);

            expect($handler)->toBeInstanceOf(SSHConnectionHandler::class);
        });

        it('creates new instances on each call', function () {
            $handler1 = $this->factory->createHandler(ConnectionType::Local);
            $handler2 = $this->factory->createHandler(ConnectionType::Local);

            expect($handler1)->not->toBe($handler2);
        });
    });

    describe('make', function () {
        it('creates handler based on config type', function () {
            $config = ConnectionConfig::local();

            $handler = $this->factory->make($config);

            expect($handler)->toBeInstanceOf(LocalConnectionHandler::class);
        });

        it('creates SSH handler from config', function () {
            $config = ConnectionConfig::sshWithPassword(
                host: 'example.com',
                username: 'user',
                password: 'pass',
            );

            $handler = $this->factory->make($config);

            expect($handler)->toBeInstanceOf(SSHConnectionHandler::class);
        });
    });

    describe('createAndConnect', function () {
        it('creates and connects local handler', function () {
            $config = ConnectionConfig::local();

            $handler = $this->factory->createAndConnect($config);

            expect($handler)->toBeInstanceOf(LocalConnectionHandler::class);
            expect($handler->isConnected())->toBeTrue();
        });
    });

    describe('custom handlers', function () {
        it('has no custom handlers by default', function () {
            expect($this->factory->getCustomHandlers())->toBe([]);
            expect($this->factory->hasCustomHandler(ConnectionType::Local))->toBeFalse();
        });

        it('registers custom handler', function () {
            $this->factory->registerHandler(ConnectionType::Local, CustomLocalHandler::class);

            expect($this->factory->hasCustomHandler(ConnectionType::Local))->toBeTrue();
            expect($this->factory->getCustomHandlers())->toBe([
                'local' => CustomLocalHandler::class,
            ]);
        });

        it('uses custom handler when registered', function () {
            $this->factory->registerHandler(ConnectionType::Local, CustomLocalHandler::class);

            $handler = $this->factory->createHandler(ConnectionType::Local);

            expect($handler)->toBeInstanceOf(CustomLocalHandler::class);
        });

        it('unregisters custom handler', function () {
            $this->factory->registerHandler(ConnectionType::Local, CustomLocalHandler::class);
            $this->factory->unregisterHandler(ConnectionType::Local);

            expect($this->factory->hasCustomHandler(ConnectionType::Local))->toBeFalse();

            $handler = $this->factory->createHandler(ConnectionType::Local);
            expect($handler)->toBeInstanceOf(LocalConnectionHandler::class);
        });

        it('supports fluent registration', function () {
            $result = $this->factory
                ->registerHandler(ConnectionType::Local, CustomLocalHandler::class)
                ->registerHandler(ConnectionType::SSH, CustomLocalHandler::class);

            expect($result)->toBe($this->factory);
            expect($this->factory->hasCustomHandler(ConnectionType::Local))->toBeTrue();
            expect($this->factory->hasCustomHandler(ConnectionType::SSH))->toBeTrue();
        });
    });

    describe('convenience methods', function () {
        it('creates local handler via convenience method', function () {
            $handler = $this->factory->local();

            expect($handler)->toBeInstanceOf(LocalConnectionHandler::class);
        });

        it('creates ssh handler via convenience method', function () {
            $handler = $this->factory->ssh();

            expect($handler)->toBeInstanceOf(SSHConnectionHandler::class);
        });
    });
});

// Custom handler for testing
class CustomLocalHandler extends LocalConnectionHandler
{
    public bool $isCustom = true;
}
