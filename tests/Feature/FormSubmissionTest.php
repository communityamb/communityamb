<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Statamic\Events\FormSubmitted;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    public function test_contact_us_form_accepts_valid_submission(): void
    {
        Event::fake([FormSubmitted::class]);

        $response = $this->post('/!/forms/contact_us', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '631-555-1234',
            'comments' => 'I have a question about volunteering.',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_contact_us_form_rejects_missing_required_fields(): void
    {
        $response = $this->post('/!/forms/contact_us', [
            'first_name' => 'John',
            // last_name missing
            // email missing
            // phone missing
            // comments missing
        ]);

        $response->assertSessionHasErrors(['last_name', 'email', 'phone', 'comments']);
    }

    public function test_contact_us_form_rejects_invalid_email(): void
    {
        $response = $this->post('/!/forms/contact_us', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
            'phone' => '631-555-1234',
            'comments' => 'Test message.',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_join_community_form_accepts_valid_submission(): void
    {
        Event::fake([FormSubmitted::class]);

        $response = $this->post('/!/forms/join_community', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'date_of_birth' => '1995-06-15',
            'phone_number' => '631-555-5678',
            'town' => 'oakdale',
            'hs_emt_interest' => 'no',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_join_community_form_rejects_missing_required_fields(): void
    {
        $response = $this->post('/!/forms/join_community', [
            'first_name' => 'Jane',
            // all other required fields missing
        ]);

        $response->assertSessionHasErrors([
            'last_name',
            'email',
            'date_of_birth',
            'phone_number',
            'town',
            'hs_emt_interest',
        ]);
    }

    public function test_youth_squad_form_accepts_valid_submission(): void
    {
        Event::fake([FormSubmitted::class]);

        $response = $this->post('/!/forms/youth_squad', [
            'first_name' => 'Alex',
            'last_name' => 'Johnson',
            'email' => 'alex@example.com',
            'date_of_birth' => '2008-03-20',
            'phone_number' => '631-555-9012',
            'town' => 'sayville',
            'hs_emt_interest' => 'yes',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_youth_squad_form_rejects_missing_required_fields(): void
    {
        $response = $this->post('/!/forms/youth_squad', []);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'date_of_birth',
            'phone_number',
            'town',
            'hs_emt_interest',
        ]);
    }

    public function test_honeypot_field_blocks_spam_submission(): void
    {
        $response = $this->post('/!/forms/contact_us', [
            'first_name' => 'Spam',
            'last_name' => 'Bot',
            'email' => 'spam@bot.com',
            'phone' => '000-000-0000',
            'comments' => 'Buy my stuff!',
            'winnie' => 'gotcha',  // honeypot field filled = bot
        ]);

        // Statamic silently discards honeypot-triggered submissions
        // (returns success but does not fire FormSubmitted event)
        Event::assertNotDispatched(FormSubmitted::class);
    }
}
