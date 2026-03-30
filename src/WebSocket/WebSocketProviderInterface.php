<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

interface WebSocketProviderInterface
{
    public function start(string $host, int $port): void;

    public function stop(): void;

    public function sendToConnection(string $sessionId, string $data): void;
}
