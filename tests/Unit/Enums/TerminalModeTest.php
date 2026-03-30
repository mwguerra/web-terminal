<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalMode;

describe('TerminalMode', function () {
    it('has classic and ghostty cases', function () {
        expect(TerminalMode::cases())->toHaveCount(2);
        expect(TerminalMode::Classic->value)->toBe('classic');
        expect(TerminalMode::Ghostty->value)->toBe('ghostty');
    });

    it('returns labels', function () {
        expect(TerminalMode::Classic->label())->toBe('Classic');
        expect(TerminalMode::Ghostty->label())->toBe('Ghostty');
    });

    it('returns descriptions', function () {
        expect(TerminalMode::Classic->description())->toBeString()->not->toBeEmpty();
        expect(TerminalMode::Ghostty->description())->toBeString()->not->toBeEmpty();
    });

    it('provides options array', function () {
        $options = TerminalMode::options();
        expect($options)->toBeArray()->toHaveCount(2);
        expect($options['classic'])->toBe('Classic');
        expect($options['ghostty'])->toBe('Ghostty');
    });
});
