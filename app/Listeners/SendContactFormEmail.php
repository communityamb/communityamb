<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Statamic\Events\FormSubmitted;

class SendContactFormEmail implements ShouldQueue
{
    public function handle(FormSubmitted $event): void
    {
        if ($event->submission->form()->handle() !== 'contact_us') {
            return;
        }

        $to = config('services.contact_form.to');
        $cc = config('services.contact_form.cc');

        if (! $to) {
            Log::warning('Contact form "to" address not configured.');

            return;
        }

        $data = $event->submission->data();
        $firstName = $data->get('first_name', '');
        $lastName = $data->get('last_name', '');
        $email = $data->get('email', '');
        $phone = $data->get('phone', '');
        $comments = $data->get('comments', '');

        $body = <<<EOT
        A new contact form submission has been received.

        Name: {$firstName} {$lastName}
        Email: {$email}
        Phone: {$phone}

        Comments:
        {$comments}
        EOT;

        try {
            Mail::raw($body, function ($message) use ($to, $cc) {
                $message->to($to)
                    ->subject('New Contact Form Submission');

                if ($cc) {
                    $message->cc($cc);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send contact form email: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
