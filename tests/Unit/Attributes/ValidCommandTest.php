<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Attributes\ValidCommand;

describe('ValidCommand', function () {
    it('validates simple commands', function () {
        $validator = new ValidCommand;

        expect($validator->validate('ls'))->toBeTrue();
        expect($validator->validate('pwd'))->toBeTrue();
        expect($validator->validate('whoami'))->toBeTrue();
        expect($validator->validate('ls -la'))->toBeTrue();
        expect($validator->validate('cat /etc/hosts'))->toBeTrue();
    });

    it('blocks command separators', function () {
        $validator = new ValidCommand;

        expect($validator->validate('ls; rm -rf /'))->toBeFalse();
        expect($validator->validate('ls && rm -rf /'))->toBeFalse();
        expect($validator->validate('ls || rm -rf /'))->toBeFalse();
    });

    it('blocks pipes by default', function () {
        $validator = new ValidCommand;

        expect($validator->validate('ls | grep txt'))->toBeFalse();
    });

    it('can allow pipes', function () {
        $validator = new ValidCommand(allowPipes: true);

        expect($validator->validate('ls | grep txt'))->toBeTrue();
    });

    it('blocks redirection by default', function () {
        $validator = new ValidCommand;

        expect($validator->validate('echo test > file.txt'))->toBeFalse();
        expect($validator->validate('cat < input.txt'))->toBeFalse();
        expect($validator->validate('echo test >> file.txt'))->toBeFalse();
    });

    it('can allow redirection', function () {
        $validator = new ValidCommand(allowRedirection: true);

        expect($validator->validate('echo test > file.txt'))->toBeTrue();
        expect($validator->validate('cat < input.txt'))->toBeTrue();
    });

    it('blocks chaining by default', function () {
        $validator = new ValidCommand;

        expect($validator->validate('ls; rm -rf /'))->toBeFalse();
        expect($validator->validate('sleep 100 &'))->toBeFalse();
        expect($validator->validate('ls || echo fail'))->toBeFalse();
        expect($validator->validate('ls && echo ok'))->toBeFalse();
    });

    it('can allow chaining', function () {
        $validator = new ValidCommand(allowChaining: true);

        expect($validator->validate('ls; echo done'))->toBeTrue();
        expect($validator->validate('sleep 1 &'))->toBeTrue();
        expect($validator->validate('ls || echo fail'))->toBeTrue();
        expect($validator->validate('ls && echo ok'))->toBeTrue();
    });

    it('allowing chaining does not allow expansion', function () {
        $validator = new ValidCommand(allowChaining: true);

        expect($validator->validate('echo $(whoami)'))->toBeFalse();
        expect($validator->validate('echo `whoami`'))->toBeFalse();
        expect($validator->validate('echo ${PATH}'))->toBeFalse();
    });

    it('blocks expansion by default', function () {
        $validator = new ValidCommand;

        expect($validator->validate('echo $(whoami)'))->toBeFalse();
        expect($validator->validate('echo `whoami`'))->toBeFalse();
        expect($validator->validate('echo ${PATH}'))->toBeFalse();
    });

    it('can allow expansion', function () {
        $validator = new ValidCommand(allowExpansion: true);

        expect($validator->validate('echo $(whoami)'))->toBeTrue();
        expect($validator->validate('echo `whoami`'))->toBeTrue();
        expect($validator->validate('echo ${HOME}'))->toBeTrue();
    });

    it('allowing expansion does not allow chaining', function () {
        $validator = new ValidCommand(allowExpansion: true);

        expect($validator->validate('ls; rm -rf /'))->toBeFalse();
        expect($validator->validate('sleep 100 &'))->toBeFalse();
    });

    it('blocks newlines', function () {
        $validator = new ValidCommand;

        expect($validator->validate("ls\nrm -rf /"))->toBeFalse();
        expect($validator->validate("ls\rrm -rf /"))->toBeFalse();
    });

    it('blocks null bytes', function () {
        $validator = new ValidCommand;

        expect($validator->validate("ls\0rm"))->toBeFalse();
    });

    it('enforces max length', function () {
        $validator = new ValidCommand(maxLength: 10);

        expect($validator->validate('ls -la'))->toBeTrue();
        expect($validator->validate('very long command that exceeds limit'))->toBeFalse();
    });

    it('rejects empty commands', function () {
        $validator = new ValidCommand;

        expect($validator->validate(''))->toBeFalse();
        expect($validator->validate(null))->toBeFalse();
    });

    it('extracts base command', function () {
        expect(ValidCommand::extractBaseCommand('ls -la /home'))->toBe('ls');
        expect(ValidCommand::extractBaseCommand('  git status  '))->toBe('git');
        expect(ValidCommand::extractBaseCommand('cat'))->toBe('cat');
    });

    it('returns dangerous characters list', function () {
        $validator = new ValidCommand;
        $chars = $validator->getDangerousCharacters();

        expect($chars)->toBeArray();
        expect($chars)->toContain(';');
        expect($chars)->toContain('|');
        expect($chars)->toContain('&');
    });

    it('has custom message', function () {
        $validator = new ValidCommand(message: 'Unsafe command');

        expect($validator->message)->toBe('Unsafe command');
    });

    it('is a readonly class with attribute target', function () {
        $reflection = new ReflectionClass(ValidCommand::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($reflection->isReadOnly())->toBeTrue();
        expect($attributes)->toHaveCount(1);
    });
});
