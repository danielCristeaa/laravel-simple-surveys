<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\CarbonInterface;
use Closure;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class SurveyTrackingContextResolver
{
    private const MAX_DURATION_MS = 86_400_000;

    private ?Closure $resolver = null;

    public function resolveUsing(?Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function clear(): void
    {
        $this->resolver = null;
    }

    /**
     * Resolve custom tracking IDs and user traits from configured cookies and the registered callback.
     *
     * @return array<string, scalar|array<int, scalar>>
     */
    public function resolveTracking(Request $request, Model $respondent, Survey $survey): array
    {
        $resolved = $this->resolveConfiguredCookies($request);

        if ($this->resolver !== null) {
            $custom = ($this->resolver)($request, $respondent, $survey);

            if (is_array($custom)) {
                foreach ($custom as $key => $value) {
                    if (! is_string($key)) {
                        continue;
                    }

                    $normalizedKey = trim($key);

                    if ($normalizedKey === '') {
                        continue;
                    }

                    $normalizedValue = $this->normalizeValue($value);

                    if ($normalizedValue !== null) {
                        $resolved[$normalizedKey] = $normalizedValue;
                    } else {
                        unset($resolved[$normalizedKey]);
                    }
                }
            }
        }

        return $resolved;
    }

    public function resolveUrl(Request $request): ?string
    {
        $url = $request->input('url');

        if (is_string($url) && trim($url) !== '') {
            return trim($url);
        }

        $referer = $request->headers->get('referer');

        if (is_string($referer) && trim($referer) !== '') {
            return trim($referer);
        }

        return null;
    }

    public function resolveDurationMs(
        Request $request,
        ?CarbonInterface $fallbackOpenedAt = null,
        ?CarbonInterface $now = null,
    ): ?int {
        $raw = $request->input('duration_ms');

        if ($raw !== null) {
            if (! is_int($raw) && ! (is_string($raw) && ctype_digit($raw)) && ! is_float($raw)) {
                return null;
            }

            $duration = (int) round((float) $raw);

            if ($duration < 0 || $duration > self::MAX_DURATION_MS) {
                return null;
            }

            return $duration;
        }

        if ($fallbackOpenedAt === null) {
            return null;
        }

        $elapsed = (int) round($fallbackOpenedAt->diffInMilliseconds($now ?? now(), false));

        if ($elapsed < 0 || $elapsed > self::MAX_DURATION_MS) {
            return null;
        }

        return $elapsed;
    }

    public function resolveDismissalSource(Request $request): ?string
    {
        $source = $request->input('source');

        if (! is_string($source)) {
            return null;
        }

        $normalized = strtolower(trim($source));

        return in_array($normalized, ['banner', 'card'], true) ? $normalized : null;
    }

    /**
     * @return array<string, scalar|array<int, scalar>>
     */
    private function resolveConfiguredCookies(Request $request): array
    {
        $configured = config('simple-surveys.tracking.cookies', []);

        if (! is_array($configured)) {
            return [];
        }

        /** @var array<string, scalar|array<int, scalar>> $cookies */
        $cookies = [];

        foreach ($configured as $key => $cookieName) {
            if (! is_string($cookieName) || trim($cookieName) === '') {
                continue;
            }

            $targetKey = is_string($key) && trim($key) !== '' ? trim($key) : trim($cookieName);
            $rawValue = $request->cookie(trim($cookieName));
            $normalized = $this->normalizeValue($rawValue);

            if ($normalized !== null) {
                $cookies[$targetKey] = $normalized;
            }
        }

        return $cookies;
    }

    /**
     * @return scalar|array<int, scalar>|null
     */
    private function normalizeValue(mixed $value): string|int|float|bool|array|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            /** @var array<int, scalar> $items */
            $items = [];

            foreach ($value as $item) {
                if (is_string($item)) {
                    $trimmed = trim($item);
                    if ($trimmed !== '') {
                        $items[] = $trimmed;
                    }
                } elseif (is_int($item) || is_float($item) || is_bool($item)) {
                    $items[] = $item;
                }
            }

            return $items !== [] ? $items : null;
        }

        return null;
    }
}
