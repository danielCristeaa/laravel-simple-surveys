<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use DanielCristea\SimpleSurveys\Enums\WidgetIconPreset;
use InvalidArgumentException;

final readonly class WidgetIcon
{
    public const NONE = 'none';

    public const MAX_LENGTH = 255;

    /**
     * Strict syntax-only pattern for a relative public/ asset path ending in .png or .svg.
     */
    public const ASSET_PATH_PATTERN = '/^(?!.*\.\.)(?:[a-zA-Z0-9_-][a-zA-Z0-9._-]*\/)*[a-zA-Z0-9_-][a-zA-Z0-9._-]*\.(?i:png|svg)$/';

    /**
     * @param  'preset'|'asset'|'none'  $type
     */
    private function __construct(
        public string $type,
        public ?WidgetIconPreset $preset = null,
        public ?string $assetPath = null,
    ) {}

    public static function default(): self
    {
        return self::fromPreset(WidgetIconPreset::Chat);
    }

    public static function none(): self
    {
        return new self(type: self::NONE);
    }

    public static function fromPreset(WidgetIconPreset $preset): self
    {
        return new self(
            type: 'preset',
            preset: $preset,
        );
    }

    public static function fromAsset(string $path): self
    {
        $normalized = self::normalizeAssetPath($path);

        if ($normalized === null) {
            throw new InvalidArgumentException(
                "Invalid widget icon asset path [{$path}]. Use a relative public/ path ending in .png or .svg."
            );
        }

        return new self(
            type: 'asset',
            assetPath: $normalized,
        );
    }

    public static function tryFrom(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof WidgetIconPreset) {
            return self::fromPreset($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || strlen($trimmed) > self::MAX_LENGTH) {
            return null;
        }

        if ($trimmed === self::NONE) {
            return self::none();
        }

        $preset = WidgetIconPreset::tryFrom($trimmed);

        if ($preset !== null) {
            return self::fromPreset($preset);
        }

        $assetPath = self::normalizeAssetPath($trimmed);

        if ($assetPath !== null) {
            return new self(
                type: 'asset',
                assetPath: $assetPath,
            );
        }

        return null;
    }

    public static function from(self|WidgetIconPreset|string $value): self
    {
        $icon = self::tryFrom($value);

        if ($icon === null) {
            $raw = is_string($value) ? $value : '';

            throw new InvalidArgumentException(
                "Invalid widget icon [{$raw}]. Use a built-in preset, \"none\", or a relative public/ .png/.svg path."
            );
        }

        return $icon;
    }

    public static function fromConfig(): self
    {
        return self::tryFrom(config('simple-surveys.widget.icon')) ?? self::default();
    }

    public static function normalizeAssetPath(string $path): ?string
    {
        $trimmed = trim($path);

        if ($trimmed === '' || strlen($trimmed) > self::MAX_LENGTH) {
            return null;
        }

        // Reject protocol-relative URLs (e.g. //evil.test/icon.svg) before stripping a single leading slash.
        if (str_starts_with($trimmed, '//')) {
            return null;
        }

        $candidate = str_starts_with($trimmed, '/') ? substr($trimmed, 1) : $trimmed;

        if ($candidate === '' || preg_match(self::ASSET_PATH_PATTERN, $candidate) !== 1) {
            return null;
        }

        return $candidate;
    }

    public static function validationPattern(): string
    {
        return '/^(?!.*\.\.)(?:'
            .self::NONE
            .'|'.implode('|', WidgetIconPreset::values())
            .'|\/?(?:[a-zA-Z0-9_-][a-zA-Z0-9._-]*\/)*[a-zA-Z0-9_-][a-zA-Z0-9._-]*\.(?i:png|svg)'
            .')$/';
    }

    public function isNone(): bool
    {
        return $this->type === self::NONE;
    }

    public function isPreset(): bool
    {
        return $this->type === 'preset' && $this->preset !== null;
    }

    public function isAsset(): bool
    {
        return $this->type === 'asset' && $this->assetPath !== null;
    }

    public function svg(): ?string
    {
        return $this->preset?->svg();
    }

    public function assetUrl(): ?string
    {
        if ($this->assetPath === null) {
            return null;
        }

        return asset($this->assetPath);
    }

    public function label(): string
    {
        if ($this->isNone()) {
            return 'Hidden';
        }

        if ($this->preset !== null) {
            return $this->preset->label();
        }

        return 'Custom Asset';
    }

    public function toStorageValue(): string
    {
        if ($this->isNone()) {
            return self::NONE;
        }

        if ($this->preset !== null) {
            return $this->preset->value;
        }

        return $this->assetPath ?? WidgetIconPreset::Chat->value;
    }

    /**
     * Public API serialization shape (used in GET /simple-surveys/eligible).
     *
     * @return array{
     *     type: 'preset'|'asset'|'none',
     *     preset: string|null,
     *     url: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'preset' => $this->preset?->value,
            'url' => $this->assetUrl(),
        ];
    }
}
