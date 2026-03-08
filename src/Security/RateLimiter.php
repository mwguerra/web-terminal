<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Security;

use Illuminate\Cache\RateLimiter as LaravelRateLimiter;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

/**
 * Rate limiter for terminal command execution.
 *
 * This service throttles command executions to prevent abuse,
 * implementing a per-user rate limit with configurable thresholds.
 */
class RateLimiter
{
    /**
     * The rate limiter key prefix.
     */
    protected string $prefix = 'secure-terminal';

    /**
     * Maximum attempts allowed within the decay period.
     */
    protected int $maxAttempts = 1;

    /**
     * Decay period in seconds.
     */
    protected int $decaySeconds = 1;

    /**
     * Whether rate limiting is enabled.
     */
    protected bool $enabled = true;

    /**
     * Create a new RateLimiter instance.
     */
    public function __construct(
        int $maxAttempts = 1,
        int $decaySeconds = 1,
        bool $enabled = true,
    ) {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->decaySeconds = max(1, $decaySeconds);
        $this->enabled = $enabled;
    }

    /**
     * Create a RateLimiter from configuration.
     */
    public static function fromConfig(): self
    {
        $config = config('web-terminal.rate_limit', []);

        return new self(
            maxAttempts: $config['max_attempts'] ?? 1,
            decaySeconds: $config['decay_seconds'] ?? 1,
            enabled: $config['enabled'] ?? true,
        );
    }

    /**
     * Attempt to execute a callback with rate limiting.
     *
     * @param  string  $key  Unique identifier for the rate limit (e.g., user ID)
     * @param  callable  $callback  The callback to execute if not rate limited
     * @param  callable|null  $onLimited  Callback to execute if rate limited
     * @return mixed The callback result or onLimited result
     */
    public function attempt(string $key, callable $callback, ?callable $onLimited = null): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $limiterKey = $this->resolveKey($key);

        if ($this->tooManyAttempts($limiterKey)) {
            if ($onLimited !== null) {
                return $onLimited($this->availableIn($limiterKey));
            }

            return null;
        }

        // Use the resolved key directly to avoid double resolution
        $this->getRateLimiter()->hit($limiterKey, $this->decaySeconds);

        return $callback();
    }

    /**
     * Check if the rate limit has been exceeded.
     *
     * @param  string  $key  Unique identifier
     */
    public function isLimited(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->tooManyAttempts($this->resolveKey($key));
    }

    /**
     * Get the number of remaining attempts.
     *
     * @param  string  $key  Unique identifier
     */
    public function remainingAttempts(string $key): int
    {
        if (! $this->enabled) {
            return $this->maxAttempts;
        }

        $limiterKey = $this->resolveKey($key);

        return $this->getRateLimiter()->remaining($limiterKey, $this->maxAttempts);
    }

    /**
     * Get the number of seconds until the rate limit resets.
     *
     * @param  string  $key  Unique identifier
     */
    public function retryAfter(string $key): int
    {
        if (! $this->enabled) {
            return 0;
        }

        return $this->availableIn($this->resolveKey($key));
    }

    /**
     * Clear the rate limit for a key.
     *
     * @param  string  $key  Unique identifier
     * @return $this
     */
    public function clear(string $key): static
    {
        $this->getRateLimiter()->clear($this->resolveKey($key));

        return $this;
    }

    /**
     * Record a hit against the rate limiter.
     *
     * @param  string  $key  Unique identifier
     * @return $this
     */
    public function hit(string $key): static
    {
        $this->getRateLimiter()->hit($this->resolveKey($key), $this->decaySeconds);

        return $this;
    }

    /**
     * Set the maximum number of attempts.
     *
     * @param  int  $attempts  Maximum attempts
     * @return $this
     */
    public function setMaxAttempts(int $attempts): static
    {
        $this->maxAttempts = max(1, $attempts);

        return $this;
    }

    /**
     * Get the maximum number of attempts.
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Set the decay period in seconds.
     *
     * @param  int  $seconds  Decay period
     * @return $this
     */
    public function setDecaySeconds(int $seconds): static
    {
        $this->decaySeconds = max(1, $seconds);

        return $this;
    }

    /**
     * Get the decay period in seconds.
     */
    public function getDecaySeconds(): int
    {
        return $this->decaySeconds;
    }

    /**
     * Enable rate limiting.
     *
     * @return $this
     */
    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable rate limiting.
     *
     * @return $this
     */
    public function disable(): static
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Check if rate limiting is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set the key prefix.
     *
     * @param  string  $prefix  Key prefix
     * @return $this
     */
    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Get the key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get rate limit information for a key.
     *
     * @param  string  $key  Unique identifier
     * @return array{remaining: int, limit: int, retry_after: int}
     */
    public function getInfo(string $key): array
    {
        return [
            'remaining' => $this->remainingAttempts($key),
            'limit' => $this->maxAttempts,
            'retry_after' => $this->retryAfter($key),
        ];
    }

    /**
     * Resolve the full rate limiter key.
     */
    protected function resolveKey(string $key): string
    {
        return $this->prefix.':'.$key;
    }

    /**
     * Check if too many attempts have been made.
     */
    protected function tooManyAttempts(string $key): bool
    {
        return $this->getRateLimiter()->tooManyAttempts($key, $this->maxAttempts);
    }

    /**
     * Get the number of seconds until the rate limit resets.
     */
    protected function availableIn(string $key): int
    {
        return $this->getRateLimiter()->availableIn($key);
    }

    /**
     * Get the underlying Laravel rate limiter.
     */
    protected function getRateLimiter(): LaravelRateLimiter
    {
        return RateLimiterFacade::getFacadeRoot();
    }
}
