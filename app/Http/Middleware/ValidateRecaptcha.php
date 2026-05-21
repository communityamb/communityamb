<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateRecaptcha
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

        $secret = config('services.recaptcha.secret_key');

        if (! $secret) {
            return $next($request);
        }

        $token = $request->input('g-recaptcha-response');

        if (! $token) {
            abort(422, 'reCAPTCHA verification failed.');
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false) || ($result['score'] ?? 0) < 0.5) {
                Log::warning('reCAPTCHA failed', [
                    'score' => $result['score'] ?? null,
                    'action' => $result['action'] ?? null,
                    'path' => $path,
                ]);
                abort(422, 'reCAPTCHA verification failed.');
            }
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA verification error: '.$e->getMessage());

            return $next($request);
        }

        return $next($request);
    }
}
