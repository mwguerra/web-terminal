<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Connections;

use MWGuerra\WebTerminal\Contracts\ConnectionHandlerInterface;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Enums\ConnectionType;
use MWGuerra\WebTerminal\Exceptions\ConnectionException;

/**
 * Factory for creating connection handlers based on connection type.
 *
 * This factory implements the Factory Method pattern to instantiate
 * the appropriate connection handler based on the ConnectionType enum.
 */
class ConnectionHandlerFactory
{
    /**
     * Custom handler class mappings.
     *
     * @var array<string, class-string<ConnectionHandlerInterface>>
     */
    protected array $customHandlers = [];

    /**
     * Create a connection handler for the given configuration.
     *
     * @param  ConnectionConfig  $config  The connection configuration
     * @return ConnectionHandlerInterface The appropriate handler for the connection type
     *
     * @throws ConnectionException If no handler exists for the connection type
     */
    public function make(ConnectionConfig $config): ConnectionHandlerInterface
    {
        return $this->createHandler($config->type);
    }

    /**
     * Create a connection handler for the given connection type.
     *
     * @param  ConnectionType  $type  The type of connection
     * @return ConnectionHandlerInterface The appropriate handler for the connection type
     *
     * @throws ConnectionException If no handler exists for the connection type
     */
    public function createHandler(ConnectionType $type): ConnectionHandlerInterface
    {
        // Check for custom handler first
        if (isset($this->customHandlers[$type->value])) {
            return new $this->customHandlers[$type->value];
        }

        return match ($type) {
            ConnectionType::Local => new LocalConnectionHandler,
            ConnectionType::SSH => new SSHConnectionHandler,
        };
    }

    /**
     * Create a handler and connect it using the provided configuration.
     *
     * @param  ConnectionConfig  $config  The connection configuration
     * @return ConnectionHandlerInterface A connected handler instance
     *
     * @throws ConnectionException If connection fails
     */
    public function createAndConnect(ConnectionConfig $config): ConnectionHandlerInterface
    {
        $handler = $this->make($config);
        $handler->connect($config);

        return $handler;
    }

    /**
     * Register a custom handler class for a connection type.
     *
     * This allows extending the factory with custom handler implementations.
     *
     * @param  ConnectionType  $type  The connection type
     * @param  class-string<ConnectionHandlerInterface>  $handlerClass  The handler class
     * @return $this
     */
    public function registerHandler(ConnectionType $type, string $handlerClass): static
    {
        $this->customHandlers[$type->value] = $handlerClass;

        return $this;
    }

    /**
     * Remove a custom handler registration.
     *
     * @param  ConnectionType  $type  The connection type to unregister
     * @return $this
     */
    public function unregisterHandler(ConnectionType $type): static
    {
        unset($this->customHandlers[$type->value]);

        return $this;
    }

    /**
     * Check if a custom handler is registered for a type.
     *
     * @param  ConnectionType  $type  The connection type
     */
    public function hasCustomHandler(ConnectionType $type): bool
    {
        return isset($this->customHandlers[$type->value]);
    }

    /**
     * Get all registered custom handlers.
     *
     * @return array<string, class-string<ConnectionHandlerInterface>>
     */
    public function getCustomHandlers(): array
    {
        return $this->customHandlers;
    }

    /**
     * Create a local connection handler.
     */
    public function local(): LocalConnectionHandler
    {
        return new LocalConnectionHandler;
    }

    /**
     * Create an SSH connection handler.
     */
    public function ssh(): SSHConnectionHandler
    {
        return new SSHConnectionHandler;
    }
}
