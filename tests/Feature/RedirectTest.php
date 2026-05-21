<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedirectTest extends TestCase
{
    /**
     * @dataProvider wordpressRedirectProvider
     */
    public function test_wordpress_redirect_returns_301(string $oldPath, string $expectedPath): void
    {
        $response = $this->get($oldPath);

        $response->assertStatus(301);
        $response->assertHeader('Location', config('app.url').$expectedPath);
    }

    public static function wordpressRedirectProvider(): array
    {
        return [
            'company history' => [
                '/community-ambulance-companys-history',
                '/about-us/community-ambulance-companys-history',
            ],
            'car seat inspection' => [
                '/car-seat-inspection-hands-only-child-infant-cpr',
                '/prevention-programs/car-seat-inspection-hands-only-child-infant-cpr',
            ],
            'community education' => [
                '/community-education',
                '/prevention-programs/community-education',
            ],
            'public aeds' => [
                '/public-aeds',
                '/prevention-programs/public-aeds',
            ],
            'shed the meds' => [
                '/shed-the-meds',
                '/prevention-programs/shed-the-meds',
            ],
            'chiefs corner' => [
                '/chiefs-corner',
                '/our-team/chiefs-corner',
            ],
            'youth squad' => [
                '/youth-squad',
                '/our-team/youth-squad',
            ],
            'cac events' => [
                '/cac-events',
                '/events',
            ],
            'cac events list' => [
                '/cac-events/list',
                '/events',
            ],
            'join youth squad' => [
                '/join-youth-squad',
                '/join-community/join-youth-squad',
            ],
            'in memoriam' => [
                '/in-memoriam',
                '/our-team/in-memoriam',
            ],
            'installation dinner videos' => [
                '/installation-dinner-videos',
                '/installation-dinner-videos',
            ],
        ];
    }

    /**
     * @dataProvider wildcardRedirectProvider
     */
    public function test_wildcard_redirect_returns_301(string $oldPath, string $expectedPath): void
    {
        $response = $this->get($oldPath);

        $response->assertStatus(301);
        $response->assertHeader('Location', config('app.url').$expectedPath);
    }

    public static function wildcardRedirectProvider(): array
    {
        return [
            'category to blog' => ['/category/foo', '/blog'],
            'nested category to blog' => ['/category/uncategorized/page/2', '/blog'],
            'tag to blog' => ['/tag/some-tag', '/blog'],
            'wp-content to home' => ['/wp-content/uploads/2023/image.jpg', '/'],
            'wp-admin to cp' => ['/wp-admin', '/cp'],
            'wp-login to cp' => ['/wp-login.php', '/cp'],
        ];
    }

    public function test_same_path_redirect_is_not_registered(): void
    {
        // Routes like '/about-us' => '/about-us' where old === new should NOT
        // be registered as redirects (they'd cause infinite loops).
        // These paths should resolve to the Statamic page, not a redirect.
        $response = $this->get('/about-us');

        $this->assertNotEquals(301, $response->getStatusCode(),
            'Same-path redirect should not be registered — would cause infinite loop');
    }
}
