<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use MWGuerra\WebTerminal\Exceptions\RateLimitException;
use MWGuerra\WebTerminal\Security\RateLimiter;

beforeEach(function () {
    // Clear any existing rate limits
    RateLimiterFacade::clear('secure-terminal:test-user');
    RateLimiterFacade::clear('secure-terminal:user-1');
    RateLimiterFacade::clear('secure-terminal:user-2');
});

describe('RateLimiter', function () {
    describe('basic limiting', function () {
        it('allows requests within limit', function () {
            $limiter = new RateLimiter(maxAttempts: 3, decaySeconds: 60);

            expect($limiter->isLimited('test-user'))->toBeFalse();
            expect($limiter->remainingAttempts('test-user'))->toBe(3);
        });

        it('limits requests after max attempts', function () {
            $limiter = new RateLimiter(maxAttempts: 2, decaySeconds: 60);

            // First two attempts should succeed
            $limiter->hit('test-user');
            expect($limiter->isLimited('test-user'))->toBeFalse();

            $limiter->hit('test-user');
            expect($limiter->isLimited('test-user'))->toBeTrue();
        });

        it('tracks remaining attempts', function () {
            $limiter = new RateLimiter(maxAttempts: 3, decaySeconds: 60);

            expect($limiter->remainingAttempts('test-user'))->toBe(3);

            $limiter->hit('test-user');
            expect($limiter->remainingAttempts('test-user'))->toBe(2);

            $limiter->hit('test-user');
            expect($limiter->remainingAttempts('test-user'))->toBe(1);

            $limiter->hit('test-user');
            expect($limiter->remainingAttempts('test-user'))->toBe(0);
        });

        it('isolates limits per user', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);

            $limiter->hit('user-1');

            expect($limiter->isLimited('user-1'))->toBeTrue();
            expect($limiter->isLimited('user-2'))->toBeFalse();
        });
    });

    describe('attempt method', function () {
        it('executes callback when not limited', function () {
            $limiter = new RateLimiter(maxAttempts: 2, decaySeconds: 60);
            $executed = false;

            $result = $limiter->attempt('test-user', function () use (&$executed) {
                $executed = true;

                return 'success';
            });

            expect($executed)->toBeTrue();
            expect($result)->toBe('success');
        });

        it('does not execute callback when limited', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);
            $limiter->hit('test-user');

            $executed = false;
            $result = $limiter->attempt('test-user', function () use (&$executed) {
                $executed = true;

                return 'success';
            });

            expect($executed)->toBeFalse();
            expect($result)->toBeNull();
        });

        it('executes onLimited callback when rate limited', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);
            $limiter->hit('test-user');

            $result = $limiter->attempt(
                'test-user',
                fn () => 'success',
                fn ($retryAfter) => "limited:{$retryAfter}"
            );

            expect($result)->toStartWith('limited:');
        });

        it('increments hit count on successful attempt', function () {
            $uniqueKey = 'attempt-test-'.uniqid();
            $limiter = new RateLimiter(maxAttempts: 3, decaySeconds: 60);

            expect($limiter->remainingAttempts($uniqueKey))->toBe(3);

            $limiter->attempt($uniqueKey, fn () => 'test');

            expect($limiter->remainingAttempts($uniqueKey))->toBe(2);
        });
    });

    describe('clear', function () {
        it('resets rate limit for a key', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);
            $limiter->hit('test-user');

            expect($limiter->isLimited('test-user'))->toBeTrue();

            $limiter->clear('test-user');

            expect($limiter->isLimited('test-user'))->toBeFalse();
            expect($limiter->remainingAttempts('test-user'))->toBe(1);
        });

        it('returns self for chaining', function () {
            $limiter = new RateLimiter;

            $result = $limiter->clear('test-user');

            expect($result)->toBe($limiter);
        });
    });

    describe('enabled/disabled', function () {
        it('can be disabled', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60, enabled: false);
            $limiter->hit('test-user');

            expect($limiter->isLimited('test-user'))->toBeFalse();
        });

        it('can be enabled after disabled', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60, enabled: false);
            $limiter->enable();

            $limiter->hit('test-user');

            expect($limiter->isLimited('test-user'))->toBeTrue();
        });

        it('can be toggled', function () {
            $limiter = new RateLimiter;

            expect($limiter->isEnabled())->toBeTrue();

            $limiter->disable();
            expect($limiter->isEnabled())->toBeFalse();

            $limiter->enable();
            expect($limiter->isEnabled())->toBeTrue();
        });

        it('always allows attempts when disabled', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60, enabled: false);

            expect($limiter->remainingAttempts('test-user'))->toBe(1);
            expect($limiter->retryAfter('test-user'))->toBe(0);
        });
    });

    describe('configuration', function () {
        it('can set max attempts', function () {
            $limiter = new RateLimiter;

            $limiter->setMaxAttempts(5);

            expect($limiter->getMaxAttempts())->toBe(5);
        });

        it('enforces minimum of 1 for max attempts', function () {
            $limiter = new RateLimiter;

            $limiter->setMaxAttempts(0);

            expect($limiter->getMaxAttempts())->toBe(1);
        });

        it('can set decay seconds', function () {
            $limiter = new RateLimiter;

            $limiter->setDecaySeconds(30);

            expect($limiter->getDecaySeconds())->toBe(30);
        });

        it('enforces minimum of 1 for decay seconds', function () {
            $limiter = new RateLimiter;

            $limiter->setDecaySeconds(0);

            expect($limiter->getDecaySeconds())->toBe(1);
        });

        it('can set custom prefix', function () {
            $limiter = new RateLimiter;

            $limiter->setPrefix('custom-prefix');

            expect($limiter->getPrefix())->toBe('custom-prefix');
        });
    });

    describe('getInfo', function () {
        it('returns rate limit information', function () {
            $limiter = new RateLimiter(maxAttempts: 3, decaySeconds: 60);
            $limiter->hit('test-user');

            $info = $limiter->getInfo('test-user');

            expect($info)->toBeArray();
            expect($info)->toHaveKeys(['remaining', 'limit', 'retry_after']);
            expect($info['remaining'])->toBe(2);
            expect($info['limit'])->toBe(3);
        });
    });

    describe('retryAfter', function () {
        it('returns 0 when not limited', function () {
            $limiter = new RateLimiter(maxAttempts: 3, decaySeconds: 60);

            expect($limiter->retryAfter('test-user'))->toBe(0);
        });

        it('returns seconds until reset when limited', function () {
            $limiter = new RateLimiter(maxAttempts: 1, decaySeconds: 60);
            $limiter->hit('test-user');

            $retryAfter = $limiter->retryAfter('test-user');

            expect($retryAfter)->toBeGreaterThan(0);
            expect($retryAfter)->toBeLessThanOrEqual(60);
        });
    });
});

describe('RateLimitException', function () {
    it('creates exception with retry information', function () {
        $exception = RateLimitException::tooManyAttempts(30, 5);

        expect($exception->getRetryAfter())->toBe(30);
        expect($exception->getMaxAttempts())->toBe(5);
        expect($exception->getCode())->toBe(429);
    });

    it('provides user-friendly message', function () {
        $exception = RateLimitException::tooManyAttempts(5);

        expect($exception->getUserMessage())->toContain('5 seconds');
    });

    it('provides short message for 1 second wait', function () {
        $exception = RateLimitException::tooManyAttempts(1);

        expect($exception->getUserMessage())->toBe(
            'Please wait a moment before sending another command.'
        );
    });

    it('provides HTTP headers', function () {
        $exception = RateLimitException::tooManyAttempts(30, 5);

        $headers = $exception->getHeaders();

        expect($headers)->toHaveKey('Retry-After');
        expect($headers)->toHaveKey('X-RateLimit-Limit');
        expect($headers)->toHaveKey('X-RateLimit-Remaining');
        expect($headers['Retry-After'])->toBe('30');
        expect($headers['X-RateLimit-Limit'])->toBe('5');
        expect($headers['X-RateLimit-Remaining'])->toBe('0');
    });
});
