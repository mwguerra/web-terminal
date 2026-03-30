<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

describe('TerminalWebSocketController', function () {
    it('generates an encrypted token with correct payload', function () {
        $user = new \Illuminate\Foundation\Auth\User;
        $user->id = 42;
        $this->actingAs($user);

        $response = $this->postJson(route('terminal.ws-token'), [
            'connectionConfig' => ['type' => 'local'],
        ]);

        $response->assertOk();
        $data = $response->json();
        expect($data)->toHaveKeys(['token', 'url', 'sessionId']);

        // Verify token can be decrypted
        $payload = json_decode(app('encrypter')->decrypt($data['token']), true);
        expect($payload['userId'])->toBe(42);
        expect($payload['sessionId'])->toBe($data['sessionId']);
        expect($payload['exp'])->toBeGreaterThan(time());
    });

    it('caches connection config for the session', function () {
        $user = new \Illuminate\Foundation\Auth\User;
        $user->id = 1;
        $this->actingAs($user);

        $response = $this->postJson(route('terminal.ws-token'), [
            'connectionConfig' => ['type' => 'local', 'timeout' => 30],
        ]);

        $sessionId = $response->json('sessionId');
        $cached = Cache::get("terminal-pty:{$sessionId}");
        expect($cached)->toBe(['type' => 'local', 'timeout' => 30]);
    });

    it('requires authentication', function () {
        $response = $this->postJson(route('terminal.ws-token'));
        $response->assertUnauthorized();
    });
});
