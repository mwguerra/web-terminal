<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StreamTerminal extends Component
{
    public bool $isConnected = false;
    public string $height = '400px';
    public string $title = 'Terminal';
    public bool $showWindowControls = true;
    public bool $hasModePill = false;
    public bool $autoConnect = false;

    #[Locked]
    public array $streamTheme = [];

    #[Locked]
    public array $connectionConfig = [];

    #[Locked]
    public string $componentId = '';

    #[Locked]
    public array $scripts = [];

    public function mount(
        array $connectionConfig = [],
        string $height = '400px',
        string $title = 'Terminal',
        array $streamTheme = [],
        bool $showWindowControls = true,
        bool $hasModePill = false,
        bool $autoConnect = false,
        array $scripts = [],
    ): void {
        $this->connectionConfig = $connectionConfig;
        $this->height = $height;
        $this->title = $title;
        $this->streamTheme = $streamTheme;
        $this->showWindowControls = $showWindowControls;
        $this->hasModePill = $hasModePill;
        $this->autoConnect = $autoConnect;
        $this->scripts = $scripts;
        $this->componentId = 'stream-' . Str::random(8);
    }

    public function getWebSocketUrl(): array
    {
        if (Gate::has('useStreamTerminal') && ! Gate::allows('useStreamTerminal')) {
            return ['error' => 'Unauthorized'];
        }

        $sessionId = Str::uuid()->toString();
        $ttl = config('web-terminal.stream.signed_url_ttl', 300);

        Cache::put("terminal-pty:{$sessionId}", $this->connectionConfig, $ttl);

        $payload = json_encode([
            'userId' => auth()->id(),
            'sessionId' => $sessionId,
            'exp' => time() + $ttl,
        ]);

        $token = app('encrypter')->encrypt($payload);
        $encodedToken = urlencode($token);

        $wsUrl = config('web-terminal.stream.websocket_url');
        if ($wsUrl) {
            $separator = str_contains($wsUrl, '?') ? '&' : '?';
            $url = "{$wsUrl}{$separator}token={$encodedToken}";
        } else {
            $host = config('web-terminal.stream.ratchet_host', '127.0.0.1');
            $port = config('web-terminal.stream.ratchet_port', 8090);
            $url = "ws://{$host}:{$port}?token={$encodedToken}";
        }

        return [
            'token' => $token,
            'url' => $url,
            'sessionId' => $sessionId,
        ];
    }

    public function connect(): void
    {
        $this->isConnected = true;
    }

    public function disconnect(): void
    {
        $this->isConnected = false;
    }

    public function getScriptsForExecution(string $key): array
    {
        foreach ($this->scripts as $script) {
            if (($script['key'] ?? '') === $key) {
                return $script['commands'] ?? [];
            }
        }

        return [];
    }

    public function render()
    {
        return view('web-terminal::stream-terminal');
    }
}
