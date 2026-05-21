<?php

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'communityamb');
set('repository', 'https://github.com/communityamb/communityamb.git');
set('branch', 'main');
set('keep_releases', 5);
set('writable_mode', 'chmod');

add('shared_files', ['.env']);
add('shared_dirs', ['storage', 'public/assets']);
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
    ->set('remote_user', getenv('DEPLOY_USER') ?: 'u000000000')
    ->set('hostname', getenv('DEPLOY_HOST') ?: '0.0.0.0')
    ->set('port', (int) (getenv('DEPLOY_PORT') ?: 22))
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '~/communityamb')
    ->set('bin/php', '~/bin/php');

task('deploy:build_upload', function () {
    $buildPath = get('release_path').'/public/build';
    run("mkdir -p {$buildPath}");
    upload('public/build/', $buildPath);
})->desc('Upload locally-built Vite assets');

task('statamic:stache:warm', artisan('statamic:stache:warm'));

task('deploy:symlink_public_html', function () {
    $deployPath = get('deploy_path');
    run('rm -rf ~/domains/communityamb.org/public_html');
    run("ln -s {$deployPath}/current/public ~/domains/communityamb.org/public_html");
})->desc('Symlink public_html to current release public/');

after('deploy:vendors', 'deploy:build_upload');
after('deploy:publish', 'statamic:stache:warm');
after('deploy:symlink', 'deploy:symlink_public_html');
after('deploy:failed', 'deploy:unlock');
