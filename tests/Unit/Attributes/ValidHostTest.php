<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Attributes\ValidHost;

describe('ValidHost', function () {
    it('validates localhost', function () {
        $validator = new ValidHost;

        expect($validator->validate('localhost'))->toBeTrue();
        expect($validator->validate('127.0.0.1'))->toBeTrue();
        expect($validator->validate('::1'))->toBeTrue();
    });

    it('can disable localhost', function () {
        $validator = new ValidHost(allowLocalhost: false);

        expect($validator->validate('localhost'))->toBeFalse();
        expect($validator->validate('127.0.0.1'))->toBeFalse();
    });

    it('validates IPv4 addresses', function () {
        $validator = new ValidHost;

        expect($validator->validate('192.168.1.1'))->toBeTrue();
        expect($validator->validate('10.0.0.1'))->toBeTrue();
        expect($validator->validate('255.255.255.255'))->toBeTrue();
        // Note: 999.999.999.999 is not a valid IP but matches hostname pattern
    });

    it('validates IPv6 addresses', function () {
        $validator = new ValidHost;

        expect($validator->validate('2001:0db8:85a3:0000:0000:8a2e:0370:7334'))->toBeTrue();
        expect($validator->validate('::1'))->toBeTrue();
        expect($validator->validate('fe80::1'))->toBeTrue();
    });

    it('can disable IPv6', function () {
        $validator = new ValidHost(allowIpv6: false, allowLocalhost: false);

        expect($validator->validate('::1'))->toBeFalse();
        expect($validator->validate('fe80::1'))->toBeFalse();
    });

    it('validates hostnames', function () {
        $validator = new ValidHost;

        expect($validator->validate('example.com'))->toBeTrue();
        expect($validator->validate('sub.domain.example.com'))->toBeTrue();
        expect($validator->validate('my-server.local'))->toBeTrue();
        expect($validator->validate('server1'))->toBeTrue();
    });

    it('rejects invalid hostnames', function () {
        $validator = new ValidHost;

        expect($validator->validate('-invalid.com'))->toBeFalse();
        expect($validator->validate('invalid-.com'))->toBeFalse();
        expect($validator->validate(''))->toBeFalse();
        expect($validator->validate(null))->toBeFalse();
    });

    it('has custom message', function () {
        $validator = new ValidHost(message: 'Custom message');

        expect($validator->message)->toBe('Custom message');
    });

    it('is a readonly class with attribute target', function () {
        $reflection = new ReflectionClass(ValidHost::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($reflection->isReadOnly())->toBeTrue();
        expect($attributes)->toHaveCount(1);
    });
});
