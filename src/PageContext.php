<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class PageContext
{
    public function __construct(
        public ?string $routeName = null,
        public ?string $path = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $routeName = $request->route()?->getName();

        return new self(
            routeName: is_string($routeName) && $routeName !== '' ? $routeName : null,
            path: self::normalizePath($request->path()),
        );
    }

    public static function fromPath(string $path): self
    {
        $normalized = self::normalizePath($path);
        $uri = $normalized === '/' ? '/' : '/'.$normalized;
        $routeName = null;

        try {
            $matched = Route::getRoutes()->match(Request::create($uri, 'GET'));
            $name = $matched->getName();
            $routeName = is_string($name) && $name !== '' ? $name : null;
        } catch (HttpExceptionInterface) {
            $routeName = null;
        }

        return new self($routeName, $normalized);
    }

    public static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $raw = is_string($parsed) ? $parsed : $path;
        $trimmed = trim($raw, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }
}
