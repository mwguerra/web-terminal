<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalMode;

describe('TerminalMode', function () {
    it('has classic and stream cases', function () {
        expect(TerminalMode::cases())->toHaveCount(2);
        expect(TerminalMode::Classic->value)->toBe('classic');
        expect(TerminalMode::Stream->value)->toBe('stream');
    });

    it('returns labels', function () {
        expect(TerminalMode::Classic->label())->toBe('Classic');
        expect(TerminalMode::Stream->label())->toBe('Stream');
    });

    it('returns descriptions', function () {
        expect(TerminalMode::Classic->description())->toBeString()->not->toBeEmpty();
        expect(TerminalMode::Stream->description())->toBeString()->not->toBeEmpty();
    });

    it('provides options array', function () {
        $options = TerminalMode::options();
        expect($options)->toBeArray()->toHaveCount(2);
        expect($options['classic'])->toBe('Classic');
        expect($options['stream'])->toBe('Stream');
    });
});
