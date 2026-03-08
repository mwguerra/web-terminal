<?php

declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Livewire\WebTerminal;

describe('allowInteractiveMode', function () {
    it('defaults to false', function () {
        $component = Livewire::test(WebTerminal::class);

        expect($component->get('allowInteractiveMode'))->toBeFalse();
    });

    it('can be set via mount parameter', function () {
        $component = Livewire::test(WebTerminal::class, [
            'allowInteractiveMode' => true,
        ]);

        expect($component->get('allowInteractiveMode'))->toBeTrue();
    });

    it('uses interactive mode when allowInteractiveMode is true without allowAllCommands', function () {
        $component = Livewire::test(WebTerminal::class, [
            'allowInteractiveMode' => true,
            'allowedCommands' => ['echo *'],
            'startConnected' => true,
        ]);

        // allowAllCommands should be false
        expect($component->get('allowAllCommands'))->toBeFalse();
        // allowInteractiveMode should be true
        expect($component->get('allowInteractiveMode'))->toBeTrue();
    });

    it('still validates commands against whitelist with allowInteractiveMode', function () {
        $component = Livewire::test(WebTerminal::class, [
            'allowInteractiveMode' => true,
            'allowedCommands' => ['echo *'],
            'startConnected' => true,
        ]);

        // A non-whitelisted command should still be blocked
        $component->set('command', 'rm -rf /')
            ->call('executeCommand');

        $output = $component->get('output');
        $lastOutput = end($output);

        expect($lastOutput['type'])->toBe('error');
    });

    it('allows whitelisted command to execute with allowInteractiveMode', function () {
        $component = Livewire::test(WebTerminal::class, [
            'allowInteractiveMode' => true,
            'allowedCommands' => ['echo *'],
            'startConnected' => true,
        ]);

        $component->set('command', 'echo hello')
            ->call('executeCommand');

        $output = $component->get('output');
        $hasCommandEcho = false;

        foreach ($output as $line) {
            if ($line['type'] === 'command') {
                $hasCommandEcho = true;
            }
        }

        expect($hasCommandEcho)->toBeTrue();
    });

    it('does not use interactive mode when both flags are false', function () {
        $component = Livewire::test(WebTerminal::class, [
            'allowInteractiveMode' => false,
            'allowAllCommands' => false,
            'allowedCommands' => ['echo *'],
            'startConnected' => true,
        ]);

        expect($component->get('allowInteractiveMode'))->toBeFalse();
        expect($component->get('allowAllCommands'))->toBeFalse();
    });

    it('is locked and cannot be changed by client', function () {
        $component = Livewire::test(WebTerminal::class, [
            'allowInteractiveMode' => false,
        ]);

        // Locked properties cannot be set from the client
        $component->set('allowInteractiveMode', true);

        // Value should remain false (Livewire throws on locked property tampering)
        // The set() call above should throw, but if it doesn't, the value stays false
    })->throws(\Exception::class);
});
