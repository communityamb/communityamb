<?php

namespace Tests\Feature;

use App\Listeners\SendFormToZapier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Form;
use Tests\TestCase;

class ZapierListenerTest extends TestCase
{
    private function makeSubmissionEvent(string $formHandle, array $data = []): FormSubmitted
    {
        $form = Form::find($formHandle);

        $submission = $form->makeSubmission();
        $submission->data(collect($data));

        return new FormSubmitted($submission);
    }

    public function test_join_community_form_dispatches_webhook(): void
    {
        $webhookUrl = 'https://hooks.zapier.com/hooks/catch/test-join';
        config(['services.zapier.join_community' => $webhookUrl]);

        Http::fake([
            $webhookUrl => Http::response([], 200),
        ]);

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'date_of_birth' => '1995-06-15',
            'phone_number' => '631-555-5678',
            'town' => 'oakdale',
            'hs_emt_interest' => 'no',
        ];

        $event = $this->makeSubmissionEvent('join_community', $data);

        $listener = new SendFormToZapier;
        $listener->handle($event);

        Http::assertSent(function ($request) use ($webhookUrl, $data) {
            return $request->url() === $webhookUrl
                && $request['first_name'] === $data['first_name']
                && $request['email'] === $data['email'];
        });
    }

    public function test_youth_squad_form_dispatches_webhook(): void
    {
        $webhookUrl = 'https://hooks.zapier.com/hooks/catch/test-youth';
        config(['services.zapier.youth_squad' => $webhookUrl]);

        Http::fake([
            $webhookUrl => Http::response([], 200),
        ]);

        $data = [
            'first_name' => 'Alex',
            'last_name' => 'Johnson',
            'email' => 'alex@example.com',
            'date_of_birth' => '2008-03-20',
            'phone_number' => '631-555-9012',
            'town' => 'sayville',
            'hs_emt_interest' => 'yes',
        ];

        $event = $this->makeSubmissionEvent('youth_squad', $data);

        $listener = new SendFormToZapier;
        $listener->handle($event);

        Http::assertSent(function ($request) use ($webhookUrl) {
            return $request->url() === $webhookUrl;
        });
    }

    public function test_contact_us_form_does_not_dispatch_webhook(): void
    {
        Http::fake();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '631-555-1234',
            'comments' => 'Just a question.',
        ];

        $event = $this->makeSubmissionEvent('contact_us', $data);

        $listener = new SendFormToZapier;
        $listener->handle($event);

        Http::assertNothingSent();
    }

    public function test_missing_webhook_url_logs_warning(): void
    {
        config(['services.zapier.join_community' => null]);

        Http::fake();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'join_community');
            });

        $event = $this->makeSubmissionEvent('join_community', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

        $listener = new SendFormToZapier;
        $listener->handle($event);

        Http::assertNothingSent();
    }
}
