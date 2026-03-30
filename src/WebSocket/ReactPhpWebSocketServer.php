<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\Socket\ConnectionInterface;

class ReactPhpWebSocketServer
{
    /** @var array<int, TerminalPtyBridge> Maps connection object ID to PTY bridge */
    private array $bridges = [];

    /** @var array<int, ConnectionInterface> Maps connection object ID to connection */
    private array $connections = [];

    /** @var array<int, MessageBuffer> Maps connection object ID to message buffer */
    private array $buffers = [];

    private PtySessionRegistry $registry;
    private Encrypter $encrypter;
    private array $config;
    private ServerNegotiator $negotiator;

    public function __construct(
        PtySessionRegistry $registry,
        Encrypter $encrypter,
        array $config,
    ) {
        $this->registry = $registry;
        $this->encrypter = $encrypter;
        $this->config = $config;
        $this->negotiator = new ServerNegotiator(
            new RequestVerifier(),
            new HttpFactory(),
        );
    }

    public function handleConnection(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        $httpBuffer = '';

        $conn->on('data', function (string $data) use ($conn, $id, &$httpBuffer) {
            // If we haven't completed the handshake yet
            if (! isset($this->buffers[$id])) {
                $httpBuffer .= $data;
                $this->handleHandshake($conn, $id, $httpBuffer);

                return;
            }

            // Feed data to WebSocket message buffer
            $this->buffers[$id]->onData($data);
        });

        $conn->on('close', function () use ($id) {
            $this->handleClose($id);
        });

        $conn->on('error', function (\Exception $e) use ($conn, $id) {
            $conn->close();
            $this->handleClose($id);
        });
    }

    private function handleHandshake(ConnectionInterface $conn, int $id, string $httpBuffer): void
    {
        // Only try to parse once we have a full HTTP request (ends with double CRLF)
        if (strpos($httpBuffer, "\r\n\r\n") === false) {
            return;
        }

        try {
            $request = Message::parseRequest($httpBuffer);
        } catch (\Throwable $e) {
            $conn->close();

            return;
        }

        // Negotiate WebSocket upgrade
        $response = $this->negotiator->handshake($request);

        if ($response->getStatusCode() !== 101) {
            $conn->write(Message::toString($response));
            $conn->close();

            return;
        }

        // Extract and validate token
        $query = $request->getUri()->getQuery();
        parse_str($query, $params);
        $token = $params['token'] ?? null;

        if (! $token) {
            $conn->close();

            return;
        }

        try {
            $payload = json_decode($this->encrypter->decrypt($token), true);
        } catch (\Exception $e) {
            $conn->close();

            return;
        }

        if (! $payload || ($payload['exp'] ?? 0) < time()) {
            $conn->close();

            return;
        }

        $sessionId = $payload['sessionId'];
        $userId = $payload['userId'];

        // Retrieve connection config from cache (one-time use)
        $configData = Cache::pull("terminal-pty:{$sessionId}");
        if ($configData === null) {
            $conn->close();

            return;
        }

        // Send the upgrade response
        $conn->write(Message::toString($response));

        // Create PTY bridge
        $connectionConfig = ConnectionConfig::fromArray($configData);
        $shell = $this->config['shell'] ?? '/bin/bash';

        $bridge = new TerminalPtyBridge($connectionConfig, $sessionId, $userId, $this->registry);
        $bridge->start($shell);

        $this->bridges[$id] = $bridge;
        $this->connections[$id] = $conn;

        // Set up WebSocket message buffer for this connection.
        // expectMask = true because browser clients always mask frames.
        $this->buffers[$id] = new MessageBuffer(
            new CloseFrameChecker(),
            function ($msg) use ($id) {
                $this->handleMessage($id, $msg->getPayload());
            },
            function ($frame) use ($conn, $id) {
                if ($frame->getOpcode() === Frame::OP_CLOSE) {
                    $conn->close();
                    $this->handleClose($id);
                }
            },
            true, // expectMask: browser clients always send masked frames
        );
    }

    private function handleMessage(int $id, string $payload): void
    {
        $bridge = $this->bridges[$id] ?? null;
        if ($bridge === null) {
            return;
        }

        // Check for resize messages
        $decoded = @json_decode($payload, true);
        if ($decoded !== null && ($decoded['type'] ?? null) === 'resize') {
            $bridge->resize((int) $decoded['cols'], (int) $decoded['rows']);

            return;
        }

        // Raw terminal input
        $bridge->write($payload);
    }

    private function handleClose(int $id): void
    {
        $bridge = $this->bridges[$id] ?? null;
        if ($bridge !== null) {
            $bridge->terminate();
        }

        unset($this->bridges[$id], $this->connections[$id], $this->buffers[$id]);
    }

    /**
     * Called periodically to stream PTY output to WebSocket clients.
     */
    public function tick(): void
    {
        foreach ($this->bridges as $id => $bridge) {
            if (! $bridge->isRunning()) {
                continue;
            }

            $output = $bridge->read();
            if ($output === '') {
                continue;
            }

            $conn = $this->connections[$id] ?? null;
            if ($conn !== null) {
                // Send as unmasked WebSocket text frame (server -> client frames are NOT masked)
                $frame = new Frame($output, true, Frame::OP_TEXT);
                $conn->write($frame->getContents());
            }
        }
    }
}
