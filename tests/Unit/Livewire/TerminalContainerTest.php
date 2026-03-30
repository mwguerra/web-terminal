<?php

declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Livewire\TerminalContainer;

describe('TerminalContainer', function () {
    it('can be mounted with both modes', function () {
        Livewire::test(TerminalContainer::class, [
            'classicParams' => ['allowedCommands' => ['ls']],
            'ghosttyParams' => ['ghosttyTheme' => []],
            'defaultMode' => 'classic',
            'height' => '400px',
            'title' => 'Terminal',
            'showWindowControls' => true,
        ])->assertStatus(200);
    });

    it('renders the container view', function () {
        Livewire::test(TerminalContainer::class, [
            'classicParams' => ['allowedCommands' => ['ls']],
            'ghosttyParams' => ['ghosttyTheme' => []],
            'defaultMode' => 'classic',
            'height' => '400px',
            'title' => 'Terminal',
            'showWindowControls' => true,
        ])->assertViewIs('web-terminal::terminal-container');
    });

    it('passes default mode to view', function () {
        Livewire::test(TerminalContainer::class, [
            'classicParams' => [],
            'ghosttyParams' => [],
            'defaultMode' => 'ghostty',
            'height' => '400px',
            'title' => 'Terminal',
            'showWindowControls' => true,
        ])->assertSet('defaultMode', 'ghostty');
    });
});
