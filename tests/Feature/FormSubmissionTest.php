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
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['last_name', 'email', 'phone', 'comments'], [], 'form.contact_us');
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

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email'], [], 'form.contact_us');
    }

    public function test_join_community_form_accepts_valid_submission(): void
    {
        Event::fake([FormSubmitted::class]);

        $response = $this->post('/!/forms/join_community', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'date_of_birth' => ['date' => '1995-06-15'],
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
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(
            ['last_name', 'email', 'phone_number', 'town', 'hs_emt_interest'],
            [],
            'form.join_community'
        );
    }

    public function test_youth_squad_form_accepts_valid_submission(): void
    {
        Event::fake([FormSubmitted::class]);

        $response = $this->post('/!/forms/youth_squad', [
            'first_name' => 'Alex',
            'last_name' => 'Johnson',
            'email' => 'alex@example.com',
            'date_of_birth' => ['date' => '2008-03-20'],
            'phone_number' => '631-555-9012',
            'town' => 'sayville',
            'hs_emt_interest' => 'yes',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_youth_squad_form_rejects_missing_required_fields(): void
    {
        $response = $this->post('/!/forms/youth_squad', []);

        $response->assertRedirect();
        $response->assertSessionHasErrors(
            ['first_name', 'last_name', 'email', 'phone_number', 'town', 'hs_emt_interest'],
            [],
            'form.youth_squad'
        );
    }

    public function test_honeypot_field_blocks_spam_submission(): void
    {
        Event::fake([FormSubmitted::class]);

        $this->post('/!/forms/contact_us', [
            'first_name' => 'Spam',
            'last_name' => 'Bot',
            'email' => 'spam@bot.com',
            'phone' => '000-000-0000',
            'comments' => 'Buy my stuff!',
            'winnie' => 'gotcha',
        ]);

        Event::assertNotDispatched(FormSubmitted::class);
    }
}
