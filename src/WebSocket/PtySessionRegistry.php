<?php
declare(strict_types=1);

namespace MWGuerra\WebTerminal\WebSocket;

class PtySessionRegistry
{
    private string $registryPath;

    public function __construct(string $storagePath)
    {
        $this->registryPath = rtrim($storagePath, '/') . '/pty-sessions.json';
    }

    public function register(string $sessionId, int $pid, int $userId): void
    {
        $sessions = $this->all();
        $sessions[$sessionId] = [
            'pid' => $pid,
            'userId' => $userId,
            'createdAt' => time(),
        ];
        $this->save($sessions);
    }

    public function unregister(string $sessionId): void
    {
        $sessions = $this->all();
        unset($sessions[$sessionId]);
        $this->save($sessions);
    }

    public function find(string $sessionId): ?array
    {
        return $this->all()[$sessionId] ?? null;
    }

    public function all(): array
    {
        if (! file_exists($this->registryPath)) {
            return [];
        }

        $content = file_get_contents($this->registryPath);
        if ($content === false || $content === '') {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    public function cleanupStale(int $maxLifetimeSeconds): array
    {
        $sessions = $this->all();
        $stale = [];
        $now = time();

        foreach ($sessions as $sessionId => $session) {
            if ($now - $session['createdAt'] > $maxLifetimeSeconds) {
                $stale[$sessionId] = $session;
                unset($sessions[$sessionId]);
            }
        }

        $this->save($sessions);

        return $stale;
    }

    private function save(array $sessions): void
    {
        $dir = dirname($this->registryPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->registryPath,
            json_encode($sessions, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
