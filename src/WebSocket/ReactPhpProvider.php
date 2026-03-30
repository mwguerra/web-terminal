<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

use Illuminate\Contracts\Foundation\Application;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

class ReactPhpProvider implements WebSocketProviderInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function start(string $host, int $port): void
    {
        $config = $this->app['config']->get('web-terminal.ghostty', []);

        $registry = new PtySessionRegistry(
            $this->app->storagePath('web-terminal')
        );

        // Cleanup orphaned PIDs from previous crashes
        $stale = $registry->cleanupStale($config['max_session_lifetime'] ?? 3600);
        foreach ($stale as $session) {
            if ($session['pid'] > 0 && posix_kill($session['pid'], 0)) {
                posix_kill($session['pid'], 9);
            }
        }

        $server = new ReactPhpWebSocketServer(
            $registry,
            $this->app['encrypter'],
            $config,
        );

        $loop = Loop::get();

        $sslCert = $config['ssl_cert'] ?? null;
        $sslKey = $config['ssl_key'] ?? null;
        $context = [];

        if ($sslCert && $sslKey && file_exists($sslCert) && file_exists($sslKey)) {
            $uri = "tls://{$host}:{$port}";
            $context = [
                'tls' => [
                    'local_cert' => $sslCert,
                    'local_pk' => $sslKey,
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                ],
            ];
        } else {
            $uri = "{$host}:{$port}";
        }

        $socket = new SocketServer($uri, $context, $loop);

        $socket->on('connection', function (\React\Socket\ConnectionInterface $conn) use ($server) {
            $server->handleConnection($conn);
        });

        // Periodic PTY output streaming (every 10ms)
        $loop->addPeriodicTimer(0.01, function () use ($server) {
            $server->tick();
        });

        // Periodic cleanup (every 60s)
        $loop->addPeriodicTimer(60, function () use ($registry, $config) {
            $stale = $registry->cleanupStale($config['max_session_lifetime'] ?? 3600);
            foreach ($stale as $session) {
                if ($session['pid'] > 0 && posix_kill($session['pid'], 0)) {
                    posix_kill($session['pid'], 9);
                }
            }
        });

        $loop->run();
    }

    public function stop(): void
    {
        Loop::get()->stop();
    }

    public function sendToConnection(string $sessionId, string $data): void
    {
        // Not used directly — the ReactPhpWebSocketServer handles output streaming via tick()
    }
}
