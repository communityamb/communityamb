<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFormSubmissions
{
    protected array $formPaths = [
        'contact-us',
        'join-community',
        'join-community/join-youth-squad',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if (! in_array($path, $this->formPaths)) {
            return $next($request);
        }

        $key = 'form-submission:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Too many form submissions. Please try again later.');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
