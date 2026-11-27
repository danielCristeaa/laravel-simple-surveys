# Contributing

Thank you for your interest in contributing to `laravel-simple-surveys`!

## Local Development Setup

### Requirements
- PHP `8.2+`
- Composer

### Install Dependencies

```bash
git clone https://github.com/danielCristeaa/laravel-simple-surveys.git
cd laravel-simple-surveys
composer install
```

## Running Tests & Quality Checks

All pull requests must pass the full Pest test suite, PHPStan Level 8 analysis, and Laravel Pint formatting checks:

```bash
# Run Pest test suite
composer test

# Run PHPStan static analysis (Level 8)
composer analyse

# Check code formatting (Laravel Pint)
composer format:check

# Auto-fix code formatting
composer format
```

## Coding Standards

- Add `declare(strict_types=1);` to every PHP file.
- Follow the Laravel Pint preset (`pint.json`).
- Keep types strict and compatible with PHPStan Level 8 (`phpstan.neon.dist`) across Laravel `11.x`, `12.x`, and `13.x`.
- Keep all singletons Octane-safe (no mutable per-request user or locale state stored on singleton services).

### CSS Backwards Compatibility

Every public `--lss-*` CSS custom property and `.lss-*` / `[data-lss-*]` selector documented in `resources/css/simple-surveys.css` is a stable public styling contract:

- **Never rename or remove** existing `--lss-*` custom properties or `.lss-*` / `[data-lss-*]` hooks without backwards-compatible fallbacks.
- **Keep zero-specificity defaults:** Wrap core widget selectors in `:where(...)` so host stylesheets override them cleanly without `!important`.
- **Never directly assign public `--lss-*` variables in `resources/css/widget.css`:** Consume them via internal `--_lss-*` variables with `var(--lss-*, <default>)` so host `:root` and `.lss-root` overrides are never shadowed.
- Update `resources/css/simple-surveys.css` if introducing new public tokens and verify the CSS contract tests in `tests/Unit/SurveyAppearanceTest.php` pass.

## Pull Request Guidelines

1. Fork the repository and create a feature or fix branch from `main`.
2. Add or update Pest tests covering your changes.
3. Update relevant documentation under `docs/` if behavior, configuration, or public APIs change.
4. Ensure `composer test`, `composer analyse`, and `composer format:check` all pass before opening your PR.
