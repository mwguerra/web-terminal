<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TerminalWebSocketController extends Controller
{
    public function generateToken(Request $request): JsonResponse
    {
        $sessionId = Str::uuid()->toString();
        $config = $request->input('connectionConfig', []);
        $ttl = config('web-terminal.stream.signed_url_ttl', 300);

        // Store connection config in cache (one-time retrieval)
        Cache::put("terminal-pty:{$sessionId}", $config, $ttl);

        $payload = json_encode([
            'userId' => $request->user()->id,
            'sessionId' => $sessionId,
            'exp' => time() + $ttl,
        ]);

        $token = app('encrypter')->encrypt($payload);

        $host = config('web-terminal.stream.ratchet_host', '127.0.0.1');
        $port = config('web-terminal.stream.ratchet_port', 8090);
        $protocol = $request->isSecure() ? 'wss' : 'ws';

        return response()->json([
            'token' => $token,
            'url' => "{$protocol}://{$host}:{$port}?token=" . urlencode($token),
            'sessionId' => $sessionId,
        ]);
    }
}
