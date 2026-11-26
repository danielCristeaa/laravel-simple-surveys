<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Translation\Translator;

final class SurveyTranslator
{
    public function __construct(
        private readonly Translator $translator,
    ) {}

    public function translationFile(): string
    {
        $configured = config('simple-surveys.translations.file', 'simple-surveys');

        if (! is_string($configured)) {
            return 'simple-surveys';
        }

        $trimmed = trim($configured);

        return $trimmed !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $trimmed) === 1
            ? $trimmed
            : 'simple-surveys';
    }

    public function ui(string $key, ?string $locale = null): string
    {
        $fullKey = 'simple-surveys::widget.'.$key;

        foreach ($this->candidateLocales($locale, allowFallbackLocale: false) as $candidateLocale) {
            $translated = $this->translator->get($fullKey, [], $candidateLocale, false);

            if (is_string($translated) && $translated !== $fullKey && trim($translated) !== '') {
                return $translated;
            }
        }

        $fallback = $this->translator->get($fullKey, [], $locale);

        return is_string($fallback) && trim($fallback) !== '' ? $fallback : $key;
    }

    public function title(Survey $survey, ?string $locale = null): string
    {
        return $this->resolveField([$survey->key(), 'title'], $locale, allowFallbackLocale: true)
            ?? $survey->getTitle();
    }

    public function bannerText(Survey $survey, ?string $locale = null): string
    {
        $direct = $this->resolveField([$survey->key(), 'banner'], $locale, allowFallbackLocale: false);

        if ($direct !== null) {
            return $direct;
        }

        if (! $survey->hasCustomBannerText()) {
            return $this->title($survey, $locale);
        }

        return $this->resolveField([$survey->key(), 'banner'], $locale, allowFallbackLocale: true)
            ?? $survey->getBannerText();
    }

    public function description(Survey $survey, ?string $locale = null): ?string
    {
        $direct = $this->resolveField([$survey->key(), 'description'], $locale, allowFallbackLocale: false);

        if ($direct !== null) {
            return $direct;
        }

        if ($survey->getDescription() === null) {
            return null;
        }

        return $this->resolveField([$survey->key(), 'description'], $locale, allowFallbackLocale: true)
            ?? $survey->getDescription();
    }

    public function submitLabel(Survey $survey, ?string $locale = null): string
    {
        $direct = $this->resolveField([$survey->key(), 'submit_label'], $locale, allowFallbackLocale: false);

        if ($direct !== null) {
            return $direct;
        }

        if (! $survey->hasCustomSubmitLabel()) {
            return $this->ui('submit', $locale);
        }

        return $this->resolveField([$survey->key(), 'submit_label'], $locale, allowFallbackLocale: true)
            ?? $survey->getSubmitLabel();
    }

    public function thankYouMessage(Survey $survey, ?string $locale = null): string
    {
        $direct = $this->resolveField([$survey->key(), 'thank_you_message'], $locale, allowFallbackLocale: false);

        if ($direct !== null) {
            return $direct;
        }

        if (! $survey->hasCustomThankYouMessage()) {
            return $this->ui('thank_you', $locale);
        }

        return $this->resolveField([$survey->key(), 'thank_you_message'], $locale, allowFallbackLocale: true)
            ?? $survey->getThankYouMessage();
    }

    public function questionLabel(Survey $survey, Question $question, ?string $locale = null): string
    {
        return $this->resolveField(
            [$survey->key(), 'questions', $question->key(), 'label'],
            $locale,
            allowFallbackLocale: true,
        ) ?? $question->label();
    }

    public function questionHelpText(Survey $survey, Question $question, ?string $locale = null): ?string
    {
        $direct = $this->resolveField(
            [$survey->key(), 'questions', $question->key(), 'help_text'],
            $locale,
            allowFallbackLocale: false,
        );

        if ($direct !== null) {
            return $direct;
        }

        if ($question->getHelpText() === null) {
            return null;
        }

        return $this->resolveField(
            [$survey->key(), 'questions', $question->key(), 'help_text'],
            $locale,
            allowFallbackLocale: true,
        ) ?? $question->getHelpText();
    }

    public function questionPlaceholder(Survey $survey, Question $question, ?string $locale = null): ?string
    {
        $direct = $this->resolveField(
            [$survey->key(), 'questions', $question->key(), 'placeholder'],
            $locale,
            allowFallbackLocale: false,
        );

        if ($direct !== null) {
            return $direct;
        }

        if ($question->getPlaceholder() === null) {
            return null;
        }

        return $this->resolveField(
            [$survey->key(), 'questions', $question->key(), 'placeholder'],
            $locale,
            allowFallbackLocale: true,
        ) ?? $question->getPlaceholder();
    }

    /**
     * Resolve translated option labels while preserving canonical option keys.
     *
     * @return array<string, string>
     */
    public function questionOptions(Survey $survey, Question $question, ?string $locale = null): array
    {
        $resolved = [];

        foreach ($question->getOptions() as $value => $defaultLabel) {
            $resolved[$value] = $this->resolveField(
                [$survey->key(), 'questions', $question->key(), 'options', $value],
                $locale,
                allowFallbackLocale: true,
            ) ?? $defaultLabel;
        }

        return $resolved;
    }

    /**
     * Extract the canonical translatable string tree for a survey.
     *
     * @return array<string, mixed>
     */
    public function extract(Survey $survey): array
    {
        /** @var array<string, mixed> $entry */
        $entry = [
            'title' => $survey->getTitle(),
        ];

        if ($survey->hasCustomBannerText()) {
            $entry['banner'] = $survey->getBannerText();
        }

        $description = $survey->getDescription();
        if ($description !== null && trim($description) !== '') {
            $entry['description'] = $description;
        }

        if ($survey->hasCustomSubmitLabel()) {
            $entry['submit_label'] = $survey->getSubmitLabel();
        }

        if ($survey->hasCustomThankYouMessage()) {
            $entry['thank_you_message'] = $survey->getThankYouMessage();
        }

        $questions = [];

        foreach ($survey->getQuestions() as $question) {
            /** @var array<string, mixed> $questionEntry */
            $questionEntry = [
                'label' => $question->label(),
            ];

            $helpText = $question->getHelpText();
            if ($helpText !== null && trim($helpText) !== '') {
                $questionEntry['help_text'] = $helpText;
            }

            $placeholder = $question->getPlaceholder();
            if ($placeholder !== null && trim($placeholder) !== '') {
                $questionEntry['placeholder'] = $placeholder;
            }

            $options = $question->getOptions();
            if ($options !== []) {
                $questionEntry['options'] = $options;
            }

            $questions[$question->key()] = $questionEntry;
        }

        if ($questions !== []) {
            $entry['questions'] = $questions;
        }

        return $entry;
    }

    /**
     * @param  list<string>  $path
     */
    private function resolveField(array $path, ?string $locale, bool $allowFallbackLocale): ?string
    {
        foreach ($this->candidateLocales($locale, $allowFallbackLocale) as $candidateLocale) {
            $group = $this->loadGroup($candidateLocale);

            if ($group === []) {
                continue;
            }

            $value = $this->lookupPath($group, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateLocales(?string $locale, bool $allowFallbackLocale): array
    {
        $candidates = [];
        $active = $this->normalizeLocale($locale ?? $this->translator->getLocale());

        if ($active !== null) {
            $candidates[] = $active;

            $base = $this->extractBaseLocale($active);
            if ($base !== null && ! in_array($base, $candidates, true)) {
                $candidates[] = $base;
            }
        }

        if ($allowFallbackLocale) {
            $fallback = $this->normalizeLocale($this->translator->getFallback());

            if ($fallback !== null && ! in_array($fallback, $candidates, true)) {
                $candidates[] = $fallback;
            }
        }

        return $candidates;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function loadGroup(string $locale): array
    {
        $file = $this->translationFile();
        $loaded = $this->translator->get($file, [], $locale, false);

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * Walk nested translation arrays using exact array keys (safe for keys containing dots).
     *
     * @param  array<mixed, mixed>  $group
     * @param  list<string>  $path
     */
    private function lookupPath(array $group, array $path): ?string
    {
        $cursor = $group;

        foreach ($path as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        if (! is_string($cursor) || trim($cursor) === '') {
            return null;
        }

        return $cursor;
    }

    private function normalizeLocale(mixed $locale): ?string
    {
        if (! is_string($locale)) {
            return null;
        }

        $trimmed = trim($locale);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function extractBaseLocale(string $locale): ?string
    {
        $parts = preg_split('/[-_]/', $locale, 2);

        if (! is_array($parts) || count($parts) < 2) {
            return null;
        }

        $base = trim($parts[0]);

        return $base !== '' && $base !== $locale ? $base : null;
    }
}
