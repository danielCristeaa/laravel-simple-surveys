# Changelog

All notable changes to `laravel-simple-surveys` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - TBD

### Added
- Code-first fluent `Survey`, `Question`, and `SurveyDefinition` API with 8 input types (`text`, `textarea`, `rating`, `boolean`, `select`, `multiselect`, `number`, `email`) and conditional follow-up visibility (`->when(...)`).
- Drop-in `<x-simple-surveys::widget />` Blade component with zero JS build step, zero-specificity `:where(...)` CSS defaults, light/dark/auto themes, 5 placements (including centered modal), banner icon presets/custom assets, and Inertia/Livewire `dynamic` SPA mode.
- Global and per-survey styling via public `--lss-*` CSS custom properties, publishable starter stylesheet (`simple-surveys-styles`), and Admin **Appearance** live preview editor.
- Eloquent (`->target(...)`), user predicate (`->targetUser(...)`), route/path (`onRoutes`, `onPaths`), schedule (`startsAt`, `endsAt`, `activeBetween`), priority, and dismissal re-prompt cooldown targeting.
- Headless JSON API endpoints (`eligible`, `widget`, `impression`, `open`, `responses`, `dismiss`) with configurable auth guards (`sanctum`, `api`, etc.) and `duration_ms` tracking.
- Protected Admin Results Dashboard (`/simple-surveys/admin`) gated by `viewSimpleSurveys` with funnel metrics, inline SVG charts, searchable & cross-filterable response table, slide-over detail drawer, Light/Dark mode, CSV export, and lifecycle controls (Close, Re-open, Reset).
- Programmatic PHP results API (`SimpleSurveys::summaries()`, `SimpleSurveys::results()`, `SimpleSurveys::exportCsv()`, lifecycle methods) and Artisan commands (`simple-surveys:sync`, `simple-surveys:results`, `simple-surveys:translations`).
- Multi-language support with publishable widget UI strings, `lang/{locale}/simple-surveys.php` scaffolding, and automatic respondent `locale` recording.
- Funnel telemetry (`impressions`, `opens`, `dismissals`, `responses`), tracking context cookies & resolver (`resolveTrackingContext`), browser `CustomEvent`s, signed lifecycle webhooks (`seen`, `opened`, `dismissed`, `submitted`), and submission notifications (Mail, Slack, custom channels).
- Consolidated single database migration stub (`create_simple_surveys_tables.php.stub`) supporting `id`, `uuid`, and `ulid` polymorphic respondent keys.

[Unreleased]: https://github.com/danielCristeaa/laravel-simple-surveys/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/danielCristeaa/laravel-simple-surveys/releases/tag/v1.0.0
