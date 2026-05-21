<?php

namespace App\Providers;

use App\Listeners\SendContactFormEmail;
use App\Listeners\SendFormToZapier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Statamic\Events\FormSubmitted;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(FormSubmitted::class, SendFormToZapier::class);
        Event::listen(FormSubmitted::class, SendContactFormEmail::class);

        RateLimiter::for('form-submissions', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
