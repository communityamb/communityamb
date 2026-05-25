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
task('statamic:glide:clear', artisan('statamic:glide:clear'));
task('statamic:stache:warm', artisan('statamic:stache:warm'));

task('deploy:reload_php_fpm', function () {
    run('sudo systemctl reload php8.4-fpm');
})->desc('Reload PHP-FPM to pick up OPcache changes');

after('deploy:vendors', 'deploy:build_upload');
after('deploy:publish', 'statamic:cache:clear');
after('statamic:cache:clear', 'statamic:glide:clear');
after('statamic:glide:clear', 'statamic:stache:warm');
task('deploy:fix_permissions', function () {
    run('sudo chown -R deploy:www-data {{deploy_path}}/shared/storage');
    run('sudo chmod -R 775 {{deploy_path}}/shared/storage');
    run('sudo chown -R deploy:www-data {{release_path}}/bootstrap/cache');
    run('sudo chmod -R 775 {{release_path}}/bootstrap/cache');
})->desc('Fix storage/cache ownership for www-data PHP-FPM');

after('deploy:symlink', 'deploy:fix_permissions');
after('deploy:fix_permissions', 'deploy:reload_php_fpm');
after('deploy:failed', 'deploy:unlock');
