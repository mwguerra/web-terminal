<?php
declare(strict_types=1);

use MWGuerra\WebTerminal\WebSocket\ReactPhpProvider;
use MWGuerra\WebTerminal\WebSocket\WebSocketProviderInterface;

describe('ReactPhpProvider', function () {
    it('implements WebSocketProviderInterface', function () {
        $provider = new ReactPhpProvider(app());
        expect($provider)->toBeInstanceOf(WebSocketProviderInterface::class);
    });
});
