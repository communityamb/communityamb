<?php

namespace App\Providers;

use App\Listeners\SendFormToZapier;
use Illuminate\Support\Facades\Event;
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
    }
}
