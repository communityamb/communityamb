<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;

class SendFormToZapier implements ShouldQueue
{
    protected array $webhooks = [
        'join_community' => 'ZAPIER_JOIN_WEBHOOK_URL',
        'youth_squad' => 'ZAPIER_YOUTH_WEBHOOK_URL',
    ];

    public function handle(FormSubmitted $event): void
    {
        $form = $event->submission->form()->handle();

        if (! isset($this->webhooks[$form])) {
            return;
        }

        $url = config("services.zapier.{$form}");

        if (! $url) {
            Log::warning("Zapier webhook URL not configured for form: {$form}");

            return;
        }

        try {
            Http::timeout(10)->post($url, $event->submission->data()->toArray());
        } catch (\Throwable $e) {
            Log::warning("Zapier webhook failed for form [{$form}]: {$e->getMessage()}");
        }
    }
}
