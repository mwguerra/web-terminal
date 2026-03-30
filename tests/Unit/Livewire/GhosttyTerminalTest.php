<?php

declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Livewire\GhosttyTerminal;

describe('GhosttyTerminal', function () {
    it('can be mounted with default parameters', function () {
        Livewire::test(GhosttyTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'ghosttyTheme' => [],
            'showWindowControls' => true,
        ])->assertStatus(200);
    });

    it('has locked connection config', function () {
        $component = Livewire::test(GhosttyTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'ghosttyTheme' => [],
            'showWindowControls' => true,
        ]);

        $component->assertStatus(200);
        expect($component->get('isConnected'))->toBeFalse();
    });

    it('renders the ghostty terminal view', function () {
        Livewire::test(GhosttyTerminal::class, [
            'connectionConfig' => ['type' => 'local'],
            'height' => '400px',
            'title' => 'Test Terminal',
            'ghosttyTheme' => [],
            'showWindowControls' => true,
        ])->assertViewIs('web-terminal::ghostty-terminal');
    });
});
