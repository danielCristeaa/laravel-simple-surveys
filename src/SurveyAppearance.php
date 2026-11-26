<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use DanielCristea\SimpleSurveys\Enums\WidgetPosition;
use DanielCristea\SimpleSurveys\Enums\WidgetTheme;

final readonly class SurveyAppearance
{
    public const COLOR_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';

    public const DIMENSION_PATTERN = '/^(?:0|\d+(?:\.\d+)?(?:px|rem|em|vh|vw|%))$/';

    public const FONT_FAMILY_PATTERN = '/^[a-zA-Z0-9\s,"\'_-]+$/';

    /**
     * Canonical mapping of structured style tokens to CSS custom properties.
     *
     * Backwards compatibility: keep every CSS custom property name stable so
     * previously published resources/css/simple-surveys.css files continue to work.
     *
     * @var array<string, array{
     *     css_var: string,
     *     type: 'color'|'dimension'|'font_family',
     *     label: string,
     *     group: 'colors'|'dimensions'|'typography',
     *     placeholder_light: string,
     *     placeholder_dark: string
     * }>
     */
    public const STYLE_TOKENS = [
        'accent' => [
            'css_var' => '--lss-accent',
            'type' => 'color',
            'label' => 'Button / Accent Color',
            'group' => 'colors',
            'placeholder_light' => '#4f46e5',
            'placeholder_dark' => '#6366f1',
        ],
        'accent_hover' => [
            'css_var' => '--lss-accent-hover',
            'type' => 'color',
            'label' => 'Button Hover Color',
            'group' => 'colors',
            'placeholder_light' => '#4338ca',
            'placeholder_dark' => '#818cf8',
        ],
        'button_text' => [
            'css_var' => '--lss-btn-text',
            'type' => 'color',
            'label' => 'Button Text Color',
            'group' => 'colors',
            'placeholder_light' => '#ffffff',
            'placeholder_dark' => '#ffffff',
        ],
        'background' => [
            'css_var' => '--lss-bg',
            'type' => 'color',
            'label' => 'Background Color',
            'group' => 'colors',
            'placeholder_light' => '#ffffff',
            'placeholder_dark' => '#0f172a',
        ],
        'surface' => [
            'css_var' => '--lss-surface',
            'type' => 'color',
            'label' => 'Surface / Footer Color',
            'group' => 'colors',
            'placeholder_light' => '#f8fafc',
            'placeholder_dark' => '#1e293b',
        ],
        'border' => [
            'css_var' => '--lss-border',
            'type' => 'color',
            'label' => 'Border Color',
            'group' => 'colors',
            'placeholder_light' => '#e2e8f0',
            'placeholder_dark' => '#334155',
        ],
        'text' => [
            'css_var' => '--lss-text',
            'type' => 'color',
            'label' => 'Primary Text Color',
            'group' => 'colors',
            'placeholder_light' => '#0f172a',
            'placeholder_dark' => '#f8fafc',
        ],
        'muted' => [
            'css_var' => '--lss-muted',
            'type' => 'color',
            'label' => 'Secondary / Muted Text',
            'group' => 'colors',
            'placeholder_light' => '#64748b',
            'placeholder_dark' => '#94a3b8',
        ],
        'card_width' => [
            'css_var' => '--lss-card-width',
            'type' => 'dimension',
            'label' => 'Survey Card Width',
            'group' => 'dimensions',
            'placeholder_light' => '390px',
            'placeholder_dark' => '390px',
        ],
        'card_max_height' => [
            'css_var' => '--lss-card-max-height',
            'type' => 'dimension',
            'label' => 'Survey Card Max Height',
            'group' => 'dimensions',
            'placeholder_light' => '82vh',
            'placeholder_dark' => '82vh',
        ],
        'banner_max_width' => [
            'css_var' => '--lss-banner-max-width',
            'type' => 'dimension',
            'label' => 'Banner Max Width',
            'group' => 'dimensions',
            'placeholder_light' => '380px',
            'placeholder_dark' => '380px',
        ],
        'radius' => [
            'css_var' => '--lss-radius',
            'type' => 'dimension',
            'label' => 'Card & Banner Radius',
            'group' => 'dimensions',
            'placeholder_light' => '12px',
            'placeholder_dark' => '12px',
        ],
        'button_radius' => [
            'css_var' => '--lss-btn-radius',
            'type' => 'dimension',
            'label' => 'Button Border Radius',
            'group' => 'dimensions',
            'placeholder_light' => '8px',
            'placeholder_dark' => '8px',
        ],
        'input_radius' => [
            'css_var' => '--lss-input-radius',
            'type' => 'dimension',
            'label' => 'Input Border Radius',
            'group' => 'dimensions',
            'placeholder_light' => '8px',
            'placeholder_dark' => '8px',
        ],
        'font_size' => [
            'css_var' => '--lss-font-size',
            'type' => 'dimension',
            'label' => 'Base Font Size',
            'group' => 'typography',
            'placeholder_light' => '14px',
            'placeholder_dark' => '14px',
        ],
        'font_family' => [
            'css_var' => '--lss-font-family',
            'type' => 'font_family',
            'label' => 'Font Family',
            'group' => 'typography',
            'placeholder_light' => 'System Sans (Default)',
            'placeholder_dark' => 'System Sans (Default)',
        ],
    ];

    /**
     * @param  array<string, string>  $styles
     */
    public function __construct(
        public ?WidgetPosition $position = null,
        public ?WidgetTheme $theme = null,
        public array $styles = [],
        public ?WidgetIcon $icon = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null || $data === []) {
            return new self;
        }

        $position = is_string($data['position'] ?? null)
            ? WidgetPosition::tryFrom($data['position'])
            : null;

        $theme = is_string($data['theme'] ?? null)
            ? WidgetTheme::tryFrom($data['theme'])
            : null;

        $icon = WidgetIcon::tryFrom($data['icon'] ?? null);

        /** @var array<string, string> $styles */
        $styles = [];

        $rawStyles = $data['styles'] ?? null;

        if (is_array($rawStyles)) {
            foreach (self::STYLE_TOKENS as $key => $meta) {
                $rawValue = $rawStyles[$key] ?? null;

                if (! is_string($rawValue)) {
                    continue;
                }

                $normalized = self::normalizeTokenValue($key, $rawValue);

                if ($normalized !== null) {
                    $styles[$key] = $normalized;
                }
            }
        }

        return new self(
            position: $position,
            theme: $theme,
            styles: $styles,
            icon: $icon,
        );
    }

    public static function normalizeTokenValue(string $token, string $value): ?string
    {
        if (! isset(self::STYLE_TOKENS[$token])) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $type = self::STYLE_TOKENS[$token]['type'];

        if ($type === 'color') {
            return preg_match(self::COLOR_PATTERN, $trimmed) === 1
                ? strtolower($trimmed)
                : null;
        }

        if ($type === 'dimension') {
            return strlen($trimmed) <= 20 && preg_match(self::DIMENSION_PATTERN, $trimmed) === 1
                ? $trimmed
                : null;
        }

        if (
            strlen($trimmed) <= 160
            && preg_match(self::FONT_FAMILY_PATTERN, $trimmed) === 1
            && preg_match('/(?:expression|url|javascript)/i', $trimmed) !== 1
        ) {
            return $trimmed;
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validationRules(): array
    {
        $rules = [
            'position' => ['nullable', 'string', 'in:'.implode(',', WidgetPosition::values())],
            'theme' => ['nullable', 'string', 'in:'.implode(',', WidgetTheme::values())],
            'icon' => ['nullable', 'string', 'max:'.WidgetIcon::MAX_LENGTH, 'regex:'.WidgetIcon::validationPattern()],
            'styles' => ['nullable', 'array'],
        ];

        foreach (self::STYLE_TOKENS as $key => $meta) {
            $rules['styles.'.$key] = match ($meta['type']) {
                'color' => ['nullable', 'string', 'regex:'.self::COLOR_PATTERN],
                'dimension' => ['nullable', 'string', 'max:20', 'regex:'.self::DIMENSION_PATTERN],
                'font_family' => [
                    'nullable',
                    'string',
                    'max:160',
                    'regex:'.self::FONT_FAMILY_PATTERN,
                    'not_regex:/(?:expression|url|javascript)/i',
                ],
            };
        }

        return $rules;
    }

    public function isEmpty(): bool
    {
        return $this->position === null
            && $this->theme === null
            && $this->icon === null
            && $this->styles === [];
    }

    public function resolvedPosition(?WidgetPosition $fallback = null): WidgetPosition
    {
        return $this->position ?? $fallback ?? WidgetPosition::BottomRight;
    }

    public function resolvedTheme(?WidgetTheme $fallback = null): WidgetTheme
    {
        return $this->theme ?? $fallback ?? WidgetTheme::Light;
    }

    public function resolvedIcon(?WidgetIcon $fallback = null): WidgetIcon
    {
        return $this->icon ?? $fallback ?? WidgetIcon::default();
    }

    public function style(string $token): ?string
    {
        return $this->styles[$token] ?? null;
    }

    public function toInlineStyle(): string
    {
        $declarations = [];

        foreach ($this->styles as $token => $value) {
            if (! isset(self::STYLE_TOKENS[$token])) {
                continue;
            }

            $normalized = self::normalizeTokenValue($token, $value);

            if ($normalized === null) {
                continue;
            }

            $declarations[] = sprintf('%s: %s;', self::STYLE_TOKENS[$token]['css_var'], $normalized);
        }

        return implode(' ', $declarations);
    }

    /**
     * Compact array suitable for database storage (null when no overrides exist).
     *
     * @return array{position?: string, theme?: string, icon?: string, styles?: array<string, string>}|null
     */
    public function toStorageArray(): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        $payload = [];

        if ($this->position !== null) {
            $payload['position'] = $this->position->value;
        }

        if ($this->theme !== null) {
            $payload['theme'] = $this->theme->value;
        }

        if ($this->icon !== null) {
            $payload['icon'] = $this->icon->toStorageValue();
        }

        if ($this->styles !== []) {
            $payload['styles'] = $this->styles;
        }

        return $payload;
    }

    /**
     * Public API serialization shape (used by GET /simple-surveys/eligible).
     *
     * @return array{
     *     position: string,
     *     theme: string,
     *     icon: array{type: 'preset'|'asset'|'none', preset: string|null, url: string|null},
     *     styles: array<string, string>
     * }
     */
    public function toArray(
        ?WidgetPosition $defaultPosition = null,
        ?WidgetTheme $defaultTheme = null,
        ?WidgetIcon $defaultIcon = null,
    ): array {
        return [
            'position' => $this->resolvedPosition($defaultPosition)->value,
            'theme' => $this->resolvedTheme($defaultTheme)->value,
            'icon' => $this->resolvedIcon($defaultIcon)->toArray(),
            'styles' => $this->styles,
        ];
    }
}
