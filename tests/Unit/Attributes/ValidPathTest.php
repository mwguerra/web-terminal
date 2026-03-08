<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Attributes\ValidPath;

describe('ValidPath', function () {
    it('validates absolute paths', function () {
        $validator = new ValidPath;

        expect($validator->validate('/home/user/file.txt'))->toBeTrue();
        expect($validator->validate('/var/log/app.log'))->toBeTrue();
        expect($validator->validate('/etc/hosts'))->toBeTrue();
    });

    it('validates Windows absolute paths', function () {
        $validator = new ValidPath;

        expect($validator->validate('C:\\Users\\User\\file.txt'))->toBeTrue();
        expect($validator->validate('D:/Projects/code'))->toBeTrue();
    });

    it('rejects relative paths by default', function () {
        $validator = new ValidPath;

        expect($validator->validate('relative/path'))->toBeFalse();
        expect($validator->validate('./current/path'))->toBeFalse();
    });

    it('can allow relative paths', function () {
        $validator = new ValidPath(allowRelative: true);

        expect($validator->validate('relative/path'))->toBeTrue();
        expect($validator->validate('./current/path'))->toBeTrue();
    });

    it('blocks path traversal by default', function () {
        $validator = new ValidPath(allowRelative: true);

        expect($validator->validate('../parent/file'))->toBeFalse();
        expect($validator->validate('/home/../etc/passwd'))->toBeFalse();
        expect($validator->validate('/var/log/../../etc/passwd'))->toBeFalse();
        expect($validator->validate('..\\parent\\file'))->toBeFalse();
    });

    it('can allow path traversal', function () {
        $validator = new ValidPath(allowRelative: true, blockTraversal: false);

        expect($validator->validate('../parent/file'))->toBeTrue();
        expect($validator->validate('/home/../etc/passwd'))->toBeTrue();
    });

    it('blocks null bytes', function () {
        $validator = new ValidPath;

        expect($validator->validate("/home/user\0/file"))->toBeFalse();
    });

    it('blocks control characters', function () {
        $validator = new ValidPath;

        expect($validator->validate("/home/user\n/file"))->toBeFalse();
        expect($validator->validate("/home/user\r/file"))->toBeFalse();
    });

    it('rejects empty values', function () {
        $validator = new ValidPath;

        expect($validator->validate(''))->toBeFalse();
        expect($validator->validate(null))->toBeFalse();
    });

    it('has custom message', function () {
        $validator = new ValidPath(message: 'Invalid path');

        expect($validator->message)->toBe('Invalid path');
    });

    it('is a readonly class with attribute target', function () {
        $reflection = new ReflectionClass(ValidPath::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($reflection->isReadOnly())->toBeTrue();
        expect($attributes)->toHaveCount(1);
    });
});
