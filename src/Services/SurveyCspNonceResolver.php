<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Closure;
use Illuminate\Support\Facades\Vite;

final class SurveyCspNonceResolver
{
    /** @var (Closure(): mixed)|null */
    private ?Closure $resolver = null;

    /**
     * Register a custom callback to resolve the active CSP nonce per request.
     *
     * @param  (Closure(): mixed)|null  $resolver
     */
    public function resolveUsing(?Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function clear(): void
    {
        $this->resolver = null;
    }

    /**
     * Resolve the active CSP nonce using precedence: explicit prop > custom callback > Vite::cspNonce().
     */
    public function resolve(?string $explicitNonce = null): ?string
    {
        $normalizedExplicit = $this->normalize($explicitNonce);

        if ($normalizedExplicit !== null) {
            return $normalizedExplicit;
        }

        if ($this->resolver !== null) {
            $custom = $this->normalize(($this->resolver)());

            if ($custom !== null) {
                return $custom;
            }
        }

        if (class_exists(Vite::class)) {
            return $this->normalize(Vite::cspNonce());
        }

        return null;
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
