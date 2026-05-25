<?php

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'communityamb');
set('repository', 'https://github.com/communityamb/communityamb.git');
set('branch', 'main');
set('keep_releases', 5);
set('writable_mode', 'chmod');
set('writable_chmod_mode', '0775');
set('writable_use_sudo', false);
set('update_code_strategy', 'clone');

add('shared_files', ['.env']);
add('shared_dirs', ['storage']);
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'storage/statamic',
    'storage/forms',
]);

host('production')
    ->set('remote_user', getenv('DEPLOY_USER') ?: 'deploy')
    ->set('hostname', getenv('DEPLOY_HOST') ?: '0.0.0.0')
    ->set('port', (int) (getenv('DEPLOY_PORT') ?: 22))
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/communityamb')
    ->set('bin/php', '/usr/bin/php')
    ->set('shell', 'bash -s');

task('deploy:build_upload', function () {
    $buildPath = get('release_path').'/public/build';
    run("mkdir -p {$buildPath}");
    upload('public/build/', $buildPath);
})->desc('Upload locally-built Vite assets');

task('statamic:cache:clear', artisan('cache:clear'));
task('statamic:assets:meta', artisan('statamic:assets:meta'));
task('statamic:glide:clear', artisan('statamic:glide:clear'));
task('statamic:stache:warm', artisan('statamic:stache:warm'));

task('deploy:reload_php_fpm', function () {
    run('sudo systemctl reload php8.4-fpm');
})->desc('Reload PHP-FPM to pick up OPcache changes');

after('deploy:vendors', 'deploy:build_upload');
after('deploy:publish', 'statamic:cache:clear');
after('statamic:cache:clear', 'statamic:assets:meta');
after('statamic:assets:meta', 'statamic:glide:clear');
after('statamic:glide:clear', 'statamic:stache:warm');
task('deploy:fix_permissions', function () {
    run('sudo chown -R deploy:www-data {{deploy_path}}/shared/storage');
    run('sudo chmod -R 775 {{deploy_path}}/shared/storage');
    run('sudo chown -R deploy:www-data {{release_path}}/bootstrap/cache');
    run('sudo chmod -R 775 {{release_path}}/bootstrap/cache');
    run('sudo chown -R deploy:www-data {{release_path}}/public/img');
    run('sudo chmod -R 775 {{release_path}}/public/img');
    run('sudo chown -R deploy:www-data {{release_path}}/public/assets');
    run('sudo chmod -R 775 {{release_path}}/public/assets');
})->desc('Fix ownership for www-data PHP-FPM');

after('statamic:stache:warm', 'deploy:fix_permissions');
after('deploy:fix_permissions', 'deploy:reload_php_fpm');
after('deploy:failed', 'deploy:unlock');

// Disable built-in writable task — deploy:fix_permissions handles permissions
// with sudo after symlink, which is required for www-data-owned files in shared storage.
task('deploy:writable', function () {})->desc('Skipped: handled by deploy:fix_permissions');

// Override cleanup to use sudo — old releases contain Glide images owned by www-data.
task('deploy:cleanup', function () {
    $releases = get('releases_list');
    $keep = get('keep_releases');
    if ($keep > 0) {
        $old = array_slice($releases, $keep);
        foreach ($old as $release) {
            run("sudo rm -rf {{deploy_path}}/releases/{$release}");
        }
    }
})->desc('Clean up old releases (with sudo for www-data files)');
