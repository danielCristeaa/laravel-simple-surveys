<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeSimpleSurveys
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('simple-surveys.guard');
        $user = $request->user(is_string($guard) ? $guard : null);

        $ability = config('simple-surveys.admin.gate', 'viewSimpleSurveys');
        $gateName = is_string($ability) && $ability !== '' ? $ability : 'viewSimpleSurveys';

        if ($user === null || ! Gate::forUser($user)->allows($gateName)) {
            abort(403, 'You are not authorized to view survey results.');
        }

        return $next($request);
    }
}
