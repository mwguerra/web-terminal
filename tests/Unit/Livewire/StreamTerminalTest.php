<?php

declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Livewire\StreamTerminal;

describe('StreamTerminal', function () {
    it('can be mounted with default parameters', function () {
        Livewire::test(StreamTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'streamTheme' => [],
            'showWindowControls' => true,
        ])->assertStatus(200);
    });

    it('has locked connection config', function () {
        $component = Livewire::test(StreamTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'streamTheme' => [],
            'showWindowControls' => true,
        ]);

        $component->assertStatus(200);
        expect($component->get('isConnected'))->toBeFalse();
    });

    it('renders the stream terminal view', function () {
        Livewire::test(StreamTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'streamTheme' => [],
            'showWindowControls' => true,
        ])->assertViewIs('web-terminal::stream-terminal');
    });
});
