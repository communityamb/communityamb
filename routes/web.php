<?php

use Illuminate\Support\Facades\Route;

// 301 redirects — old flat URL structure to new nested hierarchy
$redirects = [
    '/about-us' => '/about-us',
    '/community-ambulance-companys-history' => '/about-us/community-ambulance-companys-history',
    '/prevention-programs' => '/prevention-programs',
    '/car-seat-inspection-hands-only-child-infant-cpr' => '/prevention-programs/car-seat-inspection-hands-only-child-infant-cpr',
    '/community-education' => '/prevention-programs/community-education',
    '/community-safety-surge' => '/prevention-programs/community-safety-surge',
    '/free-turkeys-for-vets' => '/prevention-programs/free-turkeys-for-vets',
    '/life-jacket-loaner-program' => '/prevention-programs/life-jacket-loaner-program',
    '/public-aeds' => '/prevention-programs/public-aeds',
    '/public-cpr-classes' => '/prevention-programs/public-cpr-classes',
    '/public-narcan-trainings' => '/prevention-programs/public-narcan-trainings',
    '/shed-the-meds' => '/prevention-programs/shed-the-meds',
    '/shred-day-identify-theft-prevention' => '/prevention-programs/shred-day-identify-theft-prevention',
    '/our-team' => '/our-team',
    '/chiefs-corner' => '/our-team/chiefs-corner',
    '/our-officers-and-board-members' => '/our-team/our-officers-and-board-members',
    '/in-memoriam' => '/our-team/in-memoriam',
    '/charter-members-and-life-members' => '/our-team/charter-members-and-life-members',
    '/youth-squad' => '/our-team/youth-squad',
    '/cac-members-only' => '/our-team/cac-members-only',
    '/cac-events' => '/events',
    '/cac-events/list' => '/events',
    '/annual-5k-run-and-walk' => '/events/annual-5k-run-and-walk',
    '/join-community' => '/join-community',
    '/join-youth-squad' => '/join-community/join-youth-squad',
    '/contact-us' => '/contact-us',
    '/community-gallery' => '/community-gallery',
    '/installation-dinner-videos' => '/installation-dinner-videos',
];

foreach ($redirects as $old => $new) {
    if ($old !== $new) {
        Route::permanentRedirect($old, $new);
    }
}

// Legacy URL pattern redirects
Route::permanentRedirect('/category/{any}', '/blog')->where('any', '.*');
Route::permanentRedirect('/tag/{any}', '/blog')->where('any', '.*');
Route::permanentRedirect('/wp-content/{any}', '/')->where('any', '.*');
Route::permanentRedirect('/wp-admin', '/cp');
Route::permanentRedirect('/wp-login.php', '/cp');
