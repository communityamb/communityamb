<?php

namespace Tests\Feature;

use Tests\TestCase;

class PageRenderTest extends TestCase
{
    /**
     * @dataProvider publicPageProvider
     */
    public function test_public_page_renders_successfully(string $uri): void
    {
        $response = $this->get($uri);

        $response->assertOk();
    }

    public static function publicPageProvider(): array
    {
        return [
            'home' => ['/'],
            'about us' => ['/about-us'],
            'join community' => ['/join-community'],
            'contact us' => ['/contact-us'],
            'our team' => ['/our-team'],
            'prevention programs' => ['/prevention-programs'],
        ];
    }

    public function test_nonexistent_page_returns_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
    }
}
