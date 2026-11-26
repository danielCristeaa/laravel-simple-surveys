<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Console\Commands;

use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ExportSurveyTranslationsCommand extends Command
{
    protected $signature = 'simple-surveys:translations
        {--locales= : Comma-separated target locale codes to scaffold (overrides config simple-surveys.translations.locales)}
        {--source= : Source locale code for canonical strings (defaults to app.fallback_locale)}
        {--survey= : Only export translations for a specific registered survey key}
        {--empty : Leave newly scaffolded target locale entries empty instead of pre-filling source strings}
        {--prune : Remove survey or question entries that are no longer registered in code}';

    protected $description = 'Export and scaffold Laravel lang/{locale}/simple-surveys.php translation files from registered surveys';

    public function handle(SurveyRegistry $registry, SurveyTranslator $translator): int
    {
        $surveys = $this->resolveSurveys($registry);

        if ($surveys === null) {
            return self::FAILURE;
        }

        if ($surveys === []) {
            $this->components->warn('No surveys are currently registered.');

            return self::SUCCESS;
        }

        $sourceLocale = $this->resolveSourceLocale();

        if ($sourceLocale === null) {
            $this->components->error('Invalid source locale code. Use letters, numbers, hyphens, or underscores only.');

            return self::FAILURE;
        }

        $targetLocales = $this->resolveTargetLocales($sourceLocale);

        if ($targetLocales === null) {
            $this->components->error('One or more target locale codes are invalid. Use letters, numbers, hyphens, or underscores only.');

            return self::FAILURE;
        }

        /** @var array<string, array<string, mixed>> $extracted */
        $extracted = [];

        foreach ($surveys as $survey) {
            $extracted[$survey->key()] = $translator->extract($survey);
        }

        $file = $translator->translationFile();
        $emptyTargets = (bool) $this->option('empty');
        $prune = (bool) $this->option('prune') && $this->option('survey') === null;

        // 1. Export canonical source locale file
        $sourcePath = $this->langFilePath($sourceLocale, $file);
        $sourceExisting = $this->readExistingTranslations($sourcePath);
        $sourceMerged = $this->mergeSourceTranslations($sourceExisting, $extracted, $prune);
        $this->writeTranslationFile($sourcePath, $sourceMerged);

        $this->components->twoColumnDetail(
            $this->relativePath($sourcePath),
            '<fg=green>source updated</>',
        );

        // 2. Scaffold / merge each configured target locale without overwriting existing translations
        foreach ($targetLocales as $locale) {
            $targetPath = $this->langFilePath($locale, $file);
            $existed = File::exists($targetPath);
            $existing = $this->readExistingTranslations($targetPath);
            $merged = $this->mergeTargetTranslations($existing, $extracted, $emptyTargets, $prune);

            $this->writeTranslationFile($targetPath, $merged);

            $this->components->twoColumnDetail(
                $this->relativePath($targetPath),
                $existed ? '<fg=cyan>merged</>' : '<fg=green>created</>',
            );
        }

        $totalFiles = 1 + count($targetLocales);

        $this->components->info(sprintf(
            'Exported %d %s across %d locale %s.',
            count($extracted),
            count($extracted) === 1 ? 'survey' : 'surveys',
            $totalFiles,
            $totalFiles === 1 ? 'file' : 'files',
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, Survey>|null
     */
    private function resolveSurveys(SurveyRegistry $registry): ?array
    {
        $surveyOption = $this->option('survey');

        if (is_string($surveyOption) && trim($surveyOption) !== '') {
            $surveyKey = trim($surveyOption);
            $survey = $registry->find($surveyKey);

            if ($survey === null) {
                $this->components->error("Survey [{$surveyKey}] is not registered.");

                return null;
            }

            return [$survey];
        }

        return array_values($registry->all()->all());
    }

    private function resolveSourceLocale(): ?string
    {
        $option = $this->option('source');
        $fallback = config('app.fallback_locale', 'en');

        $raw = is_string($option) && trim($option) !== ''
            ? trim($option)
            : (is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : 'en');

        return $this->isValidLocale($raw) ? $raw : null;
    }

    /**
     * @return list<string>|null
     */
    private function resolveTargetLocales(string $sourceLocale): ?array
    {
        $option = $this->option('locales');

        if (is_string($option) && trim($option) !== '') {
            $rawList = explode(',', $option);
        } else {
            $configured = config('simple-surveys.translations.locales', []);
            $rawList = is_array($configured) ? $configured : [];
        }

        /** @var list<string> $locales */
        $locales = [];

        foreach ($rawList as $item) {
            if (! is_string($item)) {
                continue;
            }

            $trimmed = trim($item);

            if ($trimmed === '' || $trimmed === $sourceLocale) {
                continue;
            }

            if (! $this->isValidLocale($trimmed)) {
                return null;
            }

            if (! in_array($trimmed, $locales, true)) {
                $locales[] = $trimmed;
            }
        }

        return $locales;
    }

    private function isValidLocale(string $locale): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $locale) === 1;
    }

    private function langFilePath(string $locale, string $file): string
    {
        return $this->laravel->langPath($locale.DIRECTORY_SEPARATOR.$file.'.php');
    }

    private function relativePath(string $absolutePath): string
    {
        $base = rtrim($this->laravel->basePath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base)
            ? substr($absolutePath, strlen($base))
            : $absolutePath;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExistingTranslations(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $loaded = require $path;

        if (! is_array($loaded)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = [];

        foreach ($loaded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, array<string, mixed>>  $extracted
     * @return array<string, mixed>
     */
    private function mergeSourceTranslations(array $existing, array $extracted, bool $prune): array
    {
        $result = $prune ? [] : $existing;

        foreach ($extracted as $surveyKey => $entry) {
            $result[$surveyKey] = $entry;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, array<string, mixed>>  $extracted
     * @return array<string, mixed>
     */
    private function mergeTargetTranslations(array $existing, array $extracted, bool $emptyTargets, bool $prune): array
    {
        $result = $prune ? [] : $existing;

        foreach ($extracted as $surveyKey => $sourceEntry) {
            $existingEntry = isset($existing[$surveyKey]) && is_array($existing[$surveyKey])
                ? $existing[$surveyKey]
                : [];

            $result[$surveyKey] = $this->deepMergeTargetNode($existingEntry, $sourceEntry, $emptyTargets, $prune);
        }

        return $result;
    }

    /**
     * @param  array<mixed, mixed>  $existing
     * @param  array<mixed, mixed>  $source
     * @return array<mixed, mixed>
     */
    private function deepMergeTargetNode(array $existing, array $source, bool $emptyTargets, bool $prune): array
    {
        $merged = [];

        foreach ($source as $key => $sourceValue) {
            $hasExisting = array_key_exists($key, $existing);
            $existingValue = $hasExisting ? $existing[$key] : null;

            if (is_array($sourceValue)) {
                $merged[$key] = $this->deepMergeTargetNode(
                    is_array($existingValue) ? $existingValue : [],
                    $sourceValue,
                    $emptyTargets,
                    $prune,
                );

                continue;
            }

            if (is_string($existingValue) && trim($existingValue) !== '') {
                $merged[$key] = $existingValue;

                continue;
            }

            $merged[$key] = $emptyTargets ? '' : (is_string($sourceValue) ? $sourceValue : '');
        }

        if (! $prune) {
            foreach ($existing as $key => $existingValue) {
                if (! array_key_exists($key, $merged)) {
                    $merged[$key] = $existingValue;
                }
            }
        }

        return $merged;
    }

    /**
     * @param  array<mixed, mixed>  $data
     */
    private function writeTranslationFile(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));

        $contents = "<?php\n\nreturn ".$this->formatPhpArray($data).";\n";

        File::put($path, $contents);
    }

    /**
     * Render a clean, Pint-friendly PHP short array representation.
     *
     * @param  array<mixed, mixed>  $data
     */
    private function formatPhpArray(array $data, int $depth = 0): string
    {
        if ($data === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $closingIndent = str_repeat('    ', $depth);
        $lines = ['['];

        foreach ($data as $key => $value) {
            $exportedKey = var_export($key, true);

            if (is_array($value)) {
                $exportedValue = $this->formatPhpArray($value, $depth + 1);
            } else {
                $exportedValue = var_export($value, true);
            }

            $lines[] = "{$indent}{$exportedKey} => {$exportedValue},";
        }

        $lines[] = "{$closingIndent}]";

        return implode("\n", $lines);
    }
}
